<?php

declare(strict_types=1);

namespace App\Foundation\Service;

use App\Foundation\Entity\PasswordResetToken;
use App\Foundation\Repository\PasswordResetTokenRepository;
use App\Foundation\Repository\UserRepository;
use Marko\Hashing\Contracts\HasherInterface;
use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Message;
use Marko\View\ViewInterface;

class PasswordResetService
{
    private const int TOKEN_LIFETIME_MINUTES = 60;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PasswordResetTokenRepository $tokenRepository,
        private readonly HasherInterface $hasher,
        private readonly MailerInterface $mailer,
        private readonly ViewInterface $view,
        private readonly string $appUrl,
    ) {}

    public function sendResetLink(string $email): void
    {
        $user = $this->userRepository->findByEmail($email);

        if ($user === null || !$user->hasPassword()) {
            return;
        }

        $this->tokenRepository->deleteByUserId($user->id);

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $token = new PasswordResetToken();
        $token->userId = $user->id;
        $token->tokenHash = $tokenHash;
        $token->createdAt = new \DateTimeImmutable();
        $this->tokenRepository->save($token);

        $resetUrl = $this->appUrl . '/reset-password/' . $rawToken;

        $html = $this->view->renderToString('foundation::email/password-reset', [
            'resetUrl' => $resetUrl,
            'username' => $user->username,
        ]);

        $message = Message::create()
            ->from(env('MAIL_FROM_ADDRESS', 'noreply@shilla.org'), env('MAIL_FROM_NAME', 'Shilla'))
            ->to($user->email)
            ->subject('Reset your Shilla password')
            ->html($html)
            ->text("Reset your Shilla password.\n\nVisit: {$resetUrl}\n\nThis link expires in 60 minutes.");

        $this->mailer->send($message);
    }

    public function findValidToken(string $rawToken): ?PasswordResetToken
    {
        if (strlen($rawToken) !== 64 || !ctype_xdigit($rawToken)) {
            return null;
        }

        $tokenHash = hash('sha256', $rawToken);
        $token = $this->tokenRepository->findByTokenHash($tokenHash);

        if ($token === null) {
            return null;
        }

        if ($token->isExpired(self::TOKEN_LIFETIME_MINUTES)) {
            $this->tokenRepository->deleteByUserId($token->userId);
            return null;
        }

        return $token;
    }

    public function resetPassword(string $rawToken, string $newPassword): bool
    {
        $token = $this->findValidToken($rawToken);

        if ($token === null) {
            return false;
        }

        $user = $this->userRepository->find($token->userId);

        if ($user === null) {
            return false;
        }

        $user->password = $this->hasher->hash($newPassword);
        $user->updatedAt = new \DateTimeImmutable();
        $this->userRepository->save($user);

        $this->tokenRepository->deleteByUserId($user->id);

        return true;
    }
}
