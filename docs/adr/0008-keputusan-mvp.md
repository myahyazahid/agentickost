# ADR 0008: Keputusan produk untuk MVP

- Status: diterima
- Tanggal: 26 September 2026
- Diputuskan oleh: pemilik produk, saat memulai Fase 1
- Acuan: PRD §18 no. 6, schema §16 no. 1 dan 4

Wawancara M0.1 belum berjalan, jadi beberapa pertanyaan terbuka diputuskan lebih dulu agar mesin billing bisa dibangun. Keputusan ini ditinjau ulang setelah wawancara dan pilot.

## Keputusan

1. **Semua periode sewa didukung sejak MVP** (PRD §18 no. 6): harian, mingguan, bulanan, 3 bulanan, 6 bulanan, dan tahunan, termasuk kontrak harian dan mingguan dengan tagihan berulang.
   - Mode tanggal tetap hanya berlaku untuk periode bulanan ke atas. Kontrak harian dan mingguan selalu memakai mode anniversary.
   - Tagihan terbit paling cepat `invoice_lead_days` sebelum jatuh tempo, tetapi tidak lebih awal dari panjang periodenya dikurangi satu hari. Kontrak harian menerbitkan tagihan pada hari jatuh temponya.
2. **Satu tagihan per kontrak** (schema §16 no. 4). Kamar berpenghuni lebih dari satu tetap mendapat satu tagihan ke satu pembayar. Pembagian per orang (`share_type`, `share_amount`) disimpan sebagai informasi saja. Kolom `contracts.split_billing` dan `invoices.resident_id` tidak dibuat.
3. **Deposit ditagih di tagihan pertama dan dialokasikan paling awal** (schema §16 no. 1). Urutan alokasi default menjadi: deposit, sewa, utilitas, layanan tambahan, biaya lain, denda.

## Konsekuensi

- Mesin billing harus menguji siklus harian dan mingguan selain bulanan ke atas.
- Bila split billing dibutuhkan kelak, perlu migration untuk kolom tagihan per penghuni dan perubahan pada alokasi, deposit, dan denda.
