# ADR 0006: State machine

- Status: diterima
- Tanggal: 26 September 2026
- Acuan: PRD §9

## Keputusan

- Memakai `spatie/laravel-model-states` (kandidat di PRD §12.3). Setiap status adalah kelas state; transisi yang sah didaftarkan di `config()` kelas state dasar, dan transisi dijalankan dengan `$model->status->transitionTo(...)`.
- Nilai yang disimpan di kolom `status` adalah `$name` kelas state (misal `available`), bukan nama kelas, sesuai schema §1.5.
- Paket itu hanya memvalidasi transisi lewat `transitionTo()`. Trait `App\Support\States\EnforcesStateTransitions` menambah pengecekan saat model disimpan, sehingga mengisi atribut status langsung ke state yang tidak sah juga ditolak ("transisi di luar yang tercantum ditolak", PRD §9).
- Efek samping transisi (jurnal, notifikasi) ditaruh di Action yang memanggil `transitionTo()`, bukan di kelas transisi, agar tetap melewati otorisasi dan transaksi Action.

## Contoh

Belum ada status domain di M0.3. Contoh yang berjalan ada di `tests/Fixtures` (draft → published → archived) dengan test di `tests/Feature/Support/StateMachineTest.php`. State machine domain pertama adalah status kamar di M1.1 (PRD §9.1).

## Konsekuensi

- Model dengan status memakai `HasStates` dan `EnforcesStateTransitions` bersama-sama.
- Status "telat" pada tagihan tetap dihitung, bukan disimpan (PRD §9.4).
