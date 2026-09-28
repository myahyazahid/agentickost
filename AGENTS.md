# KostPilot

SaaS ERP kost multi-tenant (Laravel 13, Filament 5). Sebelum mengubah kode, baca dokumen yang relevan:

- `docs/prd.md`: kebutuhan produk. ID requirement (`FR-…`, `NFR-…`) dan kode modul (`BIL`, `PAY`, dst.) merujuk ke sini.
- `docs/schema.md`: skema database dan konvensinya (ULID, uang, waktu, indeks).
- `docs/roadmap (1).md`: urutan milestone. Centang tugas yang selesai dan commit bersama kodenya. Fitur di luar roadmap dicatat di bagian Parkir, tidak langsung dikerjakan.
- `docs/deployment.md`: CI dan deploy staging.
- `docs/adr/`: keputusan arsitektur (modul, isolasi tenant, Action, peran, audit log, state machine, uang). Ikuti pola di sana.

## Aturan inti dari PRD

- Isolasi tenant (§11.1): setiap tabel data tenant punya `tenant_id` dengan global scope. Job, perintah terjadwal, cache, dan path storage membawa konteks tenant. Kebocoran data antar tenant adalah insiden kritis.
- Logika bisnis hanya di Action class (§12.4). Filament, API, dan agent memanggil Action yang sama.
- Uang disimpan sebagai integer rupiah (§8.1), tidak pernah float.
- Waktu disimpan dalam UTC; aplikasi dan sesi MySQL sudah diset UTC. Tampilan dan pergantian hari mengikuti zona waktu properti (NFR-LOC-02).
- Dokumen keuangan tidak diedit atau dihapus; koreksi lewat void, credit note, atau pembalikan (§8.10).
- Antarmuka berbahasa Indonesia.

## Struktur

- Modul domain di `app/Modules/{Nama}/` dengan `{Nama}ServiceProvider` (terdaftar otomatis). Isi umum: `Actions/`, `Models/`, `Enums/`, `Policies/`, `Database/Migrations/`, `Database/Factories/`, `Filament/App|Admin/`. Kode lintas modul tanpa domain di `app/Support/`.
- Modul saat ini: `Tenancy` (tenant, konteks tenant, super admin), `Access` (user, peran, audit log), `Documents` (lampiran, penomoran dokumen), `Property` (properti, kamar, harga, pengaturan), `Lease` (penghuni, pembayar, kontrak), `Billing` (tagihan, denda, nota kredit, tarif utilitas, meteran), `Payment` (pembayaran, alokasi, saldo kredit, kas staf, kuitansi), `Finance` (akun, rekening tujuan, ledger deposit).
- Modul boleh membaca model modul lain, tetapi menulis lewat Action modul pemiliknya. Contoh: Billing memajukan kursor tagihan kontrak lewat `Lease\Support\BillingCursor`; Payment mengubah `paid_amount` tagihan lewat `Billing\Support\InvoicePayments` dan menulis ledger deposit lewat `Finance\Support\DepositLedger`.
- Panel Filament `app` di `/app` untuk owner dan staf (guard `web`), panel `admin` di `/admin` untuk super admin (guard `platform`, model `PlatformAdmin`).

## Pola wajib

- Model data tenant memakai `BelongsToTenant` dan `Auditable`, punya factory, dan alias morph di provider modulnya. `tests/Feature/TenantIsolationTest.php` otomatis menguji model baru dan gagal bila salah satu syarat ini terlewat.
- Operasi bisnis adalah Action `final` di `Actions/` yang meng-extend `App\Support\Actions\Action`: `authorize()` → `validate()` → `transaction()`.
- Permission baru ditambahkan sebagai case di enum permission modul (`DefinesPermissions`), lalu `php artisan access:sync-roles`.
- Command dan job terjadwal yang memanggil Action dibungkus `ActorContext::actingAs(Actor::system(), ...)`, dan untuk data tenant `TenantContext::each()` atau `run()`. Callback yang mengubah variabel di luarnya (misal penghitung) ditulis sebagai `function () use (&$count)`, bukan arrow function: arrow function menyalin nilainya sehingga perubahan hilang.
- Tanggal bisnis (jatuh tempo, periode, telat) dibandingkan dengan `Property::today()`, bukan `now()`, supaya mengikuti zona waktu properti.
- Nominal uang memakai cast `RupiahCast` dan ditampilkan dengan `Rupiah::format()`.
- Status domain memakai `spatie/laravel-model-states` + `EnforcesStateTransitions`.

## Lingkungan lokal

- Database aplikasi `agentickost`. Test memakai MySQL `agentickost_testing` (diset di `phpunit.xml`), bukan SQLite.
- Redis untuk queue dan cache. Horizon butuh `pcntl`, jadi di Windows jalankan `php artisan queue:work`.
- `composer.json` mendeklarasikan `ext-pcntl` dan `ext-posix` di `config.platform` agar `composer install` berhasil di Windows. Jangan dihapus.
- Disk `s3` mengarah ke SeaweedFS lokal di `http://127.0.0.1:8333` (lihat README).
- `php artisan migrate:fresh --seed` membuat super admin `admin@example.com`, tenant "Kost Demo" dengan owner `owner@example.com`, dan satu properti. Password semua akun: `password`.
- Tenant baru: `php artisan tenant:create`. Jangan pakai `make:filament-user` untuk panel `app` karena user wajib punya tenant.

## Sebelum menyatakan selesai

`composer test`, `composer lint`, dan `composer analyse` (Larastan level 8) harus lulus. CI menjalankan pemeriksaan yang sama.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record a rule with `record-rule` only when the user explicitly asks for one. Instructions for the work at hand are not rules, no matter how emphatic: "remove this typo", "use X here" are work to do, not rules to record. Never record a rule on your own initiative, as a byproduct of a change, or to summarize what you just did. When the user does ask, pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Use `record-rule` rather than your native memory or notes tool, because native memory is personal and session-scoped, while only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>

<!-- antislop:start -->
## antislop
For UI, copy, people, mobile layout, or code comments work, load the antislop skill for the task:
- Core filter, always on: `antislop`
- UI / visual: `antislop-ui`
- Copy & text: `antislop-copywriting`
- People: `antislop-human`
- Mobile / responsive: `antislop-layoutmobile`
- Code comments: `antislop-code`
Before starting, ask the user when antislop applies: during the work, or after it is done.
To update antislop later: `npx antislop-ai --update`, or run `npx antislop-ai` and pick Overwrite them.
<!-- antislop:end -->
