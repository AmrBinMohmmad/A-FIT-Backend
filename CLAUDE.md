## rakaya-standard

- **Platform**: mobile + backend (this repo is the Laravel API; the Expo client is `../A-FIT`)
- **Tier**: 1 — single developer, single-tenant (trigger wording: skill `references/adopting.md`)
- **Kit**: v4.3.0 copied 2026-10-30 from the `kit/` directory
- **Reference**: patterns from `kit/plumbing/backend/` (ApiResponse, PaginatesResults,
  SetLocaleFromAcceptLanguage, bootstrap exception map); no domain code copied
- **Envelope**: strict `{flag, data, message}` — three keys, that order, success and
  failure; no `errors` key; validation flattened to one sentence (D1)
- **Locale contract**: `SetLocaleFromAcceptLanguage` (global) + `lang/{en,ar}/{errors,messages,mail}.php`;
  `config('app.supported_locales') = ['ar','en']`; enum values on the wire, labels client-side (D3)
- **Auth**: passwordless e-mail OTP (hashed, 10-min TTL, 5 attempts, cooldown, throttled) issuing a
  Sanctum PAT at `data.token`; the mobile client stores it in expo-secure-store (D7 shape b).
  `SANCTUM_TOKEN_EXPIRATION` wired (30 days). `DEV_MASTER_OTP` is honoured only in local/testing
  (hard-coded environment check in `OtpCodes::devMasterCode()`, tested).
- **Tenancy**: none (single-tenant) — tenancy gates pass vacuously; `TenantIsolationTest` not copied

| Concern | Installed library |
|---|---|
| framework / ORM | laravel/framework 13, Eloquent, Sanctum 4 |
| cache / queue | Redis in production (`CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`); OTP store, rate limiters and leaderboard cache depend on a shared cache |
| mail | Laravel Mail, queued `OtpCodeMail` |
| formatting / analysis | laravel/pint (`laravel` preset + `declare_strict_types`), larastan level 0 |
| test runner | PHPUnit 12 — never add Pest |
| debugging | Telescope, registered only when `APP_ENV=local` |

- **Enforcement copied**: `tests/Unit/ArchitectureTest.php` (PUBLIC_ROUTES = the two OTP legs;
  `telescope.php` added to KNOWN_VENDOR_CONFIG), `tests/Feature/AuthContractTest.php` (adapted to
  OTP), `pint.json`, `phpstan.neon.dist`, phpunit env block, composer `check` scripts.
  Fail-then-pass verified 2026-10-30 on `test_controllers_never_hand_roll_response_json` and
  `test_public_routes_are_throttled`.
- **CI**: `.github/workflows/backend.yml` — image job `needs: quality`
- **Hooks**: SessionStart trigger on (global); Stop check opt-out

### Project rules beyond the kit
- Cross-user access answers 404, never 403 (`abort_if(Gate::denies(...), 404)`); 403 is reserved
  for a member who lacks a role inside a group they can already see.
- Aggregates and `DB::raw` live in `app/Services/*`; controllers only compose requests, policies,
  services and resources.
- Server-owned keys (`user_id`, `owner_id`, `invite_code`, `role`, timestamps) are stripped at the
  HTTP boundary by `StripServerOwnedFields` and are never `$fillable`.
- Lists are server-driven: every `index()` paginates (≤ 100 per page); history reads are bounded
  by `DateRangeRequest` (90 / 31 / 366 days).
