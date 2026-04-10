<?php

declare(strict_types=1);

use App\Foundation\Entity\User;
use App\Foundation\Repository\UserRepository;
use App\Foundation\Service\EmailVerificationService;
use App\Foundation\Tests\Support\StubMailer;
use Marko\Routing\Http\Response;

function makeTestUserForVerification(bool $verified = false): User
{
    $user = new User();
    $user->id = 1;
    $user->username = 'testuser';
    $user->email = 'test@example.com';
    $user->emailVerifiedAt = $verified ? new \DateTimeImmutable('2026-01-01') : null;
    return $user;
}

function makeStubViewForEmail(): object
{
    return new class implements \Marko\View\ViewInterface {
        public string $lastTemplate = '';
        public array $lastData = [];

        public function render(string $template, array $data = []): Response
        {
            $this->lastTemplate = $template;
            $this->lastData = $data;
            return Response::html('<html>stub</html>');
        }

        public function renderToString(string $template, array $data = []): string
        {
            $this->lastTemplate = $template;
            $this->lastData = $data;
            return '<html>email body</html>';
        }
    };
}

function makeVerificationService(
    ?object $mailer = null,
    ?object $userRepo = null,
    ?object $view = null,
): EmailVerificationService {
    return new EmailVerificationService(
        mailer: $mailer ?? new StubMailer(),
        userRepository: $userRepo ?? Mockery::mock(UserRepository::class),
        view: $view ?? makeStubViewForEmail(),
        encryptionKey: 'test-encryption-key-base64encoded==',
        appUrl: 'http://localhost:8001',
    );
}

it('generates a valid signed URL', function () {
    $user = makeTestUserForVerification();
    $service = makeVerificationService();

    $url = $service->makeVerificationUrl($user);

    expect($url)->toContain('/verify-email?')
        ->and($url)->toContain('id=1')
        ->and($url)->toContain('expires=')
        ->and($url)->toContain('signature=');
});

it('verifies a valid signature and sets emailVerifiedAt', function () {
    $user = makeTestUserForVerification();
    $userRepo = Mockery::mock(UserRepository::class);
    $userRepo->shouldReceive('save')->once();

    $service = makeVerificationService(userRepo: $userRepo);
    $url = $service->makeVerificationUrl($user);

    // Parse the URL to extract params
    parse_str(parse_url($url, PHP_URL_QUERY), $params);

    $result = $service->verify($user, (int) $params['expires'], $params['signature']);

    expect($result)->toBeTrue()
        ->and($user->emailVerifiedAt)->not->toBeNull();
});

it('rejects expired signature', function () {
    $user = makeTestUserForVerification();
    $service = makeVerificationService();

    // Build a signature with an already-expired timestamp
    $expires = time() - 1;
    $result = $service->verify($user, $expires, 'any-signature');

    expect($result)->toBeFalse();
});

it('rejects tampered signature', function () {
    $user = makeTestUserForVerification();
    $service = makeVerificationService();

    $expires = time() + 3600;
    $result = $service->verify($user, $expires, 'tampered-signature');

    expect($result)->toBeFalse();
});

it('rejects signature after user already verified', function () {
    $user = makeTestUserForVerification();
    $service = makeVerificationService();

    // Generate URL for unverified user
    $url = $service->makeVerificationUrl($user);
    parse_str(parse_url($url, PHP_URL_QUERY), $params);

    // Now mark user as verified (changes key material)
    $user->emailVerifiedAt = new \DateTimeImmutable();

    $result = $service->verify($user, (int) $params['expires'], $params['signature']);

    expect($result)->toBeFalse();
});

it('sends verification email with correct recipient and subject', function () {
    $user = makeTestUserForVerification();
    $mailer = new StubMailer();
    $service = makeVerificationService(mailer: $mailer);

    $service->sendVerificationEmail($user);

    expect($mailer->sent)->toHaveCount(1)
        ->and($mailer->sent[0]->to[0]->email)->toBe('test@example.com')
        ->and($mailer->sent[0]->subject)->toBe('Verify your Shilla account');
});

it('skips sending email for social.local addresses', function () {
    $user = makeTestUserForVerification();
    $user->email = 'discord_123@social.local';
    $mailer = new StubMailer();
    $service = makeVerificationService(mailer: $mailer);

    $service->sendVerificationEmail($user);

    expect($mailer->sent)->toHaveCount(0);
});

it('returns true for isVerified when emailVerifiedAt is set', function () {
    $user = makeTestUserForVerification(verified: true);
    $service = makeVerificationService();

    expect($service->isVerified($user))->toBeTrue();
});

it('returns false for isVerified when emailVerifiedAt is null', function () {
    $user = makeTestUserForVerification();
    $service = makeVerificationService();

    expect($service->isVerified($user))->toBeFalse();
});
