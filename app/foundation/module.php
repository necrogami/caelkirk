<?php

declare(strict_types=1);

use App\Foundation\Repository\UserRepository;
use App\Foundation\Repository\PlayerRepository;
use App\Foundation\Repository\SocialAccountRepository;
use App\Foundation\Repository\SystemConfigRepository;
use App\Foundation\Repository\AnnouncementRepository;
use App\Foundation\Service\ConfigService;
use App\Foundation\Service\PlayerService;
use App\Foundation\Service\SocialAuthService;
use App\Foundation\Service\CommandRegistry;

return [
    'bindings' => [],
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
