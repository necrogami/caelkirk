<?php

declare(strict_types=1);

namespace App\Foundation\Service;

use App\Foundation\Entity\SocialAccount;
use App\Foundation\Entity\User;
use App\Foundation\Repository\SocialAccountRepository;
use App\Foundation\Repository\UserRepository;
use Marko\Hashing\Contracts\HasherInterface;
use Marko\Session\Contracts\SessionInterface;

class SocialAuthService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly SocialAccountRepository $socialAccountRepository,
        private readonly HasherInterface $hasher,
        private readonly SessionInterface $session,
    ) {}

    /**
     * Handle the OAuth callback. Returns one of:
     * - ['action' => 'login', 'user' => User] — existing linked account, log in
     * - ['action' => 'created', 'user' => User] — new user created, log in
     * - ['action' => 'verify_password', 'user' => User, 'profile' => array] — email match, needs password
     * - ['action' => 'link_via_settings', 'message' => string] — social-only account, must link from settings
     */
    public function handleCallback(string $provider, array $profile): array
    {
        $providerId = (string) $profile['id'];
        $email = $profile['email'] ?? null;
        $displayName = $profile['name'] ?? $profile['username'] ?? 'Player';

        // Check for existing social link
        $socialAccount = $this->socialAccountRepository->findByProvider($provider, $providerId);

        if ($socialAccount !== null) {
            $user = $this->userRepository->find($socialAccount->userId);
            return ['action' => 'login', 'user' => $user];
        }

        // Check for existing user with same email
        if ($email !== null) {
            $existingUser = $this->userRepository->findByEmail($email);

            if ($existingUser !== null) {
                if ($existingUser->hasPassword()) {
                    // User must verify their password before we link
                    return [
                        'action' => 'verify_password',
                        'user' => $existingUser,
                        'profile' => $profile,
                    ];
                }

                // Social-only account — can't verify password, must link from settings
                return [
                    'action' => 'link_via_settings',
                    'message' => 'An account with this email exists. Log in with your linked social provider and link this account from settings.',
                ];
            }
        }

        // New user — create account + social link
        $user = new User();
        $user->username = $this->generateUniqueUsername($displayName);
        $user->email = $email ?? "{$provider}_{$providerId}@social.local";
        $user->createdAt = new \DateTimeImmutable();
        $this->userRepository->save($user);

        $this->createSocialLink($user->id, $provider, $profile);

        return ['action' => 'created', 'user' => $user];
    }

    /**
     * Verify password and link social account to existing user.
     */
    public function verifyAndLink(User $user, string $password, string $provider, array $profile): bool
    {
        if (!$this->hasher->verify($password, $user->getAuthPassword())) {
            return false;
        }

        $this->createSocialLink($user->id, $provider, $profile);
        return true;
    }

    /**
     * Link a social account to the currently authenticated user.
     */
    public function linkToCurrentUser(int $userId, string $provider, array $profile): void
    {
        $providerId = (string) $profile['id'];

        // Check if already linked
        $existing = $this->socialAccountRepository->findByProvider($provider, $providerId);
        if ($existing !== null) {
            throw new \RuntimeException('This social account is already linked to another user');
        }

        $this->createSocialLink($userId, $provider, $profile);
    }

    /**
     * Unlink a social account from the user. Requires at least one login method to remain.
     */
    public function unlinkFromCurrentUser(User $user, int $socialAccountId): void
    {
        $socialCount = $this->socialAccountRepository->countByUserId($user->id);
        $hasPassword = $user->hasPassword();

        if (!$hasPassword && $socialCount <= 1) {
            throw new \RuntimeException('Cannot unlink — this is your only login method. Set a password first.');
        }

        $account = $this->socialAccountRepository->find($socialAccountId);
        if ($account === null || $account->userId !== $user->id) {
            throw new \RuntimeException('Social account not found');
        }

        $this->socialAccountRepository->delete($account);
    }

    /**
     * Build the OAuth authorization URL for a provider.
     */
    public function getAuthorizationUrl(string $provider, array $config): string
    {
        $state = bin2hex(random_bytes(16));
        $this->session->set('oauth_state', $state);

        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect_uri'],
            'response_type' => 'code',
            'scope' => implode(' ', $config['scopes']),
            'state' => $state,
        ];

        return $config['authorize_url'] . '?' . http_build_query($params);
    }

    public function validateState(?string $state): bool
    {
        $stored = $this->session->get('oauth_state');
        $this->session->remove('oauth_state');

        if ($stored === null || $state === null) {
            return false;
        }

        return hash_equals($stored, $state);
    }

    private function createSocialLink(int $userId, string $provider, array $profile): void
    {
        $account = new SocialAccount();
        $account->userId = $userId;
        $account->provider = $provider;
        $account->providerId = (string) $profile['id'];
        $account->providerEmail = $profile['email'] ?? null;
        $account->accessToken = $profile['access_token'] ?? null;
        $account->refreshToken = $profile['refresh_token'] ?? null;
        $account->createdAt = new \DateTimeImmutable();
        $this->socialAccountRepository->save($account);
    }

    private function generateUniqueUsername(string $displayName): string
    {
        // Sanitize: alphanumeric + underscores, max 50 chars
        $base = preg_replace('/[^a-zA-Z0-9_]/', '', $displayName);
        $base = substr($base, 0, 45) ?: 'Player';

        $username = $base;
        $suffix = 1;

        while ($this->userRepository->findByUsername($username) !== null) {
            $username = $base . '_' . $suffix;
            $suffix++;
        }

        return $username;
    }
}
