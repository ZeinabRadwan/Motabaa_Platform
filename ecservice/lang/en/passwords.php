<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Password Reset Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are the default lines which match reasons
    | that are given by the password broker for a password update attempt
    | has failed, such as for an invalid token or invalid new password.
    |
    */

    'reset' => 'Your password has been reset.',
    'sent' => 'We have emailed your password reset link.',
    'throttled' => 'Please wait before retrying.',
    'token' => 'This password reset token is invalid.',
    'user' => "We can't find a user with that email address.",
    'passwords' => [
        'reset' => [
            'subject' => 'Password Reset',
            'line1' => 'We have received a request to reset your password.',
            'line2' => 'To reset your password, click the button below:',
            'action' => 'Reset Password',
            'line3' => 'If you did not request a password reset, no further action is required.',
        ],
    ],
    

];
