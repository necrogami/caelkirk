<?php

declare(strict_types=1);

namespace App\Foundation\Seed;

use App\Foundation\Repository\SystemConfigRepository;
use DateTimeImmutable;
use Marko\AdminAuth\Entity\AdminUser;
use Marko\AdminAuth\Entity\Role;
use Marko\AdminAuth\Repository\AdminUserRepositoryInterface;
use Marko\AdminAuth\Repository\RoleRepositoryInterface;
use Marko\Database\Seed\Seeder;
use Marko\Database\Seed\SeederInterface;

#[Seeder(name: 'foundation', order: 1)]
readonly class FoundationSeeder implements SeederInterface
{
    public function __construct(
        private AdminUserRepositoryInterface $adminUserRepository,
        private RoleRepositoryInterface $roleRepository,
        private SystemConfigRepository $configRepository,
    ) {}

    public function run(): void
    {
        $this->seedSuperAdminRole();
        $this->seedAdminUser();
        $this->seedSystemConfig();
    }

    private function seedSuperAdminRole(): void
    {
        $existing = $this->roleRepository->findBySlug('super-admin');
        if ($existing !== null) {
            return;
        }

        $role = new Role();
        $role->name = 'Super Admin';
        $role->slug = 'super-admin';
        $role->description = 'Full access to all admin features';
        $role->isSuperAdmin = '1';
        $role->createdAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->roleRepository->save($role);
    }

    private function seedAdminUser(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@shilla.org');
        $existing = $this->adminUserRepository->findByEmail($email);

        if ($existing !== null) {
            return;
        }

        $admin = new AdminUser();
        $admin->email = $email;
        $admin->name = 'Admin';
        $admin->password = password_hash(env('ADMIN_PASSWORD', 'admin'), PASSWORD_ARGON2ID);
        $admin->createdAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->adminUserRepository->save($admin);

        $superAdminRole = $this->roleRepository->findBySlug('super-admin');
        if ($superAdminRole !== null && $admin->id !== null) {
            $this->adminUserRepository->syncRoles($admin->id, [$superAdminRole->id]);
        }
    }

    private function seedSystemConfig(): void
    {
        $defaults = [
            'character_slot_default' => json_encode(50),
            'maintenance_mode' => json_encode(false),
        ];

        foreach ($defaults as $key => $value) {
            if ($this->configRepository->getValue($key) === null) {
                $this->configRepository->setValue($key, $value);
            }
        }
    }
}
