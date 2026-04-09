<?php

declare(strict_types=1);

use App\Foundation\Controller\Auth\LoginController;
use App\Foundation\Entity\User;
use App\Foundation\Repository\UserRepository;
use App\Foundation\Tests\Support\StubCsrfTokenManager;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Testing\Fake\FakeGuard;

function makeLoginStubView(): object
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

function makeLoginStubHasher(): object
{
    return new class implements \Marko\Hashing\Contracts\HasherInterface {
        public function hash(string $value): string { return 'hashed_' . $value; }
        public function verify(string $value, string $hash): bool { return 'hashed_' . $value === $hash; }
        public function needsRehash(string $hash): bool { return false; }
        public function algorithm(): string { return 'stub'; }
    };
}

function makeLoginStubUserRepo(?User $user = null): object
{
    return new class($user) extends UserRepository {
        public function __construct(private readonly ?User $user) {}

        public function findByEmail(string $email): ?User { return $this->user; }
        public function findByUsername(string $username): ?User { return $this->user; }
        public function findByEmailOrUsername(string $identifier): ?User { return $this->user; }
    };
}

function makeLoginController(
    ?object $view = null,
    ?object $userRepo = null,
    ?object $guard = null,
    ?object $hasher = null,
): LoginController {
    return new LoginController(
        view: $view ?? makeLoginStubView(),
        userRepository: $userRepo ?? makeLoginStubUserRepo(),
        guard: $guard ?? new FakeGuard(),
        hasher: $hasher ?? makeLoginStubHasher(),
        csrfTokenManager: new StubCsrfTokenManager(),
    );
}

function makeTestUser(bool $banned = false): User
{
    $user = new User();
    $user->id = 1;
    $user->username = 'testuser';
    $user->email = 'test@example.com';
    $user->password = 'hashed_SecurePass123!';
    if ($banned) {
        $user->bannedAt = new \DateTimeImmutable();
    }
    return $user;
}

it('renders the login page with CSRF token', function () {
    $view = makeLoginStubView();
    $controller = makeLoginController(view: $view);

    $response = $controller->show();

    expect($response->statusCode())->toBe(200)
        ->and($view->lastTemplate)->toBe('foundation::auth/login')
        ->and($view->lastData['csrfToken'])->toBe('test-csrf-token');
});

it('logs in with valid credentials and redirects to game', function () {
    $guard = new FakeGuard();
    $controller = makeLoginController(
        guard: $guard,
        userRepo: makeLoginStubUserRepo(makeTestUser()),
    );

    $request = new Request(post: [
        'identifier' => 'test@example.com',
        'password' => 'SecurePass123!',
    ]);

    $response = $controller->login($request);

    expect($response->statusCode())->toBe(302)
        ->and($response->headers()['Location'])->toBe('/game');
});

it('rejects invalid credentials', function () {
    $view = makeLoginStubView();
    $controller = makeLoginController(
        view: $view,
        userRepo: makeLoginStubUserRepo(null),
    );

    $request = new Request(post: [
        'identifier' => 'nobody@example.com',
        'password' => 'wrong',
    ]);

    $response = $controller->login($request);

    expect($response->statusCode())->toBe(200)
        ->and($view->lastData['error'])->toBe('Invalid credentials');
});

it('rejects wrong password', function () {
    $view = makeLoginStubView();
    $controller = makeLoginController(
        view: $view,
        userRepo: makeLoginStubUserRepo(makeTestUser()),
    );

    $request = new Request(post: [
        'identifier' => 'test@example.com',
        'password' => 'wrongpassword',
    ]);

    $response = $controller->login($request);

    expect($response->statusCode())->toBe(200)
        ->and($view->lastData['error'])->toBe('Invalid credentials');
});

it('rejects banned users', function () {
    $view = makeLoginStubView();
    $controller = makeLoginController(
        view: $view,
        userRepo: makeLoginStubUserRepo(makeTestUser(banned: true)),
    );

    $request = new Request(post: [
        'identifier' => 'test@example.com',
        'password' => 'SecurePass123!',
    ]);

    $response = $controller->login($request);

    expect($response->statusCode())->toBe(200)
        ->and($view->lastData['error'])->toBe('This account has been banned');
});

it('logs out and redirects to home', function () {
    $guard = new FakeGuard();
    $controller = makeLoginController(guard: $guard);

    $response = $controller->logout();

    expect($response->statusCode())->toBe(302)
        ->and($response->headers()['Location'])->toBe('/')
        ->and($guard->logoutCalled)->toBeTrue();
});
