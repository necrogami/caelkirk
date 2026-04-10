# Deferred Items

Items identified during review that belong to later sub-projects, not Foundation.

## `/game/play/{id}` — Character Play Route

**Found in:** Foundation review — `dashboard.latte` links to `/game/play/{id}` but no controller exists.

**Why it's not Foundation:** The Foundation spec says the game shell is a Latte layout (`layout/game.latte`) with `{block content}` that "each sub-project fills." The play route is the entry point to room rendering, which is World Engine (sub-project 02). Foundation provides the character select lobby and the shell template — not the gameplay.

**Belongs to:** Sub-Project 02 — World Engine

**When needed:** When rooms, zones, and movement exist. The play route needs to load a player's current room and render it into the game shell.

---

## RoleMiddleware Not Applied to Routes

**Found in:** Foundation review — `RoleMiddleware` exists and works, but no routes use it.

**Why it's not Foundation:** Admin panel has its own auth via `marko/admin-auth`. Player-facing role gating (builder tools, admin shortcuts in-game) is meaningless until those features exist in later sub-projects.

**Belongs to:** Sub-Project 12 — Builder Tools (builder role), and any sub-project adding role-gated game features.

**When needed:** When builder or admin-specific in-game features are implemented. The middleware is ready — just needs `#[Middleware(RoleMiddleware::class)]` on the relevant routes.
