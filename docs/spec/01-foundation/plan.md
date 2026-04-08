# Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up the Shilla application skeleton with auth, admin panel, game shell layout, SSE infrastructure, and design system — everything downstream sub-projects build on.

**Architecture:** Marko PHP modular framework with a single `app/foundation/` module. Entity-driven schema with PostgreSQL. Server-rendered Latte templates + Alpine.js for interactivity. SSE via `marko/sse` + `marko/pubsub-pgsql` for real-time.

**Tech Stack:** Marko PHP 8.5+, PostgreSQL, Latte, Alpine.js, Tailwind CSS, Pest PHP, Playwright

**Spec:** [docs/spec/01-foundation/README.md](README.md)

---

## File Map

```
shilla/                           # Project root (created by marko/skeleton)
├── DESIGN.md                     # Design system (twilight theme)
├── .env                          # Environment config
├── .env.example                  # Template
├── composer.json                 # Root composer with marko packages
├── package.json                  # Tailwind CLI
├── tailwind.config.js            # Tailwind config with twilight theme
├── public/
│   ├── index.php                 # Entry point (from skeleton)
│   ├── css/
│   │   └── app.css               # Compiled Tailwind output
│   └── js/
│       ├── alpine.min.js         # Vendored Alpine.js
│       ├── app.js                # Alpine.js init + toast handler
│       ├── sse.js                # EventSource manager
│       └── command-palette.js    # Command palette logic
├── resources/
│   └── css/
│       └── app.css               # Tailwind source (input file)
├── config/
│   ├── database.php              # PostgreSQL connection
│   ├── authentication.php        # Auth guards config
│   ├── session.php               # Database session driver
│   ├── security.php              # CSRF, headers
│   └── admin.php                 # Admin panel config
├── app/
│   └── foundation/
│       ├── composer.json         # Module registration
│       ├── module.php            # Bindings, singletons
│       ├── src/
│       │   ├── Entity/
│       │   │   ├── User.php
│       │   │   ├── Player.php
│       │   │   ├── SocialAccount.php
│       │   │   ├── SystemConfig.php
│       │   │   └── Announcement.php
│       │   ├── Repository/
│       │   │   ├── UserRepository.php
│       │   │   ├── PlayerRepository.php
│       │   │   ├── SocialAccountRepository.php
│       │   │   ├── SystemConfigRepository.php
│       │   │   └── AnnouncementRepository.php
│       │   ├── Service/
│       │   │   ├── ConfigService.php
│       │   │   ├── PlayerService.php
│       │   │   ├── SocialAuthService.php
│       │   │   └── CommandRegistry.php
│       │   ├── Controller/
│       │   │   ├── Auth/
│       │   │   │   ├── RegisterController.php
│       │   │   │   ├── LoginController.php
│       │   │   │   └── SocialAuthController.php
│       │   │   ├── Public/
│       │   │   │   ├── LandingController.php
│       │   │   │   ├── AboutController.php
│       │   │   │   └── FaqController.php
│       │   │   ├── Game/
│       │   │   │   ├── DashboardController.php
│       │   │   │   ├── SseController.php
│       │   │   │   └── CommandController.php
│       │   │   └── Admin/
│       │   │       ├── UserAdminController.php
│       │   │       ├── ConfigAdminController.php
│       │   │       └── AnnouncementAdminController.php
│       │   ├── AdminSection/
│       │   │   ├── UserSection.php
│       │   │   └── SystemSection.php
│       │   ├── Widget/
│       │   │   ├── UserCountWidget.php
│       │   │   ├── OnlineCountWidget.php
│       │   │   └── ActivityFeedWidget.php
│       │   ├── Middleware/
│       │   │   └── RoleMiddleware.php
│       │   └── Observer/
│       │       └── CreatePlayerOnRegister.php
│       ├── config/
│       │   ├── social_auth.php
│       │   └── game.php
│       ├── database/
│       │   └── seeders/
│       │       └── FoundationSeeder.php
│       ├── resources/
│       │   └── views/
│       │       ├── layout/
│       │       │   ├── public.latte
│       │       │   ├── auth.latte
│       │       │   └── game.latte
│       │       ├── public/
│       │       │   ├── landing.latte
│       │       │   ├── about.latte
│       │       │   └── faq.latte
│       │       ├── auth/
│       │       │   ├── login.latte
│       │       │   ├── register.latte
│       │       │   └── verify-social.latte
│       │       ├── game/
│       │       │   └── dashboard.latte
│       │       ├── admin/
│       │       │   ├── users/
│       │       │   │   ├── index.latte
│       │       │   │   └── edit.latte
│       │       │   ├── config/
│       │       │   │   └── index.latte
│       │       │   └── announcements/
│       │       │       ├── index.latte
│       │       │       └── form.latte
│       │       └── components/
│       │           ├── toast.latte
│       │           └── command-palette.latte
│       └── tests/
│           ├── Unit/
│           │   ├── ConfigServiceTest.php
│           │   ├── PlayerServiceTest.php
│           │   └── CommandRegistryTest.php
│           └── Feature/
│               ├── RegisterTest.php
│               ├── LoginTest.php
│               ├── SocialAuthTest.php
│               ├── AdminUserTest.php
│               └── AdminConfigTest.php
├── tests/
│   └── e2e/
│       ├── playwright.config.ts
│       └── example.spec.ts
└── pest.php                      # Pest config
```

---

## Task 1: Project Scaffold

**Files:**
- Create: entire project via `composer create-project`
- Modify: `composer.json` (add packages)
- Modify: `.env`
- Create: `.env.example`

- [ ] **Step 1: Create Marko skeleton project**

```bash
cd /home/necro/greenfield
rm -rf shilla/.superpowers  # clean brainstorm artifacts
# Preserve docs directory
cp -r shilla/docs /tmp/shilla-docs
composer create-project marko/skeleton shilla-app
# Move into place
mv shilla/.git /tmp/shilla-git
rm -rf shilla
mv shilla-app shilla
mv /tmp/shilla-git shilla/.git
cp -r /tmp/shilla-docs shilla/docs
rm -rf /tmp/shilla-docs /tmp/shilla-git
cd shilla
```

Note: Preserve the existing `.git` history and `docs/` directory from brainstorming.

- [ ] **Step 2: Install all Marko packages**

```bash
composer require \
  marko/database-pgsql \
  marko/authentication \
  marko/authentication-token \
  marko/authorization \
  marko/admin \
  marko/admin-auth \
  marko/admin-panel \
  marko/session-database \
  marko/validation \
  marko/security \
  marko/rate-limiting \
  marko/hashing \
  marko/sse \
  marko/pubsub-pgsql \
  marko/view-latte \
  marko/env \
  marko/config \
  marko/mail-smtp

composer require --dev marko/testing
```

- [ ] **Step 3: Configure .env**

```bash
cp .env.example .env
```

Edit `.env`:

```env
APP_NAME=Shilla
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_DRIVER=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=shilla
DB_USERNAME=shilla
DB_PASSWORD=shilla

SESSION_DRIVER=database

PUBSUB_DRIVER=pgsql
PUBSUB_PREFIX=shilla_
PUBSUB_PGSQL_HOST=127.0.0.1
PUBSUB_PGSQL_PORT=5432
PUBSUB_PGSQL_USER=shilla
PUBSUB_PGSQL_PASSWORD=shilla
PUBSUB_PGSQL_DATABASE=shilla

MAIL_DRIVER=smtp
MAIL_HOST=localhost
MAIL_PORT=1025
MAIL_FROM_ADDRESS=noreply@shilla.game
MAIL_FROM_NAME=Shilla

ADMIN_EMAIL=admin@shilla.game
ADMIN_PASSWORD=admin

SOCIAL_DISCORD_CLIENT_ID=
SOCIAL_DISCORD_CLIENT_SECRET=
SOCIAL_DISCORD_REDIRECT_URI=http://localhost:8000/auth/discord/callback

SOCIAL_GOOGLE_CLIENT_ID=
SOCIAL_GOOGLE_CLIENT_SECRET=
SOCIAL_GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback

SOCIAL_GITHUB_CLIENT_ID=
SOCIAL_GITHUB_CLIENT_SECRET=
SOCIAL_GITHUB_REDIRECT_URI=http://localhost:8000/auth/github/callback
```

- [ ] **Step 4: Create PostgreSQL database**

```bash
createdb shilla
# Or via psql:
# psql -c "CREATE DATABASE shilla;"
# psql -c "CREATE USER shilla WITH PASSWORD 'shilla';"
# psql -c "GRANT ALL PRIVILEGES ON DATABASE shilla TO shilla;"
```

- [ ] **Step 5: Verify skeleton runs**

```bash
marko up
# Visit http://localhost:8000 — should see Marko welcome page
# Ctrl+C to stop
```

- [ ] **Step 6: Update .gitignore and commit**

Add to `.gitignore`:

```
.superpowers/
.env
vendor/
node_modules/
public/css/app.css
storage/
```

```bash
git add -A
git commit -m "Scaffold Marko project with all foundation packages"
```

---

## Task 2: Frontend Pipeline

**Files:**
- Create: `package.json`
- Create: `tailwind.config.js`
- Create: `resources/css/app.css`
- Create: `public/js/alpine.min.js`
- Create: `public/js/app.js`

- [ ] **Step 1: Initialize npm and install Tailwind**

```bash
npm init -y
npm install tailwindcss @tailwindcss/cli
```

- [ ] **Step 2: Create Tailwind config with twilight theme**

Create `tailwind.config.js`:

```javascript
/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './app/**/resources/views/**/*.latte',
    './resources/**/*.latte',
    './public/js/**/*.js',
  ],
  theme: {
    extend: {
      colors: {
        twilight: {
          bg: '#20202a',
          surface: '#282834',
          elevated: '#2e2e3a',
          border: '#3a3a4a',
          'border-light': '#44444e',
        },
        text: {
          primary: '#c0c4d8',
          secondary: '#7878a0',
          muted: '#5a5a70',
        },
        accent: {
          DEFAULT: '#5a6aaa',
          hover: '#7e8ec0',
          bright: '#8a9ad0',
        },
        health: '#e05050',
        'health-dark': '#c04040',
        mana: '#5080e0',
        'mana-dark': '#6a6ab8',
        stamina: '#50c070',
        'stamina-dark': '#4a9e6a',
        gold: '#c0a050',
        danger: '#aa6a6a',
        'player-name': '#6aaa80',
        'npc-name': '#7e8ec0',
        'system-msg': '#aa8a4a',
      },
    },
  },
  plugins: [],
};
```

- [ ] **Step 3: Create Tailwind source file**

Create `resources/css/app.css`:

```css
@tailwind base;
@tailwind components;
@tailwind utilities;

@layer base {
  body {
    @apply bg-twilight-bg text-text-primary;
  }
}
```

- [ ] **Step 4: Add build script to package.json**

Edit `package.json` to add scripts:

```json
{
  "scripts": {
    "build:css": "npx @tailwindcss/cli -i resources/css/app.css -o public/css/app.css --minify",
    "dev:css": "npx @tailwindcss/cli -i resources/css/app.css -o public/css/app.css --watch"
  }
}
```

- [ ] **Step 5: Build CSS and verify**

```bash
npm run build:css
# Should create public/css/app.css
ls -la public/css/app.css
```

- [ ] **Step 6: Vendor Alpine.js**

```bash
curl -sL https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js -o public/js/alpine.min.js
```

- [ ] **Step 7: Create app.js**

Create `public/js/app.js`:

```javascript
(function () {
  'use strict';

  // Toast notification system
  document.addEventListener('alpine:init', () => {
    Alpine.store('toasts', {
      items: [],
      add(message, type = 'info', duration = 4000) {
        const id = Date.now();
        this.items.push({ id, message, type });
        if (duration > 0) {
          setTimeout(() => this.remove(id), duration);
        }
      },
      remove(id) {
        this.items = this.items.filter(t => t.id !== id);
      },
    });
  });
})();
```

- [ ] **Step 8: Commit**

```bash
git add package.json package-lock.json tailwind.config.js resources/css/app.css public/js/alpine.min.js public/js/app.js
git commit -m "Add frontend pipeline: Tailwind CSS + Alpine.js"
```

---

## Task 3: DESIGN.md

**Files:**
- Create: `DESIGN.md`

- [ ] **Step 1: Write the design system document**

Create `DESIGN.md` following the awesome-design-md format. This is a long document — write all 9 sections with the twilight theme colors, typography (Inter Variable as primary font), component styles (buttons, inputs, cards, stat pills, panels, compass grid), layout principles, elevation system, do's/don'ts, responsive behavior, and an agent prompt guide.

Reference the color table from the spec at `docs/spec/01-foundation/README.md` (Design System section) for all color values.

Key decisions for DESIGN.md:
- Font: Inter Variable (same as Linear — clean, geometric)
- Base font size: 16px
- Heading weights: 600
- Body weight: 400
- Background surfaces: 3-tier (`#20202a` → `#282834` → `#2e2e3a`)
- Border: `#3a3a4a` with 1px solid
- Border radius: 6px for cards/panels, 4px for buttons/inputs, 12px for pills
- Accent: `#5a6aaa` with `#7e8ec0` hover
- No shadows — use border + background differentiation for depth (twilight theme)
- Transitions: 150ms ease for hover/focus states

- [ ] **Step 2: Commit**

```bash
git add DESIGN.md
git commit -m "Add DESIGN.md: twilight theme design system"
```

---

## Task 4: Foundation Module Setup

**Files:**
- Create: `app/foundation/composer.json`
- Create: `app/foundation/module.php`
- Create: `app/foundation/config/game.php`
- Create: `app/foundation/config/social_auth.php`

- [ ] **Step 1: Create module composer.json**

Create `app/foundation/composer.json`:

```json
{
  "name": "app/foundation",
  "type": "marko-module",
  "autoload": {
    "psr-4": {
      "App\\Foundation\\": "src/"
    }
  },
  "extra": {
    "marko": {
      "module": true
    }
  }
}
```

- [ ] **Step 2: Create module.php**

Create `app/foundation/module.php`:

```php
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
```

- [ ] **Step 3: Create game config**

Create `app/foundation/config/game.php`:

```php
<?php

declare(strict_types=1);

return [
    'character_slot_default' => 50,
    'character_slot_max' => 100,
];
```

- [ ] **Step 4: Create social auth config**

Create `app/foundation/config/social_auth.php`:

```php
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
```

- [ ] **Step 5: Commit**

```bash
git add app/foundation/
git commit -m "Add foundation module skeleton with config"
```

---

## Task 5: Entity Definitions

**Files:**
- Create: `app/foundation/src/Entity/User.php`
- Create: `app/foundation/src/Entity/Player.php`
- Create: `app/foundation/src/Entity/SocialAccount.php`
- Create: `app/foundation/src/Entity/SystemConfig.php`
- Create: `app/foundation/src/Entity/Announcement.php`

- [ ] **Step 1: Create User entity**

Create `app/foundation/src/Entity/User.php`:

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Entity;

use DateTimeImmutable;
use Marko\Authentication\AuthenticatableInterface;
use Marko\Authorization\AuthorizableInterface;
use Marko\Database\Attributes\Table;
use Marko\Database\Attributes\Column;
use Marko\Database\Entity\Entity;

#[Table('users')]
class User extends Entity implements AuthenticatableInterface, AuthorizableInterface
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column(length: 50, unique: true)]
    public string $username;

    #[Column(length: 255, unique: true)]
    public string $email;

    #[Column(name: 'email_verified_at')]
    public ?DateTimeImmutable $emailVerifiedAt = null;

    #[Column(length: 255)]
    public ?string $password = null;

    #[Column(name: 'remember_token', length: 100)]
    public ?string $rememberToken = null;

    #[Column(length: 20, default: 'player')]
    public string $role = 'player';

    #[Column(name: 'character_slot_limit')]
    public ?int $characterSlotLimit = null;

    #[Column(name: 'banned_at')]
    public ?DateTimeImmutable $bannedAt = null;

    #[Column(name: 'created_at', default: 'CURRENT_TIMESTAMP')]
    public DateTimeImmutable $createdAt;

    #[Column(name: 'updated_at')]
    public ?DateTimeImmutable $updatedAt = null;

    public function getAuthIdentifier(): int|string
    {
        return $this->id;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthPassword(): string
    {
        return $this->password ?? '';
    }

    public function getRememberToken(): ?string
    {
        return $this->rememberToken;
    }

    public function setRememberToken(?string $token): void
    {
        $this->rememberToken = $token;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    public function can(string $ability, mixed ...$arguments): bool
    {
        return false; // Delegated to Gate
    }

    public function isBanned(): bool
    {
        return $this->bannedAt !== null;
    }

    public function hasPassword(): bool
    {
        return $this->password !== null;
    }
}
```

- [ ] **Step 2: Create Player entity**

Create `app/foundation/src/Entity/Player.php`:

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Entity;

use DateTimeImmutable;
use Marko\Database\Attributes\Table;
use Marko\Database\Attributes\Column;
use Marko\Database\Entity\Entity;

#[Table('players')]
class Player extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column(name: 'user_id', references: 'users.id', onDelete: 'cascade')]
    public int $userId;

    #[Column(length: 50, unique: true)]
    public string $name;

    #[Column(name: 'slot_number')]
    public int $slotNumber;

    #[Column(name: 'created_at', default: 'CURRENT_TIMESTAMP')]
    public DateTimeImmutable $createdAt;

    #[Column(name: 'updated_at')]
    public ?DateTimeImmutable $updatedAt = null;
}
```

- [ ] **Step 3: Create SocialAccount entity**

Create `app/foundation/src/Entity/SocialAccount.php`:

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Entity;

use DateTimeImmutable;
use Marko\Database\Attributes\Table;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Entity\Entity;

#[Table('social_accounts')]
#[Index('uq_provider_provider_id', ['provider', 'provider_id'], unique: true)]
class SocialAccount extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column(name: 'user_id', references: 'users.id', onDelete: 'cascade')]
    public int $userId;

    #[Column(length: 20)]
    public string $provider;

    #[Column(name: 'provider_id', length: 255)]
    public string $providerId;

    #[Column(name: 'provider_email', length: 255)]
    public ?string $providerEmail = null;

    #[Column(name: 'access_token', type: 'text')]
    public ?string $accessToken = null;

    #[Column(name: 'refresh_token', type: 'text')]
    public ?string $refreshToken = null;

    #[Column(name: 'created_at', default: 'CURRENT_TIMESTAMP')]
    public DateTimeImmutable $createdAt;
}
```

- [ ] **Step 4: Create SystemConfig entity**

Create `app/foundation/src/Entity/SystemConfig.php`:

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Entity;

use DateTimeImmutable;
use Marko\Database\Attributes\Table;
use Marko\Database\Attributes\Column;
use Marko\Database\Entity\Entity;

#[Table('system_config')]
class SystemConfig extends Entity
{
    #[Column(length: 100, primaryKey: true)]
    public string $key;

    #[Column(type: 'text')]
    public string $value;

    #[Column(name: 'updated_at')]
    public ?DateTimeImmutable $updatedAt = null;
}
```

- [ ] **Step 5: Create Announcement entity**

Create `app/foundation/src/Entity/Announcement.php`:

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Entity;

use DateTimeImmutable;
use Marko\Database\Attributes\Table;
use Marko\Database\Attributes\Column;
use Marko\Database\Entity\Entity;

#[Table('announcements')]
class Announcement extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column(length: 255)]
    public string $title;

    #[Column(type: 'text')]
    public string $body;

    #[Column(length: 20, default: 'info')]
    public string $type = 'info';

    #[Column(default: true)]
    public bool $active = true;

    #[Column(name: 'starts_at')]
    public ?DateTimeImmutable $startsAt = null;

    #[Column(name: 'ends_at')]
    public ?DateTimeImmutable $endsAt = null;

    #[Column(name: 'created_at', default: 'CURRENT_TIMESTAMP')]
    public DateTimeImmutable $createdAt;
}
```

- [ ] **Step 6: Run schema migration**

```bash
marko db:diff
# Review the output — should show CREATE TABLE for all 5 entities
marko db:migrate
```

- [ ] **Step 7: Commit**

```bash
git add app/foundation/src/Entity/
git commit -m "Add foundation entities: User, Player, SocialAccount, SystemConfig, Announcement"
```

---

## Task 6: Repositories

**Files:**
- Create: `app/foundation/src/Repository/UserRepository.php`
- Create: `app/foundation/src/Repository/PlayerRepository.php`
- Create: `app/foundation/src/Repository/SocialAccountRepository.php`
- Create: `app/foundation/src/Repository/SystemConfigRepository.php`
- Create: `app/foundation/src/Repository/AnnouncementRepository.php`

- [ ] **Step 1: Create UserRepository**

Create `app/foundation/src/Repository/UserRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Repository;

use App\Foundation\Entity\User;
use Marko\Database\Repository\Repository;

class UserRepository extends Repository
{
    protected const ENTITY_CLASS = User::class;

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findByUsername(string $username): ?User
    {
        return $this->findOneBy(['username' => $username]);
    }

    public function findByEmailOrUsername(string $identifier): ?User
    {
        return $this->findByEmail($identifier) ?? $this->findByUsername($identifier);
    }

    public function countAll(): int
    {
        return $this->query()->count();
    }

    public function countSince(\DateTimeImmutable $since): int
    {
        return $this->query()
            ->where('created_at', '>=', $since->format('Y-m-d H:i:s'))
            ->count();
    }

    public function search(string $term, ?string $role = null, int $limit = 50, int $offset = 0): array
    {
        $query = $this->query();

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('username', 'LIKE', "%{$term}%")
                  ->orWhere('email', 'LIKE', "%{$term}%");
            });
        }

        if ($role !== null) {
            $query->where('role', '=', $role);
        }

        return $query
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->getEntities();
    }
}
```

- [ ] **Step 2: Create PlayerRepository**

Create `app/foundation/src/Repository/PlayerRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Repository;

use App\Foundation\Entity\Player;
use Marko\Database\Repository\Repository;

class PlayerRepository extends Repository
{
    protected const ENTITY_CLASS = Player::class;

    public function findByUserId(int $userId): array
    {
        return $this->query()
            ->where('user_id', '=', $userId)
            ->orderBy('slot_number', 'asc')
            ->getEntities();
    }

    public function countByUserId(int $userId): int
    {
        return $this->query()
            ->where('user_id', '=', $userId)
            ->count();
    }

    public function findByName(string $name): ?Player
    {
        return $this->findOneBy(['name' => $name]);
    }

    public function nextSlotNumber(int $userId): int
    {
        $players = $this->findByUserId($userId);
        $usedSlots = array_map(fn (Player $p) => $p->slotNumber, $players);

        for ($i = 1; $i <= 100; $i++) {
            if (!in_array($i, $usedSlots, true)) {
                return $i;
            }
        }

        return count($usedSlots) + 1;
    }
}
```

- [ ] **Step 3: Create SocialAccountRepository**

Create `app/foundation/src/Repository/SocialAccountRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Repository;

use App\Foundation\Entity\SocialAccount;
use Marko\Database\Repository\Repository;

class SocialAccountRepository extends Repository
{
    protected const ENTITY_CLASS = SocialAccount::class;

    public function findByProvider(string $provider, string $providerId): ?SocialAccount
    {
        return $this->findOneBy([
            'provider' => $provider,
            'provider_id' => $providerId,
        ]);
    }

    public function findByUserId(int $userId): array
    {
        return $this->query()
            ->where('user_id', '=', $userId)
            ->getEntities();
    }

    public function countByUserId(int $userId): int
    {
        return $this->query()
            ->where('user_id', '=', $userId)
            ->count();
    }
}
```

- [ ] **Step 4: Create SystemConfigRepository**

Create `app/foundation/src/Repository/SystemConfigRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Repository;

use App\Foundation\Entity\SystemConfig;
use Marko\Database\Repository\Repository;

class SystemConfigRepository extends Repository
{
    protected const ENTITY_CLASS = SystemConfig::class;

    public function getValue(string $key): ?string
    {
        $config = $this->findOneBy(['key' => $key]);
        return $config?->value;
    }

    public function setValue(string $key, string $value): void
    {
        $existing = $this->findOneBy(['key' => $key]);

        if ($existing !== null) {
            $existing->value = $value;
            $existing->updatedAt = new \DateTimeImmutable();
            $this->save($existing);
        } else {
            $config = new SystemConfig();
            $config->key = $key;
            $config->value = $value;
            $config->updatedAt = new \DateTimeImmutable();
            $this->save($config);
        }
    }

    public function getAll(): array
    {
        return $this->query()->getEntities();
    }
}
```

- [ ] **Step 5: Create AnnouncementRepository**

Create `app/foundation/src/Repository/AnnouncementRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Repository;

use App\Foundation\Entity\Announcement;
use Marko\Database\Repository\Repository;

class AnnouncementRepository extends Repository
{
    protected const ENTITY_CLASS = Announcement::class;

    public function findActive(): array
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        return $this->query()
            ->where('active', '=', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')
                  ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')
                  ->orWhere('ends_at', '>=', $now);
            })
            ->orderBy('created_at', 'desc')
            ->getEntities();
    }

    public function findAll(): array
    {
        return $this->query()
            ->orderBy('created_at', 'desc')
            ->getEntities();
    }
}
```

- [ ] **Step 6: Commit**

```bash
git add app/foundation/src/Repository/
git commit -m "Add foundation repositories with query methods"
```

---

## Task 7: ConfigService (TDD)

**Files:**
- Create: `app/foundation/tests/Unit/ConfigServiceTest.php`
- Create: `app/foundation/src/Service/ConfigService.php`

- [ ] **Step 1: Write the failing test**

Create `app/foundation/tests/Unit/ConfigServiceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Foundation\Entity\SystemConfig;
use App\Foundation\Repository\SystemConfigRepository;
use App\Foundation\Service\ConfigService;

beforeEach(function () {
    $this->repo = Mockery::mock(SystemConfigRepository::class);
    $this->service = new ConfigService($this->repo);
});

it('returns default value when key not in database', function () {
    $this->repo->shouldReceive('getValue')
        ->with('character_slot_default')
        ->andReturn(null);

    $result = $this->service->get('character_slot_default', 50);

    expect($result)->toBe(50);
});

it('returns database value when key exists', function () {
    $this->repo->shouldReceive('getValue')
        ->with('character_slot_default')
        ->andReturn(json_encode(75));

    $result = $this->service->get('character_slot_default', 50);

    expect($result)->toBe(75);
});

it('sets a value', function () {
    $this->repo->shouldReceive('setValue')
        ->with('character_slot_default', json_encode(75))
        ->once();

    $this->service->set('character_slot_default', 75);
});

it('returns character slot limit for user with override', function () {
    $this->repo->shouldReceive('getValue')
        ->with('character_slot_default')
        ->andReturn(json_encode(50));

    $result = $this->service->getCharacterSlotLimit(userOverride: 25);

    expect($result)->toBe(25);
});

it('returns global default when user has no override', function () {
    $this->repo->shouldReceive('getValue')
        ->with('character_slot_default')
        ->andReturn(json_encode(50));

    $result = $this->service->getCharacterSlotLimit(userOverride: null);

    expect($result)->toBe(50);
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/pest app/foundation/tests/Unit/ConfigServiceTest.php
```

Expected: FAIL — `ConfigService` class not found.

- [ ] **Step 3: Implement ConfigService**

Create `app/foundation/src/Service/ConfigService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Service;

use App\Foundation\Repository\SystemConfigRepository;

class ConfigService
{
    public function __construct(
        private readonly SystemConfigRepository $configRepository,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->configRepository->getValue($key);

        if ($value === null) {
            return $default;
        }

        return json_decode($value, true);
    }

    public function set(string $key, mixed $value): void
    {
        $this->configRepository->setValue($key, json_encode($value));
    }

    public function getCharacterSlotLimit(?int $userOverride): int
    {
        if ($userOverride !== null) {
            return $userOverride;
        }

        return (int) $this->get('character_slot_default', 50);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
./vendor/bin/pest app/foundation/tests/Unit/ConfigServiceTest.php
```

Expected: All 5 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/foundation/tests/Unit/ConfigServiceTest.php app/foundation/src/Service/ConfigService.php
git commit -m "Add ConfigService with system config read/write and slot limits (TDD)"
```

---

## Task 8: PlayerService (TDD)

**Files:**
- Create: `app/foundation/tests/Unit/PlayerServiceTest.php`
- Create: `app/foundation/src/Service/PlayerService.php`

- [ ] **Step 1: Write the failing test**

Create `app/foundation/tests/Unit/PlayerServiceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Foundation\Entity\Player;
use App\Foundation\Repository\PlayerRepository;
use App\Foundation\Service\ConfigService;
use App\Foundation\Service\PlayerService;

beforeEach(function () {
    $this->playerRepo = Mockery::mock(PlayerRepository::class);
    $this->configService = Mockery::mock(ConfigService::class);
    $this->service = new PlayerService($this->playerRepo, $this->configService);
});

it('creates a player in the next available slot', function () {
    $this->playerRepo->shouldReceive('nextSlotNumber')
        ->with(1)
        ->andReturn(1);

    $this->playerRepo->shouldReceive('countByUserId')
        ->with(1)
        ->andReturn(0);

    $this->configService->shouldReceive('getCharacterSlotLimit')
        ->with(null)
        ->andReturn(50);

    $this->playerRepo->shouldReceive('save')
        ->once()
        ->withArgs(function (Player $player) {
            return $player->userId === 1
                && $player->name === 'Aldric'
                && $player->slotNumber === 1;
        });

    $player = $this->service->createPlayer(
        userId: 1,
        name: 'Aldric',
        userSlotOverride: null,
    );

    expect($player)->toBeInstanceOf(Player::class);
});

it('rejects creation when slot limit reached', function () {
    $this->playerRepo->shouldReceive('countByUserId')
        ->with(1)
        ->andReturn(50);

    $this->configService->shouldReceive('getCharacterSlotLimit')
        ->with(null)
        ->andReturn(50);

    $this->service->createPlayer(
        userId: 1,
        name: 'Aldric',
        userSlotOverride: null,
    );
})->throws(\RuntimeException::class, 'Character slot limit reached');

it('rejects duplicate character name', function () {
    $this->playerRepo->shouldReceive('countByUserId')
        ->with(1)
        ->andReturn(0);

    $this->configService->shouldReceive('getCharacterSlotLimit')
        ->with(null)
        ->andReturn(50);

    $existing = new Player();
    $existing->name = 'Aldric';

    $this->playerRepo->shouldReceive('findByName')
        ->with('Aldric')
        ->andReturn($existing);

    $this->service->createPlayer(
        userId: 1,
        name: 'Aldric',
        userSlotOverride: null,
    );
})->throws(\RuntimeException::class, 'Character name already taken');

it('lists players for a user', function () {
    $player = new Player();
    $player->name = 'Aldric';
    $player->slotNumber = 1;

    $this->playerRepo->shouldReceive('findByUserId')
        ->with(1)
        ->andReturn([$player]);

    $players = $this->service->getPlayersForUser(1);

    expect($players)->toHaveCount(1);
    expect($players[0]->name)->toBe('Aldric');
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/pest app/foundation/tests/Unit/PlayerServiceTest.php
```

Expected: FAIL — `PlayerService` class not found.

- [ ] **Step 3: Implement PlayerService**

Create `app/foundation/src/Service/PlayerService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Service;

use App\Foundation\Entity\Player;
use App\Foundation\Repository\PlayerRepository;

class PlayerService
{
    public function __construct(
        private readonly PlayerRepository $playerRepository,
        private readonly ConfigService $configService,
    ) {}

    public function createPlayer(int $userId, string $name, ?int $userSlotOverride): Player
    {
        $limit = $this->configService->getCharacterSlotLimit($userSlotOverride);
        $count = $this->playerRepository->countByUserId($userId);

        if ($count >= $limit) {
            throw new \RuntimeException('Character slot limit reached');
        }

        $existing = $this->playerRepository->findByName($name);
        if ($existing !== null) {
            throw new \RuntimeException('Character name already taken');
        }

        $player = new Player();
        $player->userId = $userId;
        $player->name = $name;
        $player->slotNumber = $this->playerRepository->nextSlotNumber($userId);

        $this->playerRepository->save($player);

        return $player;
    }

    public function getPlayersForUser(int $userId): array
    {
        return $this->playerRepository->findByUserId($userId);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
./vendor/bin/pest app/foundation/tests/Unit/PlayerServiceTest.php
```

Expected: All 4 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/foundation/tests/Unit/PlayerServiceTest.php app/foundation/src/Service/PlayerService.php
git commit -m "Add PlayerService with slot limits and name validation (TDD)"
```

---

## Task 9: CommandRegistry (TDD)

**Files:**
- Create: `app/foundation/tests/Unit/CommandRegistryTest.php`
- Create: `app/foundation/src/Service/CommandRegistry.php`

- [ ] **Step 1: Write the failing test**

Create `app/foundation/tests/Unit/CommandRegistryTest.php`:

```php
<?php

declare(strict_types=1);

use App\Foundation\Service\CommandRegistry;

beforeEach(function () {
    $this->registry = new CommandRegistry();
});

it('registers and retrieves commands', function () {
    $this->registry->register(
        id: 'inventory',
        label: 'Open Inventory',
        action: '/game/inventory',
        context: 'always',
    );

    $commands = $this->registry->getAvailable(
        roomContexts: [],
        playerState: [],
        role: 'player',
    );

    expect($commands)->toHaveCount(1);
    expect($commands[0]['id'])->toBe('inventory');
});

it('filters by room context', function () {
    $this->registry->register(
        id: 'buy',
        label: 'Buy Items',
        action: '/game/shop/buy',
        context: 'shop',
    );

    $withShop = $this->registry->getAvailable(
        roomContexts: ['shop'],
        playerState: [],
        role: 'player',
    );

    $withoutShop = $this->registry->getAvailable(
        roomContexts: [],
        playerState: [],
        role: 'player',
    );

    expect($withShop)->toHaveCount(1);
    expect($withoutShop)->toHaveCount(0);
});

it('filters by player state', function () {
    $this->registry->register(
        id: 'flee',
        label: 'Flee Combat',
        action: '/game/combat/flee',
        context: 'always',
        requiredState: 'in_combat',
    );

    $inCombat = $this->registry->getAvailable(
        roomContexts: [],
        playerState: ['in_combat'],
        role: 'player',
    );

    $notInCombat = $this->registry->getAvailable(
        roomContexts: [],
        playerState: [],
        role: 'player',
    );

    expect($inCombat)->toHaveCount(1);
    expect($notInCombat)->toHaveCount(0);
});

it('filters by role', function () {
    $this->registry->register(
        id: 'goto',
        label: 'Go To Room',
        action: '/admin/goto',
        context: 'always',
        requiredRole: 'admin',
    );

    $admin = $this->registry->getAvailable(
        roomContexts: [],
        playerState: [],
        role: 'admin',
    );

    $player = $this->registry->getAvailable(
        roomContexts: [],
        playerState: [],
        role: 'player',
    );

    expect($admin)->toHaveCount(1);
    expect($player)->toHaveCount(0);
});

it('fuzzy searches by label', function () {
    $this->registry->register(id: 'inventory', label: 'Open Inventory', action: '/inventory', context: 'always');
    $this->registry->register(id: 'pocket', label: 'Enter Pocket Portal', action: '/pocket', context: 'always');
    $this->registry->register(id: 'settings', label: 'Settings', action: '/settings', context: 'always');

    $results = $this->registry->search(
        query: 'inv',
        roomContexts: [],
        playerState: [],
        role: 'player',
    );

    expect($results)->toHaveCount(1);
    expect($results[0]['id'])->toBe('inventory');
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/pest app/foundation/tests/Unit/CommandRegistryTest.php
```

Expected: FAIL — `CommandRegistry` class not found.

- [ ] **Step 3: Implement CommandRegistry**

Create `app/foundation/src/Service/CommandRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Service;

class CommandRegistry
{
    /** @var array<string, array{id: string, label: string, action: string, context: string, requiredState: ?string, requiredRole: ?string}> */
    private array $commands = [];

    private const ROLE_HIERARCHY = [
        'player' => 0,
        'builder' => 1,
        'admin' => 2,
    ];

    public function register(
        string $id,
        string $label,
        string $action,
        string $context = 'always',
        ?string $requiredState = null,
        ?string $requiredRole = null,
    ): void {
        $this->commands[$id] = [
            'id' => $id,
            'label' => $label,
            'action' => $action,
            'context' => $context,
            'requiredState' => $requiredState,
            'requiredRole' => $requiredRole,
        ];
    }

    public function getAvailable(array $roomContexts, array $playerState, string $role): array
    {
        return array_values(array_filter(
            $this->commands,
            fn (array $cmd) => $this->isAvailable($cmd, $roomContexts, $playerState, $role),
        ));
    }

    public function search(string $query, array $roomContexts, array $playerState, string $role): array
    {
        $available = $this->getAvailable($roomContexts, $playerState, $role);
        $query = strtolower($query);

        return array_values(array_filter(
            $available,
            fn (array $cmd) => str_contains(strtolower($cmd['label']), $query),
        ));
    }

    private function isAvailable(array $command, array $roomContexts, array $playerState, string $role): bool
    {
        // Check context
        if ($command['context'] !== 'always' && !in_array($command['context'], $roomContexts, true)) {
            return false;
        }

        // Check player state
        if ($command['requiredState'] !== null && !in_array($command['requiredState'], $playerState, true)) {
            return false;
        }

        // Check role
        if ($command['requiredRole'] !== null) {
            $requiredLevel = self::ROLE_HIERARCHY[$command['requiredRole']] ?? 0;
            $userLevel = self::ROLE_HIERARCHY[$role] ?? 0;
            if ($userLevel < $requiredLevel) {
                return false;
            }
        }

        return true;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
./vendor/bin/pest app/foundation/tests/Unit/CommandRegistryTest.php
```

Expected: All 5 tests PASS.

- [ ] **Step 5: Register default commands**

Add a `registerDefaults()` method to `CommandRegistry` and call it from the constructor:

```php
public function __construct()
{
    $this->registerDefaults();
}

private function registerDefaults(): void
{
    $this->register('inventory', 'Open Inventory', '/game/panel/inventory', 'always');
    $this->register('equipment', 'Open Equipment', '/game/panel/equipment', 'always');
    $this->register('skills', 'Open Skills', '/game/panel/skills', 'always');
    $this->register('quests', 'Open Quests', '/game/panel/quests', 'always');
    $this->register('pocket', 'Enter Pocket Portal', '/game/pocket/enter', 'always');
    $this->register('settings', 'Settings', '/game/settings', 'always');
    $this->register('logout', 'Log Out', '/logout', 'always');
}
```

Update the test `beforeEach` to clear defaults or account for them in assertions.

- [ ] **Step 6: Commit**

```bash
git add app/foundation/tests/Unit/CommandRegistryTest.php app/foundation/src/Service/CommandRegistry.php
git commit -m "Add CommandRegistry with context/state/role filtering and fuzzy search (TDD)"
```

---

## Task 10: Latte Layouts

**Files:**
- Create: `app/foundation/resources/views/layout/public.latte`
- Create: `app/foundation/resources/views/layout/auth.latte`
- Create: `app/foundation/resources/views/layout/game.latte`
- Create: `app/foundation/resources/views/components/toast.latte`
- Create: `app/foundation/resources/views/components/command-palette.latte`

- [ ] **Step 1: Create public layout**

Create `app/foundation/resources/views/layout/public.latte`. This is the layout for landing, about, FAQ pages. Full-width content area with header nav and footer. Uses twilight theme colors from DESIGN.md. Include `<link>` to `/css/app.css`.

Header: logo left, nav links (Home, About, FAQ) center, Login/Register buttons right.
Footer: copyright, links.

- [ ] **Step 2: Create auth layout**

Create `app/foundation/resources/views/layout/auth.latte`. Centered card on twilight background for login/register forms. Minimal — just the logo above and the form card.

- [ ] **Step 3: Create game shell layout**

Create `app/foundation/resources/views/layout/game.latte`. This is the main game layout per the spec:

Structure:
- Fixed top bar: logo, player name/level, stat pills, gold, settings icon
- Main area (flex row): canvas (flex-1) + icon rail (48px right)
- Canvas contains: `{block content}` for room/game content + compass dock at bottom
- Icon rail: vertical icon buttons for equipment, inventory, skills, quests, guide, chat
- Chat peek bar at the very bottom
- Command palette overlay (hidden by default)
- Toast notification container (top-right, absolute positioned)

Include Alpine.js (`/js/alpine.min.js`), app.js (`/js/app.js`), sse.js (`/js/sse.js`), command-palette.js (`/js/command-palette.js`).

The layout uses `x-data` Alpine.js directives for:
- `activePanel` (which slide-out is open, null = none)
- `chatExpanded` (boolean)
- Hotkey listeners (E, I, S, Q, Escape, Cmd/Ctrl+K)

Stat pills data comes from `$player` variable passed by the controller (placeholder values in Foundation — real stats come from Character System sub-project).

- [ ] **Step 4: Create toast component**

Create `app/foundation/resources/views/components/toast.latte`:

```html
<div x-data x-on:sse:toast.window="$store.toasts.add($event.detail.message, $event.detail.type)"
     class="fixed top-4 right-4 z-50 flex flex-col gap-2">
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div x-show="true"
             x-transition
             :class="{
                 'bg-stamina-dark/90 text-white': toast.type === 'success',
                 'bg-danger/90 text-white': toast.type === 'error',
                 'bg-accent/90 text-white': toast.type === 'info',
             }"
             class="px-4 py-2 rounded-md shadow-lg text-sm">
            <span x-text="toast.message"></span>
        </div>
    </template>
</div>
```

- [ ] **Step 5: Create command palette component**

Create `app/foundation/resources/views/components/command-palette.latte`. An Alpine.js component that:
- Shows/hides on Cmd/Ctrl+K
- Has a search input at the top
- Filters from the cached command list
- Keyboard navigation (up/down arrows, Enter to select)
- Clicking an option dispatches the action

This component reads commands from a JS variable populated by the `/game/commands` endpoint.

- [ ] **Step 6: Commit**

```bash
git add app/foundation/resources/views/
git commit -m "Add Latte layouts: public, auth, game shell with toast and command palette"
```

---

## Task 11: Public Pages

**Files:**
- Create: `app/foundation/src/Controller/Public/LandingController.php`
- Create: `app/foundation/src/Controller/Public/AboutController.php`
- Create: `app/foundation/src/Controller/Public/FaqController.php`
- Create: `app/foundation/resources/views/public/landing.latte`
- Create: `app/foundation/resources/views/public/about.latte`
- Create: `app/foundation/resources/views/public/faq.latte`

- [ ] **Step 1: Create LandingController**

Create `app/foundation/src/Controller/Public/LandingController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Controller\Public;

use Marko\Routing\Attributes\Get;
use Marko\View\ViewInterface;

class LandingController
{
    public function __construct(
        private readonly ViewInterface $view,
    ) {}

    #[Get('/')]
    public function index(): \Marko\Routing\Http\Response
    {
        return $this->view->render('foundation::public/landing');
    }
}
```

- [ ] **Step 2: Create AboutController and FaqController**

Same pattern as LandingController:
- `AboutController` with `#[Get('/about')]` rendering `foundation::public/about`
- `FaqController` with `#[Get('/faq')]` rendering `foundation::public/faq`

- [ ] **Step 3: Create landing.latte**

Create `app/foundation/resources/views/public/landing.latte`. Extends `foundation::layout/public`. Content:
- Hero section: game title "Shilla", tagline, register CTA button
- Features grid: 3-4 key features (visual rooms, turn-based combat, player housing, guilds)
- Screenshot/mockup placeholder area
- Final CTA: "Start Your Adventure" → register

All styled with Tailwind using twilight theme colors.

- [ ] **Step 4: Create about.latte and faq.latte**

- `about.latte`: extends public layout. World lore intro, what Shilla is, what makes it different.
- `faq.latte`: extends public layout. Accordion-style Q&A (can use Alpine.js `x-show` for expand/collapse).

- [ ] **Step 5: Verify pages render**

```bash
marko up
# Visit http://localhost:8000/ — landing page
# Visit http://localhost:8000/about — about page
# Visit http://localhost:8000/faq — FAQ page
```

- [ ] **Step 6: Commit**

```bash
git add app/foundation/src/Controller/Public/ app/foundation/resources/views/public/
git commit -m "Add public site pages: landing, about, FAQ"
```

---

## Task 12: Registration Flow (TDD)

**Files:**
- Create: `app/foundation/tests/Feature/RegisterTest.php`
- Create: `app/foundation/src/Controller/Auth/RegisterController.php`
- Create: `app/foundation/resources/views/auth/register.latte`

- [ ] **Step 1: Write the failing test**

Create `app/foundation/tests/Feature/RegisterTest.php`:

```php
<?php

declare(strict_types=1);

use App\Foundation\Entity\User;
use App\Foundation\Repository\UserRepository;

it('renders the registration page', function () {
    $response = $this->get('/register');
    expect($response->getStatusCode())->toBe(200);
});

it('registers a new user', function () {
    $response = $this->post('/register', [
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
    ]);

    expect($response->getStatusCode())->toBe(302);

    $repo = $this->app->get(UserRepository::class);
    $user = $repo->findByEmail('test@example.com');
    expect($user)->toBeInstanceOf(User::class);
    expect($user->username)->toBe('testuser');
});

it('rejects duplicate email', function () {
    // Create existing user first
    $repo = $this->app->get(UserRepository::class);
    $existing = new User();
    $existing->username = 'existing';
    $existing->email = 'test@example.com';
    $existing->password = password_hash('password', PASSWORD_BCRYPT);
    $repo->save($existing);

    $response = $this->post('/register', [
        'username' => 'newuser',
        'email' => 'test@example.com',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
    ]);

    expect($response->getStatusCode())->toBe(422);
});

it('rejects duplicate username', function () {
    $repo = $this->app->get(UserRepository::class);
    $existing = new User();
    $existing->username = 'testuser';
    $existing->email = 'existing@example.com';
    $existing->password = password_hash('password', PASSWORD_BCRYPT);
    $repo->save($existing);

    $response = $this->post('/register', [
        'username' => 'testuser',
        'email' => 'new@example.com',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
    ]);

    expect($response->getStatusCode())->toBe(422);
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/pest app/foundation/tests/Feature/RegisterTest.php
```

Expected: FAIL — controller/route not found.

- [ ] **Step 3: Create RegisterController**

Create `app/foundation/src/Controller/Auth/RegisterController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Controller\Auth;

use App\Foundation\Entity\User;
use App\Foundation\Repository\UserRepository;
use Marko\Authentication\AuthManager;
use Marko\Hashing\HashManager;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Attributes\Middleware;
use Marko\Authentication\Middleware\GuestMiddleware;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Validation\ValidatorInterface;
use Marko\View\ViewInterface;

class RegisterController
{
    public function __construct(
        private readonly ViewInterface $view,
        private readonly UserRepository $userRepository,
        private readonly AuthManager $authManager,
        private readonly HashManager $hashManager,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Get('/register')]
    #[Middleware(GuestMiddleware::class)]
    public function show(): Response
    {
        return $this->view->render('foundation::auth/register');
    }

    #[Post('/register')]
    #[Middleware(GuestMiddleware::class)]
    public function store(Request $request): Response
    {
        $validation = $this->validator->validate($request->all(), [
            'username' => 'required|min:3|max:50',
            'email' => 'required|email|max:255',
            'password' => 'required|min:8|max:255',
            'password_confirmation' => 'required|same:password',
        ]);

        if ($validation->fails()) {
            return new Response(
                body: json_encode(['errors' => $validation->errors()->all()]),
                statusCode: 422,
                headers: ['Content-Type' => 'application/json'],
            );
        }

        if ($this->userRepository->findByEmail($request->get('email')) !== null) {
            return new Response(
                body: json_encode(['errors' => ['Email already taken']]),
                statusCode: 422,
                headers: ['Content-Type' => 'application/json'],
            );
        }

        if ($this->userRepository->findByUsername($request->get('username')) !== null) {
            return new Response(
                body: json_encode(['errors' => ['Username already taken']]),
                statusCode: 422,
                headers: ['Content-Type' => 'application/json'],
            );
        }

        $user = new User();
        $user->username = $request->get('username');
        $user->email = $request->get('email');
        $user->password = $this->hashManager->make($request->get('password'));
        $this->userRepository->save($user);

        $this->authManager->login($user);

        return Response::redirect('/game');
    }
}
```

- [ ] **Step 4: Create register.latte**

Create `app/foundation/resources/views/auth/register.latte`. Extends `foundation::layout/auth`. Form with fields: username, email, password, password confirmation. Social login buttons (Discord, Google, GitHub) below the form. "Already have an account? Log in" link.

- [ ] **Step 5: Run tests to verify they pass**

```bash
./vendor/bin/pest app/foundation/tests/Feature/RegisterTest.php
```

Expected: All 4 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add app/foundation/tests/Feature/RegisterTest.php app/foundation/src/Controller/Auth/RegisterController.php app/foundation/resources/views/auth/register.latte
git commit -m "Add registration flow with validation and duplicate checks (TDD)"
```

---

## Task 13: Login/Logout Flow (TDD)

**Files:**
- Create: `app/foundation/tests/Feature/LoginTest.php`
- Create: `app/foundation/src/Controller/Auth/LoginController.php`
- Create: `app/foundation/resources/views/auth/login.latte`

- [ ] **Step 1: Write the failing test**

Create `app/foundation/tests/Feature/LoginTest.php`:

```php
<?php

declare(strict_types=1);

use App\Foundation\Entity\User;
use App\Foundation\Repository\UserRepository;
use Marko\Hashing\HashManager;

it('renders the login page', function () {
    $response = $this->get('/login');
    expect($response->getStatusCode())->toBe(200);
});

it('logs in with email and password', function () {
    $repo = $this->app->get(UserRepository::class);
    $hash = $this->app->get(HashManager::class);

    $user = new User();
    $user->username = 'testuser';
    $user->email = 'test@example.com';
    $user->password = $hash->make('SecurePass123!');
    $repo->save($user);

    $response = $this->post('/login', [
        'identifier' => 'test@example.com',
        'password' => 'SecurePass123!',
    ]);

    expect($response->getStatusCode())->toBe(302);
});

it('logs in with username and password', function () {
    $repo = $this->app->get(UserRepository::class);
    $hash = $this->app->get(HashManager::class);

    $user = new User();
    $user->username = 'testuser';
    $user->email = 'test@example.com';
    $user->password = $hash->make('SecurePass123!');
    $repo->save($user);

    $response = $this->post('/login', [
        'identifier' => 'testuser',
        'password' => 'SecurePass123!',
    ]);

    expect($response->getStatusCode())->toBe(302);
});

it('rejects invalid credentials', function () {
    $response = $this->post('/login', [
        'identifier' => 'nobody@example.com',
        'password' => 'wrong',
    ]);

    expect($response->getStatusCode())->toBe(401);
});

it('rejects banned users', function () {
    $repo = $this->app->get(UserRepository::class);
    $hash = $this->app->get(HashManager::class);

    $user = new User();
    $user->username = 'banned';
    $user->email = 'banned@example.com';
    $user->password = $hash->make('SecurePass123!');
    $user->bannedAt = new \DateTimeImmutable();
    $repo->save($user);

    $response = $this->post('/login', [
        'identifier' => 'banned@example.com',
        'password' => 'SecurePass123!',
    ]);

    expect($response->getStatusCode())->toBe(403);
});

it('logs out', function () {
    // Login first, then logout
    $response = $this->post('/logout');
    expect($response->getStatusCode())->toBe(302);
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/pest app/foundation/tests/Feature/LoginTest.php
```

Expected: FAIL — controller/route not found.

- [ ] **Step 3: Create LoginController**

Create `app/foundation/src/Controller/Auth/LoginController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Controller\Auth;

use App\Foundation\Repository\UserRepository;
use Marko\Authentication\AuthManager;
use Marko\Hashing\HashManager;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Attributes\Middleware;
use Marko\Authentication\Middleware\GuestMiddleware;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

class LoginController
{
    public function __construct(
        private readonly ViewInterface $view,
        private readonly UserRepository $userRepository,
        private readonly AuthManager $authManager,
        private readonly HashManager $hashManager,
    ) {}

    #[Get('/login')]
    #[Middleware(GuestMiddleware::class)]
    public function show(): Response
    {
        return $this->view->render('foundation::auth/login');
    }

    #[Post('/login')]
    #[Middleware(GuestMiddleware::class)]
    public function login(Request $request): Response
    {
        $identifier = $request->get('identifier', '');
        $password = $request->get('password', '');

        $user = $this->userRepository->findByEmailOrUsername($identifier);

        if ($user === null || !$this->hashManager->check($password, $user->getAuthPassword())) {
            return new Response('Invalid credentials', 401);
        }

        if ($user->isBanned()) {
            return new Response('Account is banned', 403);
        }

        $this->authManager->login($user);

        return Response::redirect('/game');
    }

    #[Post('/logout')]
    public function logout(): Response
    {
        $this->authManager->logout();

        return Response::redirect('/');
    }
}
```

- [ ] **Step 4: Create login.latte**

Create `app/foundation/resources/views/auth/login.latte`. Extends `foundation::layout/auth`. Form with: identifier (username or email) + password. Social login buttons. "Don't have an account? Register" link.

- [ ] **Step 5: Run tests to verify they pass**

```bash
./vendor/bin/pest app/foundation/tests/Feature/LoginTest.php
```

Expected: All 6 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add app/foundation/tests/Feature/LoginTest.php app/foundation/src/Controller/Auth/LoginController.php app/foundation/resources/views/auth/login.latte
git commit -m "Add login/logout flow with username or email support (TDD)"
```

---

## Task 14: Social OAuth Service (TDD)

**Files:**
- Create: `app/foundation/tests/Feature/SocialAuthTest.php`
- Create: `app/foundation/src/Service/SocialAuthService.php`
- Create: `app/foundation/src/Controller/Auth/SocialAuthController.php`
- Create: `app/foundation/resources/views/auth/verify-social.latte`

- [ ] **Step 1: Write the failing test**

Create `app/foundation/tests/Feature/SocialAuthTest.php`. Test the SocialAuthService logic:

- `handleCallback()` with new user (no existing account, no email match) → creates user + social account
- `handleCallback()` with existing social link → returns the linked user
- `handleCallback()` with email match → returns `'verify_password'` response requiring password confirmation
- `verifyAndLink()` with correct password → links social account
- `verifyAndLink()` with wrong password → rejects
- `linkToCurrentUser()` → adds social account to authenticated user
- `unlinkFromCurrentUser()` → removes social account (only if user has another login method)
- `unlinkFromCurrentUser()` → rejects when it's the only login method

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/pest app/foundation/tests/Feature/SocialAuthTest.php
```

- [ ] **Step 3: Implement SocialAuthService**

Create `app/foundation/src/Service/SocialAuthService.php`. The service handles:

1. `getAuthorizationUrl(string $provider)` — builds OAuth2 authorize URL with state parameter
2. `handleCallback(string $provider, string $code)` — exchanges code for token, fetches user profile, resolves to one of: login existing user, create new user, or require password verification
3. `verifyAndLink(int $userId, string $password, string $provider, array $socialProfile)` — verifies password, links social account
4. `linkToCurrentUser(int $userId, string $provider, array $socialProfile)` — links additional social
5. `unlinkFromCurrentUser(int $userId, int $socialAccountId)` — removes link if safe

Uses `Guzzle` or PHP's `file_get_contents` for HTTP calls to providers. Provider config from `config/social_auth.php`.

- [ ] **Step 4: Create SocialAuthController**

Create `app/foundation/src/Controller/Auth/SocialAuthController.php` with routes:

- `GET /auth/{provider}` — redirects to provider authorize URL
- `GET /auth/{provider}/callback` — handles OAuth callback
- `POST /auth/verify-link` — password verification for email-match linking
- `POST /auth/link/{provider}` — link social to current user (authenticated)
- `DELETE /auth/unlink/{socialAccountId}` — unlink social from current user

- [ ] **Step 5: Create verify-social.latte**

Create `app/foundation/resources/views/auth/verify-social.latte`. Extends auth layout. Shows: "An account with this email already exists. Enter your password to link your [Provider] account." Password input + submit button.

- [ ] **Step 6: Run tests to verify they pass**

```bash
./vendor/bin/pest app/foundation/tests/Feature/SocialAuthTest.php
```

Expected: All tests PASS.

- [ ] **Step 7: Commit**

```bash
git add app/foundation/tests/Feature/SocialAuthTest.php app/foundation/src/Service/SocialAuthService.php app/foundation/src/Controller/Auth/SocialAuthController.php app/foundation/resources/views/auth/verify-social.latte
git commit -m "Add social OAuth flow with password verification on email match (TDD)"
```

---

## Task 15: RoleMiddleware

**Files:**
- Create: `app/foundation/src/Middleware/RoleMiddleware.php`

- [ ] **Step 1: Create RoleMiddleware**

Create `app/foundation/src/Middleware/RoleMiddleware.php`:

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Middleware;

use Marko\Authentication\AuthManager;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use App\Foundation\Entity\User;

class RoleMiddleware implements MiddlewareInterface
{
    private const ROLE_HIERARCHY = [
        'player' => 0,
        'builder' => 1,
        'admin' => 2,
    ];

    public function __construct(
        private readonly AuthManager $authManager,
        private readonly string $requiredRole = 'player',
    ) {}

    public function handle(Request $request, callable $next): Response
    {
        if (!$this->authManager->check()) {
            return Response::redirect('/login');
        }

        $user = $this->authManager->user();

        if (!$user instanceof User) {
            return Response::redirect('/login');
        }

        $requiredLevel = self::ROLE_HIERARCHY[$this->requiredRole] ?? 0;
        $userLevel = self::ROLE_HIERARCHY[$user->role] ?? 0;

        if ($userLevel < $requiredLevel) {
            return new Response('Forbidden', 403);
        }

        return $next($request);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/foundation/src/Middleware/RoleMiddleware.php
git commit -m "Add RoleMiddleware with role hierarchy check"
```

---

## Task 16: Game Dashboard + SSE

**Files:**
- Create: `app/foundation/src/Controller/Game/DashboardController.php`
- Create: `app/foundation/src/Controller/Game/SseController.php`
- Create: `app/foundation/src/Controller/Game/CommandController.php`
- Create: `app/foundation/resources/views/game/dashboard.latte`
- Create: `public/js/sse.js`
- Create: `public/js/command-palette.js`

- [ ] **Step 1: Create game DashboardController**

Create `app/foundation/src/Controller/Game/DashboardController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Controller\Game;

use App\Foundation\Entity\User;
use App\Foundation\Service\PlayerService;
use Marko\Authentication\AuthManager;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

class DashboardController
{
    public function __construct(
        private readonly ViewInterface $view,
        private readonly AuthManager $authManager,
        private readonly PlayerService $playerService,
    ) {}

    #[Get('/game')]
    #[Middleware(AuthMiddleware::class)]
    public function index(): Response
    {
        $user = $this->authManager->user();
        $players = $this->playerService->getPlayersForUser($user->getAuthIdentifier());

        return $this->view->render('foundation::game/dashboard', [
            'user' => $user,
            'players' => $players,
        ]);
    }
}
```

- [ ] **Step 2: Create SseController**

Create `app/foundation/src/Controller/Game/SseController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Controller\Game;

use Marko\Authentication\AuthManager;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\PubSub\SubscriberInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Sse\SseStream;
use Marko\Sse\StreamingResponse;

class SseController
{
    public function __construct(
        private readonly AuthManager $authManager,
        private readonly SubscriberInterface $subscriber,
    ) {}

    #[Get('/game/stream')]
    #[Middleware(AuthMiddleware::class)]
    public function stream(): StreamingResponse
    {
        $userId = $this->authManager->id();

        $subscription = $this->subscriber->subscribe(
            "player.{$userId}",
            'global',
        );

        $stream = new SseStream(
            subscription: $subscription,
            timeout: 300,
        );

        return new StreamingResponse($stream);
    }
}
```

- [ ] **Step 3: Create CommandController**

Create `app/foundation/src/Controller/Game/CommandController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Controller\Game;

use App\Foundation\Entity\User;
use App\Foundation\Service\CommandRegistry;
use Marko\Authentication\AuthManager;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

class CommandController
{
    public function __construct(
        private readonly AuthManager $authManager,
        private readonly CommandRegistry $commandRegistry,
    ) {}

    #[Get('/game/commands')]
    #[Middleware(AuthMiddleware::class)]
    public function index(Request $request): Response
    {
        $user = $this->authManager->user();
        $query = $request->get('q', '');

        // Room contexts and player state will come from future sub-projects
        // Foundation provides the endpoint structure
        $roomContexts = [];
        $playerState = [];

        $commands = $query !== ''
            ? $this->commandRegistry->search($query, $roomContexts, $playerState, $user->role)
            : $this->commandRegistry->getAvailable($roomContexts, $playerState, $user->role);

        return new Response(
            body: json_encode($commands),
            headers: ['Content-Type' => 'application/json'],
        );
    }
}
```

- [ ] **Step 4: Create game dashboard template**

Create `app/foundation/resources/views/game/dashboard.latte`. Extends `foundation::layout/game`. Shows the character select screen (list of player's characters, create new character button). This is the landing page after login — before a character is selected, the game shell shows this instead of a room.

- [ ] **Step 5: Create sse.js**

Create `public/js/sse.js`:

```javascript
(function () {
  'use strict';

  const ShillaSSE = {
    source: null,

    connect(url) {
      if (this.source) {
        this.source.close();
      }

      this.source = new EventSource(url);

      this.source.onopen = () => {
        console.log('[SSE] Connected');
      };

      this.source.onerror = () => {
        if (this.source.readyState === EventSource.CLOSED) {
          console.log('[SSE] Connection closed');
        }
      };

      // Listen on all channels — messages arrive with channel as event name
      // Each message is JSON with a 'type' field
      this.source.addEventListener('global', (e) => {
        this.dispatch(JSON.parse(e.data));
      });

      // Player-specific channel
      this.source.addEventListener(this.playerChannel, (e) => {
        this.dispatch(JSON.parse(e.data));
      });
    },

    dispatch(data) {
      if (data.type) {
        document.dispatchEvent(
          new CustomEvent('sse:' + data.type, { detail: data })
        );
      }
    },

    disconnect() {
      if (this.source) {
        this.source.close();
        this.source = null;
      }
    },

    setPlayerChannel(channel) {
      this.playerChannel = channel;
    },
  };

  window.ShillaSSE = ShillaSSE;

  window.addEventListener('beforeunload', () => {
    ShillaSSE.disconnect();
  });
})();
```

- [ ] **Step 6: Create command-palette.js**

Create `public/js/command-palette.js`:

```javascript
(function () {
  'use strict';

  document.addEventListener('alpine:init', () => {
    Alpine.data('commandPalette', () => ({
      open: false,
      query: '',
      commands: [],
      filtered: [],
      selectedIndex: 0,
      commandsUrl: '/game/commands',

      init() {
        this.fetchCommands();

        document.addEventListener('keydown', (e) => {
          if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            this.toggle();
          }
          if (e.key === 'Escape' && this.open) {
            this.close();
          }
        });

        // Refresh commands when room changes
        document.addEventListener('sse:room_changed', () => {
          this.fetchCommands();
        });
      },

      toggle() {
        this.open = !this.open;
        if (this.open) {
          this.query = '';
          this.selectedIndex = 0;
          this.filter();
          this.$nextTick(() => this.$refs.searchInput?.focus());
        }
      },

      close() {
        this.open = false;
        this.query = '';
      },

      async fetchCommands() {
        try {
          const res = await fetch(this.commandsUrl);
          this.commands = await res.json();
          this.filter();
        } catch (e) {
          console.error('[CommandPalette] Failed to fetch commands', e);
        }
      },

      filter() {
        if (this.query === '') {
          this.filtered = this.commands;
        } else {
          const q = this.query.toLowerCase();
          this.filtered = this.commands.filter(
            (cmd) => cmd.label.toLowerCase().includes(q)
          );
        }
        this.selectedIndex = 0;
      },

      navigate(direction) {
        if (direction === 'up' && this.selectedIndex > 0) {
          this.selectedIndex--;
        } else if (direction === 'down' && this.selectedIndex < this.filtered.length - 1) {
          this.selectedIndex++;
        }
      },

      execute(command) {
        this.close();
        if (command && command.action) {
          window.location.href = command.action;
        }
      },

      executeSelected() {
        const cmd = this.filtered[this.selectedIndex];
        if (cmd) {
          this.execute(cmd);
        }
      },
    }));
  });
})();
```

- [ ] **Step 7: Commit**

```bash
git add app/foundation/src/Controller/Game/ app/foundation/resources/views/game/ public/js/sse.js public/js/command-palette.js
git commit -m "Add game dashboard, SSE stream controller, and command palette"
```

---

## Task 17: Admin Panel Setup

**Files:**
- Create: `app/foundation/src/AdminSection/UserSection.php`
- Create: `app/foundation/src/AdminSection/SystemSection.php`
- Create: `app/foundation/src/Widget/UserCountWidget.php`
- Create: `app/foundation/src/Widget/OnlineCountWidget.php`
- Create: `app/foundation/src/Widget/ActivityFeedWidget.php`

- [ ] **Step 1: Create UserSection**

Create `app/foundation/src/AdminSection/UserSection.php`:

```php
<?php

declare(strict_types=1);

namespace App\Foundation\AdminSection;

use Marko\Admin\Attributes\AdminSection;
use Marko\Admin\Attributes\AdminPermission;
use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\Contracts\MenuItemInterface;
use Marko\Admin\MenuItem;

#[AdminSection(id: 'users', label: 'Users', icon: 'users', sortOrder: 10)]
#[AdminPermission(id: 'users.view', label: 'View Users')]
#[AdminPermission(id: 'users.edit', label: 'Edit Users')]
#[AdminPermission(id: 'users.ban', label: 'Ban Users')]
class UserSection implements AdminSectionInterface
{
    public function getId(): string { return 'users'; }
    public function getLabel(): string { return 'Users'; }
    public function getIcon(): string { return 'users'; }
    public function getSortOrder(): int { return 10; }

    public function getMenuItems(): array
    {
        return [
            new MenuItem(
                id: 'all-users',
                label: 'All Users',
                url: '/admin/users',
                sortOrder: 10,
                permission: 'users.view',
            ),
            new MenuItem(
                id: 'roles',
                label: 'Roles & Permissions',
                url: '/admin/roles',
                sortOrder: 20,
                permission: 'users.edit',
            ),
        ];
    }
}
```

- [ ] **Step 2: Create SystemSection**

Create `app/foundation/src/AdminSection/SystemSection.php`:

```php
<?php

declare(strict_types=1);

namespace App\Foundation\AdminSection;

use Marko\Admin\Attributes\AdminSection;
use Marko\Admin\Attributes\AdminPermission;
use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\MenuItem;

#[AdminSection(id: 'system', label: 'System', icon: 'settings', sortOrder: 90)]
#[AdminPermission(id: 'system.config', label: 'Manage Configuration')]
#[AdminPermission(id: 'system.announcements', label: 'Manage Announcements')]
class SystemSection implements AdminSectionInterface
{
    public function getId(): string { return 'system'; }
    public function getLabel(): string { return 'System'; }
    public function getIcon(): string { return 'settings'; }
    public function getSortOrder(): int { return 90; }

    public function getMenuItems(): array
    {
        return [
            new MenuItem(
                id: 'config',
                label: 'Configuration',
                url: '/admin/config',
                sortOrder: 10,
                permission: 'system.config',
            ),
            new MenuItem(
                id: 'announcements',
                label: 'Announcements',
                url: '/admin/announcements',
                sortOrder: 20,
                permission: 'system.announcements',
            ),
        ];
    }
}
```

- [ ] **Step 3: Create dashboard widgets**

Create `app/foundation/src/Widget/UserCountWidget.php`:

```php
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

    public function getId(): string { return 'user-count'; }
    public function getLabel(): string { return 'Total Users'; }
    public function getSortOrder(): int { return 10; }

    public function render(): string
    {
        $total = $this->userRepository->countAll();
        $weekAgo = new \DateTimeImmutable('-7 days');
        $newThisWeek = $this->userRepository->countSince($weekAgo);

        return "<div class=\"text-2xl font-bold\">{$total}</div>"
             . "<div class=\"text-sm text-gray-400\">+{$newThisWeek} this week</div>";
    }
}
```

Create `app/foundation/src/Widget/OnlineCountWidget.php` and `ActivityFeedWidget.php` with the same pattern. OnlineCountWidget is a placeholder (returns 0) until SSE tracking is built. ActivityFeedWidget queries recent users by `created_at`.

- [ ] **Step 4: Commit**

```bash
git add app/foundation/src/AdminSection/ app/foundation/src/Widget/
git commit -m "Add admin sections (Users, System) and dashboard widgets"
```

---

## Task 18: Admin Controllers (TDD)

**Files:**
- Create: `app/foundation/tests/Feature/AdminUserTest.php`
- Create: `app/foundation/tests/Feature/AdminConfigTest.php`
- Create: `app/foundation/src/Controller/Admin/UserAdminController.php`
- Create: `app/foundation/src/Controller/Admin/ConfigAdminController.php`
- Create: `app/foundation/src/Controller/Admin/AnnouncementAdminController.php`
- Create: admin view templates

- [ ] **Step 1: Write user admin test**

Create `app/foundation/tests/Feature/AdminUserTest.php`. Test:
- `GET /admin/users` — lists users (requires admin auth)
- `GET /admin/users/{id}/edit` — shows edit form
- `POST /admin/users/{id}` — updates role, slot limit
- `POST /admin/users/{id}/ban` — bans user
- `POST /admin/users/{id}/unban` — unbans user
- Unauthenticated access redirects to admin login

- [ ] **Step 2: Write config admin test**

Create `app/foundation/tests/Feature/AdminConfigTest.php`. Test:
- `GET /admin/config` — shows config form
- `POST /admin/config` — saves config values
- `GET /admin/announcements` — lists announcements
- `POST /admin/announcements` — creates announcement
- `PUT /admin/announcements/{id}` — updates announcement
- `DELETE /admin/announcements/{id}` — deletes announcement

- [ ] **Step 3: Run tests to verify they fail**

```bash
./vendor/bin/pest app/foundation/tests/Feature/AdminUserTest.php app/foundation/tests/Feature/AdminConfigTest.php
```

- [ ] **Step 4: Implement UserAdminController**

Create `app/foundation/src/Controller/Admin/UserAdminController.php` with routes:
- `GET /admin/users` — search/filter users, render list
- `GET /admin/users/{id}/edit` — edit form
- `POST /admin/users/{id}` — save changes (role, slot limit)
- `POST /admin/users/{id}/ban` — set `bannedAt`
- `POST /admin/users/{id}/unban` — clear `bannedAt`

All routes use `#[Middleware(AdminAuthMiddleware::class)]` and `#[RequiresPermission]`.

- [ ] **Step 5: Implement ConfigAdminController and AnnouncementAdminController**

`ConfigAdminController`:
- `GET /admin/config` — render config form with current values from ConfigService
- `POST /admin/config` — save submitted values

`AnnouncementAdminController`:
- `GET /admin/announcements` — list all
- `GET /admin/announcements/create` — create form
- `POST /admin/announcements` — save new
- `GET /admin/announcements/{id}/edit` — edit form
- `PUT /admin/announcements/{id}` — update
- `DELETE /admin/announcements/{id}` — delete

When creating/activating an announcement, publish to the `global` PubSub channel so all connected players receive it via SSE.

- [ ] **Step 6: Create admin view templates**

Create Latte templates for all admin views:
- `admin/users/index.latte` — user table with search, role filter, edit/ban links
- `admin/users/edit.latte` — form with role dropdown, slot limit input, social accounts list, character list
- `admin/config/index.latte` — key-value form for system settings
- `admin/announcements/index.latte` — announcement list with status badges
- `admin/announcements/form.latte` — create/edit form with title, body, type, active, starts_at, ends_at

All templates override the admin panel's default theme with twilight colors.

- [ ] **Step 7: Run tests to verify they pass**

```bash
./vendor/bin/pest app/foundation/tests/Feature/AdminUserTest.php app/foundation/tests/Feature/AdminConfigTest.php
```

Expected: All tests PASS.

- [ ] **Step 8: Commit**

```bash
git add app/foundation/tests/Feature/Admin* app/foundation/src/Controller/Admin/ app/foundation/resources/views/admin/
git commit -m "Add admin controllers for user management, config, and announcements (TDD)"
```

---

## Task 19: Seeders

**Files:**
- Create: `app/foundation/database/seeders/FoundationSeeder.php`

- [ ] **Step 1: Create FoundationSeeder**

Create `app/foundation/database/seeders/FoundationSeeder.php`:

```php
<?php

declare(strict_types=1);

namespace App\Foundation\Database\Seeders;

use App\Foundation\Entity\SystemConfig;
use App\Foundation\Repository\SystemConfigRepository;
use Marko\AdminAuth\Entity\AdminUser;
use Marko\AdminAuth\Repository\AdminUserRepositoryInterface;
use Marko\AdminAuth\Repository\RoleRepositoryInterface;
use Marko\Hashing\HashManager;

class FoundationSeeder
{
    public function __construct(
        private readonly AdminUserRepositoryInterface $adminUserRepository,
        private readonly RoleRepositoryInterface $roleRepository,
        private readonly SystemConfigRepository $configRepository,
        private readonly HashManager $hashManager,
    ) {}

    public function run(): void
    {
        $this->seedAdminUser();
        $this->seedSystemConfig();
    }

    private function seedAdminUser(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@shilla.game');
        $existing = $this->adminUserRepository->findByEmail($email);

        if ($existing !== null) {
            return;
        }

        $admin = new AdminUser();
        $admin->email = $email;
        $admin->name = 'Admin';
        $admin->password = $this->hashManager->make(env('ADMIN_PASSWORD', 'admin'));
        $this->adminUserRepository->save($admin);

        // Assign super-admin role
        $superAdmin = $this->roleRepository->findBySlug('super-admin');
        if ($superAdmin !== null) {
            $this->adminUserRepository->syncRoles($admin->id, [$superAdmin->id]);
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
```

- [ ] **Step 2: Run seeders**

```bash
marko db:seed
```

- [ ] **Step 3: Verify admin login works**

```bash
marko up
# Visit http://localhost:8000/admin/login
# Login with admin@shilla.game / admin
# Should see admin dashboard
```

- [ ] **Step 4: Commit**

```bash
git add app/foundation/database/seeders/
git commit -m "Add foundation seeders: admin user and default system config"
```

---

## Task 20: Playwright Setup

**Files:**
- Create: `tests/e2e/playwright.config.ts`
- Create: `tests/e2e/example.spec.ts`

- [ ] **Step 1: Create Playwright config**

This file lives in the repo but Playwright is installed and run from Windows. Create `tests/e2e/playwright.config.ts`:

```typescript
import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: '.',
  timeout: 30000,
  use: {
    baseURL: 'http://localhost:8000',
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'chromium',
      use: {
        browserName: 'chromium',
      },
    },
  ],
});
```

- [ ] **Step 2: Create example test stub**

Create `tests/e2e/example.spec.ts`:

```typescript
import { test, expect } from '@playwright/test';

test('landing page loads', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveTitle(/Shilla/);
});

test('login page loads', async ({ page }) => {
  await page.goto('/login');
  await expect(page.locator('form')).toBeVisible();
});
```

- [ ] **Step 3: Add README for E2E setup**

Create `tests/e2e/README.md`:

```markdown
# E2E Tests

Run from Windows (not WSL2). Requires Playwright installed on Windows.

## Setup

```powershell
cd tests/e2e
npm init -y
npm install @playwright/test
npx playwright install chromium
```

## Run

```powershell
npx playwright test
```

Ensure the Marko dev server is running in WSL2 (`marko up`) before running tests.
```

- [ ] **Step 4: Commit**

```bash
git add tests/e2e/
git commit -m "Add Playwright E2E test infrastructure with example stubs"
```

---

## Task 21: Integration Verification

- [ ] **Step 1: Build CSS**

```bash
npm run build:css
```

- [ ] **Step 2: Run all migrations**

```bash
marko db:migrate
```

- [ ] **Step 3: Run seeders**

```bash
marko db:seed
```

- [ ] **Step 4: Run all tests**

```bash
./vendor/bin/pest
```

Expected: All unit and feature tests pass.

- [ ] **Step 5: Manual smoke test**

```bash
marko up
```

Verify:
1. `http://localhost:8000/` — landing page renders with twilight theme
2. `http://localhost:8000/about` — about page
3. `http://localhost:8000/faq` — FAQ page
4. `http://localhost:8000/register` — registration form with social buttons
5. Register a new user → redirected to `/game` → character select screen
6. Game shell layout visible: top bar, icon rail, compass dock, chat peek
7. `Cmd/Ctrl+K` opens command palette
8. `http://localhost:8000/admin/login` → admin login → dashboard with widgets

- [ ] **Step 6: Final commit**

```bash
git add -A
git commit -m "Foundation complete: all systems verified"
```

---

## Task 22: Session & Auth Wiring

**Added post-implementation:** The original plan missed the config files and UserProvider needed to make auth sessions actually persist through redirects.

**Files:**
- Create: `config/session.php`
- Create: `config/authentication.php`
- Create: `app/foundation/src/Provider/DatabaseUserProvider.php`
- Modify: `app/foundation/module.php` (bind UserProviderInterface)
- Modify: `app/foundation/resources/views/layout/game.latte` (fix Latte JS variable)

- [x] **Step 1: Create `config/session.php`**

Database session driver, 24h lifetime, `shilla_session` cookie name.

- [x] **Step 2: Create `config/authentication.php`**

Session guard as default, argon2id password driver.

- [x] **Step 3: Create `DatabaseUserProvider`**

Bridges `UserRepository` to `UserProviderInterface` — implements `retrieveById`, `retrieveByCredentials`, `validateCredentials`, `retrieveByRememberToken`, `updateRememberToken`.

- [x] **Step 4: Bind `UserProviderInterface` in `module.php`**

- [x] **Step 5: Fix Latte JS variable output**

Latte doesn't allow `{$var}` inside JavaScript strings. Use `data-` attributes on the `<script>` tag instead.

- [x] **Step 6: Verify end-to-end**

Register → redirect to `/game` → game shell renders with session authenticated.

---

## Task 23: Missing Config Files

**Added post-audit:** Package audit found several missing config files that packages need.

**Files:**
- Create: `config/view.php`
- Create: `config/admin.php`
- Create: `config/admin-auth.php`
- Create: `config/security.php`
- Create: `config/mail.php`
- Create: `config/pubsub.php`
- Create: `config/pubsub-pgsql.php`

- [ ] **Step 1: Create `config/view.php`**

```php
return [
    'cache_directory' => __DIR__ . '/../storage/views',
    'extension' => '.latte',
    'auto_refresh' => true,
    'strict_types' => true,
];
```

- [ ] **Step 2: Create `config/admin.php`**

```php
return [
    'route_prefix' => '/admin',
    'name' => 'Shilla Admin',
];
```

- [ ] **Step 3: Create `config/admin-auth.php`**

```php
return [
    'guard' => 'admin',
    'super_admin_role' => 'super-admin',
];
```

- [ ] **Step 4: Create `config/security.php`**

```php
return [
    'csrf' => ['session_key' => '_csrf_token'],
    'headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
    ],
];
```

- [ ] **Step 5: Create `config/mail.php`**

```php
return [
    'driver' => env('MAIL_DRIVER', 'smtp'),
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@shilla.org'),
        'name' => env('MAIL_FROM_NAME', 'Shilla'),
    ],
    'smtp' => [
        'host' => env('MAIL_HOST', 'localhost'),
        'port' => (int) env('MAIL_PORT', 1025),
    ],
];
```

- [ ] **Step 6: Create `config/pubsub.php` and `config/pubsub-pgsql.php`**

```php
// config/pubsub.php
return [
    'driver' => env('PUBSUB_DRIVER', 'pgsql'),
    'prefix' => env('PUBSUB_PREFIX', 'shilla_'),
];

// config/pubsub-pgsql.php
return [
    'host' => env('PUBSUB_PGSQL_HOST', '127.0.0.1'),
    'port' => (int) env('PUBSUB_PGSQL_PORT', 5432),
    'user' => env('PUBSUB_PGSQL_USER', 'shilla'),
    'password' => env('PUBSUB_PGSQL_PASSWORD', 'shilla'),
    'database' => env('PUBSUB_PGSQL_DATABASE', 'shilla'),
];
```

- [ ] **Step 7: Create `storage/views/` directory**

```bash
mkdir -p storage/views
```

- [ ] **Step 8: Verify dev server starts without config errors**

```bash
php -S localhost:8000 -t public/
# Visit http://localhost:8000/ — should render without errors
```

- [ ] **Step 9: Commit**

```bash
git add config/ storage/
git commit -m "Add missing config files for all Marko packages"
```

---

## Task 24: Character Creation Controller

**Added post-audit:** The character creation form in `dashboard.latte` submits to `POST /game/characters` which has no controller. The "Play" button links to `GET /game/play/{id}` which also doesn't exist.

**Files:**
- Create: `app/foundation/src/Controller/Game/CharacterController.php`
- Create: `app/foundation/tests/Feature/CharacterTest.php`

- [ ] **Step 1: Write failing test**

```php
// Tests that:
// - POST /game/characters creates a character and redirects to /game
// - POST /game/characters rejects duplicate names
// - POST /game/characters rejects when slot limit reached
// - POST /game/characters requires authentication
```

- [ ] **Step 2: Implement CharacterController**

```php
class CharacterController
{
    #[Post('/game/characters')]
    #[Middleware(AuthMiddleware::class)]
    public function store(Request $request): Response
    {
        // Validate name, call PlayerService.createPlayer(), redirect to /game
    }

    #[Get('/game/play/{id}')]
    #[Middleware(AuthMiddleware::class)]
    public function play(int $id): Response
    {
        // Verify player belongs to current user
        // Store active player ID in session
        // Redirect to game shell (future: room view)
        // For now: redirect to /game with player selected
    }
}
```

- [ ] **Step 3: Run tests**

- [ ] **Step 4: Test in browser — create character, see it in list**

- [ ] **Step 5: Commit**

---

## Task 25: Admin Panel Template Overrides

**Added post-audit:** The admin panel renders with default unstyled Marko templates. Need to override 5 templates with twilight theme.

**Override mechanism:** `admin-panel::layout/base` resolves to any module matching the `admin-panel` name suffix. We need a separate `app/admin-panel` module, OR we override via Marko Preferences on the controllers to render our own templates.

**Approach:** Create `app/admin-panel` module that overrides the vendor templates. This is a minimal module — just `composer.json`, `module.php`, and `resources/views/`.

**Files:**
- Create: `app/admin-panel/composer.json`
- Create: `app/admin-panel/module.php`
- Create: `app/admin-panel/resources/views/layout/base.latte`
- Create: `app/admin-panel/resources/views/partials/sidebar.latte`
- Create: `app/admin-panel/resources/views/partials/flash.latte`
- Create: `app/admin-panel/resources/views/dashboard/index.latte`
- Create: `app/admin-panel/resources/views/auth/login.latte`

- [ ] **Step 1: Create `app/admin-panel/composer.json`**

```json
{
    "name": "app/admin-panel",
    "type": "marko-module",
    "extra": { "marko": { "module": true } }
}
```

- [ ] **Step 2: Create twilight base layout**

Override `layout/base.latte` with:
- Twilight background (`#1a1a24` for admin, slightly darker)
- Sidebar left (200px, `#20202a` bg, `#2e2e3a` border-right)
- Main content area
- Include `/css/app.css`
- Include Inter font

- [ ] **Step 3: Create twilight sidebar**

Override `partials/sidebar.latte` with:
- SHILLA logo + "Admin Panel" subtitle
- Menu items grouped by section with section headers
- Active item highlighting (left border accent)
- Logged-in admin user info at bottom

The sidebar receives `$menuItems` from the admin-panel framework. These are the MenuItems from our AdminSections (UserSection, SystemSection).

- [ ] **Step 4: Create twilight dashboard**

Override `dashboard/index.latte` with:
- Stat cards grid (3 columns)
- Widget rendering — inject widgets via the controller or pass them through template variables
- Activity feed section
- Twilight card styling

- [ ] **Step 5: Create twilight login**

Override `auth/login.latte` with:
- Centered card layout matching our auth.latte style
- Twilight form inputs
- SHILLA branding

- [ ] **Step 6: Create twilight flash messages**

Override `partials/flash.latte` with twilight-styled alerts.

- [ ] **Step 7: Register module in root composer.json autoload**

- [ ] **Step 8: Rebuild CSS, verify admin panel renders with twilight theme**

- [ ] **Step 9: Commit**

---

## Task 26: OAuth Token Exchange Implementation

**Added post-audit:** `SocialAuthController::exchangeCodeForProfile()` is a stub returning null. Social login is completely non-functional.

**Files:**
- Modify: `app/foundation/src/Controller/Auth/SocialAuthController.php`
- Modify: `composer.json` (add `guzzlehttp/guzzle`)

- [ ] **Step 1: Install Guzzle**

```bash
composer require guzzlehttp/guzzle
```

- [ ] **Step 2: Implement `exchangeCodeForProfile()`**

Replace the stub with real HTTP calls:
1. POST to provider's token URL with code, client_id, client_secret, redirect_uri
2. Extract access_token from response
3. GET provider's user URL with the access_token
4. Parse response into profile array (id, email, name)
5. Each provider has a different response format — handle Discord, Google, GitHub separately

- [ ] **Step 3: Write test for token exchange**

Mock Guzzle client, verify correct URLs and parameters for each provider.

- [ ] **Step 4: Test with real provider** (optional — requires credentials)

- [ ] **Step 5: Commit**

---

## Task 27: E2E Tests for New Flows

**Added post-audit:** E2E tests need to cover character creation and admin panel.

**Files:**
- Modify: `tests/e2e/example.spec.ts`

- [ ] **Step 1: Add character creation tests**

```typescript
test('user can create a character', async ({ page }) => {
  // Login as seeded player
  // Fill character name, submit
  // Verify character appears in list
  // Verify "Play" button visible
});

test('character creation rejects duplicate name', async ({ page }) => {
  // Create character, then try same name again
  // Verify error message
});
```

- [ ] **Step 2: Add admin panel tests**

```typescript
test('admin can log in to admin panel', async ({ page }) => {
  // Navigate to /admin/login
  // Fill admin@shilla.org / password
  // Verify dashboard renders with twilight theme
});

test('admin can view user list', async ({ page }) => {
  // Login to admin, navigate to /admin/users
  // Verify user table renders
});
```

- [ ] **Step 3: Run full E2E suite, all passing**

- [ ] **Step 4: Commit**

---

## Task 28: Final Verification

- [ ] **Step 1: Run Pest tests — all 41+ passing**
- [ ] **Step 2: Run Playwright tests — all passing**
- [ ] **Step 3: Manual smoke test of every flow from the spec's exit criteria**
- [ ] **Step 4: Verify admin panel has twilight theme**
- [ ] **Step 5: Verify character creation works end-to-end**
- [ ] **Step 6: Push and update PR**
