<?php

declare(strict_types=1);

namespace App\Foundation\Service;

use App\Foundation\Entity\User;
use App\Foundation\Repository\UserRepository;
use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Message;
use Marko\View\ViewInterface;

class EmailVerificationService
{
    private const int EXPIRY_SECONDS = 3600; // 60 minutes

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UserRepository $userRepository,
        private readonly ViewInterface $view,
        private readonly string $encryptionKey,
        private readonly string $appUrl,
    ) {}

    public function sendVerificationEmail(User $user): void
    {
        if (str_ends_with($user->email, '@social.local')) {
            return;
        }

        $url = $this->appUrl . $this->makeVerificationUrl($user);

        $html = $this->view->renderToString('foundation::email/verify', [
            'username' => $user->username,
            'url' => $url,
        ]);

        $message = Message::create()
            ->from(env('MAIL_FROM_ADDRESS', 'noreply@shilla.org'), env('MAIL_FROM_NAME', 'Shilla'))
            ->to($user->email, $user->username)
            ->subject('Verify your Shilla account')
            ->html($html)
            ->text("Verify your Shilla account.\n\nVisit: {$url}\n\nThis link expires in 1 hour.");

        $this->mailer->send($message);
    }

    public function verify(User $user, int $expires, string $signature): bool
    {
        if ($expires < time()) {
            return false;
        }

        $expected = $this->makeSignature($user->id, $expires, $user->emailVerifiedAt);

        if (!hash_equals($expected, $signature)) {
            return false;
        }

        $user->emailVerifiedAt = new \DateTimeImmutable();
        $user->updatedAt = new \DateTimeImmutable();
        $this->userRepository->save($user);

        return true;
    }

    public function isVerified(User $user): bool
    {
        return $user->emailVerifiedAt !== null;
    }

    public function makeVerificationUrl(User $user): string
    {
        $expires = time() + self::EXPIRY_SECONDS;
        $signature = $this->makeSignature($user->id, $expires, $user->emailVerifiedAt);

        return '/verify-email?' . http_build_query([
            'id' => $user->id,
            'expires' => $expires,
            'signature' => $signature,
        ]);
    }

    private function makeSignature(int $userId, int $expires, ?\DateTimeImmutable $emailVerifiedAt): string
    {
        $key = $this->encryptionKey . '|' . ($emailVerifiedAt?->getTimestamp() ?? 'null');
        $payload = "verify|{$userId}|{$expires}";
        return hash_hmac('sha256', $payload, $key);
    }
}
