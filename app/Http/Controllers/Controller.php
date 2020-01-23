<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Mail;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function send_mail($mail_header,$subject,$to_mail){
    	Mail::send('mail_templates.forgot_password', $mail_header, function ($message)
		 use ($subject,$to_mail) {
			$message->from(env('MAIL_USERNAME'), env('APP_NAME'));
			$message->subject($subject);
			$message->to($to_mail);
		});
    }
}
