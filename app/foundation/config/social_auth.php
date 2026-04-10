<?php

declare(strict_types=1);

return [
    'providers' => [
        'discord' => [
            'client_id' => env('SOCIAL_DISCORD_CLIENT_ID', ''),
            'client_secret' => env('SOCIAL_DISCORD_CLIENT_SECRET', ''),
            'redirect_uri' => env('SOCIAL_DISCORD_REDIRECT_URI', ''),
            'authorize_url' => 'https://discord.com/api/oauth2/authorize',
            'token_url' => 'https://discord.com/api/oauth2/token',
            'user_url' => 'https://discord.com/api/users/@me',
            'scopes' => ['identify', 'email'],
        ],
        'google' => [
            'client_id' => env('SOCIAL_GOOGLE_CLIENT_ID', ''),
            'client_secret' => env('SOCIAL_GOOGLE_CLIENT_SECRET', ''),
            'redirect_uri' => env('SOCIAL_GOOGLE_REDIRECT_URI', ''),
            'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'user_url' => 'https://www.googleapis.com/oauth2/v2/userinfo',
            'scopes' => ['email', 'profile'],
        ],
        'github' => [
            'client_id' => env('SOCIAL_GITHUB_CLIENT_ID', ''),
            'client_secret' => env('SOCIAL_GITHUB_CLIENT_SECRET', ''),
            'redirect_uri' => env('SOCIAL_GITHUB_REDIRECT_URI', ''),
            'authorize_url' => 'https://github.com/login/oauth/authorize',
            'token_url' => 'https://github.com/login/oauth/access_token',
            'user_url' => 'https://api.github.com/user',
            'scopes' => ['user:email'],
        ],
    ],
];
