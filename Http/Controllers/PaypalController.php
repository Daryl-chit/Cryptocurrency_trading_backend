<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Auth;
use App\User;
use App\Gateway;
use App\Price;
use App\Deposit;
use App\Uwdlog;
use App\Gsetting;
use Session;

use Paypal;
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\Agreement;
use PayPal\Api\Amount;
use PayPal\Api\PaymentExecution;
use PayPal\Api\Payer;
use PayPal\Api\Plan;
use PayPal\Api\Payment;
use PayPal\Api\PaymentDefinition;
use PayPal\Api\PayerInfo;
use PayPal\Api\ItemList;
use PayPal\Api\Item;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;

class PaypalController extends Controller
{
    public function __construct()
    {

        /** PayPal api context **/
        $paypal_conf = \Config::get('paypal');
        $this->_api_context = new ApiContext(new OAuthTokenCredential(
                $paypal_conf['client_id'],
                $paypal_conf['secret'])
        );
        $this->_api_context->setConfig($paypal_conf['settings']);

    }

    public function payWithpaypal(Request $request)
    {

        if ($request->amount <= 0 || $request->amount == "")
        {
            return redirect()->route('deposit')->with('alert', 'Invalid Amount');
        }


        $payer = new Payer();
        $payer->setPaymentMethod('paypal');

        $coin_amount = $request->get('amount');

        $item_1 = new Item();
        $item_1->setName('paypal'.str_random(16)) /** item name **/
            ->setCurrency('USD')
            ->setQuantity(1)
            ->setPrice($request->get('amount_alt')); /** unit price **/

        $item_list = new ItemList();
        $item_list->setItems(array($item_1));

        $amount = new Amount();
        $amount->setCurrency('USD')
            ->setTotal($request->get('amount_alt'));

        $transaction = new Transaction();
        $transaction->setAmount($amount)
            ->setItemList($item_list)
            ->setDescription($coin_amount);

        $redirect_urls = new RedirectUrls();
        $redirect_urls->setReturnUrl(route('paypal.done')) /** Specify return URL **/
        ->setCancelUrl(route('paypal.error'));

        $payment = new Payment();
        $payment->setIntent('Sale')
            ->setPayer($payer)
            ->setRedirectUrls($redirect_urls)
            ->setTransactions(array($transaction));
        /** dd($payment->create($this->_api_context));exit; **/
        try {

            $payment->create($this->_api_context);

        } catch (\PayPal\Exception\PPConnectionException $ex) {

            if (\Config::get('app.debug')) {

                \Session::put('error', 'Connection timeout');
                //return Redirect::route('paywithpaypal');
                return redirect()->to( route('paywithpaypal') );

            } else {

                \Session::put('error', 'Some error occur, sorry for inconvenient');
                //return Redirect::route('paywithpaypal');
                return redirect()->to( route('paywithpaypal') );

            }

        }

        foreach ($payment->getLinks() as $link) {

            if ($link->getRel() == 'approval_url') {

                $redirect_url = $link->getHref();
                break;

            }

        }

        /** add payment ID to session **/
        \Session::put('paypal_payment_id', $payment->getId());

        if (isset($redirect_url)) {

            /** redirect to paypal **/
            //return Redirect::away($redirect_url);
            return redirect()->to( $redirect_url );

        }

        \Session::put('error', 'Unknown error occurred');
        //return Redirect::route('paywithpaypal');
        return redirect()->to( route('paywithpaypal') );

    }

    public function getPaymentStatus()
    {
        /** Get the payment ID before session clear **/
        $payment_id = Session::get('paypal_payment_id');

        /** clear the session payment ID **/
        Session::forget('paypal_payment_id');
        if (empty(Input::get('PayerID')) || empty(Input::get('token'))) {

            \Session::put('error', 'Payment failed');
            return Redirect::route('/');

        }

        $payment = Payment::get($payment_id, $this->_api_context);
        $execution = new PaymentExecution();
        $execution->setPayerId(Input::get('PayerID'));

        /**Execute the payment **/
        $result = $payment->execute($execution, $this->_api_context);

        if ($result->getState() == 'approved') {

            \Session::put('success', 'Payment success');
            return Redirect::route('/');

        }

        \Session::put('error', 'Payment failed');
        return Redirect::route('/');

    }

    public function payDone(Request $request)
    {
        $id = $request->get('paymentId');
        $token = $request->get('token');
        $payer_id = $request->get('PayerID');
        //$payment = Paypal::getById($id, $this->_apiContext);
        $payment = Payment::get($id, $this->_api_context);

        $paymentExecution = new PaymentExecution();

        $paymentExecution->setPayerId($payer_id);
        $executePayment = $payment->execute($paymentExecution, $this->_api_context);

        if($executePayment->getState() == 'approved')
        {
            $transactions = $executePayment->getTransactions();
            $itemlists = $transactions[0]->getItemList();
            $coin_amount = $transactions[0]->getDescription();
            $items = $itemlists->getItems();
            $payer = $executePayment->getPayer();
            $payment_method = $payer->getPaymentMethod();
            $current_customer = null;

            $item_id = "paypal";
            foreach($items as $item) {
                $item_id = $item->getName();
            }

            $amount = json_decode($transactions[0]->getAmount(),true)['total'];

            $deposit['user_id'] = Auth::user()->id;
            $deposit['amount'] = $coin_amount;
            $deposit['inusd'] = $amount;
            $deposit['charge'] = "0";
            $deposit['gateway_id'] = 7;
            $deposit['trxid'] = $item_id;
            $deposit['status'] = 1;
            Deposit::create($deposit);

            $user = User::find($deposit['user_id']);
            $user['balance'] = $user->balance + $deposit['amount'];
            $user->save();

            $ulog['user_id'] = $user->id;
            $ulog['trxid'] = $deposit['trxid'];
            $ulog['amount'] = $deposit['amount'];
            $ulog['flag'] = 1;
            $ulog['status'] = 1;
            $ulog['balance'] = $user['balance'];
            $ulog['desc'] = 'Purchased';
            Uwdlog::create($ulog);

            $msg =  'Your Purchase Processed Successfully';
            send_email($user->email, $user->firstname, 'Purchase Processed', $msg);
            $sms =  'Your Purchase Processed Successfully';
            send_sms($user->mobile, $sms);

            return redirect()->route('withdraw')->with('success', 'Deposit Request Approved Successfully!');

            //return redirect()->route('customer.dashboard')->withFlashSuccess('Your payment is completed.');
        }
        else
        {
            return redirect()->route('deposit')->with('alert','Your payment is not completed with unknown errors.');
        }
    }

    public function payError()
    {
        return redirect()->route('paypal.error')->with('alert','Your payment is cancelled.');
    }
}
