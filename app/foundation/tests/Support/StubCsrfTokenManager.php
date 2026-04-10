<?php

declare(strict_types=1);

namespace App\Foundation\Tests\Support;

use Marko\Security\Contracts\CsrfTokenManagerInterface;

class StubCsrfTokenManager implements CsrfTokenManagerInterface
{
    public function get(): string
    {
        return 'test-csrf-token';
    }

    public function validate(string $token): bool
    {
        return $token === 'test-csrf-token';
    }

    public function regenerate(): string
    {
        return 'test-csrf-token';
    }
}
