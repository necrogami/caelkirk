<?php

declare(strict_types=1);

namespace App\Foundation\Controller\Public;

use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Response;
use Marko\Security\Middleware\SecurityHeadersMiddleware;
use Marko\View\ViewInterface;

#[Middleware(SecurityHeadersMiddleware::class)]
class LandingController
{
    public function __construct(
        private readonly ViewInterface $view,
    ) {}

    #[Get('/')]
    public function index(): Response
    {
        return $this->view->render('foundation::public/landing');
    }
}
