# KostPilot

SaaS ERP untuk usaha kost, multi-tenant, dengan lapisan AI agent di atasnya.

- Kebutuhan produk: [`docs/prd.md`](docs/prd.md)
- Skema database: [`docs/schema.md`](docs/schema.md)
- Urutan kerja: [`docs/roadmap (1).md`](<docs/roadmap (1).md>)
- Deploy staging: [`docs/deployment.md`](docs/deployment.md)

## Stack

Laravel 13, Filament 5, MySQL 8.4, Redis + Horizon, object storage S3-compatible, Pest, Pint, Larastan, Sentry, Tailwind CSS 4.

## Setup lokal

Kebutuhan: PHP 8.4+ (ekstensi `pdo_mysql`, `redis`, `intl`, `zip`, `bcmath`), Composer 2, Node.js 22+, MySQL 8.4, Redis. Di Windows, MySQL dan Redis bisa dari Laragon.

```sh
composer install
cp .env.example .env
php artisan key:generate
```

Buat dua database, satu untuk aplikasi dan satu khusus test:

```sql
CREATE DATABASE agentickost CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE agentickost_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```sh
php artisan migrate --seed
npm install
npm run build
```

Seeder membuat akun demo (password semua `password`):

| Akun | Panel |
|---|---|
| `admin@example.com` | `/admin` (super admin) |
| `owner@example.com` | `/app` (owner tenant "Kost Demo") |

Tenant baru beserta owner-nya dibuat dengan `php artisan tenant:create`.

### Object storage lokal

`.env.example` memakai disk `s3` yang mengarah ke SeaweedFS di `http://127.0.0.1:8333`. Unduh `weed` dari [rilis SeaweedFS](https://github.com/seaweedfs/seaweedfs/releases), lalu jalankan dengan kredensial yang sama dengan `.env`:

```sh
# macOS / Linux
AWS_ACCESS_KEY_ID=kostpilot AWS_SECRET_ACCESS_KEY=kostpilot-secret \
  weed mini -dir=$HOME/seaweed-data -bucket=agentickost

# Windows PowerShell
$env:AWS_ACCESS_KEY_ID="kostpilot"; $env:AWS_SECRET_ACCESS_KEY="kostpilot-secret"
weed.exe mini -dir="$HOME\seaweed-data" -bucket=agentickost
```

Kalau belum butuh upload file, set `FILESYSTEM_DISK=local` di `.env`.

### Menjalankan aplikasi

```sh
composer run dev
```

| URL | Isi |
|---|---|
| `/app` | Panel owner dan staf |
| `/admin` | Panel super admin |
| `/horizon` | Dashboard antrian; di luar `local` hanya untuk super admin yang login di `/admin` |

Keputusan arsitektur (modul, isolasi tenant, pola Action, peran, audit log) ada di [`docs/adr/`](docs/adr/).

Horizon butuh ekstensi `pcntl`, yang tidak ada di PHP Windows. Di Windows, proses antrian dengan `php artisan queue:work`; di macOS/Linux pakai `php artisan horizon`.

## Pemeriksaan kode

```sh
composer test      # Pest, memakai database agentickost_testing
composer lint      # Laravel Pint
composer analyse   # Larastan level 8
```

CI menjalankan ketiganya di setiap push dan pull request.
