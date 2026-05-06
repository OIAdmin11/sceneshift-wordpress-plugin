# Scene Shift WordPress plugin

This is the source repository for the **Scene Shift AI Assistant** WordPress
plugin distributed to client websites. It is intentionally hosted as its own
repository (and pulled into the customer portal as a git submodule at
`wordpress-plugin/`) so the plugin can be versioned, packaged, and shipped
independently of the SvelteKit portal.

```text
wordpress-plugin/                       ← repo root
├── .github/workflows/                  ← CI + release automation
│   ├── lint.yml
│   ├── prepare-release.yml
│   └── publish-release.yml
├── composer.json                       ← dev-only deps (PHPCS + WPCS)
├── phpcs.xml.dist                      ← WordPress coding standards ruleset
├── README.md
└── scene-shift-ai-assistant/           ← the actual plugin (this is what ships)
    ├── scene-shift-ai-assistant.php    ; plugin entry / header
    ├── uninstall.php                   ; option + cron cleanup
    ├── readme.txt                      ; WP.org plugin readme
    ├── LICENSE.txt                     ; GPL v2 text
    ├── .distignore                     ; files excluded from the SVN/zip build
    ├── includes/
    │   ├── class-plugin.php            ; bootstrap + cron lifecycle
    │   ├── class-portal-client.php     ; HTTP client to the Scene Shift portal
    │   ├── class-settings-store.php    ; wp_options wrapper
    │   ├── class-renderer.php          ; shortcodes + footer chat injection
    │   └── class-admin-page.php        ; Settings → Scene Shift admin UI
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

## Local development

```sh
# from the repo root (wordpress-plugin/)
composer install         # pulls PHPCS + WordPress Coding Standards
composer run lint        # runs PHPCS over scene-shift-ai-assistant/
composer run lint:fix    # auto-fixes whatever phpcbf can
```

The `.github/workflows/lint.yml` workflow runs the same `composer run lint`
on every push and pull request, plus a sanity check that the plugin header
`Version`, the readme.txt `Stable tag`, and `LICENSE.txt` all stay in sync.

## Releasing

### 1. Cut a release from the GitHub UI

1. Go to **Actions → Prepare release → Run workflow**.
2. Enter the new version (strict semver, e.g. `0.2.0`).
3. Leave **draft** unchecked to commit straight to `main`, or check it to open
   a PR you can edit before merging.

`prepare-release.yml`:

- Validates the version is greater than the current one (per WordPress.org
  guideline 15: version numbers must increment).
- Updates the plugin header `Version`, the
  `SCENE_SHIFT_AI_ASSISTANT_VERSION` constant, and `readme.txt` `Stable tag`
  in lock-step.
- Adds a `= X.Y.Z =` stub to the `== Changelog ==` section.
- When **draft is false**, commits and pushes a `vX.Y.Z` tag, which triggers
  the publish workflow below.
- When **draft is true**, opens a PR. After you flesh out the changelog and
  merge it, push the matching tag manually:

  ```sh
  git tag -a v0.2.0 -m "Release v0.2.0"
  git push origin v0.2.0
  ```

### 2. Publish — what `publish-release.yml` does on the tag

Triggered by any push of a `v[0-9]+.[0-9]+.[0-9]+` tag:

1. Asserts the tag, plugin header `Version`, and readme `Stable tag` all match.
2. Builds a clean copy of `scene-shift-ai-assistant/` honoring `.distignore`.
3. Zips it as `scene-shift-ai-assistant-X.Y.Z.zip`.
4. Extracts the matching changelog block from `readme.txt`.
5. Creates a GitHub Release for the tag and attaches the zip.
6. **Optionally** deploys to WordPress.org SVN via
   [`10up/action-wordpress-plugin-deploy`](https://github.com/10up/action-wordpress-plugin-deploy)
   — only when both the repository variable and secrets below are set.

### 3. Manual local zip (escape hatch)

```sh
cd wordpress-plugin/scene-shift-ai-assistant
zip -r ../../scene-shift-ai-assistant.zip . -x@.distignore
```

## CI / release secrets

| Where | Name | Required for | Notes |
|-------|------|--------------|-------|
| Repository **Variables** | `PUBLISH_TO_WORDPRESS_ORG` | Auto WP.org SVN deploy | Set to `true` to enable the SVN job. Leave unset/`false` to only build the GitHub Release. |
| Repository **Secrets** | `WORDPRESS_ORG_USERNAME` | WP.org SVN deploy | Your WordPress.org account username. |
| Repository **Secrets** | `WORDPRESS_ORG_PASSWORD` | WP.org SVN deploy | The same account's password. **Do not use a personal password** — generate a [WordPress.org Plugin Repository Token](https://make.wordpress.org/plugins/2024/06/04/security-improvements-to-deploys-with-the-deploy-key/) and use that instead. |

`GITHUB_TOKEN` is automatically provided by Actions; no setup needed for the
`prepare-release` commit/tag or the `publish-release` GitHub Release.

## Endpoints used at runtime

| Plugin call           | Portal endpoint                                       | Auth                       |
| --------------------- | ----------------------------------------------------- | -------------------------- |
| `exchange_setup_code` | `POST /voice/wordpress-installs/exchange`             | none (setup code body)     |
| `read_config`         | `GET  /voice/wordpress-installs/{installId}`          | Bearer plugin token        |
| `update_config`       | `PUT  /voice/wordpress-installs/{installId}`          | Bearer plugin token        |
| Chat widget loader    | `GET  /embed/widget.js`                               | publishable web chat key   |
| Chat runtime config   | `GET  /embed/config/{assignmentId}?key={webchatKey}`  | publishable web chat key   |

## WordPress.org submission checklist

The plugin currently passes a self-audit against the
[Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/).
Before first submission you still need to:

1. Reserve the slug at [WordPress.org/plugins/developers/add/](https://wordpress.org/plugins/developers/add/).
   The submitted zip is whatever `publish-release.yml` produces.
2. Create a `.wordpress-org/` folder at this repo root containing:
   - `icon-128x128.png` and `icon-256x256.png` (or a single `icon.svg`)
   - `banner-772x250.png` and `banner-1544x500.png`
   - `screenshot-1.png`, `screenshot-2.png`, … matching the
     `== Screenshots ==` block you add to `readme.txt`
3. Once approved, set the `PUBLISH_TO_WORDPRESS_ORG` variable + WP.org
   secrets so the next tag push deploys to SVN automatically.
4. Verify on the site our service is available.