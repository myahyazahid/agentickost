# ADR 0005: Audit log

- Status: diterima
- Tanggal: 26 September 2026
- Acuan: PRD FR-USR-04, NFR-PDP-04; schema §4.4

## Konteks

Setiap aksi yang mengubah data harus tercatat: pelaku (user, sistem, agent, super admin), waktu, data sebelum dan sesudah. PRD menyebut `spatie/laravel-activitylog` sebagai kandidat.

## Keputusan

Membuat tabel dan model `audit_logs` sendiri sesuai schema §4.4, bukan memakai `spatie/laravel-activitylog`. Alasannya: schema butuh `actor_type` untuk aktor tanpa model (sistem, agent), kolom `reason` dan `impersonation_log_id`, dan id ULID. Menyesuaikan paket itu lebih mahal daripada ~100 baris kode sendiri.

- Trait `Auditable` mencatat `created`, `updated` (hanya kolom yang berubah, nilai lama dan baru), `deleted`, dan `restored` dengan nama event `{alias morph}.{aksi}`, misal `property.updated`. Kolom hidden disimpan sebagai `[redacted]`; `remember_token` dan timestamp diabaikan.
- Action mencatat event bisnis yang butuh nama atau alasan lewat `AuditLogger::record()`, misal `penalty.waived` dengan `reason`.
- Pelaku diambil dari `ActorContext`, termasuk alamat IP untuk request web.
- Entri ditulis dalam transaksi yang sama dengan perubahan datanya, jadi ikut batal bila transaksi gagal.
- `AuditLog` append-only: update dan delete melempar exception. `actor_type` dijaga CHECK constraint.
- Harness isolasi mewajibkan setiap model tenant memakai `Auditable`.

## Konsekuensi

- Perubahan lewat query builder (`Model::query()->update()`) tidak memicu event model, jadi **tidak tercatat**. Action harus mengubah data lewat instance model, atau mencatat sendiri dengan `AuditLogger`.
- Tampilan audit log untuk owner (permission `audit.view`) dibuat saat UI pertama membutuhkannya.
