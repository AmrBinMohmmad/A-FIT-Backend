<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionEnum;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\TestCase;

/**
 * The executable half of the Rakaya standard — kit v4.0.0 (token-normalized core v2.0.0;
 * v4 added test_index_actions_are_paginated, test_public_routes_are_throttled, and
 * test_queued_jobs_pin_the_tenant). Decisions D1–D10 in
 * decisions.md; evasion fixes from the audit rounds.
 * PHPUnit-native by decision D5; dependency-free by design: matching runs over a
 * TOKEN-NORMALIZED source stream (ext-tokenizer — comments stripped, whitespace
 * collapsed), so comments, line splits and spacing tricks cannot defeat a rule, plus
 * use-statement resolution for facade/class aliases and app-WIDE Eloquent reflection.
 *
 * ── Enforcement layers (be honest about what each rule is) ─────────────────────────
 * FORCED  — decides its property structurally (reflection, schema, tokens): a
 *           violation of the stated property cannot be worded around.
 * GUARDED — catches the naive/accidental form; a determined author can still evade.
 *           Every known residual evasion is listed on the rule. Guarded rules are
 *           regression tripwires, not walls — the wall for those properties is
 *           review + the behavioral isolation suite.
 * No rule of either kind survives a developer editing this file or its allowlists:
 * allowlist diffs are review events (references/adopting.md).
 *
 * Every rule runs alone via  php artisan test --filter=test_<name>  and was verified
 * to FAIL on a fresh violation — and, for v2, to CATCH the audit's evasion probes —
 * before shipping.
 */
class ArchitectureTest extends TestCase
{
    use RefreshDatabase; // tenancy rules read the real (sqlite :memory:) schema

    /** Model-level global scope required on every model whose table has tenant_id (D4.1). */
    private const TENANT_TRAIT = 'App\Traits\BelongsToTenant';

    /** Model-level scope for organization_id models NOT in the allowlist below (D4.2). */
    private const ORG_TRAIT = 'App\Traits\ScopedByOrganization';

    /** Sub-tenant column name(s). Adjust if the project names its second level differently. */
    private const ORG_COLUMNS = ['organization_id'];

    /**
     * Models with an org column scoped by a named controller-level mechanism instead of
     * the model trait (D4.2). Every entry needs a justification naming the mechanism.
     */
    private const ORG_SCOPED_ALLOWLIST = [
        // 'App\Models\Example', // scoped per-controller by <mechanism>, see <file>
    ];

    /** Feature-test files that count as isolation coverage (D4.1-2). */
    private const ISOLATION_TEST_GLOBS = [
        'tests/Feature/*Tenanc*Test.php',
        'tests/Feature/*Tenant*Test.php',
        'tests/Feature/*Isolation*Test.php',
        'tests/Feature/*Scope*Test.php',
    ];

    /** Deliberately public routes ("METHOD uri"), each a reviewed decision. */
    private const PUBLIC_ROUTES = [
        // Passwordless sign-in: both legs are public by design and throttled (routes/api.php).
        'POST api/v1/auth/otp/request',
        'POST api/v1/auth/otp/verify',
    ];

    /** app/Http files allowed to touch the DB facade, each justified in-file. */
    private const HTTP_RAW_DB_ALLOWLIST = [
        // 'app/Http/Controllers/Concerns/SyncsRoleAssignees.php',
    ];

    /** app/Http files allowed hand serialization (->toArray/->toJson/json_encode), justified in-file. */
    private const SERIALIZATION_ALLOWLIST = [
        // 'app/Http/.../File.php', // justification + date
    ];

    /** Requests allowed a raw exists rule on a tenant-scoped table (D4.5). */
    private const EXISTS_RULE_ALLOWLIST = [
        // 'app/Http/Requests/ExampleRequest.php', // justification + date
    ];

    /** Controllers whose index() legitimately returns a NON-paginated collection
     * (a small fixed set provably complete in memory — e.g. an enum/options list),
     * justified in-file with a date (D10.12). */
    private const PAGINATION_ALLOWLIST = [
        // 'app/Http/Controllers/Api/OptionsController.php', // fixed enum list, not a page — YYYY-MM-DD
    ];

    /** Public (unauthenticated) api routes that legitimately carry no rate limiter,
     * justified with a date. The abuse surface is the UNAUTHENTICATED route, so those
     * must throttle unless a global `throttle:` middleware group already covers them. */
    private const THROTTLE_ALLOWLIST = [
        // 'GET api/v1/health', // liveness probe, intentionally unthrottled — YYYY-MM-DD
    ];

    /**
     * Identifiers that MUST be referenced (as code, not comments) in bootstrap/app.php —
     * middleware whose absence silently voids a Tier-1 contract (D3: the locale
     * middleware; D4.3: the server-owned-fields strip).
     */
    private const REQUIRED_BOOTSTRAP_REFS = [
        'SetLocaleFromAcceptLanguage',
        'StripServerOwnedFields',
    ];

    /** Project (non-vendor) config files: every env() key they read must be documented (D10.22). */
    private const PROJECT_CONFIG_FILES = [
        // 'config/tenancy.php',
    ];

    /** Config files known to come from the framework or installed packages. */
    private const KNOWN_VENDOR_CONFIG = [
        'app.php', 'auth.php', 'broadcasting.php', 'cache.php', 'concurrency.php',
        'cors.php', 'database.php', 'filesystems.php', 'hashing.php', 'logging.php',
        'mail.php', 'queue.php', 'services.php', 'session.php', 'sanctum.php',
        'telescope.php',
    ];

    /** Speculative-layer suffixes banned by D6. */
    private const BANNED_CLASS_SUFFIXES = ['Repository', 'Repo', 'Manager', 'Handler', 'UseCase', 'Interactor'];

    // ─────────────────────────────────────────────────────────────────────────────
    // Rules
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * FORCED (D10.18) — the file's first statement is declare(strict_types=1), decided
     * on the token stream: a comment containing the text no longer passes.
     */
    public function test_all_app_code_declares_strict_types(): void
    {
        $offenders = [];
        foreach ($this->phpFiles(app_path()) as $file) {
            if (! $this->firstStatementIsStrictTypes($file)) {
                $offenders[] = $this->rel($file);
            }
        }
        $this->assertNoOffenders($offenders, 'files whose first statement is not declare(strict_types=1)');
    }

    /**
     * FORCED-ish (D1) — the envelope is emitted only by the ApiResponse trait. Catches
     * response()->json( across any spacing/line split, and `new JsonResponse` via
     * use-statement resolution. Residual: constructing JsonResponse
     * through the container.
     */
    public function test_controllers_never_hand_roll_response_json(): void
    {
        $offenders = [];
        foreach ($this->phpFiles(app_path('Http')) as $file) {
            if (str_ends_with($file, 'ApiResponse.php')) {
                continue;
            }
            foreach ($this->normalizedMatches($file, '/\bresponse\s*\(\s*\)\s*->\s*json\s*\(/') as [$line, $ex]) {
                $offenders[] = $this->rel($file).":$line  $ex";
            }
            foreach ($this->newInstancesOf($file, 'Illuminate\Http\JsonResponse') as $line) {
                $offenders[] = $this->rel($file).":$line  new JsonResponse(...)";
            }
        }
        $this->assertNoOffenders($offenders, 'hand-rolled JSON responses outside the ApiResponse trait');
    }

    /**
     * GUARDED-BROAD (D1/D10.5) — no hand serialization in app/Http: ->toJson(),
     * json_encode(), and ANY ->toArray() call (v2 widened: the model-vs-Resource
     * receiver is undecidable without types, so the whole idiom is banned here and
     * justified exceptions go on SERIALIZATION_ALLOWLIST). Composite payloads get
     * their own Resource.
     */
    public function test_no_model_is_serialized_outside_a_resource(): void
    {
        $offenders = [];
        foreach ($this->phpFiles(app_path('Http')) as $file) {
            if (in_array($this->rel($file), self::SERIALIZATION_ALLOWLIST, true)) {
                continue;
            }
            foreach ($this->normalizedMatches($file,
                '/->\s*toJson\s*\(|\bjson_encode\s*\(|->\s*toArray\s*\(/') as [$line, $ex]) {
                $offenders[] = $this->rel($file).":$line  $ex";
            }
        }
        $this->assertNoOffenders($offenders,
            'hand serialization in app/Http (toJson/json_encode/toArray — allowlist entries need an in-file justification)');
    }

    /**
     * FORCED-ish — raw SQL and validation stay out of the HTTP layer. Token-normalized
     * (the `DB ::table` spacing evasion is dead), the validator() helper is
     * banned alongside Validator::make, and facade aliases resolve via use statements.
     */
    public function test_no_raw_db_or_validator_in_the_http_layer(): void
    {
        $offenders = [];
        foreach ($this->phpFiles(app_path('Http')) as $file) {
            if (in_array($this->rel($file), self::HTTP_RAW_DB_ALLOWLIST, true)) {
                continue;
            }
            foreach ($this->normalizedMatches($file,
                '/->\s*(whereRaw|selectRaw|havingRaw|orderByRaw)\s*\(|\bValidator\s*::\s*make\b|(?<![\w$])validator\s*\(/') as [$line, $ex]) {
                $offenders[] = $this->rel($file).":$line  $ex";
            }
            foreach ($this->staticCallsOn($file, ['Illuminate\Support\Facades\DB', 'Illuminate\Support\Facades\Validator']) as [$line, $ex]) {
                $offenders[] = $this->rel($file).":$line  $ex";
            }
        }
        $this->assertNoOffenders($offenders,
            'raw DB/Validator usage in app/Http (allowlist entries need an in-file justification)');
    }

    /**
     * D10.12 — lists are server-driven: every controller index() paginates. The backend
     * half of the standard's loudest rule (the frontend half — no client re-pagination —
     * is the frontend arch test's "lists are server-driven" rule). A controller with an
     * index() action whose body never paginates ships an unbounded (or silently truncated)
     * collection; the PaginatesResults trait or a paginate()/cursorPaginate()/perPage() call
     * is required, or the controller is allowlisted with an in-file justification for why
     * its set is provably complete in memory. GUARDED (token-normalized regex over the WHOLE
     * controller class, not the index() body alone — deciding whether THIS index() returns a
     * bounded set is undecidable). Honest residuals: a class that paginates a DIFFERENT action
     * (or merely `use PaginatesResults;` without index() calling it) yields a false pass; a
     * list action not named index() is out of scope. The gate proves a paginator is reachable
     * in the class; a reviewer owns "does index() actually use it".
     */
    public function test_index_actions_are_paginated(): void
    {
        $offenders = [];
        foreach ($this->phpFiles(app_path('Http/Controllers')) as $file) {
            if (in_array($this->rel($file), self::PAGINATION_ALLOWLIST, true)) {
                continue;
            }
            $src = (string) file_get_contents($file);
            // Only controllers that define an index() action are in scope.
            if (! preg_match('/function\s+index\s*\(/', $src)) {
                continue;
            }
            // The class as a whole must express pagination somewhere (trait use, or a
            // paginator call): PaginatesResults / paginate() / cursorPaginate() / simplePaginate()
            // / perPage(). Token-normalized so comments and spacing do not hide or forge it.
            if (empty($this->normalizedMatches($file,
                '/\b(PaginatesResults|paginate|cursorPaginate|simplePaginate|perPage)\s*[(;]|use\s+[\w\\\\]*PaginatesResults\b/'))) {
                $offenders[] = $this->rel($file).'  (index() action returns an unpaginated collection — paginate it or allowlist with a reason)';
            }
        }
        $this->assertNoOffenders($offenders,
            'unpaginated list endpoint (D10.12 — every index() paginates; PAGINATION_ALLOWLIST entries need an in-file justification)');
    }

    /** FORCED (D10.7) — every enum under app/Enums (recursively) is a string-backed enum. */
    public function test_enums_are_native_string_backed(): void
    {
        $offenders = [];
        foreach ($this->classesIn(app_path('Enums'), 'App\\Enums') as $class) {
            if (! enum_exists($class)) {
                $offenders[] = "$class is not an enum";

                continue;
            }
            $ref = new ReflectionEnum($class);
            if (! $ref->isBacked() || (string) $ref->getBackingType() !== 'string') {
                $offenders[] = "$class is not string-backed";
            }
        }
        $this->assertNoOffenders($offenders, 'non-conforming enums in app/Enums');
    }

    /**
     * GUARDED — no magic status strings in queries; v2 also catches the array form
     * where(['status' => 'draft']). Residual: variables, query scopes.
     */
    public function test_no_magic_status_strings_in_queries(): void
    {
        $offenders = [];
        foreach ($this->phpFiles(app_path()) as $file) {
            foreach ($this->normalizedMatches($file,
                '/->\s*where\s*\(\s*[\'"](status|state|type)[\'"]\s*,\s*[\'"][a-z_]+[\'"]\s*[,)]'
                .'|->\s*where\s*\(\s*\[\s*[\'"](status|state|type)[\'"]\s*=>\s*[\'"][a-z_]+[\'"]/') as [$line, $ex]) {
                $offenders[] = $this->rel($file).":$line  $ex";
            }
        }
        $this->assertNoOffenders($offenders, "where('status', 'literal') comparisons — use the backed enum");
    }

    /** GUARDED (D6) — no speculative layer suffixes (v2 adds 'Repo'). Residual: novel abbreviations. */
    public function test_no_speculative_layer_suffixes(): void
    {
        $offenders = [];
        foreach ($this->allAppClasses() as $class) {
            foreach (self::BANNED_CLASS_SUFFIXES as $suffix) {
                if (str_ends_with($class, $suffix)) {
                    $offenders[] = $class;
                }
            }
        }
        $this->assertNoOffenders($offenders,
            'banned class suffixes ('.implode('/', self::BANNED_CLASS_SUFFIXES).')');
    }

    /**
     * FORCED-ish (D6) — services are constructor-injected: app()/resolve() helpers AND
     * the App facade's make()/makeWith() (alias-resolved) are banned in controllers.
     * Residual: an injected container instance's ->make().
     *
     * NOTE: the look-behinds run against the token-NORMALIZED stream, where whitespace is
     * collapsed and `->`/`::` carry no trailing space — so they are `(?<!->)`/`(?<!::)`
     * WITHOUT a space (a trailing space made them never fire, falsely flagging injected
     * calls like `$this->svc->resolve(Foo::class)`). `function ` keeps its space (a real
     * token separator survives normalization). Do NOT re-add the space to ->/::.
     */
    public function test_controllers_do_not_service_locate(): void
    {
        $offenders = [];
        foreach ($this->phpFiles(app_path('Http/Controllers')) as $file) {
            foreach ($this->normalizedMatches($file,
                '/(?<!function )(?<!->)(?<!::)\b(app|resolve)\s*\(\s*[A-Z\\\\]/') as [$line, $ex]) {
                $offenders[] = $this->rel($file).":$line  $ex";
            }
            foreach ($this->staticCallsOn($file, ['Illuminate\Support\Facades\App'], ['make', 'makeWith']) as [$line, $ex]) {
                $offenders[] = $this->rel($file).":$line  $ex";
            }
        }
        $this->assertNoOffenders($offenders, 'service-location inside controllers — inject instead');
    }

    /**
     * FORCED (D4.1) — every Eloquent model whose table carries tenant_id uses the tenant
     * scope trait. v2 scans ALL of app/ for Model subclasses, not just app/Models
     * (a model in app/Domain was invisible).
     */
    public function test_every_tenant_model_uses_the_tenant_scope_trait(): void
    {
        $offenders = [];
        foreach ($this->eloquentModels() as $class => $model) {
            if (! Schema::hasColumn($model->getTable(), 'tenant_id')) {
                continue;
            }
            if (! in_array(self::TENANT_TRAIT, class_uses_recursive($class), true)) {
                $offenders[] = "$class (table {$model->getTable()} has tenant_id, trait missing)";
            }
        }
        $this->assertNoOffenders($offenders, 'tenant_id models without '.self::TENANT_TRAIT);
    }

    /** FORCED (D4.2) — org-column models (app-wide) use the org trait or are allowlisted. */
    public function test_every_org_model_is_scoped_or_allowlisted(): void
    {
        $offenders = [];
        foreach ($this->eloquentModels() as $class => $model) {
            if (! $this->hasAnyColumn($model, self::ORG_COLUMNS)) {
                continue;
            }
            $scoped = in_array(self::ORG_TRAIT, class_uses_recursive($class), true);
            $allowed = in_array($class, self::ORG_SCOPED_ALLOWLIST, true);
            if (! $scoped && ! $allowed) {
                $offenders[] = "$class (table {$model->getTable()}) — add the trait or allowlist with a justification";
            }
        }
        $this->assertNoOffenders($offenders, 'org-column models neither trait-scoped nor allowlisted');
    }

    /**
     * FORCED-ish (D4.1-2) — every scoped model is referenced AS CODE (an identifier
     * token: `new X`, `X::`, `use ...X`) in an isolation test. v2: names inside
     * comments or string literals (markTestIncomplete!) no longer count.
     * Residual — and the permanent ceiling: whether the referencing test MEANS anything
     * is undecidable; the behavioral TenantIsolationTest suite + review own that.
     */
    public function test_every_scoped_model_has_an_isolation_test(): void
    {
        $identifiers = [];
        foreach (self::ISOLATION_TEST_GLOBS as $glob) {
            foreach (glob(base_path($glob)) ?: [] as $file) {
                $identifiers += $this->identifierSet($file);
            }
        }
        $offenders = [];
        foreach ($this->eloquentModels() as $class => $model) {
            $scoped = Schema::hasColumn($model->getTable(), 'tenant_id')
                || $this->hasAnyColumn($model, self::ORG_COLUMNS);
            if ($scoped && ! isset($identifiers[class_basename($class)])) {
                $offenders[] = $class;
            }
        }
        $this->assertNoOffenders(
            $offenders,
            'scoped models never referenced (as code) by any isolation test ('.implode(', ', self::ISOLATION_TEST_GLOBS).')'
        );
    }

    /**
     * FORCED-ish (D4.5) — no raw exists rule targets a tenant-scoped table. v2 also
     * catches the builder form Rule::exists('table', ...) with alias resolution.
     * The sanctioned wrapper's own file is exempt by name. Residual:
     * a table name reaching the rule through a variable or constant.
     */
    public function test_no_raw_exists_rule_on_tenant_scoped_tables(): void
    {
        $tenantTables = [];
        foreach ($this->eloquentModels() as $model) {
            if (Schema::hasColumn($model->getTable(), 'tenant_id')) {
                $tenantTables[] = $model->getTable();
            }
        }
        $offenders = [];
        foreach ($this->phpFiles(app_path('Http/Requests')) as $file) {
            if (in_array($this->rel($file), self::EXISTS_RULE_ALLOWLIST, true)
                || str_ends_with($file, 'ScopesExistsToTenant.php')) {
                continue;
            }
            foreach ($this->normalizedMatches($file, '/[\'"]exists:([a-z_]+)/') as [$line, $ex, $m]) {
                if (in_array($m[1], $tenantTables, true)) {
                    $offenders[] = $this->rel($file).":$line  $ex";
                }
            }
            foreach ($this->normalizedMatches($file, '/\bRule\s*::\s*exists\s*\(\s*[\'"]([a-z_]+)[\'"]/') as [$line, $ex, $m]) {
                if (in_array($m[1], $tenantTables, true)) {
                    $offenders[] = $this->rel($file).":$line  $ex";
                }
            }
        }
        $this->assertNoOffenders($offenders, 'raw exists rules on tenant-scoped tables — use existsInTenant()');
    }

    /**
     * FORCED-ish — every API route has an authorization check or a reviewed
     * PUBLIC_ROUTES entry. v2 matches against COMMENT-STRIPPED method source, so a
     * commented-out authorize() no longer counts, and a bare `return true;` hidden
     * behind comments is still bare. Residual: an authorize() call on a
     * dead branch — meaningfulness belongs to review + feature tests.
     */
    public function test_every_route_is_policy_checked_or_explicitly_public(): void
    {
        $offenders = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/')) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                $key = "$method $uri";
                if (in_array($key, self::PUBLIC_ROUTES, true)) {
                    continue;
                }
                if (! $this->routeIsAuthorized($route)) {
                    $offenders[] = $key;
                }
            }
        }
        $this->assertNoOffenders(
            $offenders,
            'routes with no live authorize() call, no real FormRequest::authorize(), and no PUBLIC_ROUTES entry'
        );
    }

    /**
     * Every UNAUTHENTICATED api route carries a rate limiter (D10.2, Tier 1). The abuse
     * surface is the route reachable without a token — login, password reset, tenant
     * resolve, any public read. A route is satisfied by a `throttle:` in its own gathered
     * middleware OR by a project-wide throttle group; authenticated routes (behind
     * `auth:sanctum`) are out of scope here (a valid token is the gate). Same technique as
     * the route-authorization gate — reflection over the resolved route table. FORCED-ish
     * (the middleware list is resolved, not regex-matched; residual: a custom limiter not
     * named `throttle`).
     */
    public function test_public_routes_are_throttled(): void
    {
        $offenders = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/')) {
                continue;
            }
            $middleware = $route->gatherMiddleware();
            // Authenticated routes are out of scope: the token is the gate.
            $authed = false;
            foreach ($middleware as $m) {
                if (is_string($m) && str_starts_with($m, 'auth:')) {
                    $authed = true;
                    break;
                }
            }
            if ($authed) {
                continue;
            }
            $throttled = false;
            foreach ($middleware as $m) {
                if (is_string($m) && str_starts_with($m, 'throttle')) {
                    $throttled = true;
                    break;
                }
            }
            if ($throttled) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                $key = "$method $uri";
                if (in_array($key, self::THROTTLE_ALLOWLIST, true)) {
                    continue;
                }
                $offenders[] = $key;
            }
        }
        $this->assertNoOffenders(
            $offenders,
            'unauthenticated api routes with no throttle: middleware (rate-limit them, add a global throttle group, or allowlist with a reason)'
        );
    }

    /**
     * D4.4 — a queued job must pin its tenant (or declare deliberate cross-tenant access):
     * a background context has no authenticated user, so the tenant scope is silent unless
     * the job re-establishes it. Schema-driven / vacuous-pass: with no `app/Jobs` this
     * finds nothing (like the tenancy gates on a single-tenant project). Every `ShouldQueue`
     * class must reference tenant pinning (`InteractsWithTenancy`, a `tenant`/`tenant_id`
     * property, `CurrentTenant`, or `bypassesTenancy`) or an explicit
     * `// cross-tenant: <reason>` comment. GUARDED (token-normalized reference check).
     */
    public function test_queued_jobs_pin_the_tenant(): void
    {
        $offenders = [];
        foreach ($this->phpFiles(app_path('Jobs')) as $file) {
            $src = (string) file_get_contents($file);
            if (! preg_match('/implements[^\{]*\bShouldQueue\b/', $src)) {
                continue;
            }
            $pins = $this->normalizedMatches($file,
                '/\bInteractsWithTenancy\b|\bCurrentTenant\b|\bbypassesTenancy\b|\btenant_id\b|->\s*tenant\b|cross-tenant\s*:/');
            if (empty($pins)) {
                $offenders[] = $this->rel($file).'  (ShouldQueue job does not pin a tenant — set tenant_id/CurrentTenant, or add a "// cross-tenant: <reason>" comment)';
            }
        }
        $this->assertNoOffenders($offenders,
            'queued job with no tenant pinning (D4.4 — a background context is fail-open unless it re-establishes the tenant)');
    }

    /**
     * FORCED (D3 / Tier 1.8, D4.3) — bootstrap/app.php references (as code) every
     * middleware in REQUIRED_BOOTSTRAP_REFS. Registration is what makes the locale
     * contract and the server-owned-fields strip real; a missing registration
     * previously voided them silently.
     */
    public function test_required_middleware_is_registered_in_bootstrap(): void
    {
        $identifiers = $this->identifierSet(base_path('bootstrap/app.php'));
        $offenders = [];
        foreach (self::REQUIRED_BOOTSTRAP_REFS as $ref) {
            if (! isset($identifiers[$ref])) {
                $offenders[] = "$ref not referenced in bootstrap/app.php";
            }
        }
        $this->assertNoOffenders($offenders, 'required middleware missing from bootstrap/app.php');
    }

    /** D10.22 — project config env() keys documented; .env.example holds no secrets.
     * GUARDED at the edges: env() reads added to an EXISTING vendor config file are out
     * of scope by design (documented residual — the unknown-file assertion below stops
     * new files from rotting, not edits to stock ones). */
    public function test_project_env_keys_are_documented_and_example_holds_no_secrets(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));
        $offenders = [];

        foreach (self::PROJECT_CONFIG_FILES as $config) {
            $src = (string) file_get_contents(base_path($config));
            preg_match_all('/env\(\s*[\'"]([A-Z0-9_]+)[\'"]/', $src, $m);
            foreach (array_unique($m[1]) as $key) {
                if (! preg_match('/^#?\s*'.preg_quote($key, '/').'=/m', $example)) {
                    $offenders[] = "$key (read in $config, absent from .env.example)";
                }
            }
        }

        foreach (glob(config_path('*.php')) ?: [] as $file) {
            $name = basename($file);
            $registered = in_array("config/$name", self::PROJECT_CONFIG_FILES, true)
                || in_array($name, self::KNOWN_VENDOR_CONFIG, true);
            if (! $registered) {
                $offenders[] = "config/$name is neither vendor-known nor in PROJECT_CONFIG_FILES";
            }
        }

        foreach (preg_split('/\R/', $example) ?: [] as $line) {
            if (preg_match('/^([A-Z0-9_]*(SECRET|PASSWORD|TOKEN|PRIVATE)[A-Z0-9_]*)=(.+)$/', trim($line), $m)) {
                $value = trim($m[3]);
                if ($value !== '' && ! is_numeric($value)
                    && ! preg_match('/^(null|true|false|your[-_]|change|example|xxx|\$\{)/i', $value)) {
                    $offenders[] = ".env.example carries a value for {$m[1]}";
                }
            }
        }

        $this->assertNoOffenders($offenders, 'env documentation gate');
    }

    /**
     * GUARDED — no dd()/dump()/ray() calls, no TODO/FIXME/HACK markers. Residual:
     * variable-function calls ($f = 'dd'; $f()) are statically invisible.
     */
    public function test_no_debug_residue_or_todo_markers(): void
    {
        $offenders = [];
        foreach ($this->phpFiles(app_path()) as $file) {
            $tokens = token_get_all((string) file_get_contents($file));
            foreach ($tokens as $i => $token) {
                if (! is_array($token)) {
                    continue;
                }
                [$id, $text, $line] = $token;
                if ($id === T_STRING && in_array($text, ['dd', 'dump', 'ray'], true)
                    && ! $this->isMethodCallAt($tokens, $i)) {
                    $offenders[] = $this->rel($file).":$line  $text()";
                }
                if (in_array($id, [T_COMMENT, T_DOC_COMMENT], true)
                    && preg_match('/\b(TODO|FIXME|HACK|XXX)\b/', $text)) {
                    $offenders[] = $this->rel($file).":$line  marker comment";
                }
            }
        }
        $this->assertNoOffenders($offenders, 'debug calls or TODO/FIXME/HACK/XXX markers in app/');
    }

    /**
     * GUARDED — no credential-shaped literals. Residual: concatenated or encoded
     * secrets are statically invisible (every pattern scanner shares this limit);
     * secret hygiene belongs to review + secret-manager discipline.
     */
    public function test_no_hardcoded_credentials(): void
    {
        $patterns = [
            '/AKIA[0-9A-Z]{16}/' => 'AWS access key',
            '/-----BEGIN [A-Z ]*PRIVATE KEY-----/' => 'private key block',
            '/\bsk-[A-Za-z0-9]{20,}/' => 'provider secret key',
            '/\bghp_[A-Za-z0-9]{30,}/' => 'GitHub token',
            '/\bxox[baprs]-[A-Za-z0-9-]{10,}/' => 'Slack token',
        ];
        $offenders = [];
        foreach (['app', 'config', 'routes', 'database'] as $dir) {
            foreach ($this->phpFiles(base_path($dir)) as $file) {
                $src = (string) file_get_contents($file);
                foreach ($patterns as $pattern => $label) {
                    if (preg_match($pattern, $src)) {
                        $offenders[] = $this->rel($file)."  ($label)";
                    }
                }
            }
        }
        $this->assertNoOffenders($offenders, 'credential-shaped literals');
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Machinery — token-normalized matching (v2)
    // ─────────────────────────────────────────────────────────────────────────────

    /** @var array<string, array{0: string, 1: list<array{0:int,1:int}>}> */
    private array $normalizedCache = [];

    /**
     * The file's source with comments removed and every run of whitespace collapsed to
     * one space, plus an offset→line map. Matching against this stream is immune to
     * comment tricks, line splits and spacing games.
     *
     * @return array{0: string, 1: list<array{0:int,1:int}>} [stream, [[offset, line], …]]
     */
    private function normalized(string $file): array
    {
        if (isset($this->normalizedCache[$file])) {
            return $this->normalizedCache[$file];
        }
        $stream = '';
        $map = [];
        foreach (token_get_all((string) file_get_contents($file)) as $token) {
            if (is_array($token)) {
                [$id, $text, $line] = $token;
                if (in_array($id, [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                if ($id === T_WHITESPACE) {
                    if ($stream !== '' && ! str_ends_with($stream, ' ')) {
                        $stream .= ' ';
                    }

                    continue;
                }
                $map[] = [strlen($stream), $line];
                $stream .= $text;
            } else {
                $map[] = [strlen($stream), $map === [] ? 1 : end($map)[1]];
                $stream .= $token;
            }
        }

        return $this->normalizedCache[$file] = [$stream, $map];
    }

    /**
     * All matches of $pattern in the normalized stream, line-attributed.
     *
     * @return list<array{0:int, 1:string, 2:array<int,string>}> [line, excerpt, groups]
     */
    private function normalizedMatches(string $file, string $pattern): array
    {
        [$stream, $map] = $this->normalized($file);
        if (! preg_match_all($pattern, $stream, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return [];
        }
        $out = [];
        foreach ($m as $set) {
            [$text, $offset] = $set[0];
            $out[] = [
                $this->lineAt($map, $offset),
                trim(substr($stream, $offset, 90)),
                array_map(fn ($g) => is_array($g) ? $g[0] : $g, $set),
            ];
        }

        return $out;
    }

    /** @param list<array{0:int,1:int}> $map */
    private function lineAt(array $map, int $offset): int
    {
        $line = 1;
        foreach ($map as [$pos, $l]) {
            if ($pos > $offset) {
                break;
            }
            $line = $l;
        }

        return $line;
    }

    /**
     * The file's `use` imports: lowercase alias => fully-qualified name.
     *
     * @return array<string, string>
     */
    private function useMap(string $file): array
    {
        $map = [];
        foreach ($this->normalizedMatches($file,
            '/\buse\s+((?:\\\\?[A-Za-z_][A-Za-z0-9_]*)+(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*)\s*(?:as\s+([A-Za-z_][A-Za-z0-9_]*))?\s*;/') as [, , $g]) {
            $fqn = ltrim(str_replace(' ', '', $g[1]), '\\');
            $alias = $g[2] ?? '' ?: substr($fqn, (int) strrpos('\\'.$fqn, '\\'));
            $alias = $alias !== '' ? $alias : $fqn;
            $map[strtolower(ltrim($alias, '\\'))] = $fqn;
        }

        return $map;
    }

    /** Lines where `new X(` constructs $targetFqn (via alias, import, or FQN). @return list<int> */
    private function newInstancesOf(string $file, string $targetFqn): array
    {
        $uses = $this->useMap($file);
        $lines = [];
        foreach ($this->normalizedMatches($file, '/\bnew\s+(\\\\?[A-Za-z_][A-Za-z0-9_\\\\]*)\s*\(/') as [$line, , $g]) {
            $name = ltrim($g[1], '\\');
            $resolved = $uses[strtolower(explode('\\', $name)[0])] ?? null;
            $full = $resolved !== null
                ? $resolved.substr($name, strlen(explode('\\', $name)[0]))
                : $name;
            if (strcasecmp(str_replace(' ', '', $full), $targetFqn) === 0) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * Static calls `X::method(` where X resolves to one of $targetFqns (alias-aware).
     * With $methods = [] any static call on the target counts.
     *
     * @param  list<string>  $targetFqns
     * @param  list<string>  $methods
     * @return list<array{0:int,1:string}>
     */
    private function staticCallsOn(string $file, array $targetFqns, array $methods = []): array
    {
        $uses = $this->useMap($file);
        $out = [];
        foreach ($this->normalizedMatches($file,
            '/(\\\\?[A-Za-z_][A-Za-z0-9_\\\\]*)\s*::\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(/') as [$line, $ex, $g]) {
            $name = ltrim($g[1], '\\');
            $head = strtolower(explode('\\', $name)[0]);
            $full = isset($uses[$head])
                ? $uses[$head].substr($name, strlen(explode('\\', $name)[0]))
                : $name;
            foreach ($targetFqns as $target) {
                $short = substr($target, (int) strrpos($target, '\\') + 1);
                $hit = strcasecmp($full, $target) === 0 || strcasecmp($name, $short) === 0;
                if ($hit && ($methods === [] || in_array($g[2], $methods, true))) {
                    $out[] = [$line, $ex];
                }
            }
        }

        return $out;
    }

    /** True when the file's first statement (comments skipped) is declare(strict_types=1). */
    private function firstStatementIsStrictTypes(string $file): bool
    {
        $significant = [];
        foreach (token_get_all((string) file_get_contents($file)) as $token) {
            if (is_array($token) && in_array($token[0], [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_INLINE_HTML], true)) {
                continue;
            }
            $significant[] = is_array($token) ? $token : [0, $token, 0];
            if (count($significant) >= 7) {
                break;
            }
        }

        // declare ( strict_types = 1 )
        return isset($significant[4])
            && $significant[0][0] === T_DECLARE
            && $significant[1][1] === '('
            && $significant[2][0] === T_STRING && strcasecmp($significant[2][1], 'strict_types') === 0
            && $significant[3][1] === '='
            && $significant[4][1] === '1';
    }

    /**
     * Identifiers referenced AS CODE in a file: T_STRING plus the parts of qualified
     * names. Comments and string literals contribute nothing — a model name inside
     * markTestIncomplete('…') or a comment does not count.
     *
     * @return array<string, true>
     */
    private function identifierSet(string $file): array
    {
        $set = [];
        if (! is_file($file)) {
            return $set;
        }
        foreach (token_get_all((string) file_get_contents($file)) as $token) {
            if (! is_array($token)) {
                continue;
            }
            [$id, $text] = $token;
            if ($id === T_STRING) {
                $set[$text] = true;
            } elseif (defined('T_NAME_QUALIFIED') && in_array($id, [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)) {
                foreach (explode('\\', $text) as $part) {
                    if ($part !== '') {
                        $set[$part] = true;
                    }
                }
            }
        }

        return $set;
    }

    /** @return list<string> absolute paths of .php files under $dir */
    private function phpFiles(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }
        $files = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $files[] = $f->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /** @return list<class-string> PSR-4-inferred classes in a directory (recursive) */
    private function classesIn(string $dir, string $namespace): array
    {
        $classes = [];
        foreach ($this->phpFiles($dir) as $file) {
            $relative = str_replace([$dir.DIRECTORY_SEPARATOR, '.php'], '', $file);
            $class = $namespace.'\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relative);
            if (class_exists($class) || enum_exists($class) || interface_exists($class) || trait_exists($class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /** @return list<class-string> */
    private function allAppClasses(): array
    {
        return $this->classesIn(app_path(), 'App');
    }

    /**
     * ALL concrete Eloquent models anywhere under app/ (v2 — not just app/Models).
     *
     * @return array<class-string, Model>
     */
    private function eloquentModels(): array
    {
        static $models = null;
        if ($models !== null) {
            return $models;
        }
        $models = [];
        foreach ($this->allAppClasses() as $class) {
            if (! class_exists($class)) {
                continue;
            }
            $ref = new ReflectionClass($class);
            if ($ref->isAbstract() || ! $ref->isSubclassOf(Model::class)) {
                continue;
            }
            $models[$class] = $ref->newInstance();
        }

        return $models;
    }

    private function hasAnyColumn(Model $model, array $columns): bool
    {
        foreach ($columns as $column) {
            if (Schema::hasColumn($model->getTable(), $column)) {
                return true;
            }
        }

        return false;
    }

    private function routeIsAuthorized(\Illuminate\Routing\Route $route): bool
    {
        $action = $route->getActionName();
        if (! str_contains($action, '@')) {
            return false; // closures must be PUBLIC_ROUTES entries
        }
        [$class, $method] = explode('@', $action);
        if (! class_exists($class) || ! method_exists($class, $method)) {
            return false;
        }
        $body = $this->strippedMethodSource(new ReflectionMethod($class, $method));
        if (preg_match('/\$this\s*->\s*authorize\s*\(|\bGate\s*::\s*(authorize|allows|denies)\s*\(/', $body)) {
            return true;
        }
        foreach ((new ReflectionMethod($class, $method))->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()
                && is_subclass_of($type->getName(), FormRequest::class)) {
                $requestRef = new ReflectionClass($type->getName());
                if (! $requestRef->hasMethod('authorize')) {
                    continue;
                }
                $authorize = $this->strippedMethodSource($requestRef->getMethod('authorize'));
                // A bare `return true;` is not authorization (D10.4) — comment-proof.
                if (! preg_match('/\{\s*return\s+true\s*;\s*\}/', $authorize)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Method source with comments stripped and whitespace collapsed. */
    private function strippedMethodSource(ReflectionMethod $method): string
    {
        $file = $method->getFileName();
        if ($file === false) {
            return '';
        }
        $lines = file($file) ?: [];
        $raw = implode('', array_slice($lines, $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1));
        $clean = '';
        foreach (token_get_all('<?php '.$raw) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG], true)) {
                    continue;
                }
                $clean .= $token[0] === T_WHITESPACE ? ' ' : $token[1];
            } else {
                $clean .= $token;
            }
        }

        return $clean;
    }

    /** True when tokens[$i] is preceded by -> :: or function (i.e. not a global call). */
    private function isMethodCallAt(array $tokens, int $i): bool
    {
        for ($j = $i - 1; $j >= 0; $j--) {
            $prev = $tokens[$j];
            if (is_array($prev) && $prev[0] === T_WHITESPACE) {
                continue;
            }
            if (is_array($prev)) {
                return in_array($prev[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true);
            }

            return false;
        }

        return false;
    }

    private function rel(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), DIRECTORY_SEPARATOR);
    }

    /** @param list<string> $offenders */
    private function assertNoOffenders(array $offenders, string $rule): void
    {
        $shown = array_slice($offenders, 0, 25);
        $hidden = count($offenders) - count($shown);
        $this->assertSame([], $offenders,
            "Architecture rule violated — $rule:\n  ".implode("\n  ", $shown)
            .($hidden > 0 ? "\n  (and $hidden more)" : ''));
    }
}
