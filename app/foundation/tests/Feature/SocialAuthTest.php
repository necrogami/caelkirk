<?php

declare(strict_types=1);

use App\Foundation\Entity\SocialAccount;
use App\Foundation\Entity\User;
use App\Foundation\Repository\SocialAccountRepository;
use App\Foundation\Repository\UserRepository;
use App\Foundation\Service\SocialAuthService;
use App\Foundation\Tests\Support\StubSession;

function makeSocialStubHasher(): object
{
    return new class implements \Marko\Hashing\Contracts\HasherInterface {
        public function hash(string $value): string { return 'hashed_' . $value; }
        public function verify(string $value, string $hash): bool { return 'hashed_' . $value === $hash; }
        public function needsRehash(string $hash): bool { return false; }
        public function algorithm(): string { return 'stub'; }
    };
}

function makeUserWithPassword(): User
{
    $user = new User();
    $user->id = 1;
    $user->username = 'existing';
    $user->email = 'user@example.com';
    $user->password = 'hashed_correctpassword';
    return $user;
}

function makeSocialOnlyUser(): User
{
    $user = new User();
    $user->id = 2;
    $user->username = 'socialuser';
    $user->email = 'social@example.com';
    $user->password = null;
    return $user;
}

function makeSocialProfile(string $id = '12345', string $email = 'user@example.com', string $name = 'TestUser'): array
{
    return ['id' => $id, 'email' => $email, 'name' => $name];
}

it('logs in when social account already linked', function () {
    $user = makeUserWithPassword();
    $social = new SocialAccount();
    $social->userId = 1;

    $userRepo = Mockery::mock(UserRepository::class);
    $userRepo->shouldReceive('find')->with(1)->andReturn($user);

    $socialRepo = Mockery::mock(SocialAccountRepository::class);
    $socialRepo->shouldReceive('findByProvider')->with('discord', '12345')->andReturn($social);

    $service = new SocialAuthService($userRepo, $socialRepo, makeSocialStubHasher(), new StubSession());

    $result = $service->handleCallback('discord', makeSocialProfile());

    expect($result['action'])->toBe('login')
        ->and($result['user'])->toBe($user);
});

it('creates new user when no match found', function () {
    $userRepo = Mockery::mock(UserRepository::class);
    $userRepo->shouldReceive('findByEmail')->with('new@example.com')->andReturn(null);
    $userRepo->shouldReceive('findByUsername')->with('NewUser')->andReturn(null);
    $userRepo->shouldReceive('save')->once()->andReturnUsing(function (User $user) {
        $user->id = 99;
    });

    $socialRepo = Mockery::mock(SocialAccountRepository::class);
    $socialRepo->shouldReceive('findByProvider')->with('google', '99999')->andReturn(null);
    $socialRepo->shouldReceive('save')->once();

    $service = new SocialAuthService($userRepo, $socialRepo, makeSocialStubHasher(), new StubSession());

    $result = $service->handleCallback('google', makeSocialProfile('99999', 'new@example.com', 'NewUser'));

    expect($result['action'])->toBe('created')
        ->and($result['user'])->toBeInstanceOf(User::class);
});

it('requires password verification when email matches existing user with password', function () {
    $user = makeUserWithPassword();

    $userRepo = Mockery::mock(UserRepository::class);
    $userRepo->shouldReceive('findByEmail')->with('user@example.com')->andReturn($user);

    $socialRepo = Mockery::mock(SocialAccountRepository::class);
    $socialRepo->shouldReceive('findByProvider')->with('github', '12345')->andReturn(null);

    $service = new SocialAuthService($userRepo, $socialRepo, makeSocialStubHasher(), new StubSession());

    $result = $service->handleCallback('github', makeSocialProfile());

    expect($result['action'])->toBe('verify_password')
        ->and($result['user'])->toBe($user)
        ->and($result['profile'])->toBeArray();
});

it('directs to settings when email matches social-only account', function () {
    $user = makeSocialOnlyUser();

    $userRepo = Mockery::mock(UserRepository::class);
    $userRepo->shouldReceive('findByEmail')->with('social@example.com')->andReturn($user);

    $socialRepo = Mockery::mock(SocialAccountRepository::class);
    $socialRepo->shouldReceive('findByProvider')->with('discord', '12345')->andReturn(null);

    $service = new SocialAuthService($userRepo, $socialRepo, makeSocialStubHasher(), new StubSession());

    $result = $service->handleCallback('discord', makeSocialProfile('12345', 'social@example.com'));

    expect($result['action'])->toBe('link_via_settings');
});

it('verifies password and links social account', function () {
    $user = makeUserWithPassword();

    $userRepo = Mockery::mock(UserRepository::class);
    $socialRepo = Mockery::mock(SocialAccountRepository::class);
    $socialRepo->shouldReceive('save')->once();

    $service = new SocialAuthService($userRepo, $socialRepo, makeSocialStubHasher(), new StubSession());

    $result = $service->verifyAndLink($user, 'correctpassword', 'github', makeSocialProfile());

    expect($result)->toBeTrue();
});

it('rejects wrong password during verification', function () {
    $user = makeUserWithPassword();

    $userRepo = Mockery::mock(UserRepository::class);
    $socialRepo = Mockery::mock(SocialAccountRepository::class);

    $service = new SocialAuthService($userRepo, $socialRepo, makeSocialStubHasher(), new StubSession());

    $result = $service->verifyAndLink($user, 'wrongpassword', 'github', makeSocialProfile());

    expect($result)->toBeFalse();
});

it('unlinks social account when user has password', function () {
    $user = makeUserWithPassword();
    $social = new SocialAccount();
    $social->id = 10;
    $social->userId = 1;

    $userRepo = Mockery::mock(UserRepository::class);

    $socialRepo = Mockery::mock(SocialAccountRepository::class);
    $socialRepo->shouldReceive('countByUserId')->with(1)->andReturn(1);
    $socialRepo->shouldReceive('find')->with(10)->andReturn($social);
    $socialRepo->shouldReceive('delete')->with($social)->once();

    $service = new SocialAuthService($userRepo, $socialRepo, makeSocialStubHasher(), new StubSession());

    $service->unlinkFromCurrentUser($user, 10);
});

it('rejects unlink when it is the only login method', function () {
    $user = makeSocialOnlyUser();

    $userRepo = Mockery::mock(UserRepository::class);

    $socialRepo = Mockery::mock(SocialAccountRepository::class);
    $socialRepo->shouldReceive('countByUserId')->with(2)->andReturn(1);

    $service = new SocialAuthService($userRepo, $socialRepo, makeSocialStubHasher(), new StubSession());

    $service->unlinkFromCurrentUser($user, 10);
})->throws(\RuntimeException::class, 'Cannot unlink');
