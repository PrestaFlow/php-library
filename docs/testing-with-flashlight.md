# Testing against a throwaway shop

PrestaFlow drives a real PrestaShop. These are disposable ones, from the
official [Flashlight](https://github.com/PrestaShop/prestashop-flashlight)
images, so nothing depends on a shop somebody set up by hand.

One `docker compose` service per version, each with its own database and its
own ports:

| Service | PrestaShop | Shop 1 | Shop 2 | Shop 1 theme | Shop 2 theme |
|---|---|---|---|---|---|
| `ps17` | 1.7.8.11 | 8017 | 8018 | classic | classic |
| `ps82` | 8.2.8 | 8082 | 8083 | classic | classic |
| `ps92` | 9.2.0 | 8092 | 8093 | hummingbird | classic |

The 9.2 container is the interesting one: a single boot gives you **both**
themes — hummingbird on 8092, classic on 8093 — which is what you want when
you are checking that a theme-aware selector really resolves per theme instead
of happening to match on the one theme you tested.

## Prerequisites

Docker Compose v2, PHP 8.3+, and the library's dependencies:

```bash
composer install
```

Ports 8017/8018, 8082/8083 and 8092/8093 must be free. If something else holds
the port, every check below passes against the wrong shop:

```bash
docker ps --format '{{.Names}}\t{{.Ports}}' | grep 809 || echo "8092/8093 free"
```

## Start a shop

```bash
docker compose up ps92 -d     # 9.2.0 on :8092, or ps82 / ps17
```

Then wait for it. **Poll `/admin-dev/` for a `302`, not `/` for a `200`.** A
`200` on `/` proves only that *something* holds the port — a leftover
hand-made container answers it just as happily, and then every check below
passes against the wrong shop. `/admin-dev/` is the discriminator: Flashlight
fixes the admin folder and redirects it to the login page, while a hand-made
install with a random admin folder returns 404. The `302` also means PHP is
really executing, not just nginx answering.

```bash
for i in $(seq 1 60); do
  code=$(curl -s -o /dev/null -w '%{http_code}' http://localhost:8092/admin-dev/ || true)
  [ "$code" = "302" ] || [ "$code" = "200" ] && { echo "back office answers $code — shop is up"; break; }
  echo "back office answers ${code:-000}, waiting..."; sleep 5
done
```

Measured on 2026-09-24, a `ps92` boot goes from `up -d` to a `302` in about 8
seconds, with the post-scripts already finished by the time anything answers
at all — Flashlight runs them before the web server accepts connections. Do
not rely on that ordering, though: see the first trap below.

The first boot also provisions a second shop **on its own port** (8093 here;
8018 for `ps17`, 8083 for `ps82`), so multistore is there by default rather
than being something you build once and forget. A dedicated port rather than a
virtual URI such as `/shop2/` is deliberate: PrestaShop discriminates shops by
domain *including the port*, and every Flashlight tag used here is `-nginx`,
which never reads `.htaccess` — a shop on a virtual URI would serve no assets
at all. Check it is really a *second* shop and not shop 1 answering on another
port — on `ps92` the themes differ, which makes the check falsifiable:

```bash
curl -s http://localhost:8092/ | grep -o 'themes/[a-z]*/' | head -1   # hummingbird
curl -s http://localhost:8093/ | grep -o 'themes/[a-z]*/' | head -1   # classic
```

## Configure

```bash
cp .env.flashlight.example .env.flashlight
```

The back office is at `/admin-dev/` with `admin@prestashop.com` / `prestashop`
— fixed by the image, so unlike a hand-made install there is no random admin
folder to look up. Edit `.env.flashlight` if you are pointing at `ps17` or
`ps82`: change the port in both URLs, `PRESTAFLOW_PS_VERSION`, and
`PRESTAFLOW_THEME` (both older versions are `classic`).

## Run a suite

```bash
set -a; . ./.env.flashlight; set +a
php bin/prestaflow run src/Tests/Suites/Smoke/FrontOfficeSmoke.php
```

Exported variables win over a `.env` / `.env.local` sitting in the repo: the
library loads those with Dotenv's *immutable* loader, which never overwrites a
value already in the environment. So sourcing `.env.flashlight` is enough —
you do not have to move your own `.env.local` out of the way.

The same suite runs in CI (`.github/workflows/live-smoke.yml`) against all
three versions on every push to `main` and every pull request.

## Tear down

```bash
docker compose down -v        # -v also drops the database volume
```

Leaving out `-v` keeps the data, which is occasionally what you want and
usually how you end up debugging a shop whose state you no longer understand.

## Two traps worth knowing

**"The container is up" does not mean "the shop is provisioned."** A post-script
that fails does *not* stop the container. `ON_POST_SCRIPT_FAILURE` defaults to
`fail`, but the handler's `exit 8` runs inside the child process that `xargs`
spawns, so it never reaches the container: the failure is logged and the
container stays `Up (healthy)`, serving a half-built shop. Verified on
2026-09-24 with a deliberately failing script. Do not treat a green
`docker compose ps` as proof — run the smoke suite, which is what actually
exercises the shop. If something looks wrong, read the boot log:

```bash
docker compose logs ps92 | grep -i -e 'post-script' -e '✅' -e 'error'
```

**A duplicated shop has no payment methods.** PrestaShop does not copy
`ps_module_carrier` when duplicating a shop — upstream bug
[PrestaShop#42964](https://github.com/PrestaShop/PrestaShop/issues/42964),
reported 2026-09-24. `docker/post-scripts/20-carrier-restrictions.sh` copies
the rows as a workaround. Without it, checkout on the second shop stops at
"no payment method available", with nothing in the logs to explain why.
