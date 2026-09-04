---
name: verify
description: How to run and drive this Nette CMS app for end-to-end verification (launch, curl-based admin login, CMS page/component flows).
---

# Verifying changes in this app

## Launch

The app may already be running on port 8000 (check `curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/` first). If not:

```bash
npm run build                      # www/assets is gitignored; required after clone/asset changes
php -S localhost:8000 -t www       # needs MySQL up (credentials in config/common.neon, root/root)
```

Templates recompile automatically in debug mode — no cache clearing needed after editing `.latte` files.

## Admin login via curl (no browser needed)

Nette forms need the CSRF token and `_do` hidden fields, plus the session + `_nss` cookies (the `_nss` cookie satisfies the `isSameSite()` guard used by the component signal handlers):

```bash
curl -s -c cookies.txt http://localhost:8000/auth/login -o login.html
TOKEN=$(grep -o 'name="_token_" value="[^"]*' login.html | sed 's/.*value="//')
DO=$(grep -o 'name="_do" value="[^"]*' login.html | sed 's/.*value="//')
curl -s -b cookies.txt -c cookies.txt http://localhost:8000/auth/login \
  --data-urlencode "_token_=$TOKEN" --data-urlencode "_do=$DO" \
  --data-urlencode "userName=admin" --data-urlencode "password=Admin123" \
  --data-urlencode "_submit=x"     # expect 303 -> /admin/
```

## Driving the CMS

- Create page: GET `/admin/page/add` (parse `_token_`/`_do` the same way), POST `title`, `link`, `isVisible=1`, `send=x` → 303 to `/admin/page/edit/<id>`.
- Component signals (plain GET/POST, no Nette form token — guarded by the `_nss` cookie):
  - add: `GET /admin/page/edit/<id>?pageId=<id>&type=<type>&do=addComponent`
  - save: `POST /admin/page/edit/<id>?componentId=<cid>&do=saveComponent` with the type's content fields + `isVisible=on`
  - delete component: `GET /admin/page/edit/<id>?componentId=<cid>&do=deleteComponent`
- Grid actions pass the id as a path segment: `/admin/page/default/<id>?do=delete|setHomepage|unsetHomepage`.
- Public render: `/<link>`, or `/` for the selected homepage.

## Visual checks (headless screenshots)

No Playwright here, but macOS Chrome works headlessly. Authenticated admin pages can't be opened directly (no cookie support), so snapshot the HTML with curl and serve it same-origin from the docroot (assets resolve, JS modules run; signals/forms won't):

```bash
curl -s -b cookies.txt http://localhost:8000/admin/page/edit/<id> -o snap.html
cp snap.html www/__snap_test.html         # temp file in docroot — DELETE afterwards
"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless=new --disable-gpu \
  --screenshot=shot.png --window-size=1600,1400 --hide-scrollbars \
  "http://localhost:8000/__snap_test.html"
```

Re-snapshot after `npm run build` — asset hashes in the saved HTML go stale.

## Gotchas

- camelCase presenter actions are dash-cased in URLs (`actionNavFooter` → `/admin/page/nav-footer`); the camelCase URL 404s.
- The homepage’s link URL 301-redirects to `/` (canonical URL) — a curl without `-L` captures the redirect page, not the content.
- Verify sanitization by POSTing `<script>` inside rich-text fields (`text_block.content`, `cta.body`, `footer.text`, …) and checking the public render.
- Clean up test data through the admin UI (delete the test page; restore the previous homepage if you changed it — find it via `do=unsetHomepage` links in `/admin/page/`).
