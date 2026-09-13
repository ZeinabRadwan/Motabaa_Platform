<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\User;
use Mail;

//you should the queue with: php artisan queue:listen or php artisan queue:work
//nohup php artisan queue:listen --tries=3 > /dev/null 2>&1 &

//In WHM go Server Configuration/Tweak Settings/Restrict outgoing SMTP to root --> off

class SystemMail extends Mailable
{
    use Queueable, SerializesModels;

    protected $data;
    protected $language;
    protected $rejectToken=null;
    protected $acceptToken=null;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data = null, $language = 'en')
    {
        $this->data = $data;
        $this->language = $language;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build() {

        $data = $this->data;
        $language = $this->language;
        return $this->subject($data['title'])->view('mail.user_activation_template', compact('data', 'language'));
    }


    public function submit($to) {

        if(config('app.debug') === true) {
            $to = config('motabaa.dev.email');
        }

        if(empty($to)) return false;

        $this->build();
        
        try{
            Mail::to($to)->send($this);
        } catch(\Exception $e) {
            \Log::error('Mail send failed', ['exception' => $e->getMessage()]);
            return $e->getMessage();
        }

        return true;
    }
}
