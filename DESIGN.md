# Design System — Shilla

## 1. Visual Theme & Atmosphere

Shilla's interface draws from twilight — the liminal hour between day and night where the world takes on purple-gray tones and steel-blue light. The overall impression is moody enchantment: a fantasy world glimpsed through arcane glass. Surfaces are dark but never black, carrying a subtle violet undertone that distinguishes Shilla from generic dark themes. Interactive elements glow with a cool steel-blue accent that reads as magical without being garish.

The typography is built on Inter Variable with OpenType features `"cv01"` and `"ss03"` enabled for a cleaner geometric character. Inter is used across all weights from 400 (body) through 500 (emphasis) to 600 (headings). The result is precise and readable on dark surfaces, with enough character to feel intentional rather than default.

The color system is intentionally restrained. The twilight palette handles 90% of the UI through gradations of purple-gray. Game-specific semantics — health (red), mana (blue), stamina (green), gold (amber) — are the only saturated colors, and they earn their intensity by contrast with the muted surroundings. Enemy names and danger states use a desaturated red that warns without screaming. Player names use a soft green that's warm and welcoming. NPC names share the accent blue to signal interactivity.

**Key Characteristics:**
- Twilight foundation: `#20202a` background, `#282834` surfaces, `#2e2e3a` elevated surfaces
- Inter Variable with `"cv01", "ss03"` globally — geometric alternates
- Signature weight 500 for UI emphasis, 600 for headings, 400 for body
- Steel-blue accent: `#5a6aaa` (default) / `#7e8ec0` (hover) / `#8a9ad0` (bright)
- Borders at `#3a3a4a` — visible but not harsh, single pixel
- No shadows — depth communicated through background tiers and border presence
- Game stat colors are the only saturated hues in the palette
- Transitions: 150ms ease for all interactive states

## 2. Color Palette & Roles

### Background Surfaces

- **Base** (`#20202a`): The deepest background — the canvas for the entire application. Dark with a violet-cool undertone.
- **Surface** (`#282834`): Cards, panels, sidebars. One step up from base.
- **Elevated** (`#2e2e3a`): Hover states, active panels, raised elements. The lightest dark surface.
- **Input** (`#22222c`): Form inputs and text areas. Slightly different from surface to create a subtle inset feel.

### Text & Content

- **Primary** (`#c0c4d8`): Near-white with a cool lavender cast. Headings, important content, player-facing labels.
- **Secondary** (`#7878a0`): Muted purple-gray. Body text, descriptions, secondary information.
- **Muted** (`#5a5a70`): The most subdued text. Labels, metadata, timestamps, slot names.
- **Disabled** (`#40404a`): Inactive elements, unavailable directions on the compass, locked slots.

### Accent & Interactive

- **Accent** (`#5a6aaa`): Primary interactive color. Buttons, links, active nav items, available compass directions.
- **Accent Hover** (`#7e8ec0`): Hover state for accent elements. Brighter, more saturated.
- **Accent Bright** (`#8a9ad0`): Focus rings, selected states, active indicators.

### Game Stats

- **Health** (`#e05050`): HP bar fill, damage numbers. The most attention-grabbing color in the palette.
- **Health Dark** (`#c04040`): HP bar background tint, health pill border.
- **Health BG** (`#2a1a1a`): Background tint for health pill in the top bar.
- **Mana** (`#5080e0`): MP bar fill, magic-related UI.
- **Mana Dark** (`#6a6ab8`): MP bar background tint, mana pill border.
- **Mana BG** (`#1a1a2e`): Background tint for mana pill.
- **Stamina** (`#50c070`): Fatigue/stamina bar fill, success states.
- **Stamina Dark** (`#4a9e6a`): Stamina bar background tint, success messages.
- **Stamina BG** (`#1a2a1a`): Background tint for stamina pill.

### Semantic

- **Gold** (`#c0a050`): Currency, rewards, treasure, forge prompts.
- **Danger** (`#aa6a6a`): Enemy names, ban actions, error states, destructive buttons.
- **Danger BG** (`#3a2a2a`): Background tint for danger/admin role badges.
- **Player Name** (`#6aaa80`): Player character names in chat, room occupant lists, party UI.
- **NPC Name** (`#7e8ec0`): NPC names. Shares the accent blue to signal interactivity.
- **System Message** (`#aa8a4a`): System events, guard announcements, server messages.

### Border & Divider

- **Border** (`#3a3a4a`): Standard border for panels, cards, inputs. The workhorse.
- **Border Light** (`#44444e`): Lighter border for hover states and subtle separations.
- **Border Dark** (`#2e2e3a`): Subtle dividers within panels, section separators.

### Role Badges

- **Player** (`#2a2a44` bg, `#7e8ec0` text): Default player role.
- **Builder** (`#2a3a2a` bg, `#6aaa80` text): Builder/immortal role.
- **Admin** (`#3a2a2a` bg, `#c08080` text): Administrator role.

## 3. Typography Rules

### Font Family

- **Primary**: `Inter Variable`, with fallbacks: `system-ui, -apple-system, Segoe UI, Roboto, sans-serif`
- **Monospace**: `JetBrains Mono, ui-monospace, SF Mono, Menlo, monospace`
- **OpenType Features**: `"cv01", "ss03"` enabled globally

### Hierarchy

| Role | Size | Weight | Line Height | Letter Spacing | Usage |
|------|------|--------|-------------|----------------|-------|
| Display | 32px (2rem) | 600 | 1.1 | -0.5px | Landing page hero, major headings |
| Heading 1 | 24px (1.5rem) | 600 | 1.2 | -0.3px | Page titles, section headings |
| Heading 2 | 20px (1.25rem) | 600 | 1.3 | -0.2px | Panel headers, sub-sections |
| Heading 3 | 16px (1rem) | 600 | 1.4 | normal | Card titles, item names |
| Body | 14px (0.875rem) | 400 | 1.6 | normal | Default text, descriptions, room text |
| Body Emphasis | 14px (0.875rem) | 500 | 1.6 | normal | Labels, nav items, emphasized body |
| Small | 13px (0.8125rem) | 400 | 1.5 | normal | Secondary info, timestamps |
| Small Emphasis | 13px (0.8125rem) | 500 | 1.5 | normal | Badge text, stat labels |
| Caption | 12px (0.75rem) | 400 | 1.4 | normal | Metadata, slot labels (HELM, CHEST) |
| Caption Emphasis | 12px (0.75rem) | 500 | 1.4 | 0.5px | Section headers in uppercase |
| Micro | 11px (0.6875rem) | 500 | 1.3 | normal | Stat pill values, compass direction labels |
| Mono Body | 14px (0.875rem) | 400 | 1.5 | normal | Code, IDs, technical values |

### Principles

- **Base size is 14px**, not 16px. The game UI is information-dense; 14px allows more content without feeling cramped.
- **600 for headings, 500 for emphasis, 400 for body.** Three weights only. No bold (700) in the UI.
- **Negative letter-spacing on headings** (-0.5px to -0.2px) for a tighter, more engineered feel at larger sizes.
- **Uppercase + letter-spacing for section labels** (0.5px tracking on caption-emphasis). Used sparingly: sidebar section headers, stat labels, compass directions.

## 4. Component Stylings

### Buttons

**Primary Button**
- Background: `#5a6aaa` (accent)
- Text: `#e0e4f0` (near-white)
- Padding: 6px 16px
- Radius: 6px
- Border: none
- Hover: `#7e8ec0` background
- Transition: background 150ms ease

**Secondary Button**
- Background: `#282834` (surface)
- Text: `#b0b8d0`
- Padding: 6px 16px
- Radius: 6px
- Border: 1px solid `#3a3a4a`
- Hover: `#2e2e3a` background, `#44444e` border

**Ghost Button**
- Background: transparent
- Text: `#7878a0`
- Padding: 6px 12px
- Radius: 6px
- Border: none
- Hover: `#282834` background

**Danger Button**
- Background: `#aa6a6a`
- Text: `#f0e0e0`
- Padding: 6px 16px
- Radius: 6px
- Hover: `#c07070` background

**Icon Button**
- Background: `#282834` (surface) or `#2e2e3a` (elevated)
- Size: 32x32px
- Radius: 6px
- Border: 1px solid `#3a3a4a` (when on surface bg)
- Icon color: `#5a5a70` (muted), `#7e8ec0` (active)

### Inputs

**Text Input**
- Background: `#22222c`
- Text: `#c0c4d8`
- Placeholder: `#5a5a70`
- Padding: 8px 12px
- Radius: 6px
- Border: 1px solid `#3a3a4a`
- Focus: border `#5a6aaa`, outline none, ring 1px `#5a6aaa33`
- Height: 36px

**Select / Dropdown**
- Same as text input with chevron indicator
- Dropdown: `#282834` bg, `#3a3a4a` border, items hover `#2e2e3a`

### Cards & Panels

**Card**
- Background: `#282834`
- Border: 1px solid `#3a3a4a`
- Radius: 6px
- Padding: 12px (varies by context)

**Slide-Out Panel** (icon rail panels)
- Background: `#22222c`
- Border-left: 1px solid `#34343e`
- Width: 220px
- Appears from right edge, pushes nothing (overlays with room dimmed behind at 40% opacity)

### Stat Pills (Top Bar)

Compact stat indicators in the game shell top bar.

- Size: auto width, height 20px
- Background: stat-specific BG tint (`#2a1a1a` for health, `#1a1a2e` for mana, `#1a2a1a` for stamina)
- Border: 1px solid corresponding dark color
- Radius: 12px (full pill)
- Content: 6px colored dot + value in micro text
- Padding: 2px 8px

### Compass Grid

3x3 directional grid for room navigation.

- Cell size: 28x28px
- Gap: 2px
- Available exit: `#5a6aaa` background, `#e0e4f0` text, 600 weight
- Unavailable: `#2e2e3a` background, `#50506a` text
- Center cell: `#2e2e3a` background, no text (or dot indicator)
- Radius: 3px per cell
- Hover (available): `#7e8ec0` background

### Equipment Slots

- Row: flex, align-center, gap 8px, padding 4px
- Icon box: 28x28px, `#2e2e3a` bg, 4px radius, 1px `#3a3a4a` border
- Slot label: caption, `#5a5a70`, uppercase
- Item name: small, `#b0b8d0`

### Chat Messages

- Message: font-size small (13px)
- Player name: `#6aaa80` (player), `#7e8ec0` (NPC), `#aa8a4a` (system)
- Message text: `#7878a0`
- Timestamp: `#5a5a70`, same line, right-aligned or after message

### Toast Notifications

- Position: fixed top-right, z-50
- Width: auto, max 360px
- Radius: 6px
- Padding: 8px 16px
- Text: white
- Backgrounds: `#4a9e6a` (success), `#aa6a6a` (error), `#5a6aaa` (info)
- Appear with fade+slide transition, auto-dismiss after 4s

### Command Palette

- Overlay: fixed center, z-50
- Backdrop: `rgba(0,0,0,0.5)`
- Panel: `#282834` bg, `#3a3a4a` border, 8px radius
- Width: 480px, max-height 400px
- Search input at top: full width, `#22222c` bg, no border, 16px font
- Results: list of items, `#282834` bg, hover `#2e2e3a`
- Selected item: `#2e2e3a` bg, left border 2px `#5a6aaa`
- Item: label in `#c0c4d8`, description/action in `#5a5a70`

### Admin Panel

Follows the same twilight palette. Override the default marko/admin-panel templates:
- Sidebar: `#20202a` bg, `#2e2e3a` border-right
- Active item: `#28283a` bg, 2px `#5a6aaa` left border
- Section headers: caption-emphasis, `#5a5a70`, uppercase
- Main content area: `#1a1a24` bg (slightly darker than game shell for contrast)
- Tables: `#24242e` bg, `#2e2e3a` header, `#34343e` row borders
- Stat cards: `#24242e` bg, `#34343e` border, 6px radius

## 5. Layout Principles

### Game Shell

The game shell is a fixed-viewport layout. No page scrolling — all scrolling happens within panels.

```
+------------------------------------------------------------------+
|  [SHILLA]  |  Player Name  Lv 42  |  [HP] [MP] [ST]  999g  [⚙]  |  <- top bar (fixed, 40px)
+------------------------------------------------------------------+
|                                                          | [⚔] |
|                                                          | [★] |
|              Room Content Area                           | [✦] |
|              (flex-1, scrollable)                        | [📖] |
|                                                          | [?] |
|                                                          |     |
|                                                          | [💬] |
|  +--------+                                              +-----+
|  |minimap |                                                     |
|  +--------+     [NW] [ N] [NE]                                  |
|                 [ W] [ ●] [ E]    <- compass dock               |
|                 [SW] [ S] [SE]                                  |
+------------------------------------------------------------------+
|  Elora: mines?  |  [Say something...]                           |  <- chat peek (32px)
+------------------------------------------------------------------+
```

- **Top bar**: 40px fixed. Logo left, player info center-left, stat pills center-right, gold + settings right.
- **Main area**: flex-1 remaining height. Room content scrollable.
- **Icon rail**: 48px fixed right. Vertical icon buttons.
- **Compass dock**: centered at bottom of room content, above chat peek.
- **Chat peek**: 32px fixed bottom. Latest message + input.
- **Slide-out panels**: overlay from right, 220px wide, full height minus top bar.

### Public Site

Standard top-to-bottom page layout. Centered content, max-width 960px.

- Header: 56px, logo left, nav center, auth buttons right
- Content: centered, max-width 960px, padding 24px
- Footer: border-top, muted text, centered

### Auth Pages

Centered card layout. Logo above card, card max-width 400px.

### Admin Panel

Sidebar + main content. Sidebar 200px fixed left. Main content scrollable.

## 6. Depth & Elevation

Shilla uses **no box-shadows**. Depth is communicated through:

1. **Background tiers**: base → surface → elevated. Each step is a lighter shade of the twilight palette.
2. **Border presence**: Panels have borders, inline elements don't. The presence of a border signals "this is a distinct surface."
3. **Opacity for overlays**: Backdrop overlays use `rgba(0,0,0,0.5)`. The command palette and modals use this.
4. **Dimming**: When a slide-out panel opens, the room content dims to 40% opacity. This creates depth without shadows.

| Level | Background | Border | Usage |
|-------|-----------|--------|-------|
| 0 (base) | `#20202a` | none | Page background |
| 1 (surface) | `#282834` | `#3a3a4a` | Cards, panels, sidebar |
| 2 (elevated) | `#2e2e3a` | `#44444e` | Hover states, dropdown items, active elements |
| 3 (input) | `#22222c` | `#3a3a4a` | Form inputs (inset feel) |
| Overlay | `rgba(0,0,0,0.5)` | — | Backdrop behind modals/palette |

## 7. Do's and Don'ts

### Do

- Use the stat colors (health/mana/stamina) only for their designated purpose. They are the brightest colors in the palette and must be instantly recognizable.
- Use accent blue for all interactive elements. Consistency here teaches users what's clickable.
- Use muted text (`#5a5a70`) for metadata and labels. Keeps the visual hierarchy clean.
- Use uppercase + letter-spacing sparingly for section headers.
- Keep border-radius consistent: 6px for cards/panels, 4px for buttons/inputs, 12px for pills, 3px for compass cells.
- Use 1px solid borders. Never 2px except for the active indicator (left border on active nav items).

### Don't

- Don't use box-shadows. The twilight theme creates depth through background tiers and borders.
- Don't use pure white (`#ffffff`) for text. The brightest text color is `#e0e4f0` (used inside accent buttons).
- Don't use pure black (`#000000`) for backgrounds. The darkest background is `#1a1a24` (admin main area).
- Don't introduce new saturated colors without a clear semantic purpose. The palette is intentionally restrained.
- Don't use more than 3 font weights in any single view. The system uses 400, 500, 600.
- Don't mix border-radius values on adjacent elements. A 6px card should not contain a 12px button.
- Don't animate colors. Only transition background-color on hover/focus (150ms ease).

## 8. Responsive Behavior

Shilla is a desktop-first application. The game shell assumes a minimum viewport width of 1024px.

### Breakpoints

| Breakpoint | Width | Behavior |
|-----------|-------|----------|
| Desktop | 1024px+ | Full game shell layout |
| Tablet | 768px–1023px | Icon rail collapses to bottom bar, compass moves inline |
| Mobile | <768px | Not a target. Show a "desktop required" message for the game shell |

### Public Site

The public site (landing, about, FAQ) is responsive down to 375px mobile. Standard responsive patterns:
- Stack columns on mobile
- Reduce padding
- Full-width buttons

### Admin Panel

Admin panel is desktop-only (1024px+). Sidebar collapses to hamburger on narrower screens but this is a low priority.

## 9. Agent Prompt Guide

When generating UI for Shilla, follow these rules:

1. **Always use the twilight palette.** Background is `#20202a`, surfaces are `#282834`, borders are `#3a3a4a`. Never use generic dark theme colors.

2. **Text hierarchy matters.** Primary (`#c0c4d8`) for headings and important content. Secondary (`#7878a0`) for body text. Muted (`#5a5a70`) for labels and metadata. Three tiers, no more.

3. **Accent blue (`#5a6aaa`) is for interactive elements only.** Buttons, links, active states, available compass exits. Never use it for decorative purposes.

4. **Game stat colors have fixed meanings.** Red = health/damage. Blue = mana/magic. Green = stamina/success. Gold = currency. Do not repurpose these colors.

5. **No shadows, no gradients.** Depth comes from the three background tiers and border presence.

6. **Font is Inter Variable at 14px base.** Three weights: 400 body, 500 emphasis, 600 headings. OpenType features `"cv01", "ss03"`.

7. **Consistent spacing.** Use Tailwind spacing scale. Padding: 8px for compact (inputs), 12px for standard (cards), 16px for spacious (sections). Gap: 4px tight, 8px standard, 16px loose.

8. **Border radius.** 6px for cards/panels, 4px for buttons/inputs, 12px for pills/badges, 3px for compass cells.

9. **Transitions.** 150ms ease for hover/focus state changes. No other animations in the core UI.

10. **The game shell is the frame.** Room content fills the center. Everything else (top bar, icon rail, chat peek, compass) is persistent chrome. Slide-out panels overlay the room content.
