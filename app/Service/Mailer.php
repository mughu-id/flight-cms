<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Settings;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

class Mailer
{
    public function __construct(private Settings $settings)
    {
    }

    public function send(string $to, string $subject, string $html): void
    {
        $dsn = (string) $this->settings->get('mail_dsn', 'null://null');
        if ($dsn === '') {
            $dsn = 'null://null';
        }
        $from = (string) $this->settings->get('mail_from', 'cms@localhost');
        $email = (new Email())->from($from)->to($to)->subject($subject)->html($html);
        (new SymfonyMailer(Transport::fromDsn($dsn)))->send($email);
    }
}
