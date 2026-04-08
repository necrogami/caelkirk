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
use Marko\AdminAuth\Contracts\PermissionRegistryInterface;
use Marko\AdminAuth\PermissionRegistry;
use Marko\AdminAuth\Repository\AdminUserRepository;
use Marko\AdminAuth\Repository\AdminUserRepositoryInterface;
use Marko\AdminAuth\Repository\PermissionRepository;
use Marko\AdminAuth\Repository\PermissionRepositoryInterface;
use Marko\AdminAuth\Repository\RoleRepository;
use Marko\AdminAuth\Repository\RoleRepositoryInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\Database\Handler\DatabaseSessionHandler;

return [
    'bindings' => [
        // Auth
        UserProviderInterface::class => DatabaseUserProvider::class,
        // Session
        SessionHandlerInterface::class => DatabaseSessionHandler::class,
        // Admin auth
        PermissionRegistryInterface::class => PermissionRegistry::class,
        AdminUserRepositoryInterface::class => AdminUserRepository::class,
        RoleRepositoryInterface::class => RoleRepository::class,
        PermissionRepositoryInterface::class => PermissionRepository::class,
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
