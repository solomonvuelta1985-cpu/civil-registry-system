# Modern Civil Registry Dashboard

The dashboard uses a light, statistics-first layout inspired by the supplied reference screenshots. The Civil Registry identity, records, permissions, and existing workflows remain in place.

## Theme and boundaries

- `admin/dashboard.php` opts in with `body.dashboard-page` and `data-sidebar-breakpoint="1024"`.
- `assets/css/dashboard.css` owns the dashboard presentation and its scoped navigation, calendar, notes, and dialogs. It loads after the shared sidebar stylesheet. Other screens do not load it.
- Background: `#f7f9fc`; panels: white; borders: `#e4e9f1`; primary text: `#152139`; secondary text: `#526078`; primary action: `#4f46e5`.
- Panels have 16px corners and subtle shadows. Typography uses the existing Inter helper with system fallbacks. Counts use tabular numerals and render immediately without count-up animation.
- The sidebar and top bar are white, with dark text and a soft blue active item. Branding, links, feature gates, profile menu, and saved sidebar preference remain in place.

## Content order

1. Welcome banner: seal, Civil Registry Records / iSCAN eyebrow, Civil Registry Dashboard title, greeting, date, Records link, and View Reports action. The banner uses a navy left panel with an angled divider, a white action area, and a navy bottom rule.
2. Six summary cards: Total Records, This Month, Births, Marriages, Deaths, and Marriage Licenses. Each category also shows its monthly count and existing monthly comparison. Totals refer to active records, as in the existing queries.
3. Monthly Registration Trends and Certificate Distribution. The trend retains four series for six months, with an expandable data table. The doughnut includes a central total and a count/percentage list. Empty distributions show zero and a neutral ring without calculated percentages.
4. Recent Activity: registry/person, category, creator/role, relative time, and an accessible View link. Type filters announce empty results.
5. Office Workspace: calendar and Official Notes, including create/edit, full lists, and pinned notes.
6. System Overview: active users, last login, failed logins, system status, Admin document integrity, and conditional double-registration information.

## Category colors

| Metric | Color | Soft background |
| --- | --- | --- |
| Total Records | `#4f46e5` | `#f1f2ff` |
| This Month | `#0d9488` | `#edfbf8` |
| Births | `#2563eb` | `#eff5ff` |
| Marriages | `#7c3aed` | `#f6f2ff` |
| Deaths | `#64748b` | `#f1f5f9` |
| Marriage Licenses | `#d97706` | `#fff9ed` |

Colors remain consistent across statistics, charts, distribution markers, and record badges. Green, amber, and red provide relevant trend or system feedback.

## Responsive behavior and accessibility

- Statistics: six columns at 1600px and above, three at 769–1599px, two at 360–768px, one below 360px.
- At 1024px and below, the dashboard uses the mobile header and overlay sidebar. Desktop content margins follow the shared sidebar width variables and become zero on mobile.
- The sidebar script reads the optional breakpoint attribute. Its previous 768px JavaScript behavior remains the default elsewhere. Dashboard collapse preferences survive desktop/mobile resizing.
- At 768px and below, charts and workspace panels stack and activity becomes compact cards. Long names wrap; numeric totals remain available in full.
- Keyboard focus is visible. Record actions are links, filters expose `aria-pressed`, chart values have HTML equivalents, and decorative icons are hidden from assistive technology.
- Reduced-motion preferences disable transitions and chart animations. The preloader also hides without relying on a transition-end event in this mode.

## Compatibility and verification

No new public APIs, migrations, packages, or record-selection queries. Event/note endpoints, request fields, CSRF headers, authentication, role gates, and record destinations remain in place.

Verification uses PHP syntax checks, before/after query-and-data comparisons, and isolated rendered fixtures at 1920, 1440, 1024, 768, and 390px, plus 350px. Scenarios cover empty records, long names, large totals, Admin/Encoder/Viewer navigation, saved sidebar preferences, chart data, activity filters, and event/note form requests. The local installation reported a database connection error during this work; fixture-backed form persistence does not establish live database persistence.
