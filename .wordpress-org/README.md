# WordPress.org listing assets

`publish-release.yml` points the [`10up/action-wordpress-plugin-deploy`](https://github.com/10up/action-wordpress-plugin-deploy)
action's `ASSETS_DIR` at this folder. Anything you place here is rsynced into
the **`/assets/` directory of the plugin's SVN repository** (NOT into the
`/trunk/` folder that ships with the plugin zip). That `assets` directory is
where WordPress.org pulls icons, banners, and screenshot images from when
rendering your plugin's directory page.

## Required artwork

| File                              | Purpose                                                                | Format                          |
| --------------------------------- | ---------------------------------------------------------------------- | ------------------------------- |
| `icon-128x128.png` *(or `.svg`)*  | Default plugin icon shown in search results & install screens          | 128×128 PNG, or scalable SVG    |
| `icon-256x256.png`                | Retina version of the icon                                             | 256×256 PNG                     |
| `banner-772x250.png`              | Header banner shown above the readme on the plugin's directory page    | 772×250 PNG                     |
| `banner-1544x500.png`             | Retina header banner                                                   | 1544×500 PNG                    |
| `screenshot-1.png`                | First screenshot — caption #1 from `readme.txt` `== Screenshots ==`    | PNG / JPG, ≤ 1MB recommended    |
| `screenshot-2.png`                | Second screenshot — caption #2                                         | same                            |
| `screenshot-N.png`                | Repeat for each `== Screenshots ==` caption                            | same                            |

## What to capture

The current readme `== Screenshots ==` block expects:

1. **Settings → Scene Shift "Connect" form** — the empty connect screen showing the consent checkbox and setup-code input.
2. **Settings → Scene Shift "Connected" view** — the metadata grid (site, plugin token prefix, last synced) plus the Sync / Disconnect actions.
3. **Phone display section** — schedule fields, AI label, alternative phone number, sample shortcode helper text.
4. **Chat appearance section** — theme/position/radius selectors and the five color pickers.
5. **Live chat widget on a front-end page** — the floating bubble in the corner of a sample WordPress page.

Match the file names to the order of the captions exactly: `screenshot-1.png` corresponds to caption 1, `screenshot-2.png` to caption 2, and so on.

## Brand guidance for the icon and banner

Per the project's brand system in `CLAUDE.md`:

- Single indigo accent (`#4F46E5`)
- Pure white surfaces / deep black type
- Geometric or clean wordmark; no decorative ornaments
- Works in monochrome

## Local preview

Drop your artwork into this folder and commit. The publish workflow will
upload it to WP.org SVN at the next `vX.Y.Z` tag push (when the
`PUBLISH_TO_WORDPRESS_ORG` repo variable is set to `true`).

To preview locally, you can use the WordPress.org [Plugin Directory
Preview](https://wordpress.org/plugins/developers/preview/) form once the
plugin is approved.
