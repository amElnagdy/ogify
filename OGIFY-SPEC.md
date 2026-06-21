# OGify — Build Spec

A dead-simple WordPress plugin that generates a **dynamic Open Graph share image** for Posts
(no manual featured image needed). The card shows the post **title**, the **author's photo + name**,
the **reading time**, and the **site name** — all controlled from a settings page. Author of the
plugin: **Nagdy** (publishes to WordPress.org).

> This file is the complete brief. Build it from scratch following the decisions and standards below.
> **"Simple" here is defined by §0 — it is enforced by concrete rules, not a vibe.** Do not treat
> "dead simple" as license to skip security/validation, and do not gold-plate to look thorough.

---

## 0. How to build this — simplicity is enforced, not vibed
Build with **ponytail** discipline and verify with the **guard skills**. Two failure modes are equally wrong: over-engineering (abstractions nobody asked for) and under-building (skipping the non-negotiables below). Stay between them.

**Ponytail ladder — stop at the first rung that holds:**
1. Does this need to exist at all? Speculative need → skip it (see §14 out-of-scope).
2. Does WordPress core / stdlib already do it? Use it (Settings API, `get_avatar_url`, `wp_mkdir_p`, `wp_remote_get`, `WP_Filesystem`, core `wp-color-picker`/media modal). Don't hand-roll.
3. Can it be a few lines? Then a few lines — no helper class for it.
4. Only then: the minimum code that works.

**Anti-over-engineering rules (hard limits):**
- **No new runtime dependencies. No build step** (no Composer autoloader, npm, webpack, React). Plain PHP + the two bundled fonts + tiny `admin.js`.
- **~6 PHP files as listed in §4** — don't invent more (no `Interface`/`Abstract`/`Factory`/`Container`/`Service` layers, no one-implementation interfaces, no events bus).
- No settings for values that never change; no "extensibility hooks" nobody asked for beyond what §9 lists.
- Shortest working version wins. If a rule here and "simpler" conflict, follow the rule.

**Non-negotiables — do NOT simplify these away (the guard-skill bar):**
- Input **sanitized** on the way in, **escaped** on the way out (`esc_html`/`esc_attr`/`esc_url`); raw `$_POST`/`$_GET` always `wp_unslash()`-ed then sanitized.
- **Nonce + capability** check on every write (settings save, profile save).
- **Never fatal:** guard for missing GD, failed avatar fetch, missing font, unwritable uploads dir — degrade gracefully.
- Translatable strings (Text Domain `ogify` = slug); no SQL; ABSPATH guard in every file.

**Before calling it done (the builder must run these and fix to clean):**
- `wp plugin check ogify` → **0 errors** (filter out the composer/vendor noise — there is no vendor dir here, so 0 means 0).
- Self-review with **wp-guard** (escaping/nonces/caps/i18n), **clean-code-guard** (naming/DRY/dead code), and **ponytail-review** (delete anything speculative). Apply findings.

---

## 1. Goal & why
Default OG output from SEO plugins (Yoast/Rank Math) is static and ugly, and creating a featured
image per post is tedious. OGify auto-renders a good-looking 1200×630 share card per Post, so
sharing to Twitter/X, Facebook, LinkedIn, etc. always looks intentional with zero per-post effort.

## 2. Locked decisions (from requirements interview)
- **Scope:** Posts only (`post`) for MVP. Make the post-type list a setting (default `['post']`) so it's trivially extensible later — but ship with Posts only.
- **Image engine:** **GD** only (`imagecreatetruecolor`, `imagettftext`). If GD is unavailable, show an admin notice and no-op (never fatal).
- **Generation & serving:** **lazy generate-on-render, cached as a real file.** When the single-post page renders, compute a content hash; if `uploads/ogify/{post_id}-{hash}.png` is missing, generate it with GD and write it; then output that file URL in the OG tags. Crawlers fetch a stable, static file. **No custom endpoint, no rewrite rules, no batch "regenerate all."** A title/author/setting change changes the hash → next view regenerates automatically.
- **SEO relationship:** **Standalone** — OGify outputs the full OG + Twitter tag set itself on single Posts. (Also include a safety filter to replace Yoast/Rank Math `og:image` if they're active — see §8 — so there's never a conflicting image even if the user forgets to disable their SEO plugin's social output.)
- **Layout:** "badge split" — reading-time badge top-left, title in the middle, author chip (photo + name, with site name) bottom-left. Left-aligned. See §6.
- **Author photo source:** **per-user upload** (a field on the user profile) → fallback to `get_avatar_url()` (Gravatar / avatar-override plugins) → fallback to a site-wide default image set in settings → if still none, hide the photo (chip shows name only).
- **Style controls:** background **solid color** OR **two-color gradient**; **text color**; **accent color** (for the reading-time badge). (No background-image upload in MVP.)
- **Element toggles:** each of {author photo, author name, reading time, site name} can be shown/hidden in settings.
- **Distribution:** single **free** plugin on WordPress.org. Bundle the **Inter** font (SIL OFL, GPL-compatible).
- **Dimensions:** 1200×630 PNG.

## 3. Naming & metadata (follow these exactly)
- **Slug / folder / Text Domain:** `ogify` (Text Domain MUST equal the slug — .org serves language packs by slug).
- **Main file:** `ogify.php`.
- **Namespace:** `OGify\`.
- **Prefix:** constants `OGIFY_` (e.g. `OGIFY_VERSION`, `OGIFY_FILE`, `OGIFY_PATH`, `OGIFY_URL`); functions/hooks/options `ogify_`.
- **Plugin header:** `Plugin Name: OGify – Dynamic Open Graph Image Generator`, `Description`, `Version: 1.0.0`, `Author: Nagdy`, `Author URI: https://nagdy.me`, `License: GPL-3.0-or-later` (or GPLv2+), `License URI`, `Text Domain: ogify`, `Requires at least: 6.0`, `Requires PHP: 7.4`.
- **Display name vs. identifiers:** the full `Plugin Name` above is the public **display name** (match it in the `readme.txt` title: `=== OGify – Dynamic Open Graph Image Generator ===`). All code identifiers in this section — slug, Text Domain, namespace, `OGIFY_` prefix — are **unchanged**; the longer name is display-only.
- First line of every PHP file: `if ( ! defined( 'ABSPATH' ) ) { exit; }`.

## 4. Plugin structure
```
ogify/
  ogify.php                 # header, constants, ABSPATH guard, GD check, bootstrap
  uninstall.php             # delete options, cached images dir, user meta
  includes/
    Plugin.php              # wires hooks, instantiates the pieces
    Settings.php            # settings page (Settings API), option schema, sanitize
    Profile.php             # per-user OG photo field (user meta)
    ReadingTime.php         # word-count → minutes
    CardImage.php           # GD render + hash + cache (the core)
    MetaTags.php            # wp_head OG/Twitter output (single posts)
  assets/
    fonts/Inter-Bold.ttf
    fonts/Inter-Regular.ttf
    fonts/OFL.txt           # font license (required for .org)
    admin.css
    admin.js                # wp-color-picker + media uploader wiring
  languages/ogify.pot
  readme.txt                # WP.org readme (Stable tag, changelog, etc.)
  README.md
  .distignore               # exclude dev/tooling from the .org package
```
No build step (no React/webpack). Settings UI is a plain PHP page using core `wp-color-picker` and the media modal.

## 5. Reading time (`ReadingTime.php`)
- `minutes = max( 1, ceil( word_count / wpm ) )`, `wpm` from settings (default **200**).
- Word count from `post_content`: `wp_strip_all_tags( strip_shortcodes( $content ) )` then `str_word_count()` (use a multibyte-safe count: `preg_match_all('/\p{L}+/u', ...)` for non-Latin content).
- Output string: `sprintf( _n( '%d min read', '%d min read', $minutes, 'ogify' ), $minutes )` (translatable).

## 6. The card (`CardImage.php`) — GD rendering
**Canvas:** 1200×630, `imagecreatetruecolor`, `imagesavealpha`. Fonts via `imagettftext` (Inter-Bold for title/badge, Inter-Regular for meta).

**Background:** solid → fill with `bg_color`. Gradient → interpolate per-row (or diagonal) between `bg_gradient_from` and `bg_gradient_to`.

**Badge split layout (left-aligned, ~80px padding):**
- **Top-left badge:** a pill (filled rounded rect — draw rect + `imagefilledellipse` end-caps) in `accent_color`, containing the reading-time text in a contrasting color. **Do NOT use an emoji** (⏱) — GD/TTF won't render it; either omit the icon or draw a simple clock with `imagearc`/`imageline`. Text only ("6 MIN READ") is fine.
- **Middle:** post title in Inter-Bold, large (~64px), color `text_color`. **Word-wrap** to the content width (~1040px) by measuring with `imagettfbbox`; cap at ~3 lines and ellipsize overflow. Auto-shrink font a step or two if the title is very long.
- **Bottom-left author chip:** circular avatar (~96px) + author name (Inter-Bold ~32px) on line 1 and site name/domain (Inter-Regular ~26px, slightly muted) on line 2. Respect element toggles (hide photo/name/site-name/reading-time if disabled).

**Circular avatar:** load source (per-user upload attachment → `get_avatar_url($author_id, ['size'=>256])` → site default), resample to a square, then mask to a circle (create a transparent layer, draw the image, and clip using an alpha circle: `imagecolorallocatealpha` + per-pixel alpha, or draw image then overlay a same-bg "donut" — simplest reliable approach: build a circular alpha mask and copy). Provide a clean fallback if the remote avatar fetch fails (use `wp_remote_get` for Gravatar; cache the bytes).

**Output:** `imagepng` to `uploads/ogify/{post_id}-{hash}.png` (create dir with `wp_mkdir_p`). 8-bit PNG is fine; keep file < ~1MB.

## 7. Caching & invalidation
- `hash = md5( serialize([ post_title, reading_minutes, author_display_name, author_photo_id_or_url, author_photo_mtime, $settings ]) )`.
- Filename `{$post_id}-{$hash}.png` under `wp_upload_dir()['basedir'].'/ogify/'`.
- On render (see §8): if the file is missing, generate it; then **delete other `{$post_id}-*.png`** files (cleanup stale hashes).
- URL for tags: `wp_upload_dir()['baseurl'].'/ogify/'.$filename`.
- This gives automatic invalidation: change anything that feeds the hash → new filename → regenerated on next view. No cron, no batch job.

## 8. Meta tags (`MetaTags.php`) — standalone output
Hook `wp_head` (priority ~5). Only when `is_singular()` for an enabled post type and the post is public. Ensure the cached image exists (generate if missing), then output (all values `esc_attr`/`esc_url`):
- `og:type` = `article`, `og:title` (post title), `og:description` (post excerpt or trimmed content), `og:url` (permalink), `og:site_name`.
- `og:image`, `og:image:secure_url`, `og:image:width` `1200`, `og:image:height` `630`, `og:image:type` `image/png`, `og:image:alt` (title).
- `article:published_time`, `article:modified_time`.
- `twitter:card` = `summary_large_image`, `twitter:title`, `twitter:description`, `twitter:image`, `twitter:image:alt`.

**SEO-plugin safety (even in standalone):** also add filters so OGify's image wins if an SEO plugin is active —
`add_filter('wpseo_opengraph_image', fn() => $url)` and `wpseo_twitter_image`; Rank Math `rank_math/opengraph/facebook/og_image` and `.../twitter/twitter_image`. Document that users should disable their SEO plugin's social output for a fully clean `<head>`.

## 9. Settings page (`Settings.php`)
Single option `ogify_settings` (array). Page under **Settings → OGify** (`add_options_page`), Settings API, one `register_setting` with a sanitize callback. Live preview is optional/nice-to-have (render a sample card), not required for MVP.

| Key | Type | Default | Control | Sanitize |
|---|---|---|---|---|
| `enabled` | bool | `true` | checkbox | bool |
| `post_types` | string[] | `['post']` | checkboxes of public types | whitelist vs `get_post_types(['public'=>true])` |
| `show_author_photo` | bool | `true` | checkbox | bool |
| `show_author_name` | bool | `true` | checkbox | bool |
| `show_reading_time` | bool | `true` | checkbox | bool |
| `show_site_name` | bool | `true` | checkbox | bool |
| `bg_type` | enum | `solid` | radio: solid/gradient | whitelist |
| `bg_color` | hex | `#0f172a` | wp-color-picker | `sanitize_hex_color` |
| `bg_gradient_from` | hex | `#0f172a` | color | `sanitize_hex_color` |
| `bg_gradient_to` | hex | `#3b0764` | color | `sanitize_hex_color` |
| `text_color` | hex | `#ffffff` | color | `sanitize_hex_color` |
| `accent_color` | hex | `#22d3ee` | color | `sanitize_hex_color` |
| `reading_wpm` | int | `200` | number | `absint`, clamp 50–600 |
| `site_name_text` | string | `get_bloginfo('name')` | text | `sanitize_text_field` |
| `default_author_photo` | int (attachment id) | `0` | media uploader | `absint` |

Settings page save is handled by the Settings API (nonce automatic). Enqueue `wp-color-picker` (+ its CSS) and `wp_enqueue_media()` only on the OGify settings screen.

## 10. Per-user author photo (`Profile.php`)
- Add a field to user profile (`show_user_profile` + `edit_user_profile`): "OGify share photo" with a media-modal picker (button + hidden input for attachment id + thumbnail preview).
- Save on `personal_options_update` + `edit_user_profile_update`: verify nonce, `current_user_can('edit_user', $user_id)`, store `absint` attachment id in user meta `ogify_author_photo`.
- Resolution order in the card: `ogify_author_photo` → `get_avatar_url($author, ['size'=>256])` → `default_author_photo` → none.

## 11. Activation / uninstall
- No DB tables. On uninstall (`uninstall.php`): `delete_option('ogify_settings')`, delete the `uploads/ogify/` directory (via `WP_Filesystem`), and delete the `ogify_author_photo` user meta for all users.
- GD presence: check `function_exists('imagecreatetruecolor')` and `imagettftext`; if missing, `admin_notice` and skip generation (no fatals).

## 12. Coding standards (the maintainer's bar — enforce these)
- **`wp plugin check ogify` → 0 errors** before shipping (WordPress.org Plugin Check is the gate).
- Text Domain = slug `ogify`; every user-facing string translatable; ship `languages/ogify.pot`.
- **Escape on output** (`esc_html`, `esc_attr`, `esc_url`), **sanitize on input**, **nonce + capability** checks on every write (settings + profile).
- No direct SQL (options API + user meta only). No raw `$_POST/$_GET` without `wp_unslash()` + sanitize.
- File writes via GD (`imagepng`) are fine; directory creation via `wp_mkdir_p`; deletions via `WP_Filesystem`.
- `.distignore` excludes dev/tooling (`.git`, `.github`, `.distignore`, `node_modules`, `*.md` design docs, etc.) but **never** the runtime assets (fonts!).
- Bundle the font **license** (`assets/fonts/OFL.txt`).
- Keep it lazy: no abstractions with one implementation, no settings for values that never change, shortest diff that works.

## 13. Acceptance criteria (manual QA)
1. Publish a Post → view single-post source: OG + Twitter tags present; `og:image` points to `…/uploads/ogify/{id}-{hash}.png`; the file exists and is a 1200×630 PNG.
2. Facebook Sharing Debugger and Twitter/X Card Validator render the card correctly.
3. Change the title → `og:image` hash/URL changes → new image generated; old `{id}-*.png` cleaned up.
4. Change a setting (e.g. background color) → next view of any post regenerates with the new style (no manual step).
5. Author with a custom OGify photo → it shows; without → Gravatar/avatar; without → site default; without → photo hidden, name still shows.
6. Very long title wraps to ≤3 lines and ellipsizes cleanly; non-Latin titles count words and render.
7. Reading time = `ceil(words/wpm)`, minimum 1 min, reflects the WPM setting.
8. GD disabled → admin notice, no fatal, tags simply omit/fallback gracefully.
9. Element toggles hide the right pieces.
10. `wp plugin check ogify` → 0 errors.

## 14. Out of scope (MVP) / future / possible pro
Per-post overrides (custom title/disable per post), additional layouts & fonts, background image/texture, site logo + category label on the card, pages/CPT support beyond the setting, live/WYSIWYG (as-you-type) preview in settings, WebP output, CDN offload, A/B variants. Keep all of this OUT of v1.0.0.

---

## 15. Hardening & polish addendum (v1.0.0)
Distilled from researching ogify.dev and the OG-image landscape. These are the **only** net-new items beyond §1–§14 — the rest of that landscape is already covered above, deferred to §14, or impossible in GD. **Every item is a few-line diff inside an existing §4 file: no new file, dependency, abstraction, or build step.** Build each one *with* the section it extends; the same §0 non-negotiables and anti-over-engineering limits apply.

### 15.1 Card rendering hardening (`CardImage.php`)
| # | Item | Extends | How (the lazy version) |
|---|------|---------|------------------------|
| 1 | **Title contrast guard** | §6, §9 | §9 exposes `text_color` and the background as *unrelated* pickers — a light-on-light combo silently renders an invisible title (the worst failure for a share-image tool: invisible until shared). Compute luminance contrast of `text_color` vs the background (test both gradient stops); if below ~3:1, draw the title twice — a 1px-offset semi-transparent dark copy behind the main text. No new setting. |
| 2 | **Badge text auto-color** | §6 | §6 wants "a contrasting color" for the reading-time pill but fixes none. Pick black or white from `accent_color` luminance: `(R*299 + G*587 + B*114)/1000 > 128 ? black : white`. One line; legible badge for any accent. |
| 3 | **Sanitize the drawn title** | §5, §6 | §5 strips tags/shortcodes only for the word *count*. Run `wp_strip_all_tags( strip_shortcodes( $title ) )` + `html_entity_decode()` on the title string *before* `imagettftext`, or a raw shortcode/entity draws as literal garbage on the card. |
| 4 | **Word-safe, mb-safe ellipsis** | §6 | §6 says "ellipsize overflow" — a naive `substr` splits a UTF-8 codepoint (mojibake) or mid-word. Ellipsize at the last *whole word* that fits and re-measure with `…` appended via `imagettfbbox`, inside the wrap loop you already build. Free. Defends acceptance #6. |
| 5 | **Supersample the avatar circle only** | §6 | GD circles aren't anti-aliased and the avatar is a focal point. Build the circular alpha mask at ~4× (e.g. 384px) and `imagecopyresampled` down to ~96px for a smooth edge. **Bound it to the avatar buffer — never the 1200×630 canvas** (FreeType already AAs text; whole-canvas 4× = 16× memory for zero gain). |
| 6 | **Bounded avatar fetch** | §6, §2 | Lazy generate-on-render means the *first real visitor* pays the Gravatar/default fetch inline. Pass `['timeout' => 3]` to `wp_remote_get`; treat any failure / non-200 / non-image as the next fallback in §10's chain. Reinforces §0 "never fatal." |
| 7 | **Atomic PNG write** | §7 | "Generate if missing" races when a freshly-published post is hit by the social scraper *and* a visitor at once → two `imagepng` to the same path → a half-written PNG cached by Facebook (the exact failure this plugin exists to prevent). Write to a temp file in `uploads/ogify/`, then `rename()` into `{id}-{hash}.png` (atomic on the same filesystem). |
| 8 | **Measure-then-place title block** | §6 | Anchor the bottom author chip relative to the *measured* wrapped-title height, not magic y-constants, so 1-line and 3-line titles both lay out cleanly. **A private function inside `CardImage.php` returning `[lines, totalHeight]` — NOT a `TextBox`/`Layout` class or a new file** (that would cross §0's no-one-implementation-abstraction line). |

### 15.2 Meta output hygiene (`MetaTags.php`)
| # | Item | Extends | How |
|---|------|---------|-----|
| 9 | **Clean + cap the description** | §8 | §8 names the `og:description` / `twitter:description` source ("excerpt or trimmed content") but not its hygiene. Emit: `wp_strip_all_tags` + `strip_shortcodes` + `html_entity_decode`, collapse whitespace, trim to ~200 chars on a word boundary. Fall back cleanly when the excerpt is empty. (Text twin of item 3 — ship them together.) |

### 15.3 Settings preview (`Settings.php`)
| # | Item | Extends | How |
|---|------|---------|-----|
| 10 | **Static server-side preview** | §9 | Render the *real* card (via `CardImage`) with sample data — a stand-in title + the current admin as author — to `uploads/ogify/preview.png`, and show it as a plain `<img>` (cache-busted by the settings hash) on the OGify settings screen. Regenerate on settings save; an optional "Refresh preview" button is fine. **Reuses `CardImage` wholesale; no new file/dep/build.** |

This **overrides** §9's "live preview is optional/nice-to-have" hedge: the *static, server-rendered* preview is **in v1**; only the *live/as-you-type (WYSIWYG)* preview stays out (§14). Do **not** build a separate HTML/CSS preview twin — it would drift from the real GD output and mislead the user. One honest renderer, shown after save.

### 15.4 Engine decision (confirmed)
**GD stays as the v1 engine.** The generate-once-cache-to-file model (§2/§7) renders each card a single time per content-hash and serves a static file thereafter, so GD's per-render speed is amortized to near-zero — **GD is not a throughput bottleneck.** GD's *design ceiling* (no soft shadows / transforms / glassmorphism / color emoji / multi-script) sits at the §14 scope line and blocks nothing in v1. **Documented future lever — build none of it now:** a richer renderer belongs behind `extension_loaded('imagick')` as a branch *inside* `CardImage.php` (that file is the seam; it needs no abstraction until a second implementation exists). A headless/Satori engine (the ogify.dev approach) is permanently out — it needs Node / a browser / a build step, which §0 forbids and which is precisely why ogify.dev is a JS library rather than a WordPress plugin.

### 15.5 Acceptance additions (extend §13)
11. Misconfigured colors (e.g. light `text_color` on a light background) still produce a **legible title** (the contrast shadow engages); the reading-time badge text stays readable for any `accent_color`.
12. The OGify settings screen shows a **preview image** reflecting the current settings; changing a color and saving updates it.
13. A title containing a shortcode / HTML entity, and a non-Latin or emoji-bearing title, both render without literal tags or mojibake, ellipsized on a word boundary.
