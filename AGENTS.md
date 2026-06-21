# AGENTS.md — OGify

**`OGIFY-SPEC.md` (this directory) is the complete, authoritative brief. Read it before writing any code and build exactly to it.** These house rules are the load-bearing constraints distilled from spec §0 and §12 — they apply to every task.

## Simplicity (ponytail — non-negotiable)
- Stop at the first solution that works. Prefer a WordPress core API over hand-rolling (Settings API, `get_avatar_url`, `wp_mkdir_p`, `wp_remote_get`, `WP_Filesystem`, core `wp-color-picker` + media modal).
- **No new runtime dependencies. No build step.** No Composer autoloader, npm, webpack, or React. Plain PHP + two bundled fonts + a tiny `admin.js`.
- **~6 PHP files as listed in spec §4** — do not invent more. No `Interface`/`Abstract`/`Factory`/`Container`/`Service` layers, no one-implementation abstractions, no event bus. A helper is a private method, not a new class/file.
- No settings for values that never change; no extensibility hooks beyond what spec §9 lists. Shortest working diff wins.

## Security (must fix — no exceptions)
- **Escape on output**, context-correct: `esc_html`/`esc_attr`/`esc_url`/`wp_kses_post`. No `echo $var` without an `esc_*` wrapper.
- **Sanitize on input**: `wp_unslash()` first, then the type-correct sanitizer. Never touch raw `$_POST`/`$_GET`/`$_REQUEST`.
- **Every write proves identity + intent**: capability check (`current_user_can`) AND nonce on every settings save and profile save.
- **No SQL** — options API + user meta only. (If SQL ever appears, `$wpdb->prepare()` with placeholders.)
- First line of every PHP file: `if ( ! defined( 'ABSPATH' ) ) { exit; }`.

## i18n
- Every user-facing string wrapped (`__`, `esc_html__`, `esc_attr__`, `_n`, `_x`) with the **literal** text domain `ogify` — never a variable/constant. Translator comments on every placeholder. `_n()` for plurals.

## Naming & targets
- Display name: **OGify – Dynamic Open Graph Image Generator**. Slug / Text Domain: `ogify`. Namespace: `OGify\`. Constants `OGIFY_`; functions/hooks/options `ogify_`.
- Targets **WP 6.0+ / PHP 7.4+** — do not use PHP 8.0+-only syntax (no enums, `match`, named args, nullsafe `?->`, constructor promotion).

## Never fatal
- Guard missing GD/FreeType, failed avatar fetch, missing font file, unwritable uploads dir — degrade gracefully, never throw or fatal.

## Comments & process language
- Comments explain **intent**, not project meta. Do **not** put process words in code comments: no "MVP", "for now", "phase N", "TODO later", and don't use a bare spec section number as the only explanation.

## Workflow
- **Do NOT `git add` or `git commit`.** Leave changes in the working tree; the orchestrator reviews and commits.
- Before finishing, run `php -l` on every PHP file you touched and fix anything it reports. If WordPress-standard PHPCS is available, run it too. (The full `wp plugin check ogify` gate is run by the orchestrator.)
