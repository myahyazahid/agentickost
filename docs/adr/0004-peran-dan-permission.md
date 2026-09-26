# ADR 0004: Peran, permission, dan akses per properti

- Status: diterima
- Tanggal: 26 September 2026
- Acuan: PRD FR-USR-01, FR-USR-02; schema §4.2, §4.3

## Keputusan

- Memakai `spatie/laravel-permission` dengan fitur teams; kunci tim adalah `tenant_id`. `TenantTeamResolver` membaca tim dari `TenantContext`, jadi peran selalu dibaca untuk tenant aktif. Mengganti tim lewat API spatie ditolak; gunakan `TenantContext::run()`.
- Tabel spatie memakai id bawaan (bigint) untuk `roles` dan `permissions` sesuai schema §4.2; kolom `model_id` dan `tenant_id` bertipe ULID.
- Peran bawaan ada di enum `Role`: Owner, Manajer, Penjaga, Akuntan, Penghuni. Empat peran staf dibuat per tenant saat tenant dibuat. Penghuni bukan baris di `users`; perannya dipakai portal penghuni (M1.5.3).
- Permission didefinisikan per modul sebagai enum string yang mengimplementasikan `DefinesPermissions` (nama `{modul}.{aksi}`, misal `property.create`) beserta peran yang mendapatkannya secara default. Modul mendaftarkan enum-nya ke `PermissionRegistry` dari service provider.
- `access:sync-roles` menyinkronkan permission dan peran bawaan untuk semua tenant; dijalankan di setiap deploy.
- **Akses per properti:** Owner dan Akuntan melihat semua properti. Manajer dan Penjaga hanya properti yang ditugaskan lewat `property_user`. Diterapkan di policy (`Property::isAccessibleBy()`) dan scope query `accessibleBy($user)` untuk daftar.

| Permission | Owner | Manajer | Penjaga | Akuntan |
|---|:-:|:-:|:-:|:-:|
| `property.view` | ✓ | ✓ | ✓ | ✓ |
| `property.update` | ✓ | ✓ | | |
| `property.create`, `property.delete`, `property.assign-staff` | ✓ | | | |
| `user.manage` | ✓ | | | |
| `audit.view` | ✓ | | | ✓ |

## Konsekuensi

- Menambah permission cukup menambah case di enum modul lalu menjalankan `access:sync-roles`.
- Peran kustom (P1) nanti memakai tabel yang sama; `SyncTenantRoles` hanya menyentuh empat peran bawaan.
- Resource Filament yang menampilkan data per properti wajib memakai `accessibleBy()` di query-nya, selain policy.
