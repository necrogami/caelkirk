# Sub-Project 02: World Engine

## Overview

Zones, rooms, room connections, navigation, room rendering, player position tracking, minimap, and fog of war. The World Engine provides the spatial infrastructure for the entire game. Every future sub-project builds on it — mobs spawn in rooms, combat happens in rooms, shops exist in rooms, quests send players to rooms.

This is a browser RPG, not a text MUD. There are no typed commands. Navigation is through clickable compass buttons. Rooms are rendered as structured HTML with images, descriptions, and interactive elements — not scrolling text.

## Zone Hierarchy

Zones use a four-tier hierarchy: Realm, Province, Region, and Area.

| Tier | Purpose | Example | Rooms? |
|------|---------|---------|--------|
| Realm | Continent-scale landmass | Eldara, The Shattered Isles | No |
| Province | Kingdom or major political region | The Thornmark, Silverhold Dominion | No |
| Region | Coherent landscape with shared properties | Darkwood Forest, The Ashflats | No |
| Area | Named locale containing rooms | Spider Caves, Thornwall Village | Yes |

Only areas contain rooms. Realms, provinces, and regions are organizational containers that group their children and carry inheritable properties.

A single `zones` table stores all tiers with a `parent_zone_id` self-referential FK and a `zone_type` enum (`realm`, `province`, `region`, `area`). Depth is enforced by type — realms have no parent, provinces parent to realms, regions parent to provinces, areas parent to regions.

### Property Inheritance

Zone properties cascade from parent to child. A child zone inherits its parent's PvP flag, level range, safe zone status, and atmosphere unless it explicitly overrides them. This means setting a region to PvP-enabled makes all its areas PvP-enabled by default, but a specific area (a sanctuary, a neutral trading post) can override back to safe.

Property resolution walks up the tree from area to realm, returning the first explicitly-set value. The service layer resolves inheritance at read time — the database stores only explicit overrides.

### Zone Transitions

Exits between rooms in different areas or regions are ordinary rows in the exits table. Zone boundaries are implicit, derived from comparing the zone assignments of the source and destination rooms. When a player crosses a zone boundary, the UI updates the breadcrumb and shows the new zone context. If the new zone has different properties (entering PvP territory, entering a dark zone), the UI signals the change.

### Breadcrumb

The room header displays the full zone path: `Eldara > The Thornmark > Darkwood Forest > Spider Caves`. The realm always anchors the breadcrumb geographically. Breadcrumb segments are derived from the room's zone and its ancestors.

## Rooms

Rooms are the atomic unit of the world. Each room belongs to exactly one area (the leaf-level zone).

### Room Properties

| Property | Purpose |
|----------|---------|
| name | Display name, shown in header and on minimap hover |
| slug | URL-friendly identifier, unique within its zone |
| description | Full atmospheric text shown on first visit (3-5 sentences) |
| short_description | Terse text shown on return visits (1 sentence, max ~15 words) |
| room_type | Metadata hint for future sub-projects: standard, shop, inn, forge, boss_lair, sanctuary |
| image_path | Per-room image override (nullable, falls back to zone default) |
| map_x, map_y | Optional minimap coordinates (nullable floats, builder-placed) |

### Two-Tier Descriptions

Each room has a full `description` and a `short_description`. On a player's first visit to a room, the full description renders. On return visits, the short description renders. Visit tracking (via `player_room_visits`) determines which to show. There is no "look" command — this is a browser RPG. The server decides which description to deliver based on visit history.

**Description guidelines:** Full descriptions set the scene — what you see, hear, and feel. They reference zone atmosphere naturally without restating it. Short descriptions identify the room at a glance with its defining feature. Room names within a zone follow the zone's naming register (proper names in cities, descriptive names in wilderness, functional names in dungeons).

### Room Type

The `room_type` column stores what kind of interactions a room supports. In the World Engine, this is metadata only — the room renders identically regardless of type. Future sub-projects read `room_type` to determine what systems to activate (sub-07 Shops reads `shop` rooms and injects shop UI into the room extras block). The command palette also uses `room_type` to filter available commands by room context.

### Room Images

Rooms display an image banner when one is available. Each room can specify its own `image_path`. When absent, the room falls back to its area's image, then its region's image, following the zone hierarchy. Images are optional — rooms without any image in their ancestry render without the image section.

## Room Connections

Room connections (exits) are stored as a relationship table with one row per directional exit. Exits are unidirectional — a two-way passage between Room A and Room B is two rows (A-north-to-B and B-south-to-A). Builder tools create two-way passages as a convenience, but the data model is always one-directional.

### Directions

Twelve directions are supported:

| Direction | Code | UI Location |
|-----------|------|-------------|
| North | n | Compass top-center |
| Northeast | ne | Compass top-right |
| East | e | Compass middle-right |
| Southeast | se | Compass bottom-right |
| South | s | Compass bottom-center |
| Southwest | sw | Compass bottom-left |
| West | w | Compass middle-left |
| Northwest | nw | Compass top-left |
| Up | up | Compass center (top half) |
| Down | down | Compass center (bottom half) |
| In | in | Compass center (top-right quadrant, when present) |
| Out | out | Compass center (bottom-right quadrant, when present) |

The eight cardinal/intercardinal directions map to the 3x3 compass grid. Up/down/in/out share the center cell, which subdivides as needed: two halves for up/down only, a 2x2 sub-grid when in/out are also present.

### Exit Properties

| Property | Purpose |
|----------|---------|
| direction | One of the 12 direction codes |
| is_hidden | Not shown on compass until discovered (future sub-project mechanic) |
| is_locked | Shown on compass but disabled/dimmed (future sub-project mechanic) |

Hidden exits are excluded from the compass entirely. Locked exits render as disabled buttons. The discovery and unlocking mechanics belong to future sub-projects — the World Engine stores the flags and respects them during navigation.

Each room can have at most one exit per direction (unique constraint on `from_room_id` + `direction`).

## Player Position

A `current_room_id` column is added to the existing `players` table via a World Engine migration. This is a nullable FK to `rooms` with SET NULL on delete. NULL means the player is not in the world (character select screen, logged out).

When a player enters the world for the first time, they are placed at their starting area's spawn room (the area's `spawn_room_id`). A system config entry sets the default starting area for new characters.

## Navigation

Movement is instant. Players navigate by clicking compass buttons. There are no movement delays or cooldowns.

### Movement Flow

1. Player clicks an available compass direction
2. Compass disables briefly (200ms debounce to prevent rapid double-clicks)
3. Client sends `POST /game/move/{direction}`
4. Server validates the exit exists, is not locked, and is not hidden (unless discovered)
5. Server updates the player's `current_room_id`
6. Server records the visit in `player_room_visits` (insert or update visit count)
7. Server returns the new room's rendered HTML and structured data (exits, players present, minimap data)
8. Client swaps the room content area, updates compass state, updates minimap
9. Server publishes `player.leave` and `player.enter` events to other players in the old and new rooms via SSE fan-out

Movement uses fetch+swap — the game shell chrome (top bar, icon rail, compass dock, chat peek) stays in place. Only the room content area is replaced. No full page reloads during navigation.

### Keyboard Navigation

Arrow keys and WASD map to cardinal directions. Shift+direction for diagonals (Shift+W+D = northeast). Dedicated keys for up/down/in/out (to be determined during implementation).

## Room Rendering

The room content area fills the scrollable main canvas in the game shell. Content renders top-to-bottom:

### Layout

| Section | Content | Visibility |
|---------|---------|------------|
| Room header | Zone breadcrumb (left) + room name (left) + minimap thumbnail (right, 48x48) | Always |
| Room image | Zone or room image, max 180px height, full width | Conditional (if image exists) |
| Room description | Full or short description based on visit history | Always |
| Occupant list | Player name pills for other players present | Conditional (if others present) |
| Room extras | Empty extension block for future sub-projects | Always (empty in World Engine) |

There is no text-based exit list. The compass dock handles all navigation UI. Exit destination names appear as tooltips on compass buttons when hovered.

### Occupant List

Other players in the room appear as small pills below the description. Each pill shows the player's name in the player-name color. Clicking a pill is a hook for future interaction menus (out of scope for World Engine).

### Room Extras

A single `{block room_extras}` Latte block sits below the occupant list. World Engine leaves it empty. Future sub-projects fill it: NPC list (sub-04), ground items (sub-03), shop interface (sub-07), combat UI (sub-05). Content stacks vertically — walking into a room shows everything relevant at once.

## Minimap

The minimap ships with the World Engine. It shows the player's explored rooms in the current area as an interactive map.

### Thumbnail

A 48x48 canvas element floats in the room header's top-right corner. It shows a small graph of nearby rooms — the current room as an accent-colored dot, visited connected rooms as muted dots, and lines connecting them. Unvisited rooms are absent (fog of war). Clicking the thumbnail opens the expanded view.

### Expanded View

A 320x320 modal overlay (surface background, border, backdrop dimming) showing the current area's explored rooms at larger scale. Room name tooltips on hover. Current room highlighted in accent. Other players shown as small colored dots. Zone boundary outline groups the area visually. Close button top-right. Only one overlay active at a time — opening the minimap modal closes any slide-out panel.

### Graph Layout

The world is graph-based, not grid-based. Rooms do not have mandatory coordinates. Two layout strategies:

**Builder-placed coordinates:** Rooms have optional `map_x` and `map_y` float columns. When builders place coordinates (via Builder Tools, sub-project 12), the minimap renders rooms at those positions. This produces clean, intentional layouts.

**Automatic fallback:** When rooms lack coordinates, the minimap renders a 2-hop neighbor view centered on the current room. Connected rooms are plotted using direction vectors (north = up, east = right, etc.). This produces a local neighborhood view rather than a full zone map.

Pre-rendered coordinates are the preferred approach for polished zones. The fallback ensures every zone has a functional minimap even before builders place coordinates.

### Scope

The expanded minimap shows the current area only. Adjacent areas are not shown. Players navigate to another area to see its map.

## Fog of War

Players discover the map by exploring. Only visited rooms appear on the minimap. Unvisited rooms are entirely absent — not grayed out, not hinted at, just missing. This preserves genuine discovery.

### Visit Tracking

A `player_room_visits` table tracks which rooms each player has visited. Composite primary key on (`player_id`, `room_id`). Additional columns: `first_visited_at` (timestamp) and `visit_count` (integer). The table is bounded at `players * rooms` and requires no cleanup under normal conditions.

When the server builds the minimap data for a room change response, it queries visited rooms in the current area for that player and includes only those in the minimap payload.

### Dark Zones

Areas can be flagged as dark (`is_dark` on the zone). In dark zones:

- The minimap goes completely blank. No dots, no lines, no graph. The player navigates by description and compass only.
- Room visits are still tracked, but the minimap refuses to render them.
- Room descriptions shift register from visual to sensory — sounds, temperature, smell, texture instead of what you see.
- Room images use a dark/silhouette variant or are hidden entirely.
- Compass buttons still indicate available exits, but may use uncertain labels in future sub-projects.

Dark zones stay relevant because builders can mark any area as dark, and new content always introduces new dark areas. The best lore, rarest materials, and most challenging encounters are found in darkness — exploring them is a point of pride.

**Light sources** (torches, spells, lanterns) interact with dark zones but the mechanic belongs to the Character System (sub-project 03). The World Engine provides the `is_dark` flag and the description variants. The Character System determines whether a player's equipment or abilities override darkness.

### Semi-Random Zones

Deferred to Builder Tools (sub-project 12). The exit table remains static in the World Engine. Dynamic exits that change periodically would require a timer/scheduler, a state machine, and override logic for movement validation and minimap rendering. Builders can achieve similar disorientation through dark zones and creative room design without dynamic exits.

## Real-Time Events

The World Engine uses SSE to push room events to players. All events are delivered via the player's personal `player.{id}` channel using server-side fan-out. No room-specific SSE channels are needed.

### Fan-Out Architecture

When a room event occurs (player enters or leaves), the server queries which players are in the affected room and publishes the event to each player's `player.{id}` channel individually. The SSE connection established at login (subscribing to `player.{id}` + `global`) never changes. No reconnection, no dynamic subscription management.

The fan-out cost is N publishes per event, where N is the number of players in the room. For a MUD this is typically 1-20 players — trivially cheap for PostgreSQL NOTIFY.

### Event Types

| Event | Trigger | Payload | Purpose |
|-------|---------|---------|---------|
| `player.enter` | Another player enters your room | player_id, player_name, player_level | Append occupant pill |
| `player.leave` | Another player leaves your room | player_id | Remove occupant pill |
| `room.teleport` | Server-initiated room change (admin, trap, quest) | Rendered room HTML + structured data | Swap entire room content |

Normal movement uses the fetch response to update the moving player's room. SSE events notify other players in the room about the movement. `room.teleport` is the only case where SSE delivers full room content — for server-initiated moves that the player didn't request.

## Compass UI

The compass is a 3x3 directional grid in the compass dock at the bottom of the main canvas.

### Standard Layout

The eight outer cells map to cardinal and intercardinal directions. Available exits render with accent background and bright text. Unavailable directions render with elevated background and disabled text.

### Center Cell Subdivision

The center cell subdivides to accommodate vertical and portal directions:

| Available Exits | Center Layout | Cell Sizes |
|----------------|---------------|------------|
| None | Current-room dot indicator | Full cell |
| Up + Down | Horizontal split, top half = Up, bottom half = Down | 28x11px each |
| In + Out | Horizontal split, top half = In, bottom half = Out | 28x11px each |
| Up + Down + In + Out | 2x2 sub-grid: top-left Up, top-right In, bottom-left Down, bottom-right Out | 13x11px each |
| Any partial combination | Fill present directions into available positions | As above |

Arrow/chevron icons preferred over text labels at these sizes. Same active/inactive styling as the outer cells.

### Exit Tooltips

Hovering an available compass button shows a tooltip with the destination room name. If the exit crosses a zone boundary, the tooltip also shows the destination zone name.

## Zone Atmosphere

Each zone carries structured atmosphere data as key-value pairs. These describe the sensory environment — weather, ambient sounds, lighting, temperature, smell — without prescribing specific mechanical effects.

| Key | Example Values | Purpose |
|-----|---------------|---------|
| weather | clear, fog, rain, ashfall, none | Visual/ambient treatment |
| sound | birdsong, wind, dripping, silence, howling | Audio hints in descriptions |
| lighting | bright, dim, dark, magical, flickering | Description register guidance |
| temperature | warm, cold, freezing, humid, scorching | Sensory description aid |
| terrain | forest, cave, stone, sand, swamp | Builder guidance for room descriptions |

Atmosphere tags inherit through the zone hierarchy. A region sets the baseline atmosphere, and individual areas override specific keys as needed. Tags are metadata for builders and future sub-projects — the World Engine stores and resolves them but does not render mechanical effects.

## Database Schema

### zones

| Column | Type | Notes |
|--------|------|-------|
| id | serial PK | |
| parent_zone_id | FK zones, nullable | Self-referential, SET NULL on delete |
| zone_type | varchar(10) | realm, province, region, area |
| name | varchar(100) | Display name |
| slug | varchar(100) | URL-friendly, unique within parent |
| description | text | Zone description for UI and builders |
| level_min | smallint, nullable | Advisory minimum level (inheritable) |
| level_max | smallint, nullable | Advisory maximum level (inheritable) |
| pvp_enabled | boolean, nullable | Null = inherit from parent |
| is_safe_zone | boolean, nullable | Null = inherit from parent |
| is_dark | boolean default false | Dark zone flag (areas only) |
| atmosphere | jsonb, nullable | Structured key-value atmosphere tags |
| image_path | varchar(255), nullable | Default image for rooms in this zone |
| spawn_room_id | FK rooms, nullable | Zone spawn point |
| created_at | timestamp | |
| updated_at | timestamp | |

Nullable booleans for `pvp_enabled` and `is_safe_zone` enable inheritance — null means "inherit from parent," explicit true/false means "override."

### rooms

| Column | Type | Notes |
|--------|------|-------|
| id | serial PK | |
| zone_id | FK zones, cascade | Must reference an area-type zone |
| name | varchar(150) | Display name |
| slug | varchar(100) | Unique within zone |
| description | text | Full description (first visit) |
| short_description | varchar(255) | Terse description (return visits) |
| room_type | varchar(30) default 'standard' | standard, shop, inn, forge, boss_lair, sanctuary |
| image_path | varchar(255), nullable | Per-room override, falls back to zone |
| map_x | float, nullable | Minimap X coordinate (builder-placed) |
| map_y | float, nullable | Minimap Y coordinate (builder-placed) |
| created_at | timestamp | |
| updated_at | timestamp | |

### room_exits

| Column | Type | Notes |
|--------|------|-------|
| id | serial PK | |
| from_room_id | FK rooms, cascade | Source room |
| to_room_id | FK rooms, cascade | Destination room |
| direction | varchar(5) | n, ne, e, se, s, sw, w, nw, up, down, in, out |
| is_hidden | boolean default false | Hidden until discovered |
| is_locked | boolean default false | Visible but impassable |
| created_at | timestamp | |
| | unique(from_room_id, direction) | One exit per direction per room |

### player_room_visits

| Column | Type | Notes |
|--------|------|-------|
| player_id | FK players | Composite PK |
| room_id | FK rooms | Composite PK |
| first_visited_at | timestamp | When the player first entered this room |
| visit_count | integer default 1 | Incremented on each visit |

### players (modified)

| Column | Type | Notes |
|--------|------|-------|
| current_room_id | FK rooms, nullable, SET NULL | Added by World Engine migration. Null = not in world |

## Module Structure

```
app/world/
  src/
    Controller/
      NavigationController.php    # POST /game/move/{direction}, room rendering
      RoomController.php          # GET /game/room (current room view)
      MinimapController.php       # GET /game/minimap (minimap data endpoint)
    Model/
      Zone.php                    # Zone entity
      Room.php                    # Room entity
      RoomExit.php                # Exit entity
      PlayerRoomVisit.php         # Visit tracking entity
    Service/
      NavigationService.php       # Move validation, position update, SSE fan-out
      RoomService.php             # Room loading, context assembly, description tier
      ZoneService.php             # Zone hierarchy, property inheritance resolution
      MinimapService.php          # Minimap data assembly, fog of war filtering
    Repository/
      ZoneRepository.php
      RoomRepository.php
      RoomExitRepository.php
      PlayerRoomVisitRepository.php
  config/
    world.php                     # Default starting area, spawn config
  database/
    migrations/                   # Zone, room, exit, visit tables + players FK
  resources/
    views/
      room/
        show.latte                # Room content partial (header, image, description, occupants, extras)
        _occupants.latte          # Occupant list partial (for SSE updates)
      minimap/
        data.latte                # Minimap JSON endpoint
  tests/
  composer.json                   # marko.module: true
  module.php                      # Repository and service bindings
```

## Services

**NavigationService** — validates that a requested exit exists and is passable, updates the player's `current_room_id`, records the visit, assembles the new room response (rendered HTML + structured data for compass and minimap), and publishes `player.leave`/`player.enter` SSE events to other players in both the old and new rooms via fan-out.

**RoomService** — loads a room with full context: room data, resolved zone properties (walking the inheritance chain), exits (excluding hidden unless the player has discovered them), other players present, resolved image path, and visit status for description tier selection. Returns a `RoomContext` value object that the Latte template consumes.

**ZoneService** — resolves zone property inheritance by walking the parent chain. Caches resolved properties per zone since hierarchy changes are infrequent. Provides zone ancestry for breadcrumb rendering.

**MinimapService** — assembles minimap data for a player in a given area. Queries the player's visited rooms in that area, loads their exits and optional coordinates, and returns a graph structure (nodes + edges) for client-side rendering.

## Command Registration

The World Engine registers navigation commands with the Foundation's command registry. Available commands are filtered by the current room's exits:

- Move commands for each available direction (e.g., "Go North", "Go Up", "Enter")
- Filtered out when the direction has no exit from the current room
- Locked exits show in the palette but are marked as unavailable

## Testing

### Unit Tests

- ZoneService: property inheritance resolution (cascading overrides, null inheritance, multi-level chains)
- NavigationService: valid move, invalid direction, locked exit, hidden exit, position update
- RoomService: description tier selection (first visit vs return), image fallback resolution, exit filtering
- MinimapService: fog of war filtering, coordinate vs fallback layout data

### Feature Tests

- Movement flow: move request, position update, room response, SSE event publication
- Zone transitions: breadcrumb update, property change detection
- Dark zone behavior: minimap blank, description variant
- Spawn placement: new player placed at zone spawn point
- Visit tracking: first visit records, return visit increments count

## Exit Criteria

The World Engine is complete when:

- Zones can be created in a four-tier hierarchy (realm/province/region/area) with cascading properties
- Rooms exist within areas with two-tier descriptions, type, and optional images
- Unidirectional exits connect rooms across all 12 directions
- A player can navigate room-to-room via compass clicks with instant movement
- The compass center cell subdivides for up/down/in/out directions
- Room content swaps via fetch+swap without full page reload
- Other players in the room see enter/leave notifications via SSE fan-out
- The minimap renders visited rooms in the current area (builder-placed coords or 2-hop fallback)
- Fog of war hides unvisited rooms from the minimap
- Dark zones blank the minimap and shift description register
- Zone breadcrumbs display the full hierarchy path
- Zone property inheritance resolves correctly through the hierarchy
- Pest tests pass for navigation, zone inheritance, room rendering, and minimap data
