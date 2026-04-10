<?php

declare(strict_types=1);

namespace App\Foundation\Widget;

use App\Foundation\Repository\UserRepository;
use Marko\Admin\Contracts\DashboardWidgetInterface;

class ActivityFeedWidget implements DashboardWidgetInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}

    public function getId(): string
    {
        return 'activity-feed';
    }

    public function getLabel(): string
    {
        return 'Recent Activity';
    }

    public function getSortOrder(): int
    {
        return 30;
    }

    public function render(): string
    {
        $recentUsers = $this->userRepository->search('', null, 5, 0);
        $items = '';

        foreach ($recentUsers as $user) {
            $name = htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8');
            $time = $user->createdAt->format('M j, H:i');
            $items .= <<<HTML
            <div class="flex justify-between py-1.5 border-b border-twilight-border last:border-0">
                <span class="text-xs"><span class="text-accent">{$name}</span> <span class="text-text-muted">registered</span></span>
                <span class="text-xs text-text-muted">{$time}</span>
            </div>
            HTML;
        }

        if ($items === '') {
            $items = '<div class="text-xs text-text-muted py-2">No recent activity</div>';
        }

        return $items;
    }
}
