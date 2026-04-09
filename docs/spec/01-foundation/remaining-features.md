# Foundation: Remaining Features Spec

Three features needed to close Foundation's exit criteria. The CreatePlayerOnRegister observer was evaluated and dropped — manual character creation from the character select screen is better MUD UX.

---

## 1. Email Verification

### Approach: HMAC-Signed URLs (Stateless)

No database table. The verification URL contains `id` (user ID), `expires` (Unix timestamp), and `signature` (HMAC-SHA256). The signing key is derived from `ENCRYPTION_KEY` combined with the user's current `emailVerifiedAt` value, so a token is automatically invalidated once the user verifies (the key material changes). Expiry is 60 minutes.

### Routes

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/verify-email` | Consume the signed URL (requires auth) |
| POST | `/verify-email/resend` | Resend verification email (requires auth) |

Both routes require `AuthMiddleware` since the user must be logged in. Initial send happens inside `RegisterController::store()` after user creation.

### Unverified User Policy: Soft Gate

Unverified users CAN play. They see a persistent banner in the game and lobby layouts with a resend link. Progression limits (level cap, zone restrictions, character volume cap) are enforced by later sub-projects that check `emailVerifiedAt`. Foundation only provides the banner and the verification flow.

### Social Auth Users

Users created via social OAuth are auto-verified at creation time — the provider already verified their email. Users with synthetic emails (`@social.local`) are also auto-verified and never receive verification emails.

### Service: `EmailVerificationService`

Responsible for generating signed URLs, sending verification emails, and processing verification. Uses `ViewInterface::renderToString()` to render the email body from a Latte template, then sends via `MailerInterface`. Needs `ENCRYPTION_KEY` and `APP_URL` as scalar constructor params, so requires a factory closure binding in `module.php`.

### Email Template

Inline-styled HTML matching the twilight theme. Subject: "Verify your Shilla account". Contains verification button, plaintext URL fallback, and 1-hour expiry note.

### Edge Cases

- **Expired token:** Render verify-email page with error + resend form
- **Already verified:** Redirect to `/game`
- **Resend spam:** Rate-limited by middleware
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
| `module.php` | Modify — add factory binding for service |
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

256-bit random token, stored as SHA-256 hash in DB. Raw token appears only in the email URL. 60-minute expiry. One-time use — delete after successful reset. Delete all user tokens when a new reset is requested (one valid token per user at a time).

### Entity: `PasswordResetToken`

| Column | Type | Notes |
|--------|------|-------|
| id | SERIAL PK | Auto-increment — standard hydrator pattern |
| user_id | INT NOT NULL | FK → users(id) ON DELETE CASCADE |
| token_hash | VARCHAR(64) NOT NULL | SHA-256 hex of raw token |
| created_at | TIMESTAMP NOT NULL | Must be set explicitly before save per CLAUDE.md |

### Routes

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/forgot-password` | Render email input form |
| POST | `/forgot-password` | Validate email, create token, send email |
| GET | `/reset-password/{token}` | Render new password form |
| POST | `/reset-password` | Validate token + password, update user |

All routes are unauthenticated (user forgot their password).

### Anti-Enumeration

`POST /forgot-password` always shows "If an account with that email exists, we sent a reset link" — regardless of whether the email was found or the user is social-only.

### Social-Only Accounts

Silently skipped — no email sent. A social-only user has no password to reset.

### Service: `PasswordResetService`

Responsible for token generation, email sending, token validation, and password update. Uses `MailerInterface` for email, `HasherInterface` for password hashing, `ViewInterface` for email template rendering.

### Repository: `PasswordResetTokenRepository`

Standard repository with `findByTokenHash()` for lookup, `deleteByUserId()` for cleanup (raw SQL bulk delete), and `deleteExpired()` for maintenance.

### Email Template

Inline-styled HTML. Subject: "Reset your Shilla password". Contains reset button, plaintext URL fallback, and 60-minute expiry note.

### Login Page

Add "Forgot password?" link below password field in `auth/login.latte`.

### Security

- Token entropy: 256-bit
- Storage: SHA-256 hash only — raw token never stored
- Comparison: lookup by hash, then `hash_equals()` in PHP for constant-time verification
- Anti-enumeration: same response regardless of email existence
- Rate limiting: middleware on controller
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
| `module.php` | Modify — add repository + service to singletons |
| `tests/Support/StubMailer.php` | Create |
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

No new dependencies. PHP's `curl` extension is already available. Two HTTP calls per login (token exchange + profile fetch) wrapped in a thin service class.

### Service: `OAuthHttpClient`

Injected into `SocialAuthController`, replacing the `exchangeCodeForProfile()` stub. Single public method: `fetchProfile(provider, code)` returns a normalized profile array.

### Token Exchange Flow

Standard OAuth 2.0 POST to the provider's token URL with authorization code, client credentials, and redirect URI. All three providers use the same flow. GitHub requires an explicit `Accept: application/json` header.

### Profile Fetch

GET the provider's user URL with a Bearer token. GitHub additionally requires a `User-Agent` header.

### Provider Normalization

| Field | Discord | Google | GitHub |
|-------|---------|--------|--------|
| id | `id` (string) | `id` (string) | `id` (int → string) |
| email | `email` | `email` | `email` (null if private → fallback) |
| name | `username` | `name` | `login` |

**GitHub email fallback:** If `email` is null (private), secondary request to GitHub's emails API to find the primary verified email.

### Error Handling

Custom `OAuthException`. Thrown on network failure, non-200 response, missing access token, JSON decode failure, or unsupported provider. Controller catches and redirects to `/login`.

**Logging guardrails:** Exception messages must NOT include raw HTTP responses, access tokens, or full provider URLs. Log only: timestamp, provider name, and error category (timeout/4xx/5xx).

### Timeouts

5s connect, 10s total.

### Files

| File | Action |
|------|--------|
| `src/Service/OAuthHttpClient.php` | Create |
| `src/Exception/OAuthException.php` | Create |
| `src/Controller/Auth/SocialAuthController.php` | Modify — inject OAuthHttpClient, delete stub |
| `module.php` | Modify — add to singletons |
| `tests/Feature/OAuthHttpClientTest.php` | Create |

### Test Strategy

Extract raw HTTP call into a protected method so tests can subclass with canned responses. No real provider calls in tests.

### Test Cases

1. Discord happy path — token + profile → normalized output
2. Google happy path — ignores `id_token`
3. GitHub happy path with public email
4. GitHub private email fallback via emails API
5. Token exchange non-200 → `OAuthException`
6. Token exchange missing `access_token` → `OAuthException`
7. Profile endpoint failure → `OAuthException`
8. Unsupported provider → `OAuthException`
9. Controller catches `OAuthException` and redirects to `/login`

---

## Cross-Cutting Concerns

### module.php Wiring

All new services and repositories must be registered in `module.php`. `EmailVerificationService` needs a factory closure binding (scalar constructor params). The rest go in singletons. `MailerInterface` is already bound by `marko/mail-smtp`.

### Test Support: StubMailer

A test double implementing `MailerInterface` that captures sent messages for assertion. Used by email verification and password reset tests.

### Controller Middleware

| Controller | Middleware |
|------------|-----------|
| `EmailVerificationController` | Auth + Security Headers + CSRF + Rate Limit |
| `PasswordResetController` | Security Headers + CSRF + Rate Limit |

Password reset routes are unauthenticated. Email verification routes require auth.
