<?php

declare(strict_types=1);

namespace App\Foundation\Widget;

use Marko\Admin\Contracts\DashboardWidgetInterface;

class OnlineCountWidget implements DashboardWidgetInterface
{
    public function getId(): string
    {
        return 'online-count';
    }

    public function getLabel(): string
    {
        return 'Online Now';
    }

    public function getSortOrder(): int
    {
        return 20;
    }

    public function render(): string
    {
        // Placeholder — real implementation will track SSE connections
        return <<<HTML
        <div class="text-2xl font-semibold text-text-primary">0</div>
        <div class="text-xs text-text-muted">SSE tracking pending</div>
        HTML;
    }
}
