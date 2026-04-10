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
    protected function getProviderConfig(string $provider): array
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
            $category = match (true) {
                $response['status'] >= 500 => 'server error',
                $response['status'] >= 400 => 'auth error',
                default => 'unexpected status',
            };
            throw new OAuthException("Token exchange failed ({$category})");
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
            $category = match (true) {
                $response['status'] >= 500 => 'server error',
                $response['status'] >= 400 => 'auth error',
                default => 'unexpected status',
            };
            throw new OAuthException("Profile fetch failed ({$category})");
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
            if (!is_array($entry)) {
                continue;
            }
            if (($entry['primary'] ?? false) && ($entry['verified'] ?? false) && isset($entry['email'])) {
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
        curl_close($ch);

        if ($responseBody === false) {
            throw new OAuthException('HTTP request failed: network error');
        }

        return ['status' => $httpCode, 'body' => $responseBody];
    }
}
