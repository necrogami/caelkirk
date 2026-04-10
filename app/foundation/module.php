<?php

declare(strict_types=1);

use App\Foundation\Provider\DatabaseUserProvider;
use App\Foundation\Repository\UserRepository;
use App\Foundation\Repository\PlayerRepository;
use App\Foundation\Repository\SocialAccountRepository;
use App\Foundation\Repository\SystemConfigRepository;
use App\Foundation\Repository\AnnouncementRepository;
use App\Foundation\Repository\PasswordResetTokenRepository;
use App\Foundation\Service\ConfigService;
use App\Foundation\Service\PlayerService;
use App\Foundation\Service\SocialAuthService;
use App\Foundation\Service\CommandRegistry;
use App\Foundation\Service\EmailVerificationService;
use App\Foundation\Service\PasswordResetService;
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
        // Email verification (factory — scalar constructor params)
        EmailVerificationService::class => function ($container) {
            return new EmailVerificationService(
                mailer: $container->get(\Marko\Mail\Contracts\MailerInterface::class),
                userRepository: $container->get(\App\Foundation\Repository\UserRepository::class),
                view: $container->get(\Marko\View\ViewInterface::class),
                encryptionKey: env('ENCRYPTION_KEY', ''),
                appUrl: env('APP_URL', 'http://localhost:8001'),
            );
        },
        // Password reset (factory — scalar constructor param)
        PasswordResetService::class => function ($container) {
            return new PasswordResetService(
                userRepository: $container->get(\App\Foundation\Repository\UserRepository::class),
                tokenRepository: $container->get(\App\Foundation\Repository\PasswordResetTokenRepository::class),
                hasher: $container->get(\Marko\Hashing\Contracts\HasherInterface::class),
                mailer: $container->get(\Marko\Mail\Contracts\MailerInterface::class),
                view: $container->get(\Marko\View\ViewInterface::class),
                appUrl: env('APP_URL', 'http://localhost:8001'),
            );
        },
    ],
    'singletons' => [
        UserRepository::class,
        PlayerRepository::class,
        SocialAccountRepository::class,
        SystemConfigRepository::class,
        AnnouncementRepository::class,
        PasswordResetTokenRepository::class,
        ConfigService::class,
        PlayerService::class,
        SocialAuthService::class,
        CommandRegistry::class,
    ],
];
