# Shilla — Browser-Based MUD Master Design

## Overview

Shilla is a high-fantasy MUD played in the browser. Unlike traditional text MUDs, rooms, creatures, stats, and equipment are rendered as HTML. The only text fields are for chat, shops, and similar interactions. Players move room-to-room like a classic MUD, but the experience is visual.

This is a rebuild of the [necrogami/mud](https://github.com/necrogami/mud) project, which was an early proof-of-concept built on Laravel 11 + Livewire. That version had rooms, zones, navigation with a visual compass rose, and basic admin tools, but no gameplay systems. This rebuild starts fresh with a better-suited framework and a complete design.

## Decisions

### Tech Stack

| Layer | Choice | Rationale |
|-------|--------|-----------|
| **Backend framework** | Marko PHP 8.5+ | Modular architecture, plugin/interceptor system ideal for game mechanics, SSE + PubSub built in |
| **Database** | PostgreSQL | Data store + PubSub backbone via `marko/pubsub-pgsql`. One service, zero extra infrastructure |
| **Templating** | Latte (via `marko/view-latte`) | Module-namespaced templates, strict types, automatic caching |
| **Frontend interactivity** | Alpine.js | Lightweight reactivity for UI components (modals, dropdowns, combat UI, inventory) |
| **Real-time** | SSE via `marko/sse` + `marko/pubsub-pgsql` | Server pushes game events to browser. Browser reconnects automatically. No WebSocket infrastructure needed |
| **Styling** | Tailwind CSS | Utility-first, fast iteration, same as MarkoTalk reference app |
| **Auth** | `marko/authentication` + `marko/authentication-token` | Built-in Marko auth with token support |

### Frontend Architecture (Hybrid)

- **Latte templates** render the page shell, room views, character sheets, inventory, shop UIs
- **SSE** streams real-time game events (combat actions, chat messages, player enter/leave, NPC actions, loot drops)
- **Alpine.js** handles client-side interactivity — toggling panels, managing combat UI state, updating HP/MP bars, chat input
- **Vanilla JS** for SSE event handling and DOM fragment swapping (following MarkoTalk patterns)
- Server sends pre-rendered HTML fragments in SSE events where appropriate (e.g., chat messages, room descriptions on movement)

### Game Design

| Aspect | Decision |
|--------|----------|
| **Setting** | High fantasy — swords, sorcery, dungeons, dragons, classic races and classes |
| **Combat** | Turn-based. Player and enemy take explicit turns. Tactical, no tick engine required |
| **Multiplayer** | Shared persistent world. Players see each other in rooms, can group, trade, chat |
| **PvP** | Zone-based. Most of the world is safe (no PvP). Designated regions/zones are PvP-enabled. Duels available anywhere by mutual consent |
| **World building** | Builder/immortal role system — trusted players get permissions to create content. Procedural generation for some areas (random dungeons, wilderness) |
| **Housing** | Three-tier system: personal pocket dimension (portable storage/crafting space), world-integrated housing (physical buildings in towns/neighborhoods), and guild housing (instanced halls + world presence + territory control) |
| **Scope** | Full traditional MUD: classes, races, stats, levels, equipment, mobs, quests, crafting, guilds/clans, shops, economy, player/guild housing |

## Sub-Project Decomposition

The project is too large for a single implementation cycle. It is decomposed into 12 sub-projects, each building on the previous. Each sub-project gets its own spec, plan, and implementation cycle.

### Build Order

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
        └──> 10. Housing (requires World Engine + Character System + Shops/Economy)
        └──> 11. Guilds, Territory & PvP (requires Housing for territory mechanics)
   └──> 12. Builder Tools (can start after World Engine, grows with each sub-project)
```

### Sub-Project Summaries

#### 1. Foundation
Marko skeleton project setup, PostgreSQL database, authentication (register/login/logout), role system (player, builder, admin), base module structure, dev server configuration, Tailwind + Alpine.js build pipeline, base Latte layout template with game shell (sidebar, main content area, chat panel).

#### 2. World Engine
Zone and room models with proper foreign keys (not JSON blobs). Room-to-room connections as a first-class relationship table. Navigation UI (visual compass or directional buttons). Room rendering — name, description, exits, players present, items on ground, NPCs visible. Player position tracking and movement. Zone properties (PvP flag, level range, theme).

#### 3. Character System
Races (human, elf, dwarf, orc, etc.) and classes (warrior, mage, rogue, cleric, etc.) with stat modifiers. Core stats (strength, dexterity, constitution, intelligence, wisdom, charisma). Derived stats (HP, MP, attack, defense, speed). Leveling and experience. Inventory system — items, equipment slots, weight/capacity. Character sheet UI.

#### 4. NPC & Mob System
Mob definitions (templates for creature types). Spawn rules — which mobs appear in which rooms, respawn timers, density limits. Basic mob AI/behavior (aggressive, passive, patrol). Loot tables — what drops when a mob dies, drop rates. NPC definitions for non-combat characters (quest givers, shopkeepers, trainers).

#### 5. Combat Engine
Turn-based combat flow — initiative, action selection, resolution, effects. Player abilities/skills tied to class. Damage calculation with stats, equipment, and buffs. Status effects (poison, stun, buff, debuff). Death and respawn mechanics. Combat UI — turn indicator, action menu, HP/MP bars, combat log. Group combat — party members fighting together.

#### 6. Social & Chat
Room-local chat (everyone in the room sees it). Whisper/tell (private messages between players). Party chat. Global/zone channels. Emotes and social commands. SSE-driven real-time message delivery. Chat UI panel. Player presence — see who's online, who's in your room.

#### 7. Shops & Economy
Currency system (gold, or tiered coins). NPC shop interface — buy/sell with price lists rendered as HTML. Player-to-player trading (trade window UI). Item valuation. Potentially an auction house or market board for asynchronous trading.

#### 8. Quests
Quest definitions — objectives (kill X, collect Y, deliver Z, explore location). Quest giver NPCs. Quest log UI. Progress tracking. Rewards (experience, gold, items). Quest chains and prerequisites. Dialogue trees for quest NPCs.

#### 9. Crafting
Gathering skills/nodes (mining, herbalism, etc.) tied to rooms. Recipe system — ingredients + skill level = output. Crafting UI. Profession/skill progression. Crafted items feeding into the equipment and economy systems.

#### 10. Housing
Three-tier housing system:

**Personal Pocket** — a portable instanced space every player carries. Always accessible regardless of location. Contains personal storage vault, crafting bench, and rest area. Functions like a "bag of holding" that's an actual room. Not tied to any world location.

**World Housing** — physical buildings in towns and neighborhood zones. Players buy or claim a plot, and the house exists as real rooms in the game world. Other players can see the building, knock on the door, or be invited in. Can range from a single room to a multi-room estate. Decorating and furniture.

**Guild Housing** — see sub-project 11. Guild housing is part of the guild system since it ties into territory control.

#### 11. Guilds, Territory & PvP
Guild/clan creation and management. Guild chat channel. Guild roster and ranks.

**Guild Housing** — three tiers that guilds can progress through:
- *Guild Hall* — instanced functional space with shared vault, armory, meeting hall, crafting stations. Available to any guild.
- *World Presence* — a physical guild building in a town, functioning like player housing but larger and shared. Requires wealth/reputation.
- *Territory Control* — guilds can capture or claim zones/fortresses in the world. Claimed territory becomes guild-controlled with PvP implications for defense. Territory ties into the PvP zone system — guild-held territory IS PvP-enabled territory.

**PvP Systems** — zone-based PvP mechanics, flagging, combat rules in PvP/territory zones. Duel system (challenge and accept, available anywhere). Arena system for structured PvP. Leaderboards.

#### 12. Builder Tools
Builder/immortal permission tier. Admin panel for zone/room/mob/item/quest CRUD. In-game building commands for builders. Room editor with live preview. Mob/item template editor. Procedural generation tools — dungeon generators, wilderness filling. World validation tools (orphaned rooms, broken links, unreachable areas).

## Architecture Notes

### Module Structure (Marko)

Each sub-project maps roughly to one or more Marko modules:

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
  guild/            # Guilds, territory control, guild housing, PvP zones, duels, arenas
  admin/            # Builder tools, admin panel, procedural generation
```

### Real-Time Architecture

- Game events are published to PostgreSQL PubSub channels scoped by context (room, zone, party, player)
- SSE endpoints stream events to each connected player
- Channel examples: `room.{id}` (room activity), `player.{id}` (personal events), `party.{id}` (group events), `zone.{id}` (zone-wide announcements), `guild.{id}` (guild events/territory alerts), `global` (server-wide)
- When a player moves rooms, the client reconnects to the new room's SSE stream (or the server manages channel subscriptions)

### Key Improvements Over Previous Version

- Proper relational model for room connections (not JSON blobs)
- Surrogate primary keys on rooms (not composite keys)
- Service layer for game logic (not tangled in UI components)
- Module-per-feature architecture with clean boundaries
- Real-time via SSE + PubSub (not Livewire polling)
- Role-based access control from the start
- Test coverage built alongside each sub-project

## Next Steps

When resuming work on this project, begin by brainstorming and speccing **Sub-Project 1: Foundation** through the full design flow (spec, plan, implementation). Each subsequent sub-project follows the same cycle.
