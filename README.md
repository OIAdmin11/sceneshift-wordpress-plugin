# Scene Shift WordPress plugin

This directory contains the WordPress plugin distributed to client websites.
It is intentionally outside the SvelteKit `src/` so the plugin can be
versioned, packaged, and shipped independently of the portal.

```text
wordpress/
└── scene-shift-ai-assistant/
    ├── scene-shift-ai-assistant.php   ; entry / plugin header
    ├── uninstall.php                  ; option cleanup
    ├── readme.txt                     ; WP plugin readme (markdown-style)
    ├── includes/
    │   ├── class-plugin.php           ; bootstrap + hooks
    │   ├── class-portal-client.php    ; HTTP client to the Scene Shift portal
    │   ├── class-settings-store.php   ; wp_options wrapper
    │   ├── class-renderer.php         ; shortcodes + footer chat injection
    │   └── class-admin-page.php       ; Settings → Scene Shift admin UI
    └── assets/
        ├── admin.css
        ├── admin.js
        ├── phone.css
        └── phone-switcher.js
```

## What the plugin does

- Connects a WordPress site to a Scene Shift account using a one-time setup
  code generated in the portal. The code is exchanged for a long-lived plugin
  token that is stored only on the WordPress side; the portal stores only its
  SHA-256 hash.
- Mirrors the customer's chat theme + phone schedule from the portal config.
  Visitor pages render from this cached config without making API calls.
- Re-syncs configuration twice daily via `wp_cron` and on manual "Sync now"
  clicks. Saves push the form back through the portal so the portal side stays
  source of truth.

## Packaging

For now, the plugin is shipped manually:

```sh
cd wordpress
zip -r ../dist/scene-shift-ai-assistant.zip scene-shift-ai-assistant
```

Future work: add a release script under `scripts/` to bump version + zip.

## Endpoints used

| Plugin call           | Portal endpoint                                       | Auth                       |
| --------------------- | ----------------------------------------------------- | -------------------------- |
| `exchange_setup_code` | `POST /voice/wordpress-installs/exchange`             | none (setup code body)     |
| `read_config`         | `GET  /voice/wordpress-installs/{installId}`          | Bearer plugin token        |
| `update_config`       | `PUT  /voice/wordpress-installs/{installId}`          | Bearer plugin token        |
| Chat widget loader    | `GET  /embed/widget.js`                               | publishable web chat key   |
| Chat runtime config   | `GET  /embed/config/{assignmentId}?key={webchatKey}`  | publishable web chat key   |
