# Foundation: Remaining Features Spec

Three features needed to close Foundation's exit criteria. The CreatePlayerOnRegister observer was evaluated and dropped — manual character creation from the character select screen is better MUD UX.

---

## 1. Email Verification

### Approach: HMAC-Signed URLs (Stateless)

No database table. The verification URL contains `id` (user ID), `expires` (Unix timestamp), and `signature` (HMAC-SHA256).

**Signing formula:**
```
key = ENCRYPTION_KEY . '|' . ($emailVerifiedAt?->getTimestamp() ?? 'null')
payload = "verify|{$userId}|{$expires}"
signature = hash_hmac('sha256', payload, key)
```

This means:
- Tokens are stateless — nothing stored in the DB
- A token is automatically invalidated once the user verifies (because `emailVerifiedAt` changes the key material)
- Expiry is 60 minutes

### Routes

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/verify-email` | Consume the signed URL (requires auth) |
| POST | `/verify-email/resend` | Resend verification email (requires auth) |

Controller uses `#[Middleware([AuthMiddleware::class, SecurityHeadersMiddleware::class, CsrfMiddleware::class, RateLimitMiddleware::class])]` — `AuthMiddleware` is required since both routes need a logged-in user.

Initial send happens inside `RegisterController::store()` after user creation.

### Unverified User Policy: Soft Gate

Unverified users CAN play. They see a persistent banner in the game layout with a resend link. Progression limits (level cap, zone restrictions, character volume cap) are enforced by later sub-projects that check `emailVerifiedAt`. Foundation only provides:
- The banner in `layout/game.latte` and `layout/lobby.latte`
- The verification flow itself

### Social Auth Users

Users created via social OAuth get `emailVerifiedAt = new DateTimeImmutable()` at creation time — the provider already verified their email. Users with synthetic emails (`@social.local`) are also auto-verified and never receive verification emails.

### Service: `EmailVerificationService`

Methods:
- `sendVerificationEmail(User $user): void` — build signed URL, compose and send email via `MailerInterface`
- `verify(User $user, int $expires, string $signature): bool` — check expiry, recompute signature with `hash_equals()`, set `emailVerifiedAt` + save
- `isVerified(User $user): bool`
- `makeVerificationUrl(User $user): string` (private)
- `makeSignature(int $userId, int $expires, ?DateTimeImmutable $emailVerifiedAt): string` (private)

Constructor takes: `MailerInterface`, `UserRepository`, `ViewInterface`, `string $encryptionKey`, `string $appUrl`. Bound via factory closure in `module.php`.

Email body is built using `$this->view->renderToString('foundation::email/verify', [...])` — the existing `ViewInterface::renderToString()` method returns HTML as a string, which is then passed to `Message::create()->html($html)`.

### Email Template

`app/foundation/resources/views/email/verify.latte` — inline-styled HTML matching twilight theme. Subject: "Verify your Shilla account". Contains verification button + plaintext URL fallback + 1-hour expiry note.

### Edge Cases

- **Expired token:** Render verify-email page with error + resend form
- **Already verified:** Redirect to `/game`
- **Resend spam:** Handled by `RateLimitMiddleware` on the class
- **Social auth synthetic email:** Skip sending (check for `@social.local` suffix)
- **User ID mismatch in signed URL:** Reject — prevents verifying another user's email

### Files

| File | Action |
|------|--------|
| `src/Service/EmailVerificationService.php` | Create |
| `src/Controller/Auth/EmailVerificationController.php` | Create |
| `resources/views/email/verify.latte` | Create |
| `resources/views/layout/game.latte` | Modify — add verification banner |
| `resources/views/layout/lobby.latte` | Modify — add verification banner |
| `src/Controller/Auth/RegisterController.php` | Modify — inject service, send email after registration |
| `src/Service/SocialAuthService.php` | Modify — set `emailVerifiedAt` for social users |
| `module.php` | Modify — see module.php wiring section below |
| `tests/Feature/EmailVerificationTest.php` | Create |

### Test Cases

1. Generates a valid signed URL
2. Verifies a valid signature and sets `emailVerifiedAt`
3. Rejects expired signature
4. Rejects tampered signature
5. Rejects signature after user already verified (key material changed)
6. Sends verification email with correct recipient and subject
7. Redirects already-verified users to `/game`
8. Rejects verification when URL user ID doesn't match logged-in user
9. Resend sends email and redirects for unverified user
10. Registration sends verification email (modify existing RegisterTest)
11. Skips sending email for `@social.local` addresses
12. Social auth sets `emailVerifiedAt` on new users

---

## 2. Password Recovery

### Approach: Hashed Tokens in Database

256-bit random token (`bin2hex(random_bytes(32))` → 64-char hex string). Store SHA-256 hash of token in DB. Raw token appears only in the email URL. 60-minute expiry. One-time use — delete token row after successful reset. Delete all user tokens when a new reset is requested.

### Entity: `PasswordResetToken`

```
password_reset_tokens
─────────────────────
id          SERIAL PRIMARY KEY
user_id     INT NOT NULL → users(id) ON DELETE CASCADE
token_hash  VARCHAR(64) NOT NULL
created_at  TIMESTAMP NOT NULL
```

Uses auto-increment integer PK — standard `EntityHydrator::isNew()` works. Per CLAUDE.md patterns, `createdAt` must be set explicitly before save: `$token->createdAt = new DateTimeImmutable()`.

### Routes

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/forgot-password` | Render email input form |
| POST | `/forgot-password` | Validate email, create token, send email |
| GET | `/reset-password/{token}` | Render new password form |
| POST | `/reset-password` | Validate token + password, update user |

### Anti-Enumeration

`POST /forgot-password` always shows "If an account with that email exists, we sent a reset link" — regardless of whether the email was found or the user is social-only.

### Social-Only Accounts

Silently skipped — no email sent. A social-only user has no password to reset.

### Service: `PasswordResetService`

Methods:
- `sendResetLink(string $email): void` — find user, skip if not found or social-only, delete old tokens, create new token, send email
- `findValidToken(string $rawToken): ?PasswordResetToken` — validate format, hash, lookup, check expiry
- `resetPassword(string $rawToken, string $newPassword): bool` — find token, load user, hash password, save, delete all user tokens

### Repository: `PasswordResetTokenRepository`

- `findByTokenHash(string $tokenHash): ?PasswordResetToken`
- `deleteByUserId(int $userId): void` (raw SQL bulk delete)
- `deleteExpired(int $lifetimeMinutes): int` (raw SQL cleanup)

### Email Template

`app/foundation/resources/views/email/password-reset.latte` — inline-styled HTML. Subject: "Reset your Shilla password". Contains reset button + plaintext URL fallback + 60-minute expiry note.

### Login Page

Add "Forgot password?" link below password field in `auth/login.latte`.

### Security

- Token entropy: 256-bit
- Storage: SHA-256 hash only
- Comparison: lookup by `WHERE token_hash = ?`, then `hash_equals()` in PHP for constant-time verification
- Anti-enumeration: same response regardless of email existence
- Rate limiting: `RateLimitMiddleware` on controller
- One valid token per user at a time
- Banned users can reset (doesn't leak ban status) but still can't login

### Files

| File | Action |
|------|--------|
| `src/Entity/PasswordResetToken.php` | Create |
| `src/Repository/PasswordResetTokenRepository.php` | Create |
| `src/Service/PasswordResetService.php` | Create |
| `src/Controller/Auth/PasswordResetController.php` | Create |
| `resources/views/auth/forgot-password.latte` | Create |
| `resources/views/auth/reset-password.latte` | Create |
| `resources/views/email/password-reset.latte` | Create |
| `resources/views/auth/login.latte` | Modify — add "Forgot password?" link |
| `module.php` | Modify — see module.php wiring section below |
| `tests/Support/StubMailer.php` | Create — see test support section below |
| `tests/Feature/PasswordResetTest.php` | Create |
| `tests/Unit/PasswordResetServiceTest.php` | Create |

### Test Cases

**Controller:**
1. Renders forgot-password form with CSRF token
2. Submits valid email, shows generic success message
3. Submits invalid email format, shows validation errors
4. Renders reset form with valid token
5. Renders error for expired/invalid token
6. Resets password with valid token, redirects to `/login`
7. Rejects mismatched password confirmation
8. Rejects short password

**Service:**
9. `sendResetLink` sends email for existing user with password
10. `sendResetLink` sends nothing for nonexistent email
11. `sendResetLink` sends nothing for social-only user
12. `sendResetLink` deletes previous tokens before creating new one
13. `findValidToken` returns token for valid hash
14. `findValidToken` returns null and deletes expired token
15. `resetPassword` updates password hash and deletes all user tokens
16. `resetPassword` returns false for invalid token

---

## 3. OAuth Token Exchange

### Approach: Native cURL

No Guzzle or PSR-18 — zero new dependencies. PHP's `curl` extension is already available. Two HTTP calls per login (token exchange + profile fetch) wrapped in a thin service class.

### Service: `OAuthHttpClient`

Location: `src/Service/OAuthHttpClient.php`. Injected into `SocialAuthController`, replacing the `exchangeCodeForProfile()` stub.

**Public method:**
```
fetchProfile(string $provider, string $code): array
```

Returns: `['id' => string, 'email' => ?string, 'name' => string, 'access_token' => string, 'refresh_token' => ?string]`

### Token Exchange Flow

Standard OAuth 2.0 POST to `{token_url}` with `grant_type=authorization_code`, `code`, `redirect_uri`, `client_id`, `client_secret`. All three providers use the same flow. GitHub requires explicit `Accept: application/json` header.

### Profile Fetch

GET `{user_url}` with `Authorization: Bearer {access_token}`. GitHub requires `User-Agent: Shilla-MUD` header.

### Provider Normalization

| Field | Discord | Google | GitHub |
|-------|---------|--------|--------|
| id | `id` (string) | `id` (string) | `id` (int → string) |
| email | `email` | `email` | `email` (null if private → fallback) |
| name | `username` | `name` | `login` |

**GitHub email fallback:** If `email` is null (private), make secondary GET to `https://api.github.com/user/emails`, pick the primary verified email.

### Error Handling

Custom `OAuthException` class. Thrown on: cURL failure, non-200 response, missing `access_token`, JSON decode failure, unsupported provider. Controller catches `OAuthException` and redirects to `/login` — never expose OAuth error details to users.

**Logging guardrails:** Exception messages must NOT include raw HTTP responses, access tokens, or full provider URLs. Log only: timestamp, provider name, error category (timeout/4xx/5xx). Never log the exception message itself to avoid leaking sensitive data through misconfigured log viewers.

### Timeouts

5s connect, 10s total. Prevents hanging on slow providers.

### Files

| File | Action |
|------|--------|
| `src/Service/OAuthHttpClient.php` | Create |
| `src/Exception/OAuthException.php` | Create |
| `src/Controller/Auth/SocialAuthController.php` | Modify — inject OAuthHttpClient, delete stub |
| `module.php` | Modify — see module.php wiring section below |
| `tests/Feature/OAuthHttpClientTest.php` | Create |

### Test Strategy

Extract raw HTTP call into a protected `httpRequest()` method. Tests subclass `OAuthHttpClient` with a `FakeOAuthHttpClient` that returns canned responses. No real provider calls in tests.

### Test Cases

1. Discord happy path — token + profile → normalized output
2. Google happy path — ignores `id_token`
3. GitHub happy path with public email
4. GitHub private email fallback via `/user/emails`
5. Token exchange non-200 → `OAuthException`
6. Token exchange missing `access_token` → `OAuthException`
7. Profile endpoint failure → `OAuthException`
8. Unsupported provider → `OAuthException`
9. Controller catches `OAuthException` and redirects to `/login`

---

## Cross-Cutting: module.php Wiring

All new services must be registered in `app/foundation/module.php`:

**Add to `singletons` array:**
- `PasswordResetTokenRepository::class`
- `PasswordResetService::class`
- `OAuthHttpClient::class`

**Add to `bindings` array** (factory closure — needed because of scalar constructor params):
```
EmailVerificationService::class => function ($container) {
    return new EmailVerificationService(
        mailer: $container->get(MailerInterface::class),
        userRepository: $container->get(UserRepository::class),
        view: $container->get(ViewInterface::class),
        encryptionKey: config('encryption.key'),
        appUrl: env('APP_URL', 'http://localhost:8001'),
    );
},
```

`MailerInterface` is already bound by `marko/mail-smtp`'s module.php. `config/mail.php` already exists with Mailpit settings.

---

## Cross-Cutting: Test Support

### `tests/Support/StubMailer.php`

Implements `Marko\Mail\Contracts\MailerInterface`. Captures all sent messages for assertion:

- `send(Message $message): bool` — appends to `$this->sent[]`, returns true
- `sendRaw(string $to, string $raw): bool` — returns true
- `public array $sent = []` — inspectable by tests

Used by EmailVerificationTest and PasswordResetTest to assert emails were sent with correct recipient/subject without hitting SMTP.

---

## Cross-Cutting: Controller Middleware

| Controller | Middleware |
|------------|-----------|
| `EmailVerificationController` | `AuthMiddleware`, `SecurityHeadersMiddleware`, `CsrfMiddleware`, `RateLimitMiddleware` |
| `PasswordResetController` | `SecurityHeadersMiddleware`, `CsrfMiddleware`, `RateLimitMiddleware` |

Password reset routes are unauthenticated (user forgot their password). Email verification routes require auth (user must be logged in to verify).
