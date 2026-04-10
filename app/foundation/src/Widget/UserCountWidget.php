<?php

declare(strict_types=1);

namespace App\Foundation\Widget;

use App\Foundation\Repository\UserRepository;
use Marko\Admin\Contracts\DashboardWidgetInterface;

class UserCountWidget implements DashboardWidgetInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}

    public function getId(): string
    {
        return 'user-count';
    }

    public function getLabel(): string
    {
        return 'Total Users';
    }

    public function getSortOrder(): int
    {
        return 10;
    }

    public function render(): string
    {
        $total = $this->userRepository->countAll();
        $weekAgo = new \DateTimeImmutable('-7 days');
        $newThisWeek = $this->userRepository->countSince($weekAgo);

        return <<<HTML
        <div class="text-2xl font-semibold text-text-primary">{$total}</div>
        <div class="text-xs text-stamina-dark">+{$newThisWeek} this week</div>
        HTML;
    }
}
