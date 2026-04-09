<?php

declare(strict_types=1);

use App\Foundation\Controller\Auth\RegisterController;
use App\Foundation\Entity\User;
use App\Foundation\Repository\UserRepository;
use App\Foundation\Tests\Support\StubCsrfTokenManager;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Testing\Fake\FakeGuard;
use Marko\Validation\Validation\ValidationErrors;

function makeStubView(): object
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
            return '<html>stub</html>';
        }
    };
}

function makeStubValidator(bool $passes = true): object
{
    return new class($passes) implements \Marko\Validation\Contracts\ValidatorInterface {
        public function __construct(private readonly bool $passes) {}

        public function validate(array $data, array $rules): ValidationErrors
        {
            if ($this->passes) {
                return new ValidationErrors();
            }
            $errors = new ValidationErrors();
            $errors->add('username', 'Required');
            return $errors;
        }

        public function validateOrFail(array $data, array $rules): void {}
        public function passes(array $data, array $rules): bool { return $this->passes; }
        public function fails(array $data, array $rules): bool { return !$this->passes; }
    };
}

function makeStubHasher(): object
{
    return new class implements \Marko\Hashing\Contracts\HasherInterface {
        public function hash(string $value): string { return 'hashed_' . $value; }
        public function verify(string $value, string $hash): bool { return 'hashed_' . $value === $hash; }
        public function needsRehash(string $hash): bool { return false; }
        public function algorithm(): string { return 'stub'; }
    };
}

function makeStubUserRepo(?User $emailUser = null, ?User $usernameUser = null): object
{
    return new class($emailUser, $usernameUser) extends UserRepository {
        public ?User $savedUser = null;

        public function __construct(
            private readonly ?User $emailUser,
            private readonly ?User $usernameUser,
        ) {
            // Skip parent constructor — no DB needed for stubs
        }

        public function findByEmail(string $email): ?User { return $this->emailUser; }
        public function findByUsername(string $username): ?User { return $this->usernameUser; }
        public function save(\Marko\Database\Entity\Entity $entity): void { $this->savedUser = $entity; }
    };
}

function makeRegisterController(
    ?object $view = null,
    ?object $userRepo = null,
    ?object $guard = null,
    ?object $hasher = null,
    ?object $validator = null,
): RegisterController {
    return new RegisterController(
        view: $view ?? makeStubView(),
        userRepository: $userRepo ?? makeStubUserRepo(),
        guard: $guard ?? new FakeGuard(),
        hasher: $hasher ?? makeStubHasher(),
        validator: $validator ?? makeStubValidator(),
        csrfTokenManager: new StubCsrfTokenManager(),
    );
}

it('renders the registration page', function () {
    $view = makeStubView();
    $controller = makeRegisterController(view: $view);

    $response = $controller->show();

    expect($response->statusCode())->toBe(200)
        ->and($view->lastTemplate)->toBe('foundation::auth/register');
});

it('registers a new user and redirects to game', function () {
    $guard = new FakeGuard();
    $userRepo = makeStubUserRepo();
    $controller = makeRegisterController(guard: $guard, userRepo: $userRepo);

    $request = new Request(post: [
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => 'SecurePass123!',
    ]);

    $response = $controller->store($request);

    expect($response->statusCode())->toBe(302)
        ->and($response->headers()['Location'])->toBe('/game')
        ->and($userRepo->savedUser)->toBeInstanceOf(User::class)
        ->and($userRepo->savedUser->username)->toBe('testuser')
        ->and($userRepo->savedUser->email)->toBe('test@example.com')
        ->and($userRepo->savedUser->password)->toBe('hashed_SecurePass123!');
});

it('rejects duplicate email', function () {
    $existing = new User();
    $existing->email = 'test@example.com';
    $view = makeStubView();
    $controller = makeRegisterController(view: $view, userRepo: makeStubUserRepo(emailUser: $existing));

    $request = new Request(post: [
        'username' => 'newuser',
        'email' => 'test@example.com',
        'password' => 'SecurePass123!',
    ]);

    $response = $controller->store($request);

    expect($response->statusCode())->toBe(200)
        ->and($view->lastData['errors'])->toBeInstanceOf(ValidationErrors::class)
        ->and($view->lastData['errors']->has('email'))->toBeTrue();
});

it('rejects duplicate username', function () {
    $existing = new User();
    $existing->username = 'testuser';
    $view = makeStubView();
    $controller = makeRegisterController(view: $view, userRepo: makeStubUserRepo(usernameUser: $existing));

    $request = new Request(post: [
        'username' => 'testuser',
        'email' => 'new@example.com',
        'password' => 'SecurePass123!',
    ]);

    $response = $controller->store($request);

    expect($response->statusCode())->toBe(200)
        ->and($view->lastData['errors'])->toBeInstanceOf(ValidationErrors::class)
        ->and($view->lastData['errors']->has('username'))->toBeTrue();
});

it('strips password from old data on validation failure', function () {
    $view = makeStubView();
    $controller = makeRegisterController(view: $view, validator: makeStubValidator(passes: false));

    $request = new Request(post: [
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => 'SecurePass123!',
    ]);

    $controller->store($request);

    expect($view->lastData['old'])->not->toHaveKey('password')
        ->and($view->lastData['old'])->toHaveKey('username')
        ->and($view->lastData['old'])->toHaveKey('email');
});

it('rejects invalid input', function () {
    $view = makeStubView();
    $controller = makeRegisterController(view: $view, validator: makeStubValidator(passes: false));

    $request = new Request(post: [
        'username' => '',
        'email' => '',
        'password' => '',
    ]);

    $response = $controller->store($request);

    expect($response->statusCode())->toBe(200)
        ->and($view->lastData['errors'])->toBeInstanceOf(ValidationErrors::class)
        ->and($view->lastData['errors']->isNotEmpty())->toBeTrue();
});
