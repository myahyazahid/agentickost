<?php

namespace App\Modules\Onboarding\Import;

use Filament\Support\Contracts\HasLabel;

/**
 * The sheets of the import template (FR-ONB-02), in the order they are
 * imported: contracts refer to rooms and residents from the same file.
 */
enum ImportSheet: string implements HasLabel
{
    case Rooms = 'kamar';
    case Residents = 'penghuni';
    case Contracts = 'kontrak';

    public function getLabel(): string
    {
        return match ($this) {
            self::Rooms => 'Kamar',
            self::Residents => 'Penghuni',
            self::Contracts => 'Kontrak',
        };
    }

    public static function fromSheetName(string $name): ?self
    {
        return self::tryFrom(ImportColumn::normalize($name));
    }

    /**
     * @return list<ImportColumn>
     */
    public function columns(): array
    {
        return match ($this) {
            self::Rooms => [
                new ImportColumn('nomor_kamar', 'Nomor kamar', true, 'Unik di properti ini.', '101'),
                new ImportColumn('tipe_kamar', 'Tipe kamar', true, 'Nama tipe kamar yang sudah dibuat di properti ini.', 'Standar'),
                new ImportColumn('lantai', 'Lantai', false, 'Boleh kosong.', '1'),
                new ImportColumn('kapasitas', 'Kapasitas', false, 'Jumlah orang. Kosongkan untuk memakai kapasitas tipe kamar.', '1'),
                new ImportColumn('catatan', 'Catatan', false, 'Boleh kosong.', 'Dekat tangga'),
            ],
            self::Residents => [
                new ImportColumn('nama_lengkap', 'Nama lengkap', true, 'Sesuai identitas.', 'Rina Wulandari'),
                new ImportColumn('no_hp', 'No. HP', true, 'Dipakai sheet Kontrak untuk mengenali penghuni, jadi harus beda untuk setiap penghuni.', '0812 3456 7890'),
                new ImportColumn('email', 'Email', false, 'Boleh kosong.', 'rina@example.com'),
                new ImportColumn('jenis_kelamin', 'Jenis kelamin', false, 'L atau P.', 'P'),
                new ImportColumn('tanggal_lahir', 'Tanggal lahir', false, 'Format 31/12/2001.', '14/02/2003'),
                new ImportColumn('jenis_identitas', 'Jenis identitas', false, 'KTP, SIM, Paspor, Kartu pelajar, atau Lainnya. Wajib bila nomor identitas diisi.', 'KTP'),
                new ImportColumn('no_identitas', 'No. identitas', false, 'Disimpan terenkripsi.', '3374015402030001'),
                new ImportColumn('institusi', 'Kampus atau kantor', false, 'Boleh kosong.', 'Universitas Diponegoro'),
                new ImportColumn('kontak_darurat', 'Kontak darurat', false, 'Nama kontak darurat.', 'Sri Wahyuni'),
                new ImportColumn('hp_kontak_darurat', 'HP kontak darurat', false, 'Boleh kosong.', '0813 1111 2222'),
                new ImportColumn('hubungan_kontak_darurat', 'Hubungan kontak darurat', false, 'Misal ibu, kakak.', 'Ibu'),
                new ImportColumn('plat_kendaraan', 'Plat kendaraan', false, 'Boleh kosong.', 'H 1234 AB'),
            ],
            self::Contracts => [
                new ImportColumn('nomor_kamar', 'Nomor kamar', true, 'Kamar yang sudah ada atau yang ada di sheet Kamar.', '101'),
                new ImportColumn('no_hp_penghuni', 'No. HP penghuni', true, 'Pisahkan dengan koma bila sekamar lebih dari satu orang. Yang pertama menjadi penghuni utama.', '0812 3456 7890'),
                new ImportColumn('periode_sewa', 'Periode sewa', true, 'Harian, Mingguan, Bulanan, 3 bulanan, 6 bulanan, atau Tahunan.', 'Bulanan'),
                new ImportColumn('harga_sewa', 'Harga sewa', true, 'Rupiah per periode, tanpa titik juga boleh.', '1200000'),
                new ImportColumn('deposit', 'Deposit', false, 'Nilai deposit di kontrak. Deposit yang sedang dipegang dicatat di saldo awal.', '1200000'),
                new ImportColumn('tanggal_mulai', 'Tanggal mulai', true, 'Tanggal penghuni mulai menyewa.', '01/03/2026'),
                new ImportColumn('tanggal_selesai', 'Tanggal selesai', false, 'Kosongkan bila kontrak berjalan terus.', ''),
                new ImportColumn('tagihan_berikutnya', 'Tagihan berikutnya mulai', true, 'Awal periode pertama yang belum ditagih. KostPilot menagih mulai tanggal ini.', '01/10/2026'),
                new ImportColumn('nama_pembayar', 'Nama pembayar', false, 'Kosongkan bila penghuni membayar sendiri.', ''),
                new ImportColumn('hp_pembayar', 'HP pembayar', false, 'Wajib bila nama pembayar diisi.', ''),
                new ImportColumn('hubungan_pembayar', 'Hubungan pembayar', false, 'Orang tua, Wali, Perusahaan, atau Lainnya.', ''),
            ],
        };
    }
}
