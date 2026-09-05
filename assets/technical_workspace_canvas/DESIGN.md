---
name: Technical Workspace Canvas
colors:
  surface: '#111316'
  surface-dim: '#111316'
  surface-bright: '#37393d'
  surface-container-lowest: '#0c0e11'
  surface-container-low: '#1a1c1f'
  surface-container: '#1e2023'
  surface-container-high: '#282a2d'
  surface-container-highest: '#333538'
  on-surface: '#e2e2e6'
  on-surface-variant: '#c7c4d7'
  inverse-surface: '#e2e2e6'
  inverse-on-surface: '#2f3034'
  outline: '#908fa0'
  outline-variant: '#464554'
  surface-tint: '#c0c1ff'
  primary: '#c0c1ff'
  on-primary: '#1000a9'
  primary-container: '#8083ff'
  on-primary-container: '#0d0096'
  inverse-primary: '#494bd6'
  secondary: '#bcc7d8'
  on-secondary: '#27313e'
  secondary-container: '#3d4856'
  on-secondary-container: '#abb6c7'
  tertiary: '#89ceff'
  on-tertiary: '#00344d'
  tertiary-container: '#009ada'
  on-tertiary-container: '#002d43'
  error: '#ffb4ab'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#e1e0ff'
  primary-fixed-dim: '#c0c1ff'
  on-primary-fixed: '#07006c'
  on-primary-fixed-variant: '#2f2ebe'
  secondary-fixed: '#d8e3f5'
  secondary-fixed-dim: '#bcc7d8'
  on-secondary-fixed: '#111c29'
  on-secondary-fixed-variant: '#3d4856'
  tertiary-fixed: '#c9e6ff'
  tertiary-fixed-dim: '#89ceff'
  on-tertiary-fixed: '#001e2f'
  on-tertiary-fixed-variant: '#004c6e'
  background: '#111316'
  on-background: '#e2e2e6'
  surface-variant: '#333538'
typography:
  display-hero:
    fontFamily: inter
    fontSize: 2.25rem
    fontWeight: '600'
    lineHeight: 2.75rem
    letterSpacing: -0.025em
  headline-lg:
    fontFamily: inter
    fontSize: 1.5rem
    fontWeight: '600'
    lineHeight: 2rem
    letterSpacing: -0.02em
  headline-lg-mobile:
    fontFamily: inter
    fontSize: 1.25rem
    fontWeight: '600'
    lineHeight: 1.75rem
    letterSpacing: -0.015em
  headline-md:
    fontFamily: inter
    fontSize: 1.125rem
    fontWeight: '500'
    lineHeight: 1.5rem
    letterSpacing: -0.015em
  headline-sm:
    fontFamily: inter
    fontSize: 0.9375rem
    fontWeight: '500'
    lineHeight: 1.375rem
    letterSpacing: -0.01em
  body-lg:
    fontFamily: inter
    fontSize: 1rem
    fontWeight: '400'
    lineHeight: 1.625rem
    letterSpacing: -0.005em
  body-md:
    fontFamily: inter
    fontSize: 0.875rem
    fontWeight: '400'
    lineHeight: 1.375rem
    letterSpacing: 0em
  body-sm:
    fontFamily: inter
    fontSize: 0.8125rem
    fontWeight: '400'
    lineHeight: 1.25rem
    letterSpacing: 0em
  label-md:
    fontFamily: jetbrainsMono
    fontSize: 0.75rem
    fontWeight: '500'
    lineHeight: 1rem
    letterSpacing: 0.02em
  label-sm:
    fontFamily: jetbrainsMono
    fontSize: 0.6875rem
    fontWeight: '400'
    lineHeight: 0.875rem
    letterSpacing: 0.04em
  code-inline:
    fontFamily: jetbrainsMono
    fontSize: 0.8125rem
    fontWeight: '400'
    lineHeight: 1.25rem
    letterSpacing: 0em
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  space-2xs: 0.125rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 0.75rem
  space-base: 1rem
  space-lg: 1.5rem
  space-xl: 2rem
  space-2xl: 3rem
  sidebar-width: 16rem
  inspector-width: 20rem
  gutter-canvas: 1.5rem
---

## Brand & Style

This design system establishes an AI-native visual product planning workspace and PRD studio. Built for principal product managers, technical architects, and engineering leads, the environment prioritizes speed, semantic density, and absolute clarity over decorative flair. The aesthetic sits at the intersection of developer-grade command surfaces and calm, structured editorial systems.

Key tenets:
- **Calm Utility:** Information density without visual chaos. Chrome recedes to allow PRD specifications, schema blocks, and architecture graphs to command attention.
- **Instrument Precision:** Interfaces behave like high-reliability software instruments. Predictable alignments, hairline borders, and subdued surfaces prevent cognitive fatigue during multi-hour planning workflows.
- **Restrained Computation:** AI interactions manifest as discrete, structured assistants—inline diffs, structured badges, and compact suggestions—rather than radiant or theatrical effects. Glowing gradients and neon cyberpunk tropes are strictly prohibited.

## Colors

The system is engineered as an intrinsically dark workspace. Surfaces progress upward through precise tonal tiers rather than simulated light casts.

### Palette Architecture
- **Canvas Base (`#0c0d0e`):** The foundational viewport level for root canvas and document backing.
- **Layer 1 Surface (`#121417`):** Primary panel workspace, navigation rails, and PRD page roots.
- **Layer 2 Container (`#181b20`):** Nested cards, editor sidebars, and grouped metadata boxes.
- **Layer 3 Elevated (`#20242b`):** Modals, contextual menus, popovers, and elevated state nodes.
- **Borders & Dividers:**
  - Hairline Subdued: `#282d37` (structural panel framing, cell separators).
  - Hairline Strong: `#323846` (hover boundaries, focused container borders).
- **Text & Hierarchy:**
  - Primary (`#f1f3f5`): Headers, spec titles, active editor body.
  - Secondary (`#cbd2d9`): Standard labels, descriptions, schema fields.
  - Tertiary / Muted (`#8792a2`): Monospace metadata, helper hints, section counters.
  - Disabled / Ghost (`#5c667a`): Inactive iconography, placeholder text, inert states.
- **Accent Slate-Indigo (`#6366f1`):** Applied surgically to primary actionable triggers, active selection frames, and focused cursor markers. Never used as heavy solid backgrounds for wide cards.
- **Semantic Status Architecture:**
  - **Verified / Approved:** Emerald (`#10b981` text/icon, `#064e3b` border/fill tint at 15%).
  - **Review / In-Progress:** Amber (`#f59e0b` text/icon, `#78350f` border/fill tint at 15%).
  - **Risk / Blocker:** Rose (`#f43f5e` text/icon, `#881337` border/fill tint at 15%).
  - **Defined / Drafted:** Sky (`#0ea5e9` text/icon, `#0c4a6e` border/fill tint at 15%).

## Typography

The type system blends `Inter` for prose and structural framing with `JetBrains Mono` for metadata, requirements IDs, JSON schemas, and technical variables.

### Rules of Usage
- **Tracking & Weight:** Display and section headers enforce tight negative tracking (`-0.02em` to `-0.01em`) to create compact title bars matching developer tooling.
- **Technical Density:** PRD issue keys (e.g., `REQ-1049`), timestamps, parameter keys, and status flags strictly mandate `JetBrains Mono` using `label-md` and `label-sm`.
- **Editorial Legibility:** Long-form PRD text blocks are restricted to `body-md` (0.875rem) or `body-lg` (1rem) with a relaxed line-height of `1.625rem` to sustain technical reading comprehension across prolonged working sessions.

## Layout & Spacing

The architecture operates on an 8-point base scale with a 4-point micro-scale for precise technical alignment. Layouts are strictly segmented, pane-driven, and docked.

### Layout Model
- **Three-Tier Workspace Architecture:**
  - **Left Rail (Fixed `16rem`):** Tree views, spec outlines, project selector, pinned templates.
  - **Center Stage (Fluid `flex-1`):** The primary PRD visual editor, document canvas, or block canvas. Max content constraint of `860px` when in single-column document view; full width when in canvas or timeline mode.
  - **Right Inspector (Collapsible `20rem`):** AI drafting parameters, requirement traceability, metadata schemas, and acceptance criteria verification.
- **Breakpoints:**
  - `Desktop (>= 1280px)`: Three panes concurrent (Navigation + Document + AI Inspector).
  - `Tablet (768px - 1279px)`: Left navigation collapses to an overlay drawer; Inspector collapses into tabs.
  - `Mobile (< 768px)`: Single active view with sticky bottom panel controls. The document editor adapts to a 16px horizontal margin with stacked control ribbons.

## Elevation & Depth

Visual hierarchy is communicated via low-contrast outlines and stacked tonal layers rather than drop shadows. 

- **Level 0 (Backdrop Canvas):** `#0c0d0e`, no border.
- **Level 1 (Docked Panels):** `#121417`, separated from adjacent panels by a continuous `1px solid #282d37` border.
- **Level 2 (In-Canvas Cards & Nodes):** `#181b20`, bordered by `1px solid #282d37`. On hover, border transitions smoothly to `#323846`.
- **Level 3 (Overlays, Slash Menus, Floating Toolbars):** `#20242b`, bound by `1px solid #323846` with a tight, non-diffused technical shadow: `0 4px 16px rgba(0, 0, 0, 0.45)`.
- **Absolute Rule:** Avoid multi-layered colorful blurs, drop-shadow glows, or translucent glassmorphism that impedes text edge sharpness.

## Shapes

The geometry reflects surgical tooling: compact, controlled, and predominantly squared with soft chamfers.

- **Base Radius (4px / `0.25rem`):** Inputs, inline code tags, table cells, buttons, and segmented control tabs.
- **Container Radius (6px to 8px / `rounded-lg`):** Cards, visual block nodes, dialog surfaces, and dropdown panels.
- **Pill Shape:** Restricted exclusively to status indicator badges and filter counts to clearly separate metadata tokens from structural interactive buttons.

## Components

### Buttons
- **Primary:** Background `#6366f1`, text `#f1f3f5`, height `32px`, font `body-sm` (medium), horizontal padding `12px`, border radius `4px`. Hover state shifts background to `#4f46e5`.
- **Secondary / Neutral:** Background `#181b20`, text `#cbd2d9`, border `1px solid #282d37`. Hover shifts background to `#20242b` and border to `#323846`.
- **Ghost / Icon Action:** Transparent background, text `#8792a2`, hover text `#f1f3f5`, hover background `#181b20`.

### Chips & Badges
- **Status Pills:** Height `20px`, padding `2px 8px`, font `label-sm`, full pill roundedness.
  - *Verified:* Border `1px solid rgba(16, 185, 129, 0.3)`, background `rgba(16, 185, 129, 0.08)`, text `#10b981`.
  - *Review:* Border `1px solid rgba(245, 158, 11, 0.3)`, background `rgba(245, 158, 11, 0.08)`, text `#f59e0b`.
  - *Blocker:* Border `1px solid rgba(244, 63, 94, 0.3)`, background `rgba(244, 63, 94, 0.08)`, text `#f43f5e`.
  - *Draft:* Border `1px solid rgba(14, 165, 233, 0.3)`, background `rgba(14, 165, 233, 0.08)`, text `#0ea5e9`.
- **Metadata Code Chips:** Height `20px`, padding `0 6px`, font `label-sm`, background `#121417`, border `1px solid #282d37`, text `#8792a2`, radius `4px`.

### Inputs & Form Fields
- **Field Base:** Height `32px`, background `#0c0d0e`, border `1px solid #282d37`, font `body-sm`, text `#f1f3f5`. Placeholder in `#5c667a`.
- **Focus State:** Border shifts to `#6366f1` with zero fuzzy outer glow. Hairline precision ring: `box-shadow: 0 0 0 1px #6366f1`.

### Checkboxes & Radio Buttons
- **Checkbox:** Square `16px x 16px`, radius `3px`, border `1px solid #323846`, background `#121417`. Selected state uses `#6366f1` with crisp white SVG check icon.
- **Radio:** Outer diameter `16px`, circular, border `1px solid #323846`. Checked state displays a solid inner dot of `6px` in `#6366f1`.

### Documentation Tables & Spec Lists
- **Table Headers:** Background `#121417`, text `#8792a2`, font `label-sm`, uppercase tracking `0.05em`, border bottom `1px solid #282d37`, height `32px`.
- **Table Rows:** Background `#0c0d0e`, text `#cbd2d9`, font `body-sm`, border bottom `1px solid #181b20`, height `40px`. Hover row shifts to `#181b20`.

### AI Command Prompt & Slash Suggestion Panels
- **Inline Assistant Trigger:** Floating trigger surfaced via `/` command or selection ribbon. Background `#20242b`, border `1px solid #323846`, internal dividers `#282d37`. Monospace indicators for keyboard shortcuts (`CMD+K`, `Tab`) rendered in `label-sm` with background `#181b20`.
- **Diff Blocks:** Side-by-side or inline additions highlighted in muted emerald tint (`rgba(16, 185, 129, 0.12)`); removals flagged in muted rose tint (`rgba(244, 63, 94, 0.12)`); strictly avoid high-saturation floods.