<?php

declare(strict_types=1);

namespace App\Foundation\Tests\Support;

use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Flash\FlashBag;

class StubSession implements SessionInterface
{
    private array $data = [];
    public bool $started = false;

    public function start(): void { $this->started = true; }
    public function get(string $key, mixed $default = null): mixed { return $this->data[$key] ?? $default; }
    public function set(string $key, mixed $value): void { $this->data[$key] = $value; }
    public function has(string $key): bool { return array_key_exists($key, $this->data); }
    public function remove(string $key): void { unset($this->data[$key]); }
    public function clear(): void { $this->data = []; }
    public function all(): array { return $this->data; }
    public function regenerate(bool $deleteOldSession = true): void {}
    public function destroy(): void { $this->data = []; }
    public function getId(): string { return 'stub-session-id'; }
    public function setId(string $id): void {}
    public function flash(): FlashBag { return new FlashBag($this->data); }
    public function save(): void {}
}
