<?php

declare(strict_types=1);

namespace App\Foundation\Controller\Admin;

use App\Foundation\Entity\User;
use App\Foundation\Repository\PlayerRepository;
use App\Foundation\Repository\SocialAccountRepository;
use App\Foundation\Repository\UserRepository;
use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\Security\Middleware\CsrfMiddleware;
use Marko\Security\Middleware\SecurityHeadersMiddleware;
use Marko\View\ViewInterface;

#[Middleware([SecurityHeadersMiddleware::class, CsrfMiddleware::class])]
class UserAdminController
{
    public function __construct(
        private readonly ViewInterface $view,
        private readonly UserRepository $userRepository,
        private readonly PlayerRepository $playerRepository,
        private readonly SocialAccountRepository $socialAccountRepository,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    #[Get('/admin/users')]
    #[Middleware(AdminAuthMiddleware::class)]
    #[RequiresPermission(permission: 'users.view')]
    public function index(Request $request): Response
    {
        $search = $request->query('search', '');
        $role = $request->query('role');
        $users = $this->userRepository->search($search, $role);

        return $this->view->render('foundation::admin/users/index', [
            'users' => $users,
            'search' => $search,
            'role' => $role,
        ]);
    }

    #[Get('/admin/users/{id}/edit')]
    #[Middleware(AdminAuthMiddleware::class)]
    #[RequiresPermission(permission: 'users.edit')]
    public function edit(int $id): Response
    {
        $user = $this->userRepository->find($id);

        if ($user === null) {
            return Response::redirect('/admin/users');
        }

        $players = $this->playerRepository->findByUserId($id);
        $socials = $this->socialAccountRepository->findByUserId($id);

        return $this->view->render('foundation::admin/users/edit', [
            'user' => $user,
            'players' => $players,
            'socials' => $socials,
            'csrfToken' => $this->csrfTokenManager->get(),
        ]);
    }

    #[Post('/admin/users/{id}')]
    #[Middleware(AdminAuthMiddleware::class)]
    #[RequiresPermission(permission: 'users.edit')]
    public function update(Request $request, int $id): Response
    {
        $user = $this->userRepository->find($id);

        if ($user === null) {
            return Response::redirect('/admin/users');
        }

        /** @var User $user */
        $role = $request->post('role');
        $slotLimit = $request->post('character_slot_limit');

        if ($role !== null && in_array($role, ['player', 'builder', 'admin'], true)) {
            $user->role = $role;
        }

        if ($slotLimit !== null && $slotLimit !== '') {
            $limit = (int) $slotLimit;
            $user->characterSlotLimit = max(1, min(100, $limit));
        }

        $user->updatedAt = new \DateTimeImmutable();
        $this->userRepository->save($user);

        return Response::redirect("/admin/users/{$id}/edit");
    }

    #[Post('/admin/users/{id}/ban')]
    #[Middleware(AdminAuthMiddleware::class)]
    #[RequiresPermission(permission: 'users.ban')]
    public function ban(int $id): Response
    {
        $user = $this->userRepository->find($id);

        if ($user !== null) {
            /** @var User $user */
            $user->bannedAt = new \DateTimeImmutable();
            $user->updatedAt = new \DateTimeImmutable();
            $this->userRepository->save($user);
        }

        return Response::redirect("/admin/users/{$id}/edit");
    }

    #[Post('/admin/users/{id}/unban')]
    #[Middleware(AdminAuthMiddleware::class)]
    #[RequiresPermission(permission: 'users.ban')]
    public function unban(int $id): Response
    {
        $user = $this->userRepository->find($id);

        if ($user !== null) {
            /** @var User $user */
            $user->bannedAt = null;
            $user->updatedAt = new \DateTimeImmutable();
            $this->userRepository->save($user);
        }

        return Response::redirect("/admin/users/{$id}/edit");
    }
}
