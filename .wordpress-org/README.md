# WordPress.org assets

Files in this folder are the plugin's **directory assets**, the images shown on the
WordPress.org listing. They are kept here in the repo but do **not** ship inside the
plugin ZIP. On WordPress.org they live in the SVN `/assets/` folder, separate from
`/trunk/`, so they never bloat the download.

## What goes here

| File | Size | Purpose |
|------|------|---------|
| `screenshot-1.png` | any, ~1200px wide | Matches the first line under `== Screenshots ==` in `readme.txt`. Add `screenshot-2.png`, etc. for more. |
| `banner-772x250.png` | 772 x 250 | Header banner on the plugin page. |
| `banner-1544x500.png` | 1544 x 500 | Retina banner (optional but recommended). |
| `icon-128x128.png` | 128 x 128 | Plugin icon in search results. |
| `icon-256x256.png` | 256 x 256 | Retina icon (optional). |

Only `screenshot-1.png` is referenced by the main `README.md`. Drop it in and it shows
up automatically on GitHub and, once deployed, on the WordPress.org page.

## Getting them onto WordPress.org

After the plugin is approved, these files go into the SVN `/assets/` directory:

```
svn co https://plugins.svn.wordpress.org/autoloadforge
# copy this folder's files into autoloadforge/assets/
svn add assets/*
svn ci -m "Add plugin assets"
```

If you set up automated deploys with the
[10up plugin-deploy action](https://github.com/10up/action-wordpress-plugin-deploy),
it copies this exact `.wordpress-org/` folder to SVN assets for you.
