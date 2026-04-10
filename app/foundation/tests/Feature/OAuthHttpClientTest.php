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

    protected function getProviderConfig(string $provider): array
    {
        if (!isset($this->providerConfigs[$provider])) {
            throw new \App\Foundation\Exception\OAuthException("Unsupported provider: {$provider}");
        }

        return $this->providerConfigs[$provider];
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

it('returns null email when GitHub emails entries are not arrays', function () {
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
            'body' => json_encode(['not-an-array-of-objects']),
        ],
    ]);

    $profile = $client->fetchProfile('github', 'auth-code');

    expect($profile['email'])->toBeNull();
});

it('controller catches OAuthException and redirects to login', function () {
    $oauthClient = Mockery::mock(\App\Foundation\Service\OAuthHttpClient::class);
    $oauthClient->shouldReceive('fetchProfile')
        ->with('discord', 'bad-code')
        ->andThrow(new OAuthException('Token exchange failed'));

    $socialAuthService = Mockery::mock(\App\Foundation\Service\SocialAuthService::class);
    $socialAuthService->shouldReceive('validateState')->with('valid-state')->andReturn(true);

    $controller = new \App\Foundation\Controller\Auth\SocialAuthController(
        view: new class implements \Marko\View\ViewInterface {
            public function render(string $template, array $data = []): \Marko\Routing\Http\Response { return \Marko\Routing\Http\Response::html(''); }
            public function renderToString(string $template, array $data = []): string { return ''; }
        },
        socialAuthService: $socialAuthService,
        userRepository: Mockery::mock(\App\Foundation\Repository\UserRepository::class),
        guard: new \Marko\Testing\Fake\FakeGuard(),
        config: Mockery::mock(\Marko\Config\ConfigRepositoryInterface::class),
        session: new \App\Foundation\Tests\Support\StubSession(),
        csrfTokenManager: new \App\Foundation\Tests\Support\StubCsrfTokenManager(),
        oauthClient: $oauthClient,
    );

    $request = new \Marko\Routing\Http\Request(query: ['code' => 'bad-code', 'state' => 'valid-state']);
    $response = $controller->callback($request, 'discord');

    expect($response->statusCode())->toBe(302)
        ->and($response->headers()['Location'])->toBe('/login');
});
