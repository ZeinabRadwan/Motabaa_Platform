<?php

namespace App\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Twilio\Rest\Client;

class SendWhatsAppMessage
{
    use Dispatchable;

    public function __construct(
        public $phone,
        public $message,
        public $url
    ) {
    }

    public function handle(): void
    {
        $phone = (string) $this->phone;
        if (strlen($phone) !== 12) {
            \Log::channel('whatsapp')->info('Wrong Phone: '.$phone);

            return;
        }

        $sid = config('motabaa.whatsapp.sid');
        $token = config('motabaa.whatsapp.token');
        $centerName = config('motabaa.whatsapp.center_name');

        $twilio = new Client($sid, $token);
        $twilio->messages->create('whatsapp:'.$phone, [
            'from' => 'whatsapp:'.config('motabaa.whatsapp.phone'),
            'messagingServiceSid' => config('motabaa.whatsapp.service_sid'),
            'contentSid' => config('motabaa.whatsapp.content_sid'),
            'contentVariables' => json_encode([
                '1' => $centerName,
                '2' => $this->message,
                '3' => $this->url,
            ]),
        ]);
    }
}
