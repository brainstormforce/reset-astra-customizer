# Astra Customizer Reset

## Project Overview

Astra Customizer Reset is a WordPress plugin by Brainstorm Force. Reset Astra theme customizer options from the customizer interface.

- **Version:** 1.1.0
- **Text Domain:** astra-customizer-reset
- **Main File:** class-astra-theme-customizer-reset.php
- **Requires:** WordPress 5.3+, PHP 5.6+

## Tech Stack

- **Language:** PHP
- **Platform:** WordPress
- **Build:** Grunt (i18n, readme conversion)
- **Coding Standards:** PHPCS with WordPress standards

## Commands

```bash
# Install dependencies
npm install
composer install

# Build minified + RTL assets (run after editing assets/*/unminified/)
npm run build            # = npx grunt minify (rtlcss, uglify, cssmin)

# Build everything: minify + i18n + readme
npx grunt

# Generate translations
npx grunt i18n

# Convert readme.txt to README.md
npx grunt readme

# Run PHPCS
./vendor/bin/phpcs .
```

## Architecture

This is a WordPress plugin following standard WordPress patterns:
- Entry point: `class-astra-theme-customizer-reset.php` (header, constants, `require_once`, init only)
- Classes in `classes/`: `class-astra-theme-customizer-reset.php` (setup, assets, shared helpers), `-publish-now.php`, `-preview.php`, `-backup.php`
- Constants: `ASTRA_CUSTOMIZER_VERSION`, `ASTRA_THEME_CUSTOMIZER_RESET_DIR`, `ASTRA_THEME_CUSTOMIZER_RESET_URI`
- Assets follow the Astra theme layout: edit `assets/js/unminified/` and `assets/css/unminified/`, then run `npm run build` to generate `minified/*.min.*` and the `-rtl` CSS (never edit generated files). Commit the generated files: the WordPress.org deploy does not build. The plugin loads unminified files when `SCRIPT_DEBUG` is true, and `-rtl` CSS when `is_rtl()`
- Classes load when the plugin file loads (before the theme): the preview class adds an early `pre_option_astra-settings` filter
- Follows WordPress Coding Standards (WPCS)

## Conventions

- Use WordPress hooks (`add_action`, `add_filter`) for extensibility
- Prefix all functions and classes to avoid conflicts
- Use `astra-customizer-reset` text domain for all translatable strings
- Escape all output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses`)
- Sanitize all input (`sanitize_text_field`, `absint`, etc.)
- Use nonces for form submissions and AJAX requests
- Follow WordPress PHP coding standards
