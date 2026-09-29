# ADR 0010: Portal penghuni

- Status: diterima
- Tanggal: 29 September 2026
- Acuan: PRD §7.17 (FR-PRT-01 sampai FR-PRT-07), §12, §14.3, schema §16 no. 3

Penghuni dan pembayar butuh tempat melihat tagihan, mengirim bukti transfer, dan melaporkan kerusakan dari HP, tanpa akun dan password seperti staf.

## Keputusan

1. **Teknologi: Livewire + Tailwind sebagai PWA di aplikasi yang sama**, mengikuti pilihan utama PRD §12. Portal memakai Action, model, dan isolasi tenant yang sudah ada. Panel Filament tidak dipakai karena terlalu berat untuk HP penghuni. Keputusan ini masih bisa ditinjau (PRD §18 no. 5).
2. **Alamat per tenant: `/p/{slug}`.** Satu nomor bisa menjadi penghuni di dua tenant, jadi URL yang menentukan tenant (schema §16 no. 3). Tenant yang dibekukan tidak punya portal.
3. **Login dengan kode lewat WhatsApp, tanpa password.**
   - Guard `resident` untuk penghuni; guard `payer` untuk pembayar yang tinggal di tempat lain (FR-PRT-07).
   - Pencarian akun berjalan di dalam tenant dari URL, jadi sesi dari tenant lain tidak menemukan siapa pun.
   - Kode 6 angka, berlaku 5 menit, sekali pakai, mati setelah 5 kali salah.
   - Permintaan kode dibatasi per nomor dan per alamat IP. Nomor yang tidak terdaftar mendapat jawaban yang sama, supaya formulir tidak bisa dipakai menebak siapa tinggal di mana.
   - Pesan dikirim lewat `App\Support\Messaging\MessageChannel` (PRD §14.3). Sampai penyedia WhatsApp dipilih, satu-satunya driver adalah `log`, dan di production halaman login menolak mengirim kode.
4. **Hak lihat ditentukan nomor HP**, oleh `Lease\Support\PortalAccess`:
   - Penghuni melihat kontrak yang masih ia tinggali dan kontrak yang ia bayar.
   - Pembayar hanya melihat kontrak yang ia bayar, tanpa laporan kerusakan dan pengumuman.
   - Kontrak draf tidak pernah tampil. Penghuni yang sudah keluar dari kamar tidak melihat kontrak itu lagi.
   - Semua halaman membaca lewat `Portal\Support\PortalQueries`.
5. **Aksi dari portal punya Action sendiri di modul pemiliknya:** `Payment\Actions\SubmitPaymentProof` dan `Maintenance\Actions\ReportTicketFromPortal`.
   - `ActorContext` mengotorisasi aktor `resident` dan `payer` lewat Gate dengan model penghuni atau pembayar.
   - Ability portal di policy menerima `Authenticatable`, bukan `User`.
   - Bukti transfer masuk antrean verifikasi staf. Laporan kerusakan mulai dengan prioritas normal. Staf yang berwenang diberi tahu lewat notifikasi panel.
6. **Catatan staf di tiket tidak tampil di portal.** Penghuni hanya melihat perubahan status.
7. **PWA:**
   - Manifest per tenant, dengan nama dan warna usaha.
   - Service worker hanya menyimpan halaman offline. Tagihan dan pembayaran bersifat pribadi, jadi selalu diambil dari server.
   - Ikon memakai inisial usaha sebagai pengganti sampai owner bisa mengunggah ikon aplikasi.
8. **Sakelar pengembangan:** `AGENTICKOST_PORTAL_OTP_REQUIRED=false` membuat nomor HP saja cukup untuk masuk, dan diabaikan di `APP_ENV=production`.

## Konsekuensi

- Login portal di production baru bisa dipakai setelah driver WhatsApp dibuat (M1.5.4).
- Saat tenant dalam mode baca saja, portal tetap bisa dibuka, tetapi bukti transfer dan laporan kerusakan ditolak dengan pesan yang meminta penghuni menghubungi pengelola.
- Data penghuni dan pembayar kini juga entitas login: kolom `remember_token` di `residents` dan `payers`, dan tipe aktor `payer` di audit log.
