# Foundation Remaining Features Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement email verification, password recovery, and OAuth token exchange to close Foundation's exit criteria.

**Architecture:** Three independent features sharing test infrastructure (StubMailer). Email verification uses stateless HMAC-signed URLs. Password recovery uses hashed DB tokens. OAuth uses native cURL with provider normalization. All follow existing Marko patterns: entities, repositories, services, controllers with middleware.

**Tech Stack:** PHP 8.5+, Marko framework, PostgreSQL, Latte templates, Pest PHP, Mailpit (dev SMTP)

**Spec:** `docs/spec/01-foundation/remaining-features.md`

---

## File Structure

### New Files
```
app/foundation/src/Service/EmailVerificationService.php    — HMAC signing, email sending, verification
app/foundation/src/Controller/Auth/EmailVerificationController.php — verify + resend routes
app/foundation/resources/views/email/verify.latte          — verification email template

app/foundation/src/Entity/PasswordResetToken.php           — token entity
app/foundation/src/Repository/PasswordResetTokenRepository.php — token lookup + bulk delete
app/foundation/src/Service/PasswordResetService.php        — token generation, validation, reset
app/foundation/src/Controller/Auth/PasswordResetController.php — forgot + reset routes
app/foundation/resources/views/auth/forgot-password.latte  — email input form
app/foundation/resources/views/auth/reset-password.latte   — new password form
app/foundation/resources/views/email/password-reset.latte  — reset email template

app/foundation/src/Service/OAuthHttpClient.php             — cURL token exchange + profile fetch
app/foundation/src/Exception/OAuthException.php            — OAuth-specific exception

app/foundation/tests/Support/StubMailer.php                — captures sent emails for testing
app/foundation/tests/Feature/EmailVerificationTest.php
app/foundation/tests/Feature/PasswordResetTest.php
app/foundation/tests/Unit/PasswordResetServiceTest.php
app/foundation/tests/Feature/OAuthHttpClientTest.php
```

### Modified Files
```
app/foundation/module.php                                  — register new services/repos
app/foundation/src/Controller/Auth/RegisterController.php  — inject + call verification service
app/foundation/src/Service/SocialAuthService.php           — set emailVerifiedAt on social users
app/foundation/src/Controller/Auth/SocialAuthController.php — inject OAuthHttpClient, delete stub
app/foundation/resources/views/layout/game.latte           — verification banner
app/foundation/resources/views/layout/lobby.latte          — verification banner
app/foundation/resources/views/auth/login.latte            — "Forgot password?" link
app/foundation/tests/Feature/RegisterTest.php              — add verification email assertion
app/foundation/tests/Feature/SocialAuthTest.php            — add emailVerifiedAt assertion
```

---

### Task 1: Test Infrastructure — StubMailer

**Files:**
- Create: `app/foundation/tests/Support/StubMailer.php`

- [ ] **Step 1: Create StubMailer**

```php
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
```

- [ ] **Step 2: Verify it autoloads**

Run: `./vendor/bin/pest --filter "it registers"` (any passing test — confirms autoload works)
Expected: Tests still pass (no regressions).

- [ ] **Step 3: Commit**

```bash
git add app/foundation/tests/Support/StubMailer.php
git commit -m "Add StubMailer test double for email assertions"
```

---

### Task 2: Email Verification Service

**Files:**
- Create: `app/foundation/src/Service/EmailVerificationService.php`
- Create: `app/foundation/tests/Feature/EmailVerificationTest.php`
- Create: `app/foundation/resources/views/email/verify.latte`
- Modify: `app/foundation/module.php`

- [ ] **Step 1: Write failing tests for EmailVerificationService**

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest --filter "EmailVerification"`
Expected: FAIL — class `EmailVerificationService` not found.

- [ ] **Step 3: Create the email template**

```latte
{* app/foundation/resources/views/email/verify.latte *}
<div style="font-family: Inter, system-ui, sans-serif; max-width: 480px; margin: 0 auto; padding: 32px 24px; background: #1a1a24; color: #c0c4d8; border-radius: 8px;">
    <h1 style="color: #5a6aaa; font-size: 20px; margin: 0 0 16px 0;">Welcome to Shilla, {$username}</h1>
    <p style="margin: 0 0 24px 0; line-height: 1.6;">Click the link below to verify your email address.</p>
    <a href="{$url}" style="display: inline-block; background: #5a6aaa; color: #ffffff; padding: 10px 24px; border-radius: 6px; text-decoration: none; font-weight: 600;">Verify Email</a>
    <p style="margin: 24px 0 0 0; font-size: 13px; color: #5a5a70;">This link expires in 1 hour. If you didn't create a Shilla account, ignore this email.</p>
    <p style="margin: 12px 0 0 0; font-size: 12px; color: #5a5a70; word-break: break-all;">{$url}</p>
</div>
```

- [ ] **Step 4: Implement EmailVerificationService**

```php
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
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `./vendor/bin/pest --filter "EmailVerification"`
Expected: 8 tests pass.

- [ ] **Step 6: Add factory binding to module.php**

In `app/foundation/module.php`, add to the `bindings` array:

```php
use App\Foundation\Service\EmailVerificationService;
use Marko\Mail\Contracts\MailerInterface;

// In 'bindings' array:
EmailVerificationService::class => function ($container) {
    return new EmailVerificationService(
        mailer: $container->get(MailerInterface::class),
        userRepository: $container->get(\App\Foundation\Repository\UserRepository::class),
        view: $container->get(\Marko\View\ViewInterface::class),
        encryptionKey: env('ENCRYPTION_KEY', ''),
        appUrl: env('APP_URL', 'http://localhost:8001'),
    );
},
```

- [ ] **Step 7: Run full test suite**

Run: `./vendor/bin/pest`
Expected: All tests pass (previous 44 + new 8).

- [ ] **Step 8: Commit**

```bash
git add app/foundation/src/Service/EmailVerificationService.php \
       app/foundation/tests/Feature/EmailVerificationTest.php \
       app/foundation/resources/views/email/verify.latte \
       app/foundation/module.php
git commit -m "Add EmailVerificationService with HMAC-signed URLs"
```

---

### Task 3: Email Verification Controller + Templates

**Files:**
- Create: `app/foundation/src/Controller/Auth/EmailVerificationController.php`
- Modify: `app/foundation/resources/views/layout/game.latte`
- Modify: `app/foundation/resources/views/layout/lobby.latte`
- Modify: `app/foundation/tests/Feature/EmailVerificationTest.php`

- [ ] **Step 1: Add controller tests to EmailVerificationTest.php**

Append to the existing test file:

```php
use App\Foundation\Controller\Auth\EmailVerificationController;
use App\Foundation\Tests\Support\StubCsrfTokenManager;
use Marko\Routing\Http\Request;
use Marko\Testing\Fake\FakeGuard;

function makeVerificationController(
    ?object $guard = null,
    ?object $verificationService = null,
    ?object $csrfTokenManager = null,
    ?object $view = null,
): EmailVerificationController {
    return new EmailVerificationController(
        guard: $guard ?? new FakeGuard(),
        verificationService: $verificationService ?? makeVerificationService(),
        csrfTokenManager: $csrfTokenManager ?? new StubCsrfTokenManager(),
        view: $view ?? makeStubViewForEmail(),
    );
}

it('redirects already-verified users to game on verify', function () {
    $user = makeTestUserForVerification(verified: true);
    $guard = new FakeGuard();
    $guard->setUser($user);

    $controller = makeVerificationController(guard: $guard);

    $request = new Request(query: ['id' => '1', 'expires' => (string) (time() + 3600), 'signature' => 'any']);
    $response = $controller->verify($request);

    expect($response->statusCode())->toBe(302)
        ->and($response->headers()['Location'])->toBe('/game');
});

it('rejects verification when user ID does not match', function () {
    $user = makeTestUserForVerification();
    $guard = new FakeGuard();
    $guard->setUser($user);

    $view = makeStubViewForEmail();
    $controller = makeVerificationController(guard: $guard, view: $view);

    $request = new Request(query: ['id' => '999', 'expires' => (string) (time() + 3600), 'signature' => 'any']);
    $response = $controller->verify($request);

    expect($response->statusCode())->toBe(200)
        ->and($view->lastData['error'] ?? null)->not->toBeNull();
});

it('resend sends email and redirects for unverified user', function () {
    $user = makeTestUserForVerification();
    $guard = new FakeGuard();
    $guard->setUser($user);

    $mailer = new StubMailer();
    $service = makeVerificationService(mailer: $mailer);
    $controller = makeVerificationController(guard: $guard, verificationService: $service);

    $request = new Request(post: ['_token' => 'test-csrf-token']);
    $response = $controller->resend($request);

    expect($response->statusCode())->toBe(302)
        ->and($mailer->sent)->toHaveCount(1);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest --filter "EmailVerification"`
Expected: FAIL — `EmailVerificationController` not found.

- [ ] **Step 3: Implement EmailVerificationController**

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Controller\Auth;

use App\Foundation\Entity\User;
use App\Foundation\Service\EmailVerificationService;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\RateLimiting\Middleware\RateLimitMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\Security\Middleware\CsrfMiddleware;
use Marko\Security\Middleware\SecurityHeadersMiddleware;
use Marko\View\ViewInterface;

#[Middleware([AuthMiddleware::class, SecurityHeadersMiddleware::class, CsrfMiddleware::class, RateLimitMiddleware::class])]
class EmailVerificationController
{
    public function __construct(
        private readonly GuardInterface $guard,
        private readonly EmailVerificationService $verificationService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly ViewInterface $view,
    ) {}

    #[Get('/verify-email')]
    public function verify(Request $request): Response
    {
        /** @var User $user */
        $user = $this->guard->user();

        if ($this->verificationService->isVerified($user)) {
            return Response::redirect('/game');
        }

        $id = (int) $request->query('id', '0');
        $expires = (int) $request->query('expires', '0');
        $signature = $request->query('signature', '');

        if ($id !== $user->id) {
            return $this->renderError('Invalid verification link.');
        }

        if (!$this->verificationService->verify($user, $expires, $signature)) {
            return $this->renderError('This link has expired. Request a new one.');
        }

        return Response::redirect('/game?verified=1');
    }

    #[Post('/verify-email/resend')]
    public function resend(Request $request): Response
    {
        /** @var User $user */
        $user = $this->guard->user();

        if ($this->verificationService->isVerified($user)) {
            return Response::redirect('/game');
        }

        try {
            $this->verificationService->sendVerificationEmail($user);
        } catch (\Throwable) {
            // Log but don't block — email delivery failures shouldn't break UX
        }

        return Response::redirect('/game');
    }

    private function renderError(string $message): Response
    {
        return $this->view->render('foundation::auth/verify-email', [
            'error' => $message,
            'csrfToken' => $this->csrfTokenManager->get(),
        ]);
    }
}
```

- [ ] **Step 4: Check if FakeGuard has setUser method**

Run: `grep -n 'function setUser\|public.*user' vendor/marko/testing/src/Fake/FakeGuard.php`

If `setUser` doesn't exist, the test needs to use a different approach. The existing tests use `FakeGuard` without setting a user (they test controllers that don't call `$guard->user()`). Check the FakeGuard implementation and adjust the test factory accordingly — you may need to create a custom stub guard or use Mockery.

- [ ] **Step 5: Run tests to verify they pass**

Run: `./vendor/bin/pest --filter "EmailVerification"`
Expected: All 11 tests pass.

- [ ] **Step 6: Add verification banner to lobby.latte**

In `app/foundation/resources/views/layout/lobby.latte`, after the closing `</header>` tag (after line 28), add:

```latte
    {if isset($user) && $user->emailVerifiedAt === null}
    <div class="bg-[#2a2518] border-b border-[#3a3528] px-6 py-1.5 flex items-center justify-between text-xs max-w-[640px] mx-auto">
        <span class="text-[#d4a840]">Verify your email to secure your account.</span>
        <form method="POST" action="/verify-email/resend" class="inline">
            <input type="hidden" name="_token" value="{$csrfToken}">
            <button type="submit" class="text-accent hover:text-accent-hover underline">Resend link</button>
        </form>
    </div>
    {/if}
```

- [ ] **Step 7: Add verification banner to game.latte**

In `app/foundation/resources/views/layout/game.latte`, after the closing `</header>` tag inside the flex column, add the same banner (adapted for full-width game layout):

```latte
        {if isset($user) && $user->emailVerifiedAt === null}
        <div class="bg-[#2a2518] border-b border-[#3a3528] px-3 py-1.5 flex items-center justify-between text-xs flex-shrink-0">
            <span class="text-[#d4a840]">Verify your email to secure your account.</span>
            <form method="POST" action="/verify-email/resend" class="inline">
                <input type="hidden" name="_token" value="{$csrfToken}">
                <button type="submit" class="text-accent hover:text-accent-hover underline">Resend link</button>
            </form>
        </div>
        {/if}
```

- [ ] **Step 8: Run full test suite**

Run: `./vendor/bin/pest`
Expected: All tests pass.

- [ ] **Step 9: Commit**

```bash
git add app/foundation/src/Controller/Auth/EmailVerificationController.php \
       app/foundation/resources/views/layout/game.latte \
       app/foundation/resources/views/layout/lobby.latte \
       app/foundation/tests/Feature/EmailVerificationTest.php
git commit -m "Add EmailVerificationController with banners and tests"
```

---

### Task 4: Wire Email Verification into Registration + Social Auth

**Files:**
- Modify: `app/foundation/src/Controller/Auth/RegisterController.php`
- Modify: `app/foundation/src/Service/SocialAuthService.php`
- Modify: `app/foundation/tests/Feature/RegisterTest.php`
- Modify: `app/foundation/tests/Feature/SocialAuthTest.php`

- [ ] **Step 1: Add test to RegisterTest — registration sends verification email**

Append to `app/foundation/tests/Feature/RegisterTest.php`:

```php
use App\Foundation\Tests\Support\StubMailer;
use App\Foundation\Service\EmailVerificationService;

it('sends verification email after registration', function () {
    $guard = new FakeGuard();
    $userRepo = makeStubUserRepo();
    $mailer = new StubMailer();
    $view = makeStubView();

    $verificationService = new EmailVerificationService(
        mailer: $mailer,
        userRepository: $userRepo,
        view: $view,
        encryptionKey: 'test-key',
        appUrl: 'http://localhost:8001',
    );

    $controller = new RegisterController(
        view: $view,
        userRepository: $userRepo,
        guard: $guard,
        hasher: makeStubHasher(),
        validator: makeStubValidator(),
        csrfTokenManager: new StubCsrfTokenManager(),
        verificationService: $verificationService,
    );

    $request = new Request(post: [
        'username' => 'newuser',
        'email' => 'new@example.com',
        'password' => 'SecurePass123!',
    ]);

    $response = $controller->store($request);

    expect($response->statusCode())->toBe(302)
        ->and($mailer->sent)->toHaveCount(1)
        ->and($mailer->sent[0]->to[0]->email)->toBe('new@example.com');
});
```

- [ ] **Step 2: Add test to SocialAuthTest — social auth sets emailVerifiedAt**

Append to `app/foundation/tests/Feature/SocialAuthTest.php`:

```php
it('sets emailVerifiedAt when creating social user', function () {
    $userRepo = Mockery::mock(UserRepository::class);
    $userRepo->shouldReceive('findByEmail')->with('new@example.com')->andReturn(null);
    $userRepo->shouldReceive('findByUsername')->with('NewUser')->andReturn(null);
    $userRepo->shouldReceive('save')->once()->andReturnUsing(function (User $user) {
        $user->id = 99;
        expect($user->emailVerifiedAt)->not->toBeNull();
    });

    $socialRepo = Mockery::mock(SocialAccountRepository::class);
    $socialRepo->shouldReceive('findByProvider')->with('google', '99999')->andReturn(null);
    $socialRepo->shouldReceive('save')->once();

    $service = new SocialAuthService($userRepo, $socialRepo, makeSocialStubHasher(), new StubSession());

    $result = $service->handleCallback('google', makeSocialProfile('99999', 'new@example.com', 'NewUser'));

    expect($result['action'])->toBe('created');
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `./vendor/bin/pest --filter "sends verification|sets emailVerifiedAt when creating"`
Expected: FAIL — RegisterController doesn't accept `verificationService` param yet, SocialAuthService doesn't set `emailVerifiedAt`.

- [ ] **Step 4: Modify RegisterController to inject and call EmailVerificationService**

In `app/foundation/src/Controller/Auth/RegisterController.php`:

Add to imports:
```php
use App\Foundation\Service\EmailVerificationService;
```

Add to constructor:
```php
private readonly EmailVerificationService $verificationService,
```

After `$this->guard->login($user);` (line 88), add:
```php
try {
    $this->verificationService->sendVerificationEmail($user);
} catch (\Throwable) {
    // Log but don't block registration
}
```

- [ ] **Step 5: Update makeRegisterController in RegisterTest.php**

The factory function needs the new param. Update it to accept and pass `verificationService`:

```php
function makeRegisterController(
    ?object $view = null,
    ?object $userRepo = null,
    ?object $guard = null,
    ?object $hasher = null,
    ?object $validator = null,
    ?object $verificationService = null,
): RegisterController {
    $stubMailer = new \App\Foundation\Tests\Support\StubMailer();
    $stubView = $view ?? makeStubView();
    return new RegisterController(
        view: $stubView,
        userRepository: $userRepo ?? makeStubUserRepo(),
        guard: $guard ?? new FakeGuard(),
        hasher: $hasher ?? makeStubHasher(),
        validator: $validator ?? makeStubValidator(),
        csrfTokenManager: new StubCsrfTokenManager(),
        verificationService: $verificationService ?? new \App\Foundation\Service\EmailVerificationService(
            mailer: $stubMailer,
            userRepository: $userRepo ?? makeStubUserRepo(),
            view: $stubView,
            encryptionKey: 'test-key',
            appUrl: 'http://localhost:8001',
        ),
    );
}
```

- [ ] **Step 6: Modify SocialAuthService to set emailVerifiedAt**

In `app/foundation/src/Service/SocialAuthService.php`, in the `handleCallback()` method, after line 70 (`$user->createdAt = new \DateTimeImmutable();`), add:

```php
$user->emailVerifiedAt = new \DateTimeImmutable();
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `./vendor/bin/pest`
Expected: All tests pass.

- [ ] **Step 8: Commit**

```bash
git add app/foundation/src/Controller/Auth/RegisterController.php \
       app/foundation/src/Service/SocialAuthService.php \
       app/foundation/tests/Feature/RegisterTest.php \
       app/foundation/tests/Feature/SocialAuthTest.php
git commit -m "Wire email verification into registration and social auth"
```

---

### Task 5: Password Reset Entity + Repository

**Files:**
- Create: `app/foundation/src/Entity/PasswordResetToken.php`
- Create: `app/foundation/src/Repository/PasswordResetTokenRepository.php`
- Modify: `app/foundation/module.php`

- [ ] **Step 1: Create PasswordResetToken entity**

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Entity;

use DateTimeImmutable;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('password_reset_tokens')]
class PasswordResetToken extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(name: 'user_id')]
    public int $userId;

    #[Column(name: 'token_hash', length: 64)]
    public string $tokenHash;

    #[Column(name: 'created_at', default: 'CURRENT_TIMESTAMP')]
    public ?DateTimeImmutable $createdAt = null;

    public function isExpired(int $lifetimeMinutes = 60): bool
    {
        if ($this->createdAt === null) {
            return true;
        }
        $expiry = $this->createdAt->modify("+{$lifetimeMinutes} minutes");
        return new DateTimeImmutable() > $expiry;
    }
}
```

- [ ] **Step 2: Create PasswordResetTokenRepository**

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Repository;

use App\Foundation\Entity\PasswordResetToken;
use Marko\Database\Repository\Repository;

class PasswordResetTokenRepository extends Repository
{
    protected const string ENTITY_CLASS = PasswordResetToken::class;

    public function findByTokenHash(string $tokenHash): ?PasswordResetToken
    {
        return $this->findOneBy(['token_hash' => $tokenHash]);
    }

    public function deleteByUserId(int $userId): void
    {
        $this->connection->execute(
            'DELETE FROM password_reset_tokens WHERE user_id = ?',
            [$userId],
        );
    }

    public function deleteExpired(int $lifetimeMinutes = 60): int
    {
        $cutoff = (new \DateTimeImmutable())
            ->modify("-{$lifetimeMinutes} minutes")
            ->format('Y-m-d H:i:s');

        return $this->connection->execute(
            'DELETE FROM password_reset_tokens WHERE created_at < ?',
            [$cutoff],
        );
    }
}
```

- [ ] **Step 3: Register in module.php**

Add to `singletons` array in `app/foundation/module.php`:

```php
use App\Foundation\Repository\PasswordResetTokenRepository;

// In 'singletons' array:
PasswordResetTokenRepository::class,
```

- [ ] **Step 4: Run migrations to create the table**

Run: `./vendor/bin/marko db:migrate`
Expected: Generates and applies a `create_password_reset_tokens` migration.

- [ ] **Step 5: Run full test suite**

Run: `./vendor/bin/pest`
Expected: All tests pass (no regressions).

- [ ] **Step 6: Commit**

```bash
git add app/foundation/src/Entity/PasswordResetToken.php \
       app/foundation/src/Repository/PasswordResetTokenRepository.php \
       app/foundation/module.php
git commit -m "Add PasswordResetToken entity and repository"
```

---

### Task 6: Password Reset Service

**Files:**
- Create: `app/foundation/src/Service/PasswordResetService.php`
- Create: `app/foundation/tests/Unit/PasswordResetServiceTest.php`
- Create: `app/foundation/resources/views/email/password-reset.latte`
- Modify: `app/foundation/module.php`

- [ ] **Step 1: Write failing tests for PasswordResetService**

```php
<?php

declare(strict_types=1);

use App\Foundation\Entity\PasswordResetToken;
use App\Foundation\Entity\User;
use App\Foundation\Repository\PasswordResetTokenRepository;
use App\Foundation\Repository\UserRepository;
use App\Foundation\Service\PasswordResetService;
use App\Foundation\Tests\Support\StubMailer;
use Marko\Routing\Http\Response;

function makeStubViewForResetEmail(): object
{
    return new class implements \Marko\View\ViewInterface {
        public function render(string $template, array $data = []): Response
        {
            return Response::html('<html>stub</html>');
        }

        public function renderToString(string $template, array $data = []): string
        {
            return '<html>reset email</html>';
        }
    };
}

function makeStubHasherForReset(): object
{
    return new class implements \Marko\Hashing\Contracts\HasherInterface {
        public function hash(string $value): string { return 'hashed_' . $value; }
        public function verify(string $value, string $hash): bool { return 'hashed_' . $value === $hash; }
        public function needsRehash(string $hash): bool { return false; }
        public function algorithm(): string { return 'stub'; }
    };
}

function makeUserWithPasswordForReset(): User
{
    $user = new User();
    $user->id = 1;
    $user->username = 'testuser';
    $user->email = 'test@example.com';
    $user->password = 'hashed_oldpassword';
    return $user;
}

function makeSocialOnlyUserForReset(): User
{
    $user = new User();
    $user->id = 2;
    $user->username = 'socialuser';
    $user->email = 'social@example.com';
    $user->password = null;
    return $user;
}

it('sendResetLink sends email for existing user with password', function () {
    $user = makeUserWithPasswordForReset();
    $mailer = new StubMailer();

    $userRepo = Mockery::mock(UserRepository::class);
    $userRepo->shouldReceive('findByEmail')->with('test@example.com')->andReturn($user);

    $tokenRepo = Mockery::mock(PasswordResetTokenRepository::class);
    $tokenRepo->shouldReceive('deleteByUserId')->with(1)->once();
    $tokenRepo->shouldReceive('save')->once();

    $service = new PasswordResetService(
        userRepository: $userRepo,
        tokenRepository: $tokenRepo,
        hasher: makeStubHasherForReset(),
        mailer: $mailer,
        view: makeStubViewForResetEmail(),
        appUrl: 'http://localhost:8001',
    );

    $service->sendResetLink('test@example.com');

    expect($mailer->sent)->toHaveCount(1)
        ->and($mailer->sent[0]->to[0]->email)->toBe('test@example.com')
        ->and($mailer->sent[0]->subject)->toBe('Reset your Shilla password');
});

it('sendResetLink sends nothing for nonexistent email', function () {
    $mailer = new StubMailer();

    $userRepo = Mockery::mock(UserRepository::class);
    $userRepo->shouldReceive('findByEmail')->with('nobody@example.com')->andReturn(null);

    $tokenRepo = Mockery::mock(PasswordResetTokenRepository::class);

    $service = new PasswordResetService(
        userRepository: $userRepo,
        tokenRepository: $tokenRepo,
        hasher: makeStubHasherForReset(),
        mailer: $mailer,
        view: makeStubViewForResetEmail(),
        appUrl: 'http://localhost:8001',
    );

    $service->sendResetLink('nobody@example.com');

    expect($mailer->sent)->toHaveCount(0);
});

it('sendResetLink sends nothing for social-only user', function () {
    $user = makeSocialOnlyUserForReset();
    $mailer = new StubMailer();

    $userRepo = Mockery::mock(UserRepository::class);
    $userRepo->shouldReceive('findByEmail')->with('social@example.com')->andReturn($user);

    $tokenRepo = Mockery::mock(PasswordResetTokenRepository::class);

    $service = new PasswordResetService(
        userRepository: $userRepo,
        tokenRepository: $tokenRepo,
        hasher: makeStubHasherForReset(),
        mailer: $mailer,
        view: makeStubViewForResetEmail(),
        appUrl: 'http://localhost:8001',
    );

    $service->sendResetLink('social@example.com');

    expect($mailer->sent)->toHaveCount(0);
});

it('sendResetLink deletes previous tokens before creating new one', function () {
    $user = makeUserWithPasswordForReset();
    $mailer = new StubMailer();

    $userRepo = Mockery::mock(UserRepository::class);
    $userRepo->shouldReceive('findByEmail')->with('test@example.com')->andReturn($user);

    $deleteCalledBeforeSave = false;
    $tokenRepo = Mockery::mock(PasswordResetTokenRepository::class);
    $tokenRepo->shouldReceive('deleteByUserId')->with(1)->once()->andReturnUsing(function () use (&$deleteCalledBeforeSave) {
        $deleteCalledBeforeSave = true;
    });
    $tokenRepo->shouldReceive('save')->once()->andReturnUsing(function () use (&$deleteCalledBeforeSave) {
        expect($deleteCalledBeforeSave)->toBeTrue();
    });

    $service = new PasswordResetService(
        userRepository: $userRepo,
        tokenRepository: $tokenRepo,
        hasher: makeStubHasherForReset(),
        mailer: $mailer,
        view: makeStubViewForResetEmail(),
        appUrl: 'http://localhost:8001',
    );

    $service->sendResetLink('test@example.com');
});

it('findValidToken returns null for malformed token', function () {
    $tokenRepo = Mockery::mock(PasswordResetTokenRepository::class);
    $userRepo = Mockery::mock(UserRepository::class);

    $service = new PasswordResetService(
        userRepository: $userRepo,
        tokenRepository: $tokenRepo,
        hasher: makeStubHasherForReset(),
        mailer: new StubMailer(),
        view: makeStubViewForResetEmail(),
        appUrl: 'http://localhost:8001',
    );

    expect($service->findValidToken('too-short'))->toBeNull()
        ->and($service->findValidToken('not-hex-chars-not-hex-chars-not-hex-chars-not-hex-chars-not-hex!!'))->toBeNull();
});

it('resetPassword updates password hash and deletes all user tokens', function () {
    $user = makeUserWithPasswordForReset();

    $token = new PasswordResetToken();
    $token->id = 1;
    $token->userId = 1;
    $token->tokenHash = hash('sha256', str_repeat('a', 64));
    $token->createdAt = new \DateTimeImmutable();

    $userRepo = Mockery::mock(UserRepository::class);
    $userRepo->shouldReceive('find')->with(1)->andReturn($user);
    $userRepo->shouldReceive('save')->once()->withArgs(function (User $u) {
        return $u->password === 'hashed_newpassword123';
    });

    $tokenRepo = Mockery::mock(PasswordResetTokenRepository::class);
    $tokenRepo->shouldReceive('findByTokenHash')->andReturn($token);
    $tokenRepo->shouldReceive('deleteByUserId')->with(1)->once();

    $service = new PasswordResetService(
        userRepository: $userRepo,
        tokenRepository: $tokenRepo,
        hasher: makeStubHasherForReset(),
        mailer: new StubMailer(),
        view: makeStubViewForResetEmail(),
        appUrl: 'http://localhost:8001',
    );

    $result = $service->resetPassword(str_repeat('a', 64), 'newpassword123');

    expect($result)->toBeTrue();
});

it('resetPassword returns false for invalid token', function () {
    $tokenRepo = Mockery::mock(PasswordResetTokenRepository::class);
    $tokenRepo->shouldReceive('findByTokenHash')->andReturn(null);

    $service = new PasswordResetService(
        userRepository: Mockery::mock(UserRepository::class),
        tokenRepository: $tokenRepo,
        hasher: makeStubHasherForReset(),
        mailer: new StubMailer(),
        view: makeStubViewForResetEmail(),
        appUrl: 'http://localhost:8001',
    );

    $result = $service->resetPassword(str_repeat('b', 64), 'newpassword');

    expect($result)->toBeFalse();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest --filter "PasswordResetService"`
Expected: FAIL — class `PasswordResetService` not found.

- [ ] **Step 3: Create the email template**

```latte
{* app/foundation/resources/views/email/password-reset.latte *}
<div style="font-family: Inter, system-ui, sans-serif; max-width: 480px; margin: 0 auto; padding: 32px 24px; background: #1a1a24; color: #c0c4d8; border-radius: 8px;">
    <h1 style="color: #5a6aaa; font-size: 20px; margin: 0 0 16px 0;">Reset Your Password</h1>
    <p style="margin: 0 0 8px 0; line-height: 1.6;">You requested a password reset for your Shilla account (<strong>{$username}</strong>).</p>
    <p style="margin: 0 0 24px 0; line-height: 1.6;">Click the button below to choose a new password. This link expires in 60 minutes.</p>
    <a href="{$resetUrl}" style="display: inline-block; background: #5a6aaa; color: #ffffff; padding: 10px 24px; border-radius: 6px; text-decoration: none; font-weight: 600;">Reset Password</a>
    <p style="margin: 24px 0 0 0; font-size: 13px; color: #5a5a70;">If you did not request this, ignore this email. Your password will remain unchanged.</p>
    <p style="margin: 12px 0 0 0; font-size: 12px; color: #5a5a70; word-break: break-all;">{$resetUrl}</p>
</div>
```

- [ ] **Step 4: Implement PasswordResetService**

```php
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
```

- [ ] **Step 5: Register in module.php**

Add to `singletons` array:

```php
use App\Foundation\Service\PasswordResetService;

// In 'singletons' array:
PasswordResetService::class,
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `./vendor/bin/pest --filter "PasswordResetService"`
Expected: 7 tests pass.

- [ ] **Step 7: Run full test suite**

Run: `./vendor/bin/pest`
Expected: All tests pass.

- [ ] **Step 8: Commit**

```bash
git add app/foundation/src/Service/PasswordResetService.php \
       app/foundation/tests/Unit/PasswordResetServiceTest.php \
       app/foundation/resources/views/email/password-reset.latte \
       app/foundation/module.php
git commit -m "Add PasswordResetService with hashed tokens and email sending"
```

---

### Task 7: Password Reset Controller + Templates

**Files:**
- Create: `app/foundation/src/Controller/Auth/PasswordResetController.php`
- Create: `app/foundation/resources/views/auth/forgot-password.latte`
- Create: `app/foundation/resources/views/auth/reset-password.latte`
- Create: `app/foundation/tests/Feature/PasswordResetTest.php`
- Modify: `app/foundation/resources/views/auth/login.latte`

- [ ] **Step 1: Write failing controller tests**

```php
<?php

declare(strict_types=1);

use App\Foundation\Controller\Auth\PasswordResetController;
use App\Foundation\Entity\PasswordResetToken;
use App\Foundation\Service\PasswordResetService;
use App\Foundation\Tests\Support\StubCsrfTokenManager;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Validation\Validation\ValidationErrors;

function makeResetStubView(): object
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
            return '<html>stub</html>';
        }
    };
}

function makeResetStubValidator(bool $passes = true): object
{
    return new class($passes) implements \Marko\Validation\Contracts\ValidatorInterface {
        public function __construct(private readonly bool $passes) {}

        public function validate(array $data, array $rules): ValidationErrors
        {
            if ($this->passes) {
                return new ValidationErrors();
            }
            $errors = new ValidationErrors();
            $errors->add('email', 'Invalid email');
            return $errors;
        }

        public function validateOrFail(array $data, array $rules): void {}
        public function passes(array $data, array $rules): bool { return $this->passes; }
        public function fails(array $data, array $rules): bool { return !$this->passes; }
    };
}

function makeResetController(
    ?object $view = null,
    ?object $resetService = null,
    ?object $validator = null,
): PasswordResetController {
    return new PasswordResetController(
        view: $view ?? makeResetStubView(),
        passwordResetService: $resetService ?? Mockery::mock(PasswordResetService::class),
        validator: $validator ?? makeResetStubValidator(),
        csrfTokenManager: new StubCsrfTokenManager(),
    );
}

it('renders forgot-password form with CSRF token', function () {
    $view = makeResetStubView();
    $controller = makeResetController(view: $view);

    $response = $controller->show();

    expect($response->statusCode())->toBe(200)
        ->and($view->lastTemplate)->toBe('foundation::auth/forgot-password')
        ->and($view->lastData['csrfToken'])->toBe('test-csrf-token');
});

it('submits valid email and shows generic success message', function () {
    $view = makeResetStubView();
    $resetService = Mockery::mock(PasswordResetService::class);
    $resetService->shouldReceive('sendResetLink')->with('test@example.com')->once();

    $controller = makeResetController(view: $view, resetService: $resetService);

    $request = new Request(post: ['email' => 'test@example.com']);
    $response = $controller->sendResetLink($request);

    expect($response->statusCode())->toBe(200)
        ->and($view->lastData['success'])->toContain('If an account');
});

it('submits invalid email format and shows validation errors', function () {
    $view = makeResetStubView();
    $controller = makeResetController(view: $view, validator: makeResetStubValidator(passes: false));

    $request = new Request(post: ['email' => 'not-an-email']);
    $response = $controller->sendResetLink($request);

    expect($response->statusCode())->toBe(200)
        ->and($view->lastData['errors'])->toBeInstanceOf(ValidationErrors::class);
});

it('renders reset form with valid token', function () {
    $view = makeResetStubView();

    $token = new PasswordResetToken();
    $token->id = 1;
    $token->userId = 1;
    $token->createdAt = new \DateTimeImmutable();

    $resetService = Mockery::mock(PasswordResetService::class);
    $resetService->shouldReceive('findValidToken')->with(str_repeat('a', 64))->andReturn($token);

    $controller = makeResetController(view: $view, resetService: $resetService);
    $response = $controller->showResetForm(str_repeat('a', 64));

    expect($response->statusCode())->toBe(200)
        ->and($view->lastTemplate)->toBe('foundation::auth/reset-password')
        ->and($view->lastData['token'])->toBe(str_repeat('a', 64));
});

it('renders error for expired or invalid token', function () {
    $resetService = Mockery::mock(PasswordResetService::class);
    $resetService->shouldReceive('findValidToken')->andReturn(null);

    $view = makeResetStubView();
    $controller = makeResetController(view: $view, resetService: $resetService);

    $response = $controller->showResetForm(str_repeat('b', 64));

    expect($response->statusCode())->toBe(200)
        ->and($view->lastData['error'])->not->toBeNull();
});

it('resets password with valid token and redirects to login', function () {
    $resetService = Mockery::mock(PasswordResetService::class);
    $resetService->shouldReceive('resetPassword')
        ->with(str_repeat('a', 64), 'NewSecurePass1!')
        ->andReturn(true);

    $controller = makeResetController(resetService: $resetService);

    $request = new Request(post: [
        'token' => str_repeat('a', 64),
        'password' => 'NewSecurePass1!',
        'password_confirmation' => 'NewSecurePass1!',
    ]);
    $response = $controller->resetPassword($request);

    expect($response->statusCode())->toBe(302)
        ->and($response->headers()['Location'])->toBe('/login');
});

it('rejects mismatched password confirmation', function () {
    $view = makeResetStubView();
    $controller = makeResetController(view: $view);

    $request = new Request(post: [
        'token' => str_repeat('a', 64),
        'password' => 'NewSecurePass1!',
        'password_confirmation' => 'different',
    ]);
    $response = $controller->resetPassword($request);

    expect($response->statusCode())->toBe(200)
        ->and($view->lastData['errors']->has('password_confirmation'))->toBeTrue();
});

it('rejects short password', function () {
    $view = makeResetStubView();
    $controller = makeResetController(view: $view, validator: makeResetStubValidator(passes: false));

    $request = new Request(post: [
        'token' => str_repeat('a', 64),
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);
    $response = $controller->resetPassword($request);

    expect($response->statusCode())->toBe(200)
        ->and($view->lastData['errors'])->toBeInstanceOf(ValidationErrors::class);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest --filter "PasswordResetTest"`
Expected: FAIL — `PasswordResetController` not found.

- [ ] **Step 3: Create forgot-password.latte**

```latte
{layout 'foundation::layout/auth'}

{block title}Forgot Password — Shilla{/block}

{block subtitle}Reset your password{/block}

{block content}
<form method="POST" action="/forgot-password" class="space-y-4">
    <input type="hidden" name="_token" value="{$csrfToken}">

    {if isset($success)}
    <div class="bg-stamina-dark/10 border border-stamina-dark/30 rounded-md px-3 py-2 text-stamina-dark text-sm">
        {$success}
    </div>
    {/if}

    {if isset($errors) && $errors->has('email')}
    <div class="bg-danger/10 border border-danger/30 rounded-md px-3 py-2 text-danger text-sm">
        {$errors->first('email')}
    </div>
    {/if}

    <div>
        <label for="email" class="block text-xs font-medium text-text-muted mb-1">Email Address</label>
        <input type="email" id="email" name="email" value="{$old['email'] ?? ''}"
               class="w-full bg-[#22222c] border border-twilight-border rounded-md px-3 py-2 text-sm text-text-primary placeholder-text-muted focus:border-accent focus:outline-none transition-colors duration-150"
               placeholder="Enter your email address" required>
    </div>

    <button type="submit" class="w-full bg-accent hover:bg-accent-hover text-white font-medium py-2 rounded-md transition-colors duration-150">
        Send Reset Link
    </button>
</form>
{/block}

{block footer_link}
<a href="/login" class="text-accent hover:text-accent-hover transition-colors duration-150">Back to login</a>
{/block}
```

- [ ] **Step 4: Create reset-password.latte**

```latte
{layout 'foundation::layout/auth'}

{block title}Reset Password — Shilla{/block}

{block subtitle}Choose a new password{/block}

{block content}
{if isset($error)}
<div class="space-y-4">
    <div class="bg-danger/10 border border-danger/30 rounded-md px-3 py-2 text-danger text-sm">
        {$error}
    </div>
    <a href="/forgot-password" class="text-accent hover:text-accent-hover text-sm transition-colors duration-150">Request a new reset link</a>
</div>
{else}
<form method="POST" action="/reset-password" class="space-y-4">
    <input type="hidden" name="_token" value="{$csrfToken}">
    <input type="hidden" name="token" value="{$token}">

    {if isset($errors) && $errors->has('password')}
    <div class="bg-danger/10 border border-danger/30 rounded-md px-3 py-2 text-danger text-sm">
        {$errors->first('password')}
    </div>
    {/if}

    {if isset($errors) && $errors->has('password_confirmation')}
    <div class="bg-danger/10 border border-danger/30 rounded-md px-3 py-2 text-danger text-sm">
        {$errors->first('password_confirmation')}
    </div>
    {/if}

    <div>
        <label for="password" class="block text-xs font-medium text-text-muted mb-1">New Password</label>
        <input type="password" id="password" name="password"
               class="w-full bg-[#22222c] border border-twilight-border rounded-md px-3 py-2 text-sm text-text-primary placeholder-text-muted focus:border-accent focus:outline-none transition-colors duration-150"
               placeholder="At least 8 characters" required>
    </div>

    <div>
        <label for="password_confirmation" class="block text-xs font-medium text-text-muted mb-1">Confirm Password</label>
        <input type="password" id="password_confirmation" name="password_confirmation"
               class="w-full bg-[#22222c] border border-twilight-border rounded-md px-3 py-2 text-sm text-text-primary placeholder-text-muted focus:border-accent focus:outline-none transition-colors duration-150"
               placeholder="Repeat your password" required>
    </div>

    <button type="submit" class="w-full bg-accent hover:bg-accent-hover text-white font-medium py-2 rounded-md transition-colors duration-150">
        Reset Password
    </button>
</form>
{/if}
{/block}

{block footer_link}
<a href="/login" class="text-accent hover:text-accent-hover transition-colors duration-150">Back to login</a>
{/block}
```

- [ ] **Step 5: Implement PasswordResetController**

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Controller\Auth;

use App\Foundation\Service\PasswordResetService;
use Marko\RateLimiting\Middleware\RateLimitMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\Security\Middleware\CsrfMiddleware;
use Marko\Security\Middleware\SecurityHeadersMiddleware;
use Marko\Validation\Contracts\ValidatorInterface;
use Marko\View\ViewInterface;

#[Middleware([SecurityHeadersMiddleware::class, CsrfMiddleware::class, RateLimitMiddleware::class])]
class PasswordResetController
{
    public function __construct(
        private readonly ViewInterface $view,
        private readonly PasswordResetService $passwordResetService,
        private readonly ValidatorInterface $validator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    #[Get('/forgot-password')]
    public function show(): Response
    {
        return $this->view->render('foundation::auth/forgot-password', [
            'csrfToken' => $this->csrfTokenManager->get(),
        ]);
    }

    #[Post('/forgot-password')]
    public function sendResetLink(Request $request): Response
    {
        $email = $request->post('email', '');

        $errors = $this->validator->validate($request->post(), [
            'email' => ['required', 'email'],
        ]);

        if ($errors->isNotEmpty()) {
            return $this->view->render('foundation::auth/forgot-password', [
                'errors' => $errors,
                'old' => ['email' => $email],
                'csrfToken' => $this->csrfTokenManager->get(),
            ]);
        }

        $this->passwordResetService->sendResetLink($email);

        return $this->view->render('foundation::auth/forgot-password', [
            'success' => 'If an account with that email exists, we sent a reset link.',
            'csrfToken' => $this->csrfTokenManager->get(),
        ]);
    }

    #[Get('/reset-password/{token}')]
    public function showResetForm(string $token): Response
    {
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            return Response::redirect('/forgot-password');
        }

        $validToken = $this->passwordResetService->findValidToken($token);

        if ($validToken === null) {
            return $this->view->render('foundation::auth/reset-password', [
                'error' => 'This reset link is invalid or has expired.',
                'csrfToken' => $this->csrfTokenManager->get(),
            ]);
        }

        return $this->view->render('foundation::auth/reset-password', [
            'token' => $token,
            'csrfToken' => $this->csrfTokenManager->get(),
        ]);
    }

    #[Post('/reset-password')]
    public function resetPassword(Request $request): Response
    {
        $token = $request->post('token', '');
        $password = $request->post('password', '');
        $passwordConfirmation = $request->post('password_confirmation', '');

        $errors = $this->validator->validate($request->post(), [
            'token' => ['required'],
            'password' => ['required', 'min:8', 'max:255'],
        ]);

        if ($password !== $passwordConfirmation) {
            $errors->add('password_confirmation', 'Passwords do not match');
        }

        if ($errors->isNotEmpty()) {
            return $this->view->render('foundation::auth/reset-password', [
                'errors' => $errors,
                'token' => $token,
                'csrfToken' => $this->csrfTokenManager->get(),
            ]);
        }

        $result = $this->passwordResetService->resetPassword($token, $password);

        if (!$result) {
            return $this->view->render('foundation::auth/reset-password', [
                'error' => 'This reset link is invalid or has expired.',
                'token' => $token,
                'csrfToken' => $this->csrfTokenManager->get(),
            ]);
        }

        return Response::redirect('/login');
    }
}
```

- [ ] **Step 6: Add "Forgot password?" link to login.latte**

In `app/foundation/resources/views/auth/login.latte`, after the password `</div>` (line 28) and before the submit button (line 30), add:

```latte
    <div class="text-right">
        <a href="/forgot-password" class="text-xs text-accent hover:text-accent-hover transition-colors duration-150">Forgot password?</a>
    </div>
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `./vendor/bin/pest --filter "PasswordResetTest"`
Expected: 8 tests pass.

- [ ] **Step 8: Run full test suite**

Run: `./vendor/bin/pest`
Expected: All tests pass.

- [ ] **Step 9: Commit**

```bash
git add app/foundation/src/Controller/Auth/PasswordResetController.php \
       app/foundation/resources/views/auth/forgot-password.latte \
       app/foundation/resources/views/auth/reset-password.latte \
       app/foundation/tests/Feature/PasswordResetTest.php \
       app/foundation/resources/views/auth/login.latte
git commit -m "Add PasswordResetController with forgot/reset flow and templates"
```

---

### Task 8: OAuth Token Exchange

**Files:**
- Create: `app/foundation/src/Service/OAuthHttpClient.php`
- Create: `app/foundation/src/Exception/OAuthException.php`
- Create: `app/foundation/tests/Feature/OAuthHttpClientTest.php`
- Modify: `app/foundation/src/Controller/Auth/SocialAuthController.php`
- Modify: `app/foundation/module.php`

- [ ] **Step 1: Create OAuthException**

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Exception;

class OAuthException extends \RuntimeException {}
```

- [ ] **Step 2: Write failing tests for OAuthHttpClient**

```php
<?php

declare(strict_types=1);

use App\Foundation\Exception\OAuthException;
use App\Foundation\Service\OAuthHttpClient;

class FakeOAuthHttpClient extends OAuthHttpClient
{
    /** @var array<string, array{status: int, body: string}> */
    public array $responses = [];

    public function __construct()
    {
        // Skip parent constructor — no config needed for tests
    }

    protected function httpRequest(string $url, string $method, array $headers, ?string $body = null): array
    {
        return $this->responses[$url] ?? ['status' => 500, 'body' => ''];
    }

    public function setProviderConfig(string $provider, array $config): void
    {
        $this->providerConfigs[$provider] = $config;
    }
}

function makeFakeClient(array $responses = [], ?array $providerConfig = null): FakeOAuthHttpClient
{
    $client = new FakeOAuthHttpClient();
    $client->responses = $responses;

    $config = $providerConfig ?? [
        'client_id' => 'test-id',
        'client_secret' => 'test-secret',
        'redirect_uri' => 'http://localhost:8001/auth/test/callback',
        'token_url' => 'https://provider.example/token',
        'user_url' => 'https://provider.example/user',
        'scopes' => ['email'],
    ];
    $client->setProviderConfig('discord', $config);
    $client->setProviderConfig('google', $config);
    $client->setProviderConfig('github', array_merge($config, [
        'token_url' => 'https://github.com/login/oauth/access_token',
        'user_url' => 'https://api.github.com/user',
    ]));

    return $client;
}

it('normalizes Discord profile', function () {
    $client = makeFakeClient([
        'https://provider.example/token' => [
            'status' => 200,
            'body' => json_encode(['access_token' => 'tok123', 'refresh_token' => 'ref456']),
        ],
        'https://provider.example/user' => [
            'status' => 200,
            'body' => json_encode(['id' => '12345', 'email' => 'user@discord.com', 'username' => 'DiscordUser']),
        ],
    ]);

    $profile = $client->fetchProfile('discord', 'auth-code');

    expect($profile['id'])->toBe('12345')
        ->and($profile['email'])->toBe('user@discord.com')
        ->and($profile['name'])->toBe('DiscordUser')
        ->and($profile['access_token'])->toBe('tok123')
        ->and($profile['refresh_token'])->toBe('ref456');
});

it('normalizes Google profile and ignores id_token', function () {
    $client = makeFakeClient([
        'https://provider.example/token' => [
            'status' => 200,
            'body' => json_encode(['access_token' => 'tok', 'id_token' => 'ignored']),
        ],
        'https://provider.example/user' => [
            'status' => 200,
            'body' => json_encode(['id' => '67890', 'email' => 'user@google.com', 'name' => 'Google User']),
        ],
    ]);

    $profile = $client->fetchProfile('google', 'auth-code');

    expect($profile['id'])->toBe('67890')
        ->and($profile['name'])->toBe('Google User');
});

it('normalizes GitHub profile with public email', function () {
    $client = makeFakeClient([
        'https://github.com/login/oauth/access_token' => [
            'status' => 200,
            'body' => json_encode(['access_token' => 'ghtok']),
        ],
        'https://api.github.com/user' => [
            'status' => 200,
            'body' => json_encode(['id' => 111, 'email' => 'user@github.com', 'login' => 'ghuser']),
        ],
    ]);

    $profile = $client->fetchProfile('github', 'auth-code');

    expect($profile['id'])->toBe('111')
        ->and($profile['email'])->toBe('user@github.com')
        ->and($profile['name'])->toBe('ghuser')
        ->and($profile['refresh_token'])->toBeNull();
});

it('falls back to GitHub emails API for private email', function () {
    $client = makeFakeClient([
        'https://github.com/login/oauth/access_token' => [
            'status' => 200,
            'body' => json_encode(['access_token' => 'ghtok']),
        ],
        'https://api.github.com/user' => [
            'status' => 200,
            'body' => json_encode(['id' => 111, 'email' => null, 'login' => 'ghuser']),
        ],
        'https://api.github.com/user/emails' => [
            'status' => 200,
            'body' => json_encode([
                ['email' => 'secondary@github.com', 'primary' => false, 'verified' => true],
                ['email' => 'primary@github.com', 'primary' => true, 'verified' => true],
            ]),
        ],
    ]);

    $profile = $client->fetchProfile('github', 'auth-code');

    expect($profile['email'])->toBe('primary@github.com');
});

it('returns null email when GitHub emails API fails', function () {
    $client = makeFakeClient([
        'https://github.com/login/oauth/access_token' => [
            'status' => 200,
            'body' => json_encode(['access_token' => 'ghtok']),
        ],
        'https://api.github.com/user' => [
            'status' => 200,
            'body' => json_encode(['id' => 111, 'email' => null, 'login' => 'ghuser']),
        ],
        'https://api.github.com/user/emails' => [
            'status' => 403,
            'body' => '{"message": "Forbidden"}',
        ],
    ]);

    $profile = $client->fetchProfile('github', 'auth-code');

    expect($profile['email'])->toBeNull();
});

it('returns null email when GitHub emails has no primary verified', function () {
    $client = makeFakeClient([
        'https://github.com/login/oauth/access_token' => [
            'status' => 200,
            'body' => json_encode(['access_token' => 'ghtok']),
        ],
        'https://api.github.com/user' => [
            'status' => 200,
            'body' => json_encode(['id' => 111, 'email' => null, 'login' => 'ghuser']),
        ],
        'https://api.github.com/user/emails' => [
            'status' => 200,
            'body' => json_encode([
                ['email' => 'unverified@github.com', 'primary' => true, 'verified' => false],
            ]),
        ],
    ]);

    $profile = $client->fetchProfile('github', 'auth-code');

    expect($profile['email'])->toBeNull();
});

it('throws OAuthException on token exchange non-200', function () {
    $client = makeFakeClient([
        'https://provider.example/token' => ['status' => 400, 'body' => '{"error": "bad_code"}'],
    ]);

    $client->fetchProfile('discord', 'bad-code');
})->throws(OAuthException::class);

it('throws OAuthException when access_token missing', function () {
    $client = makeFakeClient([
        'https://provider.example/token' => ['status' => 200, 'body' => json_encode(['no_token' => true])],
    ]);

    $client->fetchProfile('discord', 'auth-code');
})->throws(OAuthException::class);

it('throws OAuthException on profile endpoint failure', function () {
    $client = makeFakeClient([
        'https://provider.example/token' => [
            'status' => 200,
            'body' => json_encode(['access_token' => 'tok']),
        ],
        'https://provider.example/user' => ['status' => 401, 'body' => ''],
    ]);

    $client->fetchProfile('discord', 'auth-code');
})->throws(OAuthException::class);

it('throws OAuthException for unsupported provider', function () {
    $client = makeFakeClient();

    $client->fetchProfile('linkedin', 'auth-code');
})->throws(OAuthException::class);
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `./vendor/bin/pest --filter "OAuthHttpClient"`
Expected: FAIL — `OAuthHttpClient` not found.

- [ ] **Step 4: Implement OAuthHttpClient**

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Service;

use App\Foundation\Exception\OAuthException;
use Marko\Config\ConfigRepositoryInterface;

class OAuthHttpClient
{
    /** @var array<string, array<string, mixed>> */
    protected array $providerConfigs = [];

    public function __construct(
        private readonly ConfigRepositoryInterface $config,
    ) {}

    /**
     * @return array{id: string, email: ?string, name: string, access_token: string, refresh_token: ?string}
     * @throws OAuthException
     */
    public function fetchProfile(string $provider, string $code): array
    {
        $config = $this->getProviderConfig($provider);

        $tokenData = $this->exchangeToken($config, $code);
        $accessToken = $tokenData['access_token'];

        $rawProfile = $this->fetchUserProfile($provider, $accessToken, $config['user_url']);
        $profile = $this->normalizeProfile($provider, $rawProfile, $accessToken);

        $profile['access_token'] = $accessToken;
        $profile['refresh_token'] = $tokenData['refresh_token'] ?? null;

        return $profile;
    }

    /**
     * @return array<string, mixed>
     */
    private function getProviderConfig(string $provider): array
    {
        if (isset($this->providerConfigs[$provider])) {
            return $this->providerConfigs[$provider];
        }

        $key = "social_auth.providers.{$provider}";
        if (!$this->config->has($key)) {
            throw new OAuthException("Unsupported provider: {$provider}");
        }

        return $this->config->getArray($key);
    }

    private function exchangeToken(array $config, string $code): array
    {
        $response = $this->httpRequest(
            url: $config['token_url'],
            method: 'POST',
            headers: [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            body: http_build_query([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $config['redirect_uri'],
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
            ]),
        );

        if ($response['status'] !== 200) {
            throw new OAuthException('Token exchange failed (HTTP ' . $response['status'] . ')');
        }

        $data = json_decode($response['body'], true);

        if (!is_array($data) || !isset($data['access_token'])) {
            throw new OAuthException('Token response missing access_token');
        }

        return $data;
    }

    private function fetchUserProfile(string $provider, string $accessToken, string $userUrl): array
    {
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ];

        if ($provider === 'github') {
            $headers[] = 'User-Agent: Shilla-MUD';
        }

        $response = $this->httpRequest($userUrl, 'GET', $headers);

        if ($response['status'] !== 200) {
            throw new OAuthException('Profile fetch failed (HTTP ' . $response['status'] . ')');
        }

        $data = json_decode($response['body'], true);

        if (!is_array($data)) {
            throw new OAuthException('Invalid profile response');
        }

        return $data;
    }

    private function normalizeProfile(string $provider, array $raw, string $accessToken): array
    {
        return match ($provider) {
            'discord' => [
                'id' => (string) $raw['id'],
                'email' => $raw['email'] ?? null,
                'name' => $raw['username'] ?? $raw['global_name'] ?? 'Player',
            ],
            'google' => [
                'id' => (string) $raw['id'],
                'email' => $raw['email'] ?? null,
                'name' => $raw['name'] ?? 'Player',
            ],
            'github' => [
                'id' => (string) $raw['id'],
                'email' => $raw['email'] ?? $this->fetchGitHubEmail($accessToken),
                'name' => $raw['login'] ?? 'Player',
            ],
            default => throw new OAuthException("Unsupported provider: {$provider}"),
        };
    }

    private function fetchGitHubEmail(string $accessToken): ?string
    {
        $response = $this->httpRequest(
            'https://api.github.com/user/emails',
            'GET',
            [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json',
                'User-Agent: Shilla-MUD',
            ],
        );

        if ($response['status'] !== 200) {
            return null;
        }

        $emails = json_decode($response['body'], true);

        if (!is_array($emails)) {
            return null;
        }

        foreach ($emails as $entry) {
            if (($entry['primary'] ?? false) && ($entry['verified'] ?? false)) {
                return $entry['email'];
            }
        }

        return null;
    }

    /**
     * @return array{status: int, body: string}
     */
    protected function httpRequest(string $url, string $method, array $headers, ?string $body = null): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        }

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            throw new OAuthException('HTTP request failed: network error');
        }

        return ['status' => $httpCode, 'body' => $responseBody];
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `./vendor/bin/pest --filter "OAuthHttpClient"`
Expected: 10 tests pass.

- [ ] **Step 6: Modify SocialAuthController — inject OAuthHttpClient, delete stub**

In `app/foundation/src/Controller/Auth/SocialAuthController.php`:

Add import:
```php
use App\Foundation\Service\OAuthHttpClient;
use App\Foundation\Exception\OAuthException;
```

Add to constructor:
```php
private readonly OAuthHttpClient $oauthClient,
```

Replace the callback method's profile fetching (lines 60-67) with:
```php
        try {
            $profile = $this->oauthClient->fetchProfile($provider, $code);
        } catch (OAuthException) {
            return Response::redirect('/login');
        }
```

Delete the `exchangeCodeForProfile()` private method (lines 143-154).

- [ ] **Step 7: Register OAuthHttpClient in module.php**

Add to `singletons` array:
```php
use App\Foundation\Service\OAuthHttpClient;

// In 'singletons' array:
OAuthHttpClient::class,
```

- [ ] **Step 8: Update SocialAuthTest to pass OAuthHttpClient mock**

The `SocialAuthController` constructor now requires `OAuthHttpClient`. Update any controller tests in `SocialAuthTest.php` if they directly instantiate the controller. (Current tests only test `SocialAuthService`, not the controller, so this may not require changes — verify.)

- [ ] **Step 9: Run full test suite**

Run: `./vendor/bin/pest`
Expected: All tests pass.

- [ ] **Step 10: Commit**

```bash
git add app/foundation/src/Service/OAuthHttpClient.php \
       app/foundation/src/Exception/OAuthException.php \
       app/foundation/tests/Feature/OAuthHttpClientTest.php \
       app/foundation/src/Controller/Auth/SocialAuthController.php \
       app/foundation/module.php
git commit -m "Add OAuthHttpClient with native cURL and provider normalization"
```

---

### Task 9: Final Verification

- [ ] **Step 1: Run full Pest test suite**

Run: `./vendor/bin/pest`
Expected: All tests pass (previous 44 + ~33 new ≈ 77 total).

- [ ] **Step 2: Run E2E tests**

Run: `npx --prefix tests/e2e playwright test --config=tests/e2e/playwright.config.ts --reporter=list`
Expected: 19/19 pass (existing tests still work).

- [ ] **Step 3: Manual smoke test — email verification**

1. Visit `http://localhost:8001/register`, create a new user
2. Check Mailpit at `http://localhost:8025` — verification email should appear
3. Click the verification link in the email
4. Banner should disappear from game layout

- [ ] **Step 4: Manual smoke test — password recovery**

1. Visit `http://localhost:8001/login`, click "Forgot password?"
2. Enter the test user's email
3. Check Mailpit — reset email should appear
4. Click reset link, enter new password
5. Login with new password

- [ ] **Step 5: Commit any fixes from smoke testing**

- [ ] **Step 6: Final commit**

```bash
git push
```

---

## Self-Review Checklist

**Spec coverage:**
- ✅ Email verification: HMAC-signed URLs, service, controller, templates, banners, social auth auto-verify
- ✅ Password recovery: entity, repository, service, controller, templates, forgot link, anti-enumeration
- ✅ OAuth token exchange: cURL service, provider normalization, GitHub email fallback, exception handling
- ✅ module.php wiring for all new services
- ✅ StubMailer test infrastructure
- ✅ Registration sends verification email
- ✅ Social auth sets emailVerifiedAt
- ✅ All test cases from spec covered

**Placeholder scan:** No TBD/TODO/placeholder language found.

**Type consistency:** Method signatures match across tasks. `fetchProfile` returns same shape everywhere. `EmailVerificationService.makeVerificationUrl` is public (tests call it directly). `PasswordResetService` constructor params consistent between service and test factories.
