# A-FIT API

Laravel 13 JSON API behind the A-FIT mobile app (Expo). Passwordless e-mail OTP sign-in,
meals and nutrition targets, background step totals from HealthKit / Health Connect,
water and weight logs, friend groups with invite codes, step leaderboards and challenges.

Built to the project standard (see `CLAUDE.md`): strict `{flag, data, message}` envelope,
Sanctum bearer tokens, every route policy-checked, en/ar messages via `Accept-Language`,
and an architecture test that fails the build on hand-rolled responses, unthrottled public
routes, raw DB access in controllers, missing strict types and more.

## Run locally

```bash
composer install
cp .env.example .env && php artisan key:generate
# local overrides: APP_ENV=local APP_DEBUG=true DB_CONNECTION=sqlite CACHE_STORE=file QUEUE_CONNECTION=sync MAIL_MAILER=log
touch database/database.sqlite && php artisan migrate
php artisan serve --host 0.0.0.0 --port 8000
```

With `MAIL_MAILER=log` the OTP code is written to `storage/logs/laravel.log`. For local
testing you may set `DEV_MASTER_OTP=000000`: that code then signs in any e-mail, but only while
`APP_ENV` is `local` or `testing`; the API ignores the variable in every other environment.

## Branching

- `main` — what is deployed. Changes only through a pull request from `dev`; every merge tags
  and ships a release (`.github/workflows/backend.yml`).
- `dev` — integration branch and the GitHub default. Feature branches (`feat/...`, `fix/...`)
  branch from `dev` and come back through a pull request; the quality gate must be green.
- Nothing is pushed straight to `main` or `dev`: every change is a branch and a pull request.
  Commits carry the author's own identity, no generated attribution.

## Quality gate

```bash
composer check      # pint --test, phpstan, phpunit (incl. tests/Unit/ArchitectureTest.php)
```

CI (`.github/workflows/backend.yml`) runs exactly those three members; the image build
depends on them passing.

## Production

`docker compose up -d --build` starts nginx → php-fpm, a Redis-backed queue worker (OTP
mail), the scheduler (token pruning), MySQL 8.4 and Redis. Set every value in
`.env.example`; TLS terminates in front of nginx. Redis is required in production: the OTP
store, the rate limiters and the leaderboard cache live in it and must be shared by the app
and queue containers.

## Releases

Releases are automatic. Every push to `main` (normally the merge of a `dev` pull request) runs the
gate, then creates the next tag — `MAJOR.MINOR` with the minor incremented from the latest tag,
starting at `0.1` — pushes the image `amromohmmad/a_fit_backend` to Docker Hub as `:<tag>`,
`:latest` and `:sha-<short>`, and publishes a GitHub release with generated notes. Pushes to `dev`
publish `:dev`. Bump the major by tagging it once by hand (`git tag 1.0 && git push origin 1.0`);
the next merge continues from there.

Repository secrets needed: `DOCKERHUB_USERNAME` and `DOCKERHUB_TOKEN` (a Docker Hub access
token); until they exist the image step skips and the release says so. Deploy by setting
`DOCKER_IMAGE=amromohmmad/a_fit_backend:<tag>` in the server's `.env` and `docker compose up -d`.

## Postman

`postman/collections/A-FIT API v1` is a Git-native (v3) collection covering every route, with
the verify request storing `data.token` into the collection variable automatically. Lint it with
`postman collection lint "postman/collections/A-FIT API v1"`; the Local environment points at
`http://127.0.0.1:8000/api/v1`.

## API (all under `/api/v1`, JSON, `Accept-Language: ar|en`)

| Method | Path | Notes |
|---|---|---|
| POST | `auth/otp/request` | `{email}` → always 200. Throttled 5/min per IP, 2/min and 20/day per e-mail |
| POST | `auth/otp/verify` | `{email, code, name?, device_name?}` → `data.token` + `data.user`, or `requires_name: true` |
| POST | `auth/logout` | revokes the current token |
| GET/PATCH/DELETE | `me` | profile fields `name`, `locale`; DELETE removes the account and all data |
| GET/PUT | `me/profile` | physical inputs in, server-computed daily targets out |
| GET | `dashboard?date=` | the whole home screen in one call |
| GET/POST | `meals`, `meals/{id}` (PUT/DELETE) | paginated per day; `meals/summary?from&to` ≤ 31 days |
| PUT | `steps` | batch upsert `{entries:[{date, steps, source}]}` ≤ 31 rows, 12/min |
| GET | `steps?from&to` | ≤ 90 days |
| GET/PUT | `water/{date}` | absolute daily total |
| GET/POST/DELETE | `weights`, `weights/{id}` | one per day, ≤ 366 days history |
| GET/POST | `groups`, `groups/join {code}` | groups the caller belongs to |
| GET/PATCH/DELETE | `groups/{id}` | non-members get 404 on every group route |
| POST | `groups/{id}/leave`, `groups/{id}/invite/rotate`; DELETE `groups/{id}/members/{user}` | owner-only where it matters |
| GET | `groups/{id}/leaderboard?period=today\|week\|month&date=` | cached 60 s per group and window |
| GET/POST | `groups/{id}/challenges`; GET/DELETE `challenges/{id}` | standings on the detail view |

Every response is `{ "flag": bool, "data": payload|null, "message": "one sentence" }`.
Paginated payloads nest Laravel's paginator inside `data` (`data.data`, `data.meta.total`).
