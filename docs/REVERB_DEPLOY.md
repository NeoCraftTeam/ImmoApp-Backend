# Reverb Deployment

WebSocket server (`php artisan reverb:start`) for the chat real-time pipeline:
message delivery, presence (online/offline), typing indicators, read receipts,
last-seen heartbeat. Without Reverb, all those features silently degrade — chat
falls back to "refresh to see new messages".

## What was added

- **`docker-compose.yml`** — new `reverb` service (prod). Uses the same image
  as `app`/`worker`, runs `php artisan reverb:start --host=0.0.0.0 --port=8080`,
  joins `keyhome-network` + `traefik-public`, healthchecked via `pgrep`.
- **`docker-compose.preprod.yml`** — same service for preprod, on
  `preprod-network` + `keyhome-prod-network` + `traefik-public`.
- **`.env.example`** — corrected `REVERB_*` template (split between public
  endpoint vars and internal listen vars).
- **`.env.preprod.example`** — flipped `BROADCAST_CONNECTION=log → reverb`
  and added the full Reverb block.

## Required env vars (per environment)

| Variable | Local dev | Preprod | Prod |
|---|---|---|---|
| `BROADCAST_CONNECTION` | `reverb` | `reverb` | `reverb` |
| `REVERB_APP_ID` | `keyhome_chat` | `keyhome_preprod` | `keyhome_prod` |
| `REVERB_APP_KEY` | (any 32-hex) | **generate, secret** | **generate, secret** |
| `REVERB_APP_SECRET` | (any 64-hex) | **generate, secret** | **generate, secret** |
| `REVERB_HOST` | `127.0.0.1` | `reverb-api.keyhome.neocraft.dev` | `reverb.keyhome.app` |
| `REVERB_PORT` | `8080` | `443` | `443` |
| `REVERB_SCHEME` | `http` | `https` | `https` |
| `REVERB_SERVER_HOST` | `0.0.0.0` | `0.0.0.0` | `0.0.0.0` |
| `REVERB_SERVER_PORT` | `8080` | `8080` | `8080` |
| `REVERB_DOMAIN` | (n/a) | `reverb-api.keyhome.neocraft.dev` | `reverb.keyhome.app` |

`REVERB_DOMAIN` is consumed only by the docker-compose Traefik label.
`REVERB_HOST`/`REVERB_PORT`/`REVERB_SCHEME` are consumed by Laravel's broadcaster
auth signatures **and** the Pusher SDK on the frontend.

## Frontend (Vercel) env vars

`REVERB_APP_KEY` must match `NEXT_PUBLIC_REVERB_APP_KEY` exactly:

```
NEXT_PUBLIC_REVERB_APP_KEY=<same as backend REVERB_APP_KEY>
NEXT_PUBLIC_REVERB_HOST=reverb.keyhome.app          # or reverb-api.keyhome.neocraft.dev for preprod
NEXT_PUBLIC_REVERB_PORT=443
NEXT_PUBLIC_REVERB_SCHEME=https
```

## DNS

Add an A record for each public hostname pointing at the same VPS IP as the
existing `api.keyhome.app` / `api.keyhome.neocraft.dev` records:

```
reverb.keyhome.app                  A   <vps-ip>
reverb-api.keyhome.neocraft.dev     A   <vps-ip>
```

Traefik will issue Let's Encrypt certs automatically on first connection
(label `traefik.http.routers.keyhome-reverb.tls.certresolver=letsencrypt`).

## Deployment steps

```bash
# 1. On the VPS, generate fresh keys for each env (NEVER reuse local dev keys)
ssh <vps>
php -r "echo bin2hex(random_bytes(16)).PHP_EOL;"   # → REVERB_APP_KEY
php -r "echo bin2hex(random_bytes(32)).PHP_EOL;"   # → REVERB_APP_SECRET

# 2. Edit /opt/keyhome-prod/.env  (or  /opt/keyhome-preprod/.env)
#    Set all REVERB_* vars from the table above.

# 3. Pull the new image and start the reverb service
docker compose pull reverb
docker compose up -d reverb

# 4. Verify
docker compose ps reverb                            # Up (healthy)
docker compose logs -f reverb                       # "Starting server on 0.0.0.0:8080"
curl -I https://reverb.keyhome.app                  # Traefik 200/upgrade required
wscat -c wss://reverb.keyhome.app/app/<APP_KEY>     # connect → Pusher hello frame

# 5. Set frontend NEXT_PUBLIC_REVERB_* on Vercel and redeploy.
```

## Sanity checks post-deploy

1. Open chat as User A, leave it open; open chat as User B → both see
   "● En ligne" on each other within ≤ 2 s.
2. B types a message → A sees the typing indicator before B sends.
3. B sends → A sees the message instantly (no refresh).
4. A closes the tab → B sees "● Vu il y a quelques secondes" within 1 min.
5. `docker compose logs reverb` shows `connection.opened` /
   `subscription.succeeded` lines.

## Rollback

`docker compose stop reverb` — chat reverts to refresh-only behaviour but
nothing else breaks. The `BROADCAST_CONNECTION=reverb` setting will queue
broadcasts that fail silently if no daemon is running, so leave the env var
alone unless rolling back the entire chat feature.

## Resource sizing

- Prod: `memory: 384m`, `cpus: 0.75` (~5 k concurrent connections per instance).
- Preprod: `memory: 256m`, `cpus: 0.50`.

## Kernel & runtime tuning

Two non-default tunings ship with the `reverb` service. Without **both**,
the daemon caps out around 1 k connections per instance regardless of the
memory/cpu limits above.

### 1. `nofile` ulimit — 10 000

Each WebSocket client consumes ≥ 1 file descriptor. Containers do **not**
honour `/etc/security/limits.conf` on the host (that file is consumed by
PAM at login time); they inherit `nofile` from the docker daemon (typically
1024 soft / 1048576 hard). Both compose files set:

```yaml
ulimits:
  nofile:
    soft: 10000
    hard: 10000
```

Verify after rollout:

```bash
docker compose exec reverb sh -c 'cat /proc/1/limits | grep "open files"'
# Max open files            10000                10000                files
```

### 2. PHP `ext-ev` — libev event loop

Reverb runs on top of ReactPHP's event loop. The default fallback is PHP's
`stream_select()` which is **O(n) per tick** and is also capped at 1024 file
descriptors by `FD_SETSIZE` on most builds — so even with the 10 000 nofile
ulimit, `stream_select` would silently truncate the fd set.

The Dockerfile installs `ext-ev` (`pecl install ev` + `apk add libev-dev`)
which binds **libev** (epoll on Linux, kqueue on BSD/macOS). ReactPHP
automatically picks it up when the extension is loaded — no app code change
required. Loop driver priority: `ev` ← us → `uv` → `event` → `stream_select`.

**Why `ev` and not `uv`:** the PECL `uv` package has been unmaintained
since 2022 (v0.3.0) and no longer compiles cleanly on PHP 8.4. `ext-ev`
1.1.5 (2023) is actively maintained and supports PHP 8+. It is also
*higher* priority than `uv` in ReactPHP's loop autodetection, so even
if both were installed `ev` would win.

Verify after rebuild:

```bash
docker compose exec reverb php -m | grep -i ev    # ev
docker compose exec reverb php -r 'echo Ev::version();'   # "4.x.x"
```

Reverb's `--debug` startup logs should also show the loop class name (e.g.
`ExtEvLoop`), not `StreamSelectLoop`.

## Horizontal scaling

Reverb is single-process per container. To scale beyond ~10 k concurrent
connections per instance, enable horizontal scaling via Redis pub/sub:

```
REVERB_SCALING_ENABLED=true
REVERB_SCALING_CHANNEL=reverb
```

…then run `docker compose up -d --scale reverb=2` (or more). All instances
publish/subscribe through the existing Redis service, so no extra infra is
needed.
