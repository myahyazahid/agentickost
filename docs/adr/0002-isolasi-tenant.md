# ADR 0002: Isolasi tenant

- Status: diterima
- Tanggal: 26 September 2026
- Acuan: PRD §11.1 (NFR-ISO-01 sampai 05), §12.5

## Konteks

Satu database dengan kolom `tenant_id`. Kebocoran data antar tenant adalah insiden kritis, jadi isolasi harus gagal-tertutup: lupa memasang konteks harus berujung error, bukan data tenant lain.

## Keputusan

**Konteks tenant.** `TenantContext` (scoped per request/job) menyimpan tenant aktif. Sumbernya:

| Jalur | Cara konteks diisi |
|---|---|
| Panel `app` | Middleware `SetTenantContext` dari user login, dipasang persistent agar request Livewire ikut |
| Route lain | Alias middleware `tenant` |
| Job antrian | Otomatis: id tenant ikut di `Context` Laravel (hidden) dan dipulihkan saat job mulai |
| Perintah terjadwal | Eksplisit: `TenantContext::each()` untuk semua tenant aktif, atau `run($tenant, ...)` |

**Trait `BelongsToTenant`** untuk setiap model data tenant:

- Global scope `TenantScope` membatasi query ke tenant aktif, dan **melempar `MissingTenantContext`** bila tidak ada konteks.
- `tenant_id` diisi otomatis saat create. `tenant_id` tidak bisa diubah, dan menyimpan/menghapus data tenant lain melempar `TenantMismatch`.
- Create dengan `tenant_id` eksplisit di luar konteks tetap diizinkan untuk provisioning, seeder, dan factory.

**Login.** User dicari sebelum tenant diketahui, jadi user provider `tenant_users` melewati scope tenant. Query `User` lain tetap ter-scope.

**Cache dan berkas.** `TenantCache` memberi awalan `tenant:{id}:` pada kunci. `TenantStorage` menyimpan berkas di `tenants/{id}/` dan hanya menerbitkan temporary URL untuk path milik tenant aktif (menolak `..`).

**Harness test** (`tests/Feature/TenantIsolationTest.php`) menemukan model dari `app/Modules/*/Models` dan untuk setiap model tenant menguji: daftar hanya milik tenant sendiri, `find()` id tenant lain mengembalikan null, delete lewat query tidak mengenai tenant lain, `tenant_id` terisi otomatis, simpan/pindah ke tenant lain ditolak, query tanpa konteks ditolak. Harness juga gagal bila ada model yang bukan model tenant maupun model platform yang dikenal, atau ada tabel ber-`tenant_id` tanpa model tenant.

Filament multi-tenancy bawaan tidak dipakai: fitur itu untuk user yang pindah-pindah tim lewat URL, sedangkan di sini satu user terikat ke satu tenant (schema §16 no. 2).

## Konsekuensi

- Setiap model tenant baru wajib punya factory yang bekerja di dalam konteks tenant; harness memakainya.
- Kode platform (super admin) yang perlu lintas tenant harus eksplisit memakai `run()` per tenant atau `withoutGlobalScope(TenantScope::class)`.
- **Jebakan:** jangan kembalikan `PendingDispatch` dari closure `run()`/`actingAs()` (misal `fn () => Job::dispatch()`). Job baru dikirim saat objek itu dihancurkan, setelah konteks dipulihkan, sehingga job berangkat tanpa tenant. Tulis sebagai blok: `function () { Job::dispatch(); }`.
- Harness baru menguji lapisan model. Uji lewat UI/API dan tool agent ditambahkan saat resource dan endpoint pertama dibuat.
