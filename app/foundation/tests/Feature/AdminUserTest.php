<?php

declare(strict_types=1);

use App\Foundation\Controller\Admin\UserAdminController;
use App\Foundation\Entity\User;
use App\Foundation\Repository\PlayerRepository;
use App\Foundation\Repository\SocialAccountRepository;
use App\Foundation\Repository\UserRepository;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

function makeAdminStubView(): object
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

function makeAdminUser(int $id = 1, string $role = 'player'): User
{
    $user = new User();
    $user->id = $id;
    $user->username = 'testuser';
    $user->email = 'test@example.com';
    $user->role = $role;
    $user->password = 'hashed';
    return $user;
}

it('renders user list', function () {
    $view = makeAdminStubView();
    $user = makeAdminUser();

    $userRepo = Mockery::mock(UserRepository::class);
    $userRepo->shouldReceive('search')->with('', null)->andReturn([$user]);

    $playerRepo = Mockery::mock(PlayerRepository::class);
    $socialRepo = Mockery::mock(SocialAccountRepository::class);

    $controller = new UserAdminController($view, $userRepo, $playerRepo, $socialRepo);

    $request = new Request(query: ['search' => '', 'role' => null]);
    $response = $controller->index($request);

    expect($response->statusCode())->toBe(200)
        ->and($view->lastTemplate)->toBe('foundation::admin/users/index')
        ->and($view->lastData['users'])->toHaveCount(1);
});

it('renders user edit form', function () {
    $view = makeAdminStubView();
    $user = makeAdminUser();

    $userRepo = Mockery::mock(UserRepository::class);
    $userRepo->shouldReceive('find')->with(1)->andReturn($user);

    $playerRepo = Mockery::mock(PlayerRepository::class);
    $playerRepo->shouldReceive('findByUserId')->with(1)->andReturn([]);

    $socialRepo = Mockery::mock(SocialAccountRepository::class);
    $socialRepo->shouldReceive('findByUserId')->with(1)->andReturn([]);

    $controller = new UserAdminController($view, $userRepo, $playerRepo, $socialRepo);

    $response = $controller->edit(1);

    expect($response->statusCode())->toBe(200)
        ->and($view->lastTemplate)->toBe('foundation::admin/users/edit')
        ->and($view->lastData['user'])->toBe($user);
});

it('updates user role', function () {
    $user = makeAdminUser(role: 'player');

    $userRepo = Mockery::mock(UserRepository::class);
    $userRepo->shouldReceive('find')->with(1)->andReturn($user);
    $userRepo->shouldReceive('save')->once()->withArgs(function (User $u) {
        return $u->role === 'builder';
    });

    $playerRepo = Mockery::mock(PlayerRepository::class);
    $socialRepo = Mockery::mock(SocialAccountRepository::class);

    $controller = new UserAdminController(makeAdminStubView(), $userRepo, $playerRepo, $socialRepo);

    $request = new Request(post: ['role' => 'builder', 'character_slot_limit' => '']);
    $response = $controller->update($request, 1);

    expect($response->statusCode())->toBe(302);
});

it('bans a user', function () {
    $user = makeAdminUser();

    $userRepo = Mockery::mock(UserRepository::class);
    $userRepo->shouldReceive('find')->with(1)->andReturn($user);
    $userRepo->shouldReceive('save')->once()->withArgs(function (User $u) {
        return $u->isBanned();
    });

    $playerRepo = Mockery::mock(PlayerRepository::class);
    $socialRepo = Mockery::mock(SocialAccountRepository::class);

    $controller = new UserAdminController(makeAdminStubView(), $userRepo, $playerRepo, $socialRepo);

    $response = $controller->ban(1);

    expect($response->statusCode())->toBe(302);
});

it('unbans a user', function () {
    $user = makeAdminUser();
    $user->bannedAt = new \DateTimeImmutable();

    $userRepo = Mockery::mock(UserRepository::class);
    $userRepo->shouldReceive('find')->with(1)->andReturn($user);
    $userRepo->shouldReceive('save')->once()->withArgs(function (User $u) {
        return !$u->isBanned();
    });

    $playerRepo = Mockery::mock(PlayerRepository::class);
    $socialRepo = Mockery::mock(SocialAccountRepository::class);

    $controller = new UserAdminController(makeAdminStubView(), $userRepo, $playerRepo, $socialRepo);

    $response = $controller->unban(1);

    expect($response->statusCode())->toBe(302);
});
