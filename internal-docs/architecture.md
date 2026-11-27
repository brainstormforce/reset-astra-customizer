# Architecture — Astra Customizer Reset

## Overview

Astra Customizer Reset is a WordPress plugin that follows standard WordPress plugin architecture patterns.

## Entry Point

The plugin entry point is `class-astra-theme-customizer-reset.php`, which:
1. Returns early unless Astra is the active theme
2. Defines plugin constants (version, directory, URI)
3. Loads the classes in `classes/` and calls `Astra_Theme_Customizer_Reset::get_instance()` at plugin load

`Astra_Theme_Customizer_Reset` (setup) starts the three feature classes:
- `Astra_Theme_Customizer_Reset_Publish_Now`: Reset and Publish Now
- `Astra_Theme_Customizer_Reset_Preview`: Reset in Preview. It must load before the theme, because it adds the `pre_option_astra-settings` filter for the defaults request.
- `Astra_Theme_Customizer_Reset_Backup`: the backup row

## Request Lifecycle

1. WordPress loads the plugin via `class-astra-theme-customizer-reset.php`
2. Constants are defined for use throughout the plugin
3. Main class is instantiated/loaded on the appropriate hook
4. Classes register their own hooks and filters
5. Frontend/admin output is rendered when WordPress fires the relevant hooks

## Key Patterns

- **Hook-based architecture:** All functionality is attached via `add_action()` and `add_filter()`
- **Class autoloading:** Classes are loaded via `require_once` in the main plugin file or loader class
- **Separation of concerns:** Admin and frontend code are separated into different classes/directories
- **WordPress Customizer integration:** Settings are registered via the Customizer API where applicable

## Reset Flow

Both resets must give the same result: the `astra-settings` option with only `theme-auto-version` and `astra-addon-auto-version`, plus the palette, typography presets, logo and Astra admin settings removed.

- **Reset and Publish Now** (`Astra_Theme_Customizer_Reset_Publish_Now::ajax_customizer_reset`): writes that result directly, dismisses the session autosave, then reloads.
- **Reset in Preview** (JS `resetInPreview`):
  1. Fetches defaults from `astra_customizer_reset_defaults`. That request reads `astra-settings` as reset (`pre_option_` filter, writes blocked), because Astra computes many defaults from its saved compatibility flags.
  2. Sets every Customizer setting that has a default and marks it changed, so it is in the changeset even when the value is the same.
  3. Sets the `astra-customizer-reset-pending` flag: `{timestamp}|{keys}`. The keys are settings without a default changed before the reset.
  4. Preview (`preview_pending_reset`) and publish (`publish_pending_reset`, `customize_save_after`) in `Astra_Theme_Customizer_Reset_Preview` keep only the version keys and the changeset keys, minus those listed keys. This removes settings without a default (breadcrumb, blog filter, Astra's `isset()` compatibility flags).
  5. After a publish that contained the reset, the Customizer reloads.

## Dependencies

- WordPress core (required)
- Astra theme (recommended/required for full functionality)
- No external PHP dependencies beyond WordPress
