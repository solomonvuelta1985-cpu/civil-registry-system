# Admin Dashboard — Design Specification

Redesign of [admin/dashboard.php](dashboard.php) from consumer-app Material Design 3 (purple `#6750A4`) to an institutional governmental aesthetic suitable for the Civil Registry Office.

---

## 1. Design Principles

| Principle | Application |
|---|---|
| **Institutional authority** | Deep navy primary, muted gold accent, no bright/playful hues |
| **Document-like formality** | Flat surfaces, 1px borders, small radii, minimal shadows |
| **Information density** | Tabular numerals, uppercase eyebrow labels, clear hierarchy |
| **Official identity** | Agency seal in header, "CIVIL REGISTRY OFFICE" eyebrow label |
| **Visual discipline** | Consistent stat-card accent stripes, no ornamental gradients |

---

## 2. Color Palette — Governmental Navy / Gold

Defined in [admin/dashboard.php:314](dashboard.php#L314) `:root`.

### Core tokens

| Token | Value | Purpose |
|---|---|---|
| `--gov-primary` | `#0f2847` | Deep institutional navy — headers, titles, emphasis |
| `--gov-primary-600` | `#1e3a5f` | Navy hover/active state |
| `--gov-accent` | `#c9a961` | Muted gold — seal ring, key accents, active indicators |
| `--gov-blue` | `#1d4ed8` | Action blue — buttons, links (matches sidebar accent) |
| `--gov-surface` | `#ffffff` | Card surface |
| `--gov-bg` | `#f4f6fa` | Page background (cooler, more formal than #f9fafb) |
| `--gov-border` | `#dde3ed` | Subtle formal borders |
| `--gov-text` | `#0f172a` | Primary text |
| `--gov-text-muted` | `#475569` | Secondary text |
| `--gov-text-subtle` | `#64748b` | Tertiary / meta text |

### Semantic tokens (formal, not saturated)

| Token | Value | Purpose |
|---|---|---|
| `--gov-success` | `#0f766e` | Deep teal (formal) |
| `--gov-warning` | `#b45309` | Deep amber (formal) |
| `--gov-danger` | `#991b1b` | Deep maroon (formal) |

### Stat-card accent stripes (left border, 4px)

Each stat card uses a distinct but muted institutional tone:

| Card | Accent color |
|---|---|
| Total Births | `--gov-primary` (navy) |
| Monthly Births | `--gov-success` (teal) |
| Total Marriages | `#475569` (slate) |
| Monthly Marriages | `--gov-danger` (maroon) |
| Total Deaths | `--gov-warning` (amber) |
| Marriage Licenses | `#4338ca` (deep indigo) |
| Archives | `#6b7280` (gray) |
| Trash | `--gov-accent` (gold) |

### Radii & Shadows

| Token | Value |
|---|---|
| `--radius-xs` | `4px` |
| `--radius-sm` | `6px` |
| `--radius-md` | `8px` (card default — down from 16–28px Material) |
| `--radius-lg` | `10px` |
| `--shadow-sm` | `0 1px 2px rgba(15, 40, 71, 0.04)` |
| `--shadow-md` | `0 2px 6px rgba(15, 40, 71, 0.06)` |

---

## 3. Typography

**Font:** `Inter` (weights 400–800) via `google_fonts_tag()`.

| Element | Style |
|---|---|
| Page title | `28px / 700 / letter-spacing -0.01em` navy |
| Subtitle | `14px / 400` muted slate |
| **Eyebrow label** | `11px / 600` uppercase, `letter-spacing 0.12em`, gold — above title |
| Stat numbers | `32px / 700` tabular-nums |
| Section heading | `16px / 600` navy + small uppercase label (11px, letter-spacing 0.1em, muted) |
| Body | `14px / 400` text |

**Numeric alignment:** `font-feature-settings: "tnum" 1, "ss01" 1` globally so all counters/stats align column-wise.

---

## 4. Header — Official Document Banner

Two-tier formal banner (no gradient):

1. **Top tier** — 3px gold accent bar (`--gov-accent`)
2. **Main banner** (white surface, 1px border-bottom):
   - **Left identity block:**
     - `.header-seal` — 56px circle, 2px gold border, containing [assets/img/LOGO1.png](../assets/img/LOGO1.png)
     - `.header-eyebrow` — "CIVIL REGISTRY OFFICE" (uppercase, gold, with trailing line)
     - `<h1>` "Administrative Dashboard"
     - `<p>` welcome message
   - **Right block:**
     - `.header-date` — formal "Tuesday, 14 April 2026" with calendar icon
     - 4 quick-action buttons (navy outline, gold hover ring)

---

## 5. Icon System

Two libraries, with distinct roles:

### Lucide icons (sidebar only)
Loaded in [includes/asset_urls.php:38](../includes/asset_urls.php#L38) — pinned to `lucide@0.446.0` UMD build.
Used as `<i data-lucide="name"></i>` and initialized via `lucide.createIcons()`.

### Font Awesome 6.4.0 (dashboard content)
Used as `<i class="fas fa-name"></i>`.

### Icon replacements — playful → institutional

| Before (playful/consumer) | After (formal institutional) | Where |
|---|---|---|
| `fa-baby` | `fa-file-lines` | Birth certificate |
| `fa-ring` | `fa-file-signature` | Marriage certificate |
| `fa-cross` | `fa-file-lines` | Death certificate |
| `fa-heart` | `fa-file-signature` | Marriage month card |
| `fa-clipboard-check` | `fa-stamp` | Marriage license (official seal metaphor) |
| `fa-chart-line` | *removed* / `fa-landmark` | Header (replaced by seal image) |
| `fa-calendar-alt` | `fa-calendar-days` | Calendar widget |
| `fa-sticky-note` | `fa-clipboard` | Notes widget |
| `fa-lightbulb` | `fa-circle-info` | Info callouts |
| `fa-search` | `fa-magnifying-glass` | Search bar |
| `fa-layer-group` | `fa-folder-open` | "All" filter |
| `fa-calendar-minus` | `fa-calendar-xmark` | Archives / empty states |
| `fa-clock` *(activity header)* | `fa-clock-rotate-left` | Recent activity section |

### Complete Font Awesome inventory (current dashboard)

**Document / record icons**
- `fa-file-lines` — Birth, Death records
- `fa-file-signature` — Marriage records
- `fa-stamp` — Marriage license (official seal)
- `fa-file-pdf` — PDF quick action
- `fa-clipboard-list` — Monthly license count card

**Navigation / UI**
- `fa-magnifying-glass` — Search input
- `fa-folder-open` — "All records" filter
- `fa-arrow-right` — View-more links
- `fa-chevron-left` / `fa-chevron-right` — Calendar navigation
- `fa-plus` — FAB main, add-event, add-note
- `fa-times` — Modal close / cancel
- `fa-check` — Confirm actions
- `fa-edit` — Edit event
- `fa-trash` — Delete event
- `fa-save` — Update

**Calendar / time**
- `fa-calendar-day` — Date display badge
- `fa-calendar-days` — Calendar widget header
- `fa-calendar-plus` — Add-event modal
- `fa-calendar-check` — Monthly birth stat
- `fa-calendar-xmark` — Archives / empty states
- `fa-clock` — Time displays
- `fa-clock-rotate-left` — Recent activity header

**Status / feedback**
- `fa-shield-halved` — Security status widget
- `fa-circle-info` — Info callouts, tooltips
- `fa-info-circle` — Stat card tooltips
- `fa-check-circle` — Success messages
- `fa-exclamation-triangle` — Alerts
- `fa-exclamation-circle` — Errors
- `fa-minus` — Flat trend indicator
- `fa-spinner fa-spin` — Loading states

**People**
- `fa-users` — Security / users count
- `fa-user` — Author attribution
- `fa-user-tag` — Role badge

**Notes / misc**
- `fa-clipboard` — Notes widget
- `fa-sticky-note` — Notes modal
- `fa-plus-circle` — Activity action type

---

## 6. Component Styling

### Stat cards
- White surface, 1px border, 8px radius
- **Left 4px colored accent stripe** (institutional tone per card)
- Top-right small icon in 32px circle, tinted 8% alpha of accent
- Trend pill (success/danger/neutral) next to number
- `font-variant-numeric: tabular-nums` on numbers

### Search / filter bar
- White surface, 1px border, 8px radius
- `.filter-chip` — pill outline, navy active fill with gold underline indicator
- `.date-range-group` — segmented control (Monthly | Quarterly | Yearly), navy active

### Charts (Chart.js 4.4.0)
- White card with navy section header + gold accent underline
- Palette: navy `#0f2847`, gold `#c9a961`, maroon `#991b1b`, teal `#0f766e`
- Navy tooltips with gold border
- No drop shadows — 1px border + subtle inset

### Activity feed
- Table-row style, subtle hover
- Formal icons in small muted circles

### Calendar / Notes
- Current day: gold ring (outline, not filled)
- Event dots: type-colored
- Pinned notes: gold left border

### Floating Action Button (FAB)
- Main: navy background, gold 2px ring on hover, `fa-plus`
- Sub-actions: white bg, navy icons, gold hover border

---

## 7. Files Changed

- [admin/dashboard.php](dashboard.php) — all visual redesign (CSS tokens, inline `<style>`, HTML structure, icon swaps, Chart.js palette)
- [includes/asset_urls.php](../includes/asset_urls.php) — Lucide CDN pinned to `0.446.0` (fixes sidebar icon regression from unpkg `@latest` alias drift)

**Not modified** (no changes needed):
- [assets/css/sidebar.css](../assets/css/sidebar.css)
- [includes/sidebar_nav.php](../includes/sidebar_nav.php)
- [includes/top_navbar.php](../includes/top_navbar.php)
- PHP queries, data structures, Chart.js data binding
