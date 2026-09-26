# ADR 0001: Modul domain dan registrasinya

- Status: diterima
- Tanggal: 26 September 2026
- Acuan: PRD §12.1, §12.4

## Konteks

PRD memilih modular monolith: satu aplikasi Laravel, dibagi per modul domain dengan batas tegas, agar peristiwa keuangan tetap dalam satu transaksi database.

## Keputusan

- Setiap modul berada di `app/Modules/{Nama}/` dan wajib punya `{Nama}ServiceProvider` yang meng-extend `App\Support\Modules\ModuleServiceProvider`.
- `ModuleRegistry` menemukan modul dari konvensi nama tersebut; `AppServiceProvider` mendaftarkan semua provider modul. Modul baru tidak perlu didaftarkan manual.
- Provider dasar memuat migration dari `Database/Migrations` modul. Factory berada di `Database/Factories` modul dan dihubungkan ke model dengan atribut `#[UseFactory]`.
- Panel Filament menemukan resource, page, dan widget dari `app/Modules/{Nama}/Filament/App` dan `.../Filament/Admin`.
- Setiap modul mendaftarkan alias morph modelnya (`Relation::enforceMorphMap`). Alias dipakai di audit log dan tabel polimorfik, sehingga nama kelas tidak tersimpan di database.
- Kode lintas modul yang tidak memiliki domain (base Action, aktor, uang, zona waktu, state machine) berada di `app/Support/`. `App\Support` tidak boleh bergantung pada `App\Modules` (dijaga arch test).
- Modul yang dibuat di M0.3: `Tenancy` (tenant, konteks tenant, super admin), `Access` (user, peran, audit log; kode modul USR di PRD), dan `Property` sebagai model contoh nyata. `Access` tidak ada di daftar PRD §12.4; ditambahkan karena USR butuh tempat sendiri.

## Konsekuensi

- Arah dependensi saat ini: `Property → Access → Tenancy`, dan semua modul → `Support`. Modul lain berkomunikasi lewat Action publik dan event (contoh: `TenantCreated` didengarkan `Access` untuk membuat peran).
- Aturan "modul tidak mengubah tabel modul lain" belum dijaga otomatis; baru lewat review. Arch test batas modul bisa ditambahkan saat jumlah modul bertambah.
