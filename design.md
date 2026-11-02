# Universal Driveshafts — Design System

> Living reference for **universaldriveshafts.com** / the `uds-app` rebuild.
> Last revised 2026-09-09, after the design overhaul.
>
> **Source of truth:** `public/css/uds-2026.css` (design system + global chrome).
> `public/css/uds.css` holds the remaining un-migrated page modules and loads first.
> Icons: `resources/views/components/icon.blade.php`. Behaviour: `public/js/uds.js`.

---

## 1. Brand

| | |
|---|---|
| **Legal name** | Universal Driveshafts LLC |
| **Monogram** | UDS |
| **Category** | Driveshaft manufacturing plus parts e-commerce |
| **Positioning** | Precision-engineered, built in the USA, shipped fast |
| **Catalogue** | 2,327 parts across 39 categories |
| **Locations** | Odessa, FL (OEM parts, active) · Tampa, FL (workshop, coming soon) |
| **Contact** | (813) 524-6491 Odessa · (813) 644-5707 Tampa · inquiry@universaldriveshafts.com |
| **Social** | instagram.com/universaldriveshafts · facebook.com/universaldriveshafts · tiktok.com/@uds247 |

### Voice

Technical and plain-spoken. Lead with specs, series numbers and fitment. Sentence case
everywhere except small mono labels. Trust signals stated as facts, not slogans.

**Banned in copy:** "elevate", "unleash", "seamless", "cutting-edge", "unparalleled",
"next-gen", "revolutionise", exclamation marks in confirmations, Title Case headings,
and the em-dash character (use a comma, colon, period or parentheses instead).

### Logo

`public/img/uds-logo.webp`. "UDS" monogram pierced by a red driveshaft graphic, beside
"UNIVERSAL" (blue) / "DRIVESHAFTS" (red). Header 38px, footer 30px, mobile 32px.
On dark surfaces it is currently rendered white via `filter: brightness(0) invert(1)`;
a purpose-made mono SVG is still outstanding.

---

## 2. Tokens

All tokens live in `:root` in `uds-2026.css`.

### Colour

Blue carries the brand. Teal is the single accent and is reserved for go / in-stock /
positive states. Red is reserved for errors. **Do not introduce a fourth hue.**

| Token | Value | Use |
|---|---|---|
| `--blue` | `#1246ab` | Primary. Buttons, links, active state, focus |
| `--blue-600` / `--blue-700` / `--blue-ink` | `#1657cf` / `#0e3a8c` / `#0b2455` | Hover, pressed, text on light blue |
| `--blue-050` / `--blue-100` / `--blue-200` | `#eef3fc` / `#dce7f8` / `#b7cdf0` | Tints, focus ring, hover borders |
| `--teal` / `--teal-300` / `--teal-050` / `--teal-ink` | `#1f9e88` / `#54c6b1` / `#e3f4f0` / `#0c4d43` | Accent; `-300` for text on dark |
| `--steel` | `#0b1a33` | The dark surface: promo bar, hero, bands, footer |
| `--ink` / `--ink-2` / `--ink-faint` | `#141b28` / `#3a4557` / `#697687` | Heading / body / meta. All pass AA on white |
| `--paper` / `--paper-2` / `--paper-3` | `#ffffff` / `#f6f8fc` / `#eef2f8` | Page, tinted section, plate |
| `--line` / `--line-2` / `--line-strong` | `#e4e8f0` / `#eef1f7` / `#d4dae6` | Hairline, faint divider, input border |
| `--ok` / `--warn` / `--err` | `#1a7f4b` / `#9a6b12` / `#c02a2a` | Status, each with a `-bg` tint |

**Dark band recipe** — used by the hero, CTA bands, builder band, checkout hero:

```css
background:
  radial-gradient(70% 130% at 12% 0%, rgba(18,70,171,.55), transparent 60%),
  linear-gradient(120deg, #0b1a33, #153a86);
```

### Type

Self-hosted woff2 in `public/fonts/`, latin subset, `font-display: swap`.
Poppins 700 and the Hanken Grotesk variable file are preloaded.

| Token | Family | Use |
|---|---|---|
| `--font-h` | **Poppins** 500/600/700/800 | Headings, buttons, nav, labels, card titles |
| `--font-b` | **Hanken Grotesk** variable 100-900 | Body copy, inputs, long-form prose |
| `--font-m` | **IBM Plex Mono** 400/500/600 | SKUs, prices, measurements, spec keys, table headers, order numbers, counts |

Scale is fluid: `--fs-display`, `--fs-h1`, `--fs-h2`, `--fs-h3`, `--fs-lead`,
`--fs-body` (17px). Headings are `700`, `line-height: 1.12`, `letter-spacing: -.02em`,
`text-wrap: balance`. Body is `line-height: 1.62`, `text-wrap: pretty`.

Anything numeric gets `--font-m` plus `font-variant-numeric: tabular-nums`. That is the
single strongest "engineered" signal on the site: use it.

### Shape, space, elevation, motion

- **Radius lock:** `--r-xs 8` inner chips · `--r-sm 10` inputs and small buttons ·
  `--r 12` cards · `--r-lg 16` panels and media · `--r-xl 22` large bands · `--r-pill` buttons and avatars.
- **Layout:** `--wrap 1220px`, `--wrap-narrow 900px`, `--wrap-prose 68ch`,
  `--gutter clamp(1.1rem, .6rem + 2vw, 1.9rem)`, `--section-y clamp(3.5rem, 2rem + 6vw, 6.75rem)`.
- **Elevation:** three blue-tinted shadows, one light source from the top:
  `--sh-1` resting, `--sh-2` hover, `--sh-3` overlay. Focus is `--sh-focus`.
- **Motion:** `--ease cubic-bezier(.16,1,.3,1)`; `--dur-1 .14s` press, `--dur-2 .24s` hover,
  `--dur-3 .42s` reveal. Everything is gated behind `prefers-reduced-motion`.

---

## 3. Components

Global chrome, all `u`-prefixed and independent of the legacy sheet.

| Component | Class | Notes |
|---|---|---|
| Promo bar | `.upromo` | Steel, free-shipping message, locations dropdown, utility links |
| Header | `.uhead` | Sticky, blurred, single 74px row, `is-stuck` shadow on scroll |
| Primary nav | `.umenu` / `.umega` | Three mega menus plus two plain links, hover + keyboard + touch |
| Mobile drawer | `.udrawer` | Right slide-over, `<details>` groups, scroll-locked |
| Footer | `.ufoot` | Four columns, contact block, trademark note, legal row, social |
| Buttons | `.btn` + `-primary` `-teal` `-ghost` `-line` `-lg` `-sm` `-block` | Pill, `translateY(-2px)` hover, `scale(.985)` press |
| Hero | `.uhero` | Asymmetric split, dark band, grid texture, max 4 text elements |
| Assurance strip | `.ustrip` | Four hairline-divided cells, no card chrome |
| Section head | `.s-head` / `.s-head--between` | Optional trailing link |
| Label | `.kicker` | The only eyebrow style. Max one per three sections |
| Product card | `.uprod` | SKU in mono, no fake ratings |
| Product rail | `.urail` | Horizontal scroll-snap, used instead of a second grid |
| Category tile | `.ucat`, `.uhubcard` | Image-led, count in mono |
| Decision panels | `.upaths` / `.upath` | The custom-vs-premade fork |
| Feature row | `.ufeature` | Asymmetric 1.15/0.85 text and media |
| Dark band | `.uband`, `.ctaband`, `.premade-band`, `.uendcta` | Rounded steel bands |
| Product page | `.pdp` | Gallery, buy box, spec cards, async interchange |
| Parts table | `.ptbl` | Steel header, mono, hairlines between rows only |
| Cart | `.cartline`, `.cartsum`, `.freebar` | Live totals, no page reload |
| Forms | `.fitform`, `.field`, `.co-grid`, `.uqty` | Label above input, error below, no placeholder-as-label |
| Guides | `.guidewrap`, `.gside`, `.sbcard`, `.gdiag`, `.measitem` | Sticky sidebar |

Two self-contained tools keep their own markup and are pulled onto the system by
overriding their local custom properties: **checkout** (`.ck`) and the
**driveshaft builder** (`.ubs-dsb-wrap`). Do not fork their markup; retune the tokens.

### Icons

`<x-icon name="..."/>` renders an inline Phosphor path (MIT) at `1em`, `currentColor`.
About 40 glyphs plus Instagram, Facebook, TikTok and WhatsApp marks from Simple Icons.
**No emoji anywhere in customer-facing markup.** Add new glyphs to the component's map
rather than pasting SVG into a view.

---

## 4. Rules

1. One accent. Blue leads, teal supports, red means error. Nothing else.
2. One radius scale, per the lock above.
3. Numbers are mono and tabular. Always.
4. Sentence case in visible headings.
5. No em-dashes in copy.
6. No emoji. Use `<x-icon>`.
7. No fake data: no invented ratings, no `99.99%`, no placeholder names.
8. No dead `href="#"` links. Link it or drop it.
9. Every image gets real alt text. One `<h1>` per page.
10. Motion must be motivated and must collapse under `prefers-reduced-motion`.
11. Do not change route slugs, form field names or analytics hooks.

---

## 5. Accessibility

- Skip link, visible `:focus-visible` ring (2px blue, 2px offset) on every interactive element.
- `--ink-2` 8.9:1 and `--ink-faint` 4.6:1 on white; both pass AA for body text.
- White on `--blue` is 7:1. Teal is never used as text on white.
- Reduced-motion media query disables all transitions, animations and scroll reveals.
- Nav, drawer and menus are keyboard operable; menus close on Escape and focus-out.

---

## 6. Outstanding

1. Ship a true monochrome white logo SVG and stop filtering the colour file.
2. Reconcile the logo's red with the teal UI accent, or formally scope red to the logo.
3. Proper favicon set (`.ico`, sized PNG, SVG) instead of the full-colour WebP.
4. Migrate the remaining page modules out of `uds.css` and retire the file.
5. Product names in the catalogue repeat the SKU and carry en-dashes; clean at the data layer.
6. Admin panel (`resources/views/admin/`) has not been through the overhaul.

---

## Sources

`public/css/uds-2026.css` · `public/css/uds.css` · `public/js/uds.js` ·
`resources/views/components/icon.blade.php` · `resources/views/layouts/app.blade.php` ·
`config/uds.php` · live reference `https://universaldriveshafts.com`
