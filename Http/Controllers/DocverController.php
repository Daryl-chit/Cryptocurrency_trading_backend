<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Docver;
use App\User;
use App\Tranlimit;

class DocverController extends Controller
{
    public function requests()
    {
        $tranl = Tranlimit::first();
    	$docs = Docver::orderBy('id', 'desc')->paginate(10);

    	return view('admin.document.index', compact('docs','tranl'));
    }

    public function approve(Request $request, $id)
    {
    	$user = User::findOrFail($id);

    	$user['docv'] = $request->docv =="1" ?1:0 ;

    	$user->save();

        $msg =  'Your Document Verified Successfully';
        send_email($user->email, $user->username, 'Document Verified', $msg);
        $sms =  'Your Document Verified Successfully';
        send_sms($user->mobile, $sms);


    	return back()->withSuccess('Document Verification Successful');

    }

    public function remove(Request $request, $id)
    {
        $docv = Docver::findOrFail($id);
        $photo = "assets/images/document/".$docv['photo'];
        $photo_1 = "assets/images/document/".$docv['photo_1'];

        if ( file_exists($photo) ) {
            unlink($photo);
        }
        if ( file_exists($photo_1) ) {
            unlink($photo_1);
        }

        $user = User::findOrFail($docv->user_id);
        $user['docv'] = 0 ;
        $user->save();

        $docv->delete();

        $msg =  'Your Document Verification was Failed.';
        send_email($user->email, $user->username, 'Document Verify Failed', $msg);
        $sms =  'Your Document Verification was Failed.';
        send_sms($user->mobile, $sms);

        return back()->withSuccess('Document Verification Canceled Successfully');
    }
}
