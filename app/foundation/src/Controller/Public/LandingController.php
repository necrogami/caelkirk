<?php

declare(strict_types=1);

namespace App\Foundation\Controller\Public;

use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

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
