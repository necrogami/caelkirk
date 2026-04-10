<?php

declare(strict_types=1);

namespace App\Foundation\Controller\Admin;

use App\Foundation\Service\ConfigService;
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
class ConfigAdminController
{
    public function __construct(
        private readonly ViewInterface $view,
        private readonly ConfigService $configService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    #[Get('/admin/config')]
    #[Middleware(AdminAuthMiddleware::class)]
    #[RequiresPermission(permission: 'system.config')]
    public function index(): Response
    {
        $config = [
            'character_slot_default' => $this->configService->get('character_slot_default', 50),
            'maintenance_mode' => $this->configService->get('maintenance_mode', false),
        ];

        return $this->view->render('foundation::admin/config/index', [
            'config' => $config,
            'csrfToken' => $this->csrfTokenManager->get(),
        ]);
    }

    #[Post('/admin/config')]
    #[Middleware(AdminAuthMiddleware::class)]
    #[RequiresPermission(permission: 'system.config')]
    public function update(Request $request): Response
    {
        $slotDefault = $request->post('character_slot_default');
        if ($slotDefault !== null) {
            $this->configService->set('character_slot_default', max(1, min(100, (int) $slotDefault)));
        }

        $maintenance = $request->post('maintenance_mode');
        $this->configService->set('maintenance_mode', $maintenance === '1');

        return Response::redirect('/admin/config');
    }
}
