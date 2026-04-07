# Shilla — Master Specification

## Documentation Standards

### Audiences

All documentation is written as clean, portable markdown that serves two readers:

- **Claude** — reads raw markdown via the `Read` tool
- **Human** — reads rendered markdown via markdownbin (details TBD)

No GitHub-specific rendering features. No inline HTML. Structure through headings, links, lists, code blocks, and tables.

### File Structure

```
docs/
  SPEC.md                          # This file — the map
  spec/
    01-foundation/
      README.md                    # Sub-project spec
      schema.md, api.md, ...       # Detail files, added when needed
    02-world-engine/
      README.md
      ...
```

- `SPEC.md` is the top-level overview: decisions, dependency graph, one-paragraph summaries linking to sub-specs. Readable in under a minute.
- Each sub-project gets a numbered directory. `README.md` is the main spec. Detail files (schema, API, UI) are added when that sub-project is specced — not before.
- Cross-references use relative links: `see [room exits](spec/02-world-engine/schema.md#room_exits)`

### Adding and Reordering Sub-Projects

- **New sub-projects** are inserted at their logical position in the dependency graph. Existing directories are renumbered to maintain order. `SPEC.md` and cross-reference links are updated in the same commit.
- **New files within an existing sub-project** are simply added. No renumbering.
- **Renumbering is always a standalone commit** with no functional changes mixed in.

### Writing Style

- Lead with what, not why-we-decided-what. Rationale goes in a "Rationale" section or inline where brief.
- Keep `SPEC.md` summaries to one paragraph per sub-project. Detail lives in the sub-project's `README.md`.
- Prefer tables for structured comparisons. Prefer lists for sequences and enumerations.
- No empty scaffolding. Don't create files until there's content to put in them.

---

## Overview

Shilla is a high-fantasy MUD played in the browser. Rooms, creatures, stats, and equipment are rendered as HTML — not text. The only text fields are chat, shops, and similar interactions. Players move room-to-room like a classic MUD, but the experience is visual.

Rebuild of [necrogami/mud](https://github.com/necrogami/mud) (Laravel 11 proof-of-concept). Fresh start with a better-suited framework and complete design.

## Tech Stack

| Layer | Choice | Rationale |
|-------|--------|-----------|
| Backend | Marko PHP 8.5+ | Modular architecture, plugin/interceptor system, SSE + PubSub built in |
| Database | PostgreSQL | Data store + PubSub backbone via `marko/pubsub-pgsql`. One service |
| Templating | Latte (`marko/view-latte`) | Module-namespaced templates, strict types, caching |
| Interactivity | Alpine.js | Lightweight reactivity for UI components |
| Real-time | SSE (`marko/sse` + `marko/pubsub-pgsql`) | Server pushes events to browser. Auto-reconnect. No WebSockets |
| Styling | Tailwind CSS | Utility-first, fast iteration |
| Auth | `marko/authentication` + `marko/authentication-token` | Built-in framework auth |

### Frontend Architecture

Hybrid server-rendered + lightweight client. Latte renders pages. SSE streams game events (including pre-rendered HTML fragments). Alpine.js handles interactive UI state. Vanilla JS for SSE event handling and DOM swapping (following [MarkoTalk](https://github.com/marko-php/markotalk) patterns).

## Game Design

| Aspect | Decision |
|--------|----------|
| Setting | High fantasy — swords, sorcery, dungeons, classic races/classes |
| Combat | Turn-based — explicit turns, tactical, no tick engine |
| Multiplayer | Shared persistent world — see other players, group, trade, chat |
| PvP | Zone-based — safe zones + PvP regions. Duels anywhere by consent |
| World building | Builder/immortal roles + procedural generation |
| Housing | Personal pocket (portable), world housing (physical), guild housing (instanced + world + territory) |

## Sub-Projects

### Dependency Graph

```
1. Foundation
   └──> 2. World Engine
        └──> 3. Character System
             ├──> 4. NPC & Mob System
             │    └──> 5. Combat Engine
             ├──> 6. Social & Chat
             └──> 7. Shops & Economy
                  └──> 8. Quests
                       └──> 9. Crafting
        └──> 10. Housing (requires 2 + 3 + 7)
        └──> 11. Guilds, Territory & PvP (requires 10)
   └──> 12. Builder Tools (starts after 2, grows with each sub-project)
```

### Summaries

**[01 — Foundation](spec/01-foundation/README.md)**
Marko skeleton, PostgreSQL, auth, role system (player/builder/admin), base module structure, Tailwind + Alpine.js pipeline, base Latte layout with game shell.

**[02 — World Engine](spec/02-world-engine/README.md)**
Zones, rooms, room connections as a relationship table, navigation UI, room rendering (description/exits/players/items/NPCs), player position tracking, zone properties (PvP flag, level range).

**[03 — Character System](spec/03-character-system/README.md)**
Races, classes, stat modifiers, core stats (STR/DEX/CON/INT/WIS/CHA), derived stats (HP/MP/attack/defense), leveling, inventory, equipment slots, character sheet UI.

**[04 — NPC & Mob System](spec/04-npc-mob-system/README.md)**
Mob templates, spawn rules, AI behavior (aggressive/passive/patrol), loot tables, NPC definitions for non-combat roles.

**[05 — Combat Engine](spec/05-combat-engine/README.md)**
Turn-based flow, initiative, abilities/skills by class, damage calculation, status effects, death/respawn, combat UI, group combat.

**[06 — Social & Chat](spec/06-social-chat/README.md)**
Room chat, whispers, party/zone/global channels, emotes, SSE-driven delivery, chat UI, player presence.

**[07 — Shops & Economy](spec/07-shops-economy/README.md)**
Currency, NPC shops (buy/sell UI), player trading, item valuation, potential auction house.

**[08 — Quests](spec/08-quests/README.md)**
Quest definitions (kill/collect/deliver/explore), quest givers, quest log UI, progress tracking, rewards, chains, dialogue trees.

**[09 — Crafting](spec/09-crafting/README.md)**
Gathering nodes in rooms, recipes, crafting UI, profession progression, feeds into equipment and economy.

**[10 — Housing](spec/10-housing/README.md)**
Personal pocket (portable instanced storage/crafting space), world housing (physical buildings in towns, decorating), guild housing (see sub-project 11).

**[11 — Guilds, Territory & PvP](spec/11-guilds-territory-pvp/README.md)**
Guild management, guild housing (hall/world presence/territory control), territory capture with PvP implications, zone-based PvP, duels, arenas, leaderboards.

**[12 — Builder Tools](spec/12-builder-tools/README.md)**
Builder/immortal permissions, admin panel CRUD, in-game building, room/mob/item editors, procedural generation, world validation.

## Architecture

### Marko Modules

```
app/
  foundation/       # Auth, roles, base layout, shared services
  world/            # Zones, rooms, navigation, movement
  character/        # Races, classes, stats, inventory, equipment
  mob/              # Mob definitions, spawning, AI, loot
  combat/           # Combat engine, abilities, damage, status effects
  chat/             # Chat channels, messages, SSE streaming
  shop/             # NPC shops, currency, trading
  quest/            # Quest definitions, tracking, dialogue
  crafting/         # Gathering, recipes, professions
  housing/          # Personal pocket, world housing, furniture, decoration
  guild/            # Guilds, territory control, guild housing, PvP, arenas
  admin/            # Builder tools, admin panel, procedural generation
```

### Real-Time

Events published to PostgreSQL PubSub channels scoped by context. SSE endpoints stream to each connected player.

Channels: `room.{id}`, `player.{id}`, `party.{id}`, `zone.{id}`, `guild.{id}`, `global`

Player room changes trigger SSE stream reconnection or server-side channel subscription management.

### Improvements Over Previous Version

- Relational model for room connections (not JSON)
- Surrogate primary keys (not composite)
- Service layer for game logic (not UI components)
- Module-per-feature architecture
- SSE + PubSub real-time (not Livewire)
- Role-based access control from day one
- Test coverage alongside each sub-project
