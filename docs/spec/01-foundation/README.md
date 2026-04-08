# Sub-Project 01: Foundation

## Overview

Marko skeleton project, PostgreSQL database, authentication with social OAuth, role system, admin panel with user management and system config, game shell layout, public site, SSE infrastructure, command palette, design system, and test framework.

This is the base layer every other sub-project builds on. Nothing game-specific beyond the player entity and the game shell — game systems start in sub-project 02.

## Marko Packages

```
marko/skeleton                # Project scaffold
marko/database-pgsql          # PostgreSQL driver
marko/authentication          # Session + token auth guards
marko/authentication-token    # API token support
marko/authorization           # Gates, policies, #[Can] attribute
marko/admin                   # Admin section contracts + registry
marko/admin-auth              # Admin roles, permissions, wildcard matching
marko/admin-panel             # Server-rendered admin UI (login, dashboard, sidebar)
marko/session-database        # PostgreSQL-backed sessions
marko/validation              # Input validation with rules
marko/security                # CSRF, security headers middleware
marko/rate-limiting           # Throttle login attempts, API rate limits
marko/hashing                 # Password hashing (bcrypt/argon2)
marko/sse                     # Server-Sent Events
marko/pubsub-pgsql            # PostgreSQL PubSub driver
marko/view-latte              # Latte templating
marko/env                     # Environment variables
marko/config                  # Configuration management
marko/mail-smtp               # Email for password recovery + verification
```

## Frontend Dependencies

```
tailwindcss + @tailwindcss/cli    # Styling (npm)
Alpine.js                         # Loaded via CDN or vendored into public/js/
```

No bundler. Tailwind CLI compiles CSS. Vanilla JS files in `public/js/` for SSE and command palette. Same pattern as MarkoTalk.

## Module Structure

Single module: `app/foundation/`

```
app/foundation/
  src/
    Controller/
      Auth/                   # LoginController, RegisterController, SocialAuthController
      Public/                 # LandingController, AboutController, FaqController
      Admin/                  # UserAdminController, ConfigAdminController, AnnouncementController
    Model/
      User.php                # AuthenticatableInterface + AuthorizableInterface
      Player.php              # Game entity linked to User
      SocialAccount.php       # OAuth provider links
      SystemConfig.php        # Key-value system settings
      Announcement.php        # Server-wide announcements
    Service/
      SocialAuthService.php   # OAuth flow for Discord/Google/GitHub
      ConfigService.php       # Read/write system config with defaults
      PlayerService.php       # Create player, slot management
    AdminSection/
      UserSection.php         # #[AdminSection] for user management
      SystemSection.php       # #[AdminSection] for system config
    Widget/
      UserCountWidget.php     # Dashboard widget
      OnlineCountWidget.php   # Dashboard widget
      ActivityFeedWidget.php  # Dashboard widget
    Observer/
      CreatePlayerOnRegister.php
    Middleware/
      RoleMiddleware.php
  config/
    authentication.php
    social_auth.php           # OAuth provider credentials
    game.php                  # Default character slots, etc.
  database/
    migrations/
    seeders/
  resources/
    views/
      layout/                 # game.latte, public.latte, auth.latte
      auth/                   # Login, register forms
      public/                 # Landing, about, FAQ
      admin/                  # User management, config, announcements (theme overrides)
  tests/
  composer.json
  module.php

public/
  js/
    app.js                    # Alpine.js init, toast handler
    sse.js                    # EventSource management, channel subscriptions
    command-palette.js        # Command palette logic
  css/                        # Compiled Tailwind output

tests/
  e2e/                        # Playwright config + test stubs (run from Windows)
```

## Database Schema

### users

| Column | Type | Notes |
|--------|------|-------|
| id | serial PK | |
| username | varchar(50) unique | Display name, login identifier |
| email | varchar(255) unique | Required on all accounts |
| email_verified_at | timestamp null | Null until verified |
| password | varchar(255) null | Null for social-only accounts |
| remember_token | varchar(100) null | |
| created_at | timestamp | |
| updated_at | timestamp | |

### players

| Column | Type | Notes |
|--------|------|-------|
| id | serial PK | |
| user_id | FK -> users | |
| name | varchar(50) unique | Character name |
| slot_number | smallint | Which slot (1-based) |
| created_at | timestamp | |
| updated_at | timestamp | |

Intentionally thin. Sub-project 03 (Character System) adds race, class, stats, level via its own migration.

### social_accounts

| Column | Type | Notes |
|--------|------|-------|
| id | serial PK | |
| user_id | FK -> users | |
| provider | varchar(20) | discord, google, github |
| provider_id | varchar(255) | External user ID |
| provider_email | varchar(255) null | Email from provider |
| access_token | text null | Encrypted |
| refresh_token | text null | Encrypted |
| created_at | timestamp | |
| | unique(provider, provider_id) | One link per provider account |

### system_config

| Column | Type | Notes |
|--------|------|-------|
| key | varchar(100) PK | e.g. `character_slot_default` |
| value | text | JSON-encoded |
| updated_at | timestamp | |

### announcements

| Column | Type | Notes |
|--------|------|-------|
| id | serial PK | |
| title | varchar(255) | |
| body | text | Markdown or plain text |
| type | varchar(20) | info, warning, maintenance |
| active | boolean | |
| starts_at | timestamp null | Scheduled display |
| ends_at | timestamp null | Auto-expire |
| created_at | timestamp | |

Admin tables (`admin_users`, `roles`, `permissions`, `role_permissions`, `admin_user_roles`) and `sessions` table come from `marko/admin-auth` and `marko/session-database` migrations.

## Authentication

### Standard Registration

1. User submits username + email + password
2. Validate: unique username, unique email, password strength
3. Create `users` row
4. Send verification email
5. Log in, redirect to character select

### Social Login (Discord, Google, GitHub)

1. User clicks "Sign in with [Provider]"
2. Redirect to provider's OAuth2 authorize URL
3. Provider redirects back with auth code
4. Exchange code for access token, fetch user profile (email, provider ID)
5. Lookup `social_accounts` by provider + provider_id:
   - **Found:** log in as the linked user
   - **Not found, email matches existing user:** redirect to verification page — user must enter their existing account password to confirm ownership before linking. If the existing account has no password (social-only), they must log in via their other social provider and link from account settings instead.
   - **Not found, no match:** create new user (username from provider display name, email from provider), create social account link, log in
6. Redirect to character select

### Linking Additional Socials

From account settings. Same OAuth flow but attaches `social_accounts` row to current user. Unlinking removes the row but requires at least one login method (password or social) to remain.

### Password Recovery

Email-based reset link flow. Available for all accounts since email is required.

### Rate Limiting

- Login attempts throttled per IP via `marko/rate-limiting`
- Social OAuth callback throttled to prevent abuse

## Character Slots

- Every user has a configurable character slot limit
- **Admin-configurable global default** stored in `system_config` (key: `character_slot_default`, default: 50)
- **Per-user override** — admin can set a higher or lower limit for individual users
- **User-configurable** — players can set their own limit lower than their maximum (self-imposed)
- Players table tracks `slot_number` per character

## Roles

Three roles for the player-facing side:

| Role | Access |
|------|--------|
| player | Game shell, public site |
| builder | Player access + builder tools (sub-project 12) |
| admin | Full access including admin panel |

Admin panel uses `marko/admin-auth` roles and permissions separately. The player-facing role is a column or relation on the `users` table. Admin panel access is controlled by `admin_users` + `roles` from the admin-auth package.

## Game Shell Layout

Modern layout with progressive disclosure. The game shell is the Latte template (`layout/game.latte`) that every game sub-project renders into.

### Structure

- **Top bar** (fixed) — logo, player name + level, stat pills (colored dots with values for HP/MP/stamina), gold, settings icon
- **Main canvas** (fills remaining height) — room content. Room header with zone breadcrumb + room name. Room image area. Context-dependent interaction panel below (forge, shop, combat — whatever the room provides)
- **Compass dock** (bottom of main canvas) — 3x3 directional grid centered, available exits highlighted in accent color, unavailable dimmed
- **Icon rail** (right edge, vertical) — icons for equipment, inventory, skills, quests, guide, chat. Click to slide out a panel over the main canvas
- **Chat peek** (bottom bar) — single line showing latest message + inline input. Click or hotkey to expand into full chat panel

### Behaviors

- One slide-out panel open at a time — clicking another icon closes the current
- Hotkeys for each panel (E, I, S, Q, etc.)
- ESC closes any open panel
- Chat expands upward from the peek bar
- Minimap floats in room header corner — small by default, click to expand as modal
- Room content is a Latte `{block content}` that each sub-project fills

### Command Palette

`Cmd/Ctrl+K` opens a fuzzy search command palette (Alpine.js component).

Available commands are filtered by:

```
always-available (inventory, pocket portal, settings, navigation, logout)
+ current room contexts (shop, forge, bank, combat — based on what's in the room)
+ current player state (in combat? in party? in guild?)
+ role-gated (builder/admin commands)
```

API endpoint `/game/commands` returns filtered commands for the current player context. Client caches the list and re-fetches when the player moves rooms or state changes (triggered by SSE event).

Each sub-project registers its own commands with the command registry. Foundation provides the registry service, the API endpoint, the Alpine.js palette component, and the `Cmd/Ctrl+K` binding.

### Latte Template Hierarchy

```
layout/game.latte        # Game shell — top bar, canvas, icon rail, chat peek, command palette
layout/public.latte      # Public site — header, nav, content, footer
layout/auth.latte        # Login/register — centered card on twilight background
```

## SSE & Real-Time Infrastructure

Foundation establishes the real-time plumbing. Game events come from each sub-project.

### Server Side

- `marko/pubsub-pgsql` configured with PostgreSQL
- Base `SseController` — player connects on login via `/game/stream`
- Player subscribes to personal channel: `player.{id}`
- Future sub-projects add channel subscriptions (room, zone, party, guild)
- Events are JSON with a `type` field and optional pre-rendered `html` field

### Client Side (`public/js/sse.js`)

- Opens `EventSource` to `/game/stream` on login
- Auto-reconnects (native EventSource behavior)
- Dispatches received events as custom DOM events for Alpine.js components
- Pattern: `document.dispatchEvent(new CustomEvent('sse:announcement', { detail: data }))`

### Foundation Events

Only two event types in Foundation:

| Event | Scope | Purpose |
|-------|-------|---------|
| `announcement` | All connected players | Server-wide announcement |
| `toast` | Single player | Personal notification (success/error/info) |

## Admin Panel

Built on `marko/admin-panel`. Foundation adds two sections and three dashboard widgets.

### What marko/admin-panel provides

- Login/logout flow with admin guard
- Permission-filtered sidebar navigation
- Dashboard with widget slots
- Section registry (modules self-register)
- Template overrides via module priority

### Foundation Admin Sections

**Users Section**
- List all users with search and role filter
- User detail: username, email, linked social accounts, character list, slot limit
- Edit: change role, adjust character slot limit
- Ban/unban users

**System Section**
- Configuration: key-value settings UI (character slot default, maintenance mode)
- Announcements: create/edit/deactivate server-wide announcements with type, scheduling, and expiry

### Dashboard Widgets

- **User count** — total users + new this week
- **Online count** — currently connected players + today's peak
- **Activity feed** — recent registrations, character creations, failed logins

### Theme

Admin panel templates overridden with twilight theme to match the game's visual identity.

## Public Site Pages

All use `layout/public.latte`. Static content + forms, no Alpine.js needed.

| Path | Page | Content |
|------|------|---------|
| `/` | Landing | Hero, key features, screenshot area, player count, register/login CTA |
| `/about` | About / Lore | World lore introduction, what the game is |
| `/faq` | FAQ / Guide | Common questions, getting started |
| `/login` | Login | Username/email + password form, social login buttons |
| `/register` | Register | Username + email + password form, social login buttons |

## Design System

A `DESIGN.md` file at the project root following the [awesome-design-md](https://github.com/VoltAgent/awesome-design-md) format. Defines the visual contract for the entire application.

### Theme: Twilight

Purple-gray foundation with steel-blue accents. Moody, enchanted atmosphere.

**Key colors (to be refined in DESIGN.md):**

| Role | Color | Usage |
|------|-------|-------|
| Background | `#20202a` | Page/app background |
| Surface | `#282834` | Cards, panels |
| Surface elevated | `#2e2e3a` | Hover states, raised elements |
| Border | `#3a3a4a` | Panel borders, dividers |
| Text primary | `#c0c4d8` | Headings, important text |
| Text secondary | `#7878a0` | Body text, descriptions |
| Text muted | `#5a5a70` | Labels, metadata |
| Accent | `#5a6aaa` | Interactive elements, links, active states |
| Accent hover | `#7e8ec0` | Hover on interactive elements |
| Health | `#e05050` / `#c04040` | HP bar, damage |
| Mana | `#5080e0` / `#6a6ab8` | MP bar, arcane |
| Stamina/Success | `#50c070` / `#4a9e6a` | Fatigue bar, success states |
| Gold | `#c0a050` | Currency, rewards |
| Danger | `#aa6a6a` | Enemies, ban actions, errors |
| Player name | `#6aaa80` | Player names in chat/rooms |
| NPC name | `#7e8ec0` | NPC names |
| System message | `#aa8a4a` | System/event messages |

### DESIGN.md Sections

Following the awesome-design-md structure:

1. Visual Theme & Atmosphere
2. Color Palette & Roles
3. Typography Rules
4. Component Stylings (buttons, inputs, cards, stat pills, panels, compass)
5. Layout Principles
6. Depth & Elevation
7. Do's and Don'ts
8. Responsive Behavior
9. Agent Prompt Guide

The full DESIGN.md is written as part of Foundation implementation.

## Testing

### Unit + Feature Tests (Pest PHP)

Written and run in Foundation.

- **Unit:** ConfigService (defaults, overrides), PlayerService (slot limits, creation), command registry (filtering by context/role)
- **Feature:** Registration flow (happy path, duplicate email, duplicate username), login flow, social OAuth callback (including password verification on email-match), password recovery, admin user management CRUD, admin config CRUD, role/permission checks on protected routes

### Browser/E2E Tests (Playwright)

Installed on Windows (not WSL2). Connects to the Marko dev server running in WSL2 via localhost.

- Playwright config and tests in `tests/e2e/` at project root
- Run from Windows terminal: `npx playwright test`
- Infrastructure only in Foundation — config, directory structure, example test stub
- Real E2E test cases added by later sub-projects

## Seeders

- Default admin user (configurable credentials via `.env`)
- Default system config values (character_slot_default: 50)
- Default roles (player, builder, admin)

## Exit Criteria

Foundation is complete when:

- A user can register with username + email + password
- A user can register/login via Discord, Google, or GitHub
- Social linking requires password verification when email matches an existing account
- A logged-in user sees the game shell layout (top bar, main canvas, icon rail, compass dock, chat peek)
- `Cmd/Ctrl+K` opens the command palette with always-available commands
- The SSE connection is established on login and delivers announcement/toast events
- An admin can log into the admin panel, view/edit users, manage system config, create announcements
- Public site pages render (landing, about, FAQ)
- Character slots are configurable (global default + per-user override)
- Pest tests pass for auth flows and admin CRUD
- Playwright is installed on Windows with config pointing at the dev server
- DESIGN.md is written with the twilight theme specification
- Tailwind CSS compiles, Alpine.js initializes, the game shell renders correctly
