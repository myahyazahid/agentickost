# ADR 0009: Langganan, batas paket, dan mode baca saja

- Status: diterima
- Tanggal: 29 September 2026
- Acuan: PRD FR-SUB-01 sampai FR-SUB-07, §9.6

Tenant yang tidak membayar langganan tidak boleh mengubah data, tetapi datanya tetap bisa dilihat dan diekspor. Aturan ini berlaku di semua modul, termasuk proses terjadwal, sehingga tidak bisa diserahkan ke masing-masing layar.

## Keputusan

1. **Mode baca saja dijaga di `Action::transaction()`.** Sebelum membuka transaksi, Action memanggil `App\Support\Subscriptions\SubscriptionGate::ensureWritable()`. Bila status langganan tidak mengizinkan tulis (`read_only`, `frozen`), yang dilempar adalah `ReadOnlyMode`.
   - Kontraknya ada di `app/Support` supaya `Action` tidak bergantung pada modul. Implementasinya, `Subscription\Support\PlanGate`, didaftarkan oleh modul Subscription.
   - Action yang harus tetap jalan saat baca saja meng-implement penanda `AllowedWhenReadOnly`. Contohnya memilih paket, konfirmasi pembayaran, ekspor data, membuka identitas penghuni (hanya menulis log audit), dan operasi super admin terhadap tenant seperti impersonasi dan sinkronisasi peran.
   - `DomainActions` menampilkan `ReadOnlyMode` sebagai notifikasi. `IsolatedRuns` melewati tenant baca saja tanpa menghitungnya sebagai kegagalan, jadi tagihan penghuni tidak terbit otomatis (PRD §9.6).
2. **Batas paket dicek oleh Action yang menambah sumber daya.** `CreateProperty`, `CreateRoom`, `InviteStaff`, dan `ResendStaffInvitation` (untuk undangan kedaluwarsa) memanggil `SubscriptionGate::ensureCanAdd()`. Undangan yang masih berlaku dihitung sebagai pengguna. Pindah ke paket yang batasnya sudah terlampaui ditolak (FR-SUB-05).
3. **Selama trial tanpa paket, tidak ada batas dan semua fitur aktif.**
4. **Pembayaran langganan dikonfirmasi manual oleh super admin** sampai penyedia payment gateway diputuskan (PRD §18 no. 4). Kolom `gateway_reference` sudah disiapkan.
5. **Nilai bawaan yang belum diputuskan produk:**
   - masa tenggang 7 hari;
   - masa baca saja 30 hari sebelum dibekukan;
   - tagihan perpanjangan terbit 7 hari sebelum periode berakhir;
   - ganti paket saat periode berjalan berlaku langsung tanpa prorata.

   Masa tenggang dan masa baca saja bisa diubah super admin di Pengaturan langganan.

## Konsekuensi

- Action baru otomatis tunduk pada mode baca saja. Action yang menulis di luar `transaction()` tidak terjaga, jadi semua tulis harus lewat `transaction()`.
- Operasi platform yang berjalan di dalam konteks tenant, seperti membekukan tenant, harus memakai `AllowedWhenReadOnly`.
- Trial yang berakhir tanpa pembayaran masuk masa tenggang dulu (`trial → grace`). Transisi ini tidak ada di diagram PRD §9.6, tetapi mengikuti maksudnya: tenant tidak langsung terkunci.
