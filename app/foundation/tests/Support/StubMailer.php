<?php

declare(strict_types=1);

namespace App\Foundation\Tests\Support;

use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Message;

class StubMailer implements MailerInterface
{
    /** @var Message[] */
    public array $sent = [];

    public function send(Message $message): bool
    {
        $this->sent[] = $message;
        return true;
    }

    public function sendRaw(string $to, string $raw): bool
    {
        return true;
    }
}
