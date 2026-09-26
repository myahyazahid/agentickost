# ADR 0003: Pola Action dan aktor

- Status: diterima
- Tanggal: 26 September 2026
- Acuan: PRD §1 ("semua aksi lewat satu pintu"), §10.1, §12.4

## Konteks

UI Filament, API, dan agent AI harus menjalankan logika bisnis yang sama beserta validasi dan permission-nya. Agent tercatat sebagai aktor bertipe `agent`.

## Keputusan

Setiap operasi bisnis adalah satu kelas `final` di `app/Modules/{Nama}/Actions/` yang meng-extend `App\Support\Actions\Action` dan punya method `handle()`. Urutan di dalam `handle()`:

1. `$this->authorize($ability, $arguments)`: cek policy/permission untuk aktor saat ini.
2. `$this->validate($input, $rules)`: validasi input di dalam Action, bukan hanya di form, agar API dan agent ikut tervalidasi.
3. `$this->transaction(fn () => ...)`: semua penulisan, termasuk audit log dan listener event sinkron, dalam satu transaksi.

Contoh: `CreateProperty`, `AssignStaffToProperty`, `CreateUser`, `CreateTenant`.

**Aktor.** `ActorContext` (scoped) menyimpan aktor saat ini (`user`, `resident`, `system`, `agent`, `platform_admin`) dan ikut ke job antrian lewat `Context`.

| Aktor | Otorisasi |
|---|---|
| `user` | Gate/policy Laravel dengan user tersebut |
| `system` | Lolos, **hanya bila di-set eksplisit** lewat `actingAs(Actor::system(), ...)`. Request tanpa login tidak pernah jatuh ke hak sistem |
| `agent`, `resident`, `platform_admin` | Ditolak sampai aturannya dibuat (F3, M1.5.3, M1.9) |

## Konsekuensi

- Arch test menjaga Action tetap `final`, meng-extend base class, dan punya `handle()`. Pemanggilan `authorize()` dan `transaction()` belum bisa dijaga otomatis; dicek saat review.
- `SyncTenantRoles` sengaja tidak memanggil `authorize()`: ia provisioning internal yang hanya dipanggil listener `TenantCreated` dan command `access:sync-roles`.
- Command dan job terjadwal yang memanggil Action harus membungkusnya dengan `actingAs(Actor::system(), ...)`.
