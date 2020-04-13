<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Uaccount;
use App\Gsetting;
use App\User;
use App\Uwdlog;
use App\Charge;
use App\Tranlimit;
use App\Withdraw;
use App\Price;
use Auth;

define("WALLET_URL", "http://funds.skyrus.io:8090/rpc");
define("WALLET_PW", "mm198782");

class UaccountController extends Controller
{
    public function requestMoney()
    {
        $gset = Gsetting::first();

            $uac['user_id'] = Auth::id();
            $uac['accnum'] = str_random(32);
            $new_account = Uaccount::create($uac);

            if($new_account)
            {

                $var = $gset->curCode.":".$uac['accnum'];
                $uac['code'] =  "<img src=\"https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=$var&choe=UTF-8\" title='' style='width:300px;' />";

                return $uac;
            }
           else
           {
                $err = "Please Reload The Page";
                return $err;
           }
    }

    public function sendToken($wdid, $to_address, $amount) {
        echo 'Start  >>>>>>>>>' . PHP_EOL;
        $ch = curl_init();

        $data = array(
            'withdraw_id' => $wdid,
            'user_id' => Auth::id(),
            'amount' => $amount,
            'to_address' => $to_address
        );
        $payload = json_encode($data);

        $payload = "withdraw_id=".$wdid."&user_id=".Auth::id()."&amount=".$amount."&to_address=".$to_address;

        curl_setopt($ch, CURLOPT_URL,"http://43849c9b.ngrok.io/send");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                //'Content-Type: application/json',
                'Content-Length: ' . strlen($payload))
        );

        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch,  CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);

        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);

        $server_output = curl_exec($ch);

        curl_close ($ch);

        //if ($server_output == "OK") {
        //    return $server_output;
        //} else {
        //    return $server_output;
        //}
    }

    public function send_request($url,  $param)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $param);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($param))
        );

        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    public function unlock(){
        $payload = '{"jsonrpc": "2.0", "method": "unlock", "params": ["'.WALLET_PW.'"], "id": 1}';
        $res = $this->send_request(WALLET_URL, $payload);
        $result = json_decode($res,true)['result'];
        if($result == null){
            return true;
        }else{
            return false;
        }
    }

    public function lock(){
        $payload = '{"jsonrpc": "2.0", "method": "lock", "params": [], "id": 1}';
        $res =  $this->send_request(WALLET_URL, $payload);
        $result = json_decode($res,true)['result'];
        if($result == null){
            return true;
        }else{
            return false;
        }
    }

    public function sendMoney(Request $request)
    {
        $this->validate($request,
        [
            'amount' => 'required',
            'curn' => 'required',
            'code' => 'required',
        ]);
        $sender = User::find(Auth::id());
        $charge = Charge::first();
        $tnchrg = $charge->trancharge + ($request->amount*$charge->trncrgp)/100;
        $tamount = $request->amount + $tnchrg;

        $tlimit = Tranlimit::first();
        if($tlimit == null){
            $tlimit_coin = 1000;
        }
        else{
            $tlimit_coin = $tlimit['coin'];
        }

        if($sender->docv!="1" && $request->amount > $tlimit_coin){
            return back()->with('alert', 'Not allow to withdraw more '.strval($tlimit_coin).'Coins.   First you need to verify your document');
        }


        /*$ret = $this->sendToken("qqqq", $request->code, $request->amount);
        return redirect()->route('home')->withSuccess($ret);*/

        if ($request->curn == '1')
        {
            try{
                $payload = '{"jsonrpc": "2.0", "method": "get_account_id", "params": ["'.$request->code.'"], "id": 1}';
                $ret = $this->send_request(WALLET_URL, $payload);
                //$ret = file_get_contents("http://43849c9b.ngrok.io/validate_address?address=".$request->code);
            } catch(\Exception $e){
                return back()->with('alert', 'Failed access to wallet.');
            }

            if (isset(json_decode($ret,true)["result"])){
                //return back()->with('alert', json_decode($ret,true)["result"]);
            }
            else{
                return back()->with('alert', 'Invalid Skyrus Account Name');
            }

            if($sender->balance < $tamount ||  $request->amount <= 0)
            {
                return back()->with('alert', 'Insufficient Balance');
            }
            else
            {
                $code = $request->code;
                if($code == null)
                {
                    return back()->with('alert', 'Invalid Skyrus Account Name...');
                }
                else
                {
                    if($this->unlock()==true){
                        $send_amount = number_format((float)$request->amount, 4, '.', '');
                        $payload = '{"jsonrpc": "2.0", "method": "transfer", "params": ["skyrus-funds","'.$code
                            .'","'.$send_amount.'","SKX","Withdraw SKX to '.$code.'",true], "id": 1}';

                        $res =  $this->send_request(WALLET_URL, $payload);
                        //return back()->with('alert', $res);

                        if(isset(json_decode($res,true)["result"])){
                            $withdraw['wdid'] = $request->code;
                            $withdraw['user_id'] = Auth::id();
                            $withdraw['amount'] = $request->amount;
                            $withdraw['charge'] = $tnchrg;
                            $withdraw['wdmethod_id'] = $request->curn;
                            $withdraw['details'] = $request->desc;
                            $withdraw['status'] = 1;

                            $wd_row = Withdraw::create($withdraw);
                            $wd_id = $wd_row['id'];

                            $sender['balance'] =  $sender['balance'] - $tamount;
                            $sender->save();

                            $slog['user_id'] =  $sender['id'];
                            $slog['trxid'] = str_random(40);
                            $slog['toacc'] = $request->code;
                            $slog['amount'] = $request->amount;
                            $slog['charge'] = $tnchrg;
                            $slog['flag'] = 0;
                            $slog['status'] = 1;
                            $slog['balance'] = $sender['balance'];
                            $slog['desc'] = 'Sent '.$request->amount.'SKX to '.$request->code;
                            Uwdlog::create($slog);

                            $flag_lock = $this->lock();
                            //return back()->with('alert', $flag_lock);

                            $msg =  'Sent Coin withdraw request.';
                            send_email($sender->email, $sender->username, 'Sent Coin withdraw request.', $msg);
                            $sms =  'Sent Coin withdraw request.';
                            send_sms($sender->mobile, $sms);

                            return redirect()->route('home.transaction.withdraw')->withSuccess('Sent Request Successfuly.');
                        }else{
                            return back()->with('alert', $res);
                        }
                    }
                    else{
                        return back()->with('alert', 'Wallet Error!');
                    }
                }

            }
        }
        else
        {
            if($sender->bitcoin < $tamount ||  $request->amount <= 0)
            {
                return back()->with('alert', 'Insufficient Balance');
            }
            else
            {
                $withdraw['wdid'] = $request->code;
                $withdraw['user_id'] = Auth::id();
                $withdraw['amount'] = $request->amount;
                $withdraw['charge'] = $tnchrg;
                $withdraw['wdmethod_id'] = $request->curn;
                $withdraw['details'] = $request->desc;
                $withdraw['status'] = 0;

                Withdraw::create($withdraw);

                $sender['bitcoin'] =  $sender['bitcoin'] - $tamount;
                $sender->save();

                $slog['user_id'] =  $sender['id'];
                $slog['trxid'] = str_random(40);
                $slog['toacc'] = $request->code;
                $slog['amount'] = $request->amount;
                $slog['charge'] = $tnchrg;
                $slog['flag'] = 0;
                $slog['status'] = 0;
                $slog['balance'] = $sender['bitcoin'];
                $slog['desc'] = 'Sent BitCoin';
                Uwdlog::create($slog);

                $msg =  'Sent Coin';
                send_email($sender->email, $sender->username, 'Sent Coin', $msg);
                $sms =  'Sent BitCoin';
                send_sms($sender->mobile, $sms);

                return redirect()->route('home')->withSuccess('Coin Sent Successfuly');
            }
        }
    }

    public function convertMoney(Request $request)
    {
        $this->validate($request,
            [
                'framo' => 'required',
                'fromc' => 'required',
            ]);

        $user = User::find(Auth::id());
        $tlimit = Tranlimit::first();
        $charge = Charge::first();
        $gset = Gsetting::first();

        $all = file_get_contents("https://blockchain.info/ticker");
        $res = json_decode($all);
        $btcRate = $res->USD->last;
        $cprice = Price::latest()->first();
        $ucode = Uaccount::where('user_id',Auth::id())->first();

        if ($ucode == null)
        {
            $uac['user_id'] = Auth::id();
            $uac['accnum'] = str_random(32);
            $new_account = Uaccount::create($uac);
            $ucode = Uaccount::where('user_id',Auth::id())->first();
        }

        if ($request->fromc == '1') 
        {
            $concrg = ($charge->convcrg * $request->framo)/100;
            $balance = $user->balance - $concrg;

            if ($user->docv != '1' && $request->framo >  $tlimit->coin) 
            {
                return back()->with('alert', 'Please Verify Your Document to Convert Money');
            }
            elseif($request->framo > $balance ||  $request->framo <= 0)
            {
                return back()->with('alert', 'Insufficient Balance');
            }
            else
            {
                $user['balance'] = $user->balance - ($request->framo + $concrg);
                $btc = ($request->framo*$cprice->price)/$btcRate;
                $user['bitcoin'] = $user['bitcoin'] + $btc;
                $user->save();

                $clog['user_id'] = Auth::id();
                $clog['trxid'] = str_random(40);
                $clog['amount'] = $request->framo;
                $clog['charge'] = $concrg;
                $clog['toacc'] = $ucode->accnum;
                $clog['flag'] = 1;
                $clog['status'] = 0;
                $clog['balance'] = $user['balance'];
                $clog['desc'] = 'Coverted to BitCoin';
                Uwdlog::create($clog);

                $rlog['user_id'] = Auth::id();
                $rlog['trxid'] = str_random(40);
                $rlog['amount'] = $btc;
                $rlog['charge'] = 0;
                $rlog['toacc'] = $ucode->accnum;
                $rlog['flag'] = 0;
                $rlog['status'] = 1;
                $rlog['balance'] = $user['bitcoin'];
                $rlog['desc'] = 'Coverted to BitCoin';
                Uwdlog::create($rlog);

                $msg =  'Coin Converted to BitCoin';
                send_email($user->email, $user->username, 'Converted to BitCoin', $msg);
                $sms =  'Coin Converted to BitCoin';
                send_sms($user->mobile, $sms);

                return back()->with('success', 'Converted to BitCoin Successfully');
            }
        }
        else
        {
            $concrg = ($charge->convcrg*$request->framo)/100;
            $bitcoin = $user->bitcoin - $concrg;

            if ($user->docv != '1' && $request->framo >  $tlimit->coin) 
            {
                return back()->with('alert', 'Please Verify Your Document to Convert Money');
            }
            elseif($request->framo > $bitcoin || $request->framo <= 0)
            {
                return back()->with('alert', 'Insufficient Balance');
            }
            else
            {
                $user['bitcoin'] = $user->bitcoin - ($request->framo + $concrg);
                $baln = ($request->framo*$btcRate)/$cprice->price;
                $user['balance'] = $user['balance'] + $baln;
                $user->save();

               $clog['user_id'] = Auth::id();
                $clog['trxid'] = str_random(40);
                $clog['amount'] = $request->framo;
                $clog['charge'] = $concrg;
                $clog['toacc'] = $ucode->accnum;
                $clog['flag'] = 0;
                $clog['status'] = 0;
                $clog['balance'] = $user['bitcoin'];
                $clog['desc'] = 'Coverted From BitCoin';
                Uwdlog::create($clog);

                $rlog['user_id'] = Auth::id();
                $rlog['trxid'] = str_random(40);
                $rlog['amount'] = $baln;
                $rlog['charge'] = 0;
                $rlog['toacc'] = $ucode->accnum;
                $rlog['flag'] = 1;
                $rlog['status'] = 1;
                $rlog['balance'] = $user['balance'];
                $rlog['desc'] = 'Coverted to BitCoin';
                Uwdlog::create($rlog);

                $msg =  'BitCoin Converted to Coin';
                send_email($user->email, $user->username, 'Converted to Coin', $msg);
                $sms =  'BitCoin Converted to Coin';
                send_sms($user->mobile, $sms);

                return back()->with('success', 'Converted from BitCoin Successfully');
            }   
        }
    }

    public function sellcoin()
    {
        $all = file_get_contents("https://blockchain.info/ticker");
        $res = json_decode($all);
        $currentRate = $res->USD->last;
        $price = Price::latest()->first();

        $btusd = Auth::user()->bitcoin * $currentRate;
        $nusd = Auth::user()->balance * $price->price;
        $totusd = $btusd + $nusd;

        return view('front.user.sell' , compact('btusd','nusd','totusd'));
    }

    public function sellview(Request $request)
    {
        $this->validate($request,
            [
                'amount' => 'required',
            ]);
        if ($request->amount <= 0) 
         {
             return redirect()->route('sell.coin')->with('alert', 'Invalid Amount');
         }
         else
         {

            $all = file_get_contents("https://blockchain.info/ticker");
            $res = json_decode($all);
            $btcRate = $res->USD->last;
            $cprice = Price::latest()->first();
            $amount = $request->amount;
            $btc = ($request->amount*$cprice->price)/$btcRate;

            return view('front.user.sellview', compact('btc','amount'));
        }
    }

    public function sellconfirm(Request $request){
        $this->validate($request,
            [
                'amount' => 'required',
            ]);
        
        $all = file_get_contents("https://blockchain.info/ticker");
        $res = json_decode($all);
        $btcRate = $res->USD->last;
        $cprice = Price::latest()->first();

        $user = User::find(Auth::id());
        $tlimit = Tranlimit::first();
        $charge = Charge::first();
        $concrg = ($charge->convcrg * $request->framo)/100;
        $balance = $user->balance - $concrg;

        $ucode = Uaccount::where('user_id',Auth::id())->first();

        if ($ucode == null)
        {
            $uac['user_id'] = Auth::id();
            $uac['accnum'] = str_random(32);
            $new_account = Uaccount::create($uac);
            $ucode = Uaccount::where('user_id',Auth::id())->first();
        }

        if ($user->docv != '1' && $request->amount >  $tlimit->coin) {
            return redirect('home')->with('alert', 'Please Verify Your Document to Sell Coin');
        }
        elseif($request->amount > $balance || $request->amount <= 0){
            return redirect('home')->with('alert', 'Insufficient Balance');
        }
        else{
            $user['balance'] = $user->balance - ($request->amount + $concrg);
            $btc = ($request->amount*$cprice->price)/$btcRate;
            $user['bitcoin'] = $user['bitcoin'] + $btc;
            $user->save();

            $clog['user_id'] = Auth::id();
            $clog['trxid'] = str_random(40);
            $clog['amount'] = $request->amount;
            $clog['charge'] = $concrg;
            $clog['toacc'] = $ucode->accnum;
            $clog['flag'] = 1;
            $clog['status'] = 0;
            $clog['balance'] = $user['balance'];
            $clog['desc'] = 'Sold Coin';
            Uwdlog::create($clog);

            $rlog['user_id'] = Auth::id();
            $rlog['trxid'] = str_random(40);
            $rlog['amount'] = $btc;
            $rlog['charge'] = 0;
            $rlog['toacc'] = $ucode->accnum;
            $rlog['flag'] = 0;
            $rlog['status'] = 1;
            $rlog['balance'] = $user['bitcoin'];
            $rlog['desc'] = 'Bought BitCoin';
            Uwdlog::create($rlog);

            $msg =  'Coin Sold Successfully';
            send_email($user->email, $user->username, 'Coin Sold', $msg);
            $sms =  'Coin Sold Successfully';
            send_sms($user->mobile, $sms);

            return redirect('home')->with('success', 'Coin Sold Successfully');
        }
    }
}

