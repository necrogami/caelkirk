<?php

declare(strict_types=1);

use App\Foundation\Provider\DatabaseUserProvider;
use App\Foundation\Repository\UserRepository;
use App\Foundation\Repository\PlayerRepository;
use App\Foundation\Repository\SocialAccountRepository;
use App\Foundation\Repository\SystemConfigRepository;
use App\Foundation\Repository\AnnouncementRepository;
use App\Foundation\Service\ConfigService;
use App\Foundation\Service\PlayerService;
use App\Foundation\Service\SocialAuthService;
use App\Foundation\Service\CommandRegistry;
use Marko\Authentication\Contracts\UserProviderInterface;

return [
    'bindings' => [
        UserProviderInterface::class => DatabaseUserProvider::class,
    ],
    'singletons' => [
        UserRepository::class,
        PlayerRepository::class,
        SocialAccountRepository::class,
        SystemConfigRepository::class,
        AnnouncementRepository::class,
        ConfigService::class,
        PlayerService::class,
        SocialAuthService::class,
        CommandRegistry::class,
    ],
];
