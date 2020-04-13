<?php
use App\Gsetting;
use Illuminate\Support\Facades\Log; 
use Illuminate\Support\Facades\Mail;


if (! function_exists('send_email')) {
    
    function send_email( $receiver, $name, $subject, $message)
    {
        $settings = Gsetting::first();
        $template = $settings->emailMessage;
		$from = $settings->emailSender;
		if($settings->emailNotify == 1)
		{			
			$headers = "From: $settings->webTitle <$from> \r\n";
			$headers .= "Reply-To: $settings->webTitle <$from> \r\n";
			$headers .= "MIME-Version: 1.0\r\n";
			$headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";

			$mm = str_replace("{{name}}",$name,$template);     
			$message = str_replace("{{message}}",$message,$mm); 
			//Log::debug($name."-------------");
			///////////////////////	
			// send mail to photographers
			$from_text = $settings->webTitle;
            		Mail::send([],[],function($message1) use($from,$from_text,$receiver,$subject,$message)
            		{
				$message1->from($from,$from_text);				
				$message1->to($receiver)->subject($subject)
				->setBody($message);
			});
			///////////////////////////////////
			/*
			if (mail($to, $subject, $message, $headers)) {
			  // echo 'Your message has been sent.';
			  Log::debug('success');
			} else {
			 //echo 'There was a problem sending the email.';
			 Log::debug('fail');
			}*/

		}

    }
}

if (! function_exists('send_sms')) 
{
    
    function send_sms( $to, $message)
    {
		$settings = Gsetting::first();
		if($settings->smsNotify == 1)
		{

			$sendtext = urlencode("$message");
		    $appi = $settings->smsApi;
			$appi = str_replace("{{number}}",$to,$appi);     
			$appi = str_replace("{{message}}",$sendtext,$appi); 
			//$result = file_get_contents($appi);

			$ret = "";
			$query = http_build_query([
				'username' => 'tthomas5839',
				'userid' => '14976',
				'handle' => '00a6cd3e7a01d86e7073b96b3add2db0',
				'msg' => $message,
				'from' => 'Skyrus',
				'to' => $to
			]);

			$url = "https://api.budgetsms.net/sendsms?".$query;
			$ret = file_get_contents($url);

			
		}

    }
}


/*
|--------------------------------------------------------------------------
| Detect Active Route
|--------------------------------------------------------------------------
|
| Compare given route with current route and return output if they match.
| Very useful for navigation, marking if the link is active.
|
*/
function isActiveRoute($route, $output = "active")
{
    if (Route::currentRouteName() == $route) return $output;
}

/*
|--------------------------------------------------------------------------
| Detect Active Routes
|--------------------------------------------------------------------------
|
| Compare given routes with current route and return output if they match.
| Very useful for navigation, marking if the link is active.
|
*/
function areActiveRoutes(Array $routes, $output = "active")
{
    foreach ($routes as $route)
    {
        if (Route::currentRouteName() == $route) return $output;
    }

}