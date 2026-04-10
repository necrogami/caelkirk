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
