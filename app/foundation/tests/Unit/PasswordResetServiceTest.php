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

it('findValidToken returns token for valid hash', function () {
    $token = new PasswordResetToken();
    $token->id = 1;
    $token->userId = 1;
    $token->tokenHash = hash('sha256', str_repeat('c', 64));
    $token->createdAt = new \DateTimeImmutable();

    $tokenRepo = Mockery::mock(PasswordResetTokenRepository::class);
    $tokenRepo->shouldReceive('findByTokenHash')
        ->with(hash('sha256', str_repeat('c', 64)))
        ->andReturn($token);

    $service = new PasswordResetService(
        userRepository: Mockery::mock(UserRepository::class),
        tokenRepository: $tokenRepo,
        hasher: makeStubHasherForReset(),
        mailer: new StubMailer(),
        view: makeStubViewForResetEmail(),
        appUrl: 'http://localhost:8001',
    );

    $result = $service->findValidToken(str_repeat('c', 64));

    expect($result)->toBeInstanceOf(PasswordResetToken::class)
        ->and($result->userId)->toBe(1);
});

it('findValidToken returns null and deletes expired token', function () {
    $token = new PasswordResetToken();
    $token->id = 1;
    $token->userId = 1;
    $token->tokenHash = hash('sha256', str_repeat('d', 64));
    $token->createdAt = new \DateTimeImmutable('-2 hours');

    $tokenRepo = Mockery::mock(PasswordResetTokenRepository::class);
    $tokenRepo->shouldReceive('findByTokenHash')
        ->with(hash('sha256', str_repeat('d', 64)))
        ->andReturn($token);
    $tokenRepo->shouldReceive('deleteByUserId')->with(1)->once();

    $service = new PasswordResetService(
        userRepository: Mockery::mock(UserRepository::class),
        tokenRepository: $tokenRepo,
        hasher: makeStubHasherForReset(),
        mailer: new StubMailer(),
        view: makeStubViewForResetEmail(),
        appUrl: 'http://localhost:8001',
    );

    $result = $service->findValidToken(str_repeat('d', 64));

    expect($result)->toBeNull();
});
