<?php

namespace App\Services;

use Resend;

class EastBusMailService
{
    public static function send(
        string $to,
        string $subject,
        string $html
    ): void {
        $resend = Resend::client(
            env('re_LBPXzx6T_A9odz7HmexWdKMhbiJuiTCVT')
        );

        $resend->emails->send([
            'from' => env(
                'MAIL_FROM_ADDRESS',
                'onboarding@resend.dev'
            ),
            'to' => [$to],
            'subject' => $subject,
            'html' => $html,
        ]);
    }
}