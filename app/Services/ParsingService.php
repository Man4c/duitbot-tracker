<?php

namespace App\Services;

use App\Enums\TransactionCategory;
use InvalidArgumentException;

class ParsingService
{
    /**
     * Pecah satu pesan menjadi beberapa transaksi (dipisah baris baru, koma, atau titik koma).
     * Format Rupiah memakai titik sebagai pemisah ribuan, sehingga koma aman dijadikan pemisah.
     *
     * @return list<array{amount:int, description:string, category:TransactionCategory, ambiguous:bool}>
     */
    public function parseMany(string $text): array
    {
        $segments = preg_split('/[\r\n,;]+/u', $text) ?: [];
        $parsed = [];
        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            try {
                $parsed[] = $this->parse($segment);
            } catch (InvalidArgumentException) {
                // Lewati segmen tanpa nominal; segmen valid lain tetap dicatat.
            }
        }
        if ($parsed === []) {
            throw new InvalidArgumentException('Nominal tidak ditemukan. Contoh: Makan siang 25rb');
        }

        return $parsed;
    }

    /** @return array{amount:int, description:string, category:TransactionCategory, ambiguous:bool} */
    public function parse(string $text): array
    {
        $text = trim($text);
        if (! preg_match('/(?<!\w)(\d[\d.]*)\s*(rb|ribu|k)?\b/iu', $text, $match, PREG_OFFSET_CAPTURE)) {
            throw new InvalidArgumentException('Nominal tidak ditemukan. Contoh: Makan siang 25rb');
        }

        $raw = $match[1][0];
        $suffix = strtolower($match[2][0] ?? '');
        $digits = (int) str_replace('.', '', $raw);
        $amount = in_array($suffix, ['rb', 'ribu', 'k'], true) ? $digits * 1000 : $digits;
        if ($amount < 1) {
            throw new InvalidArgumentException('Nominal harus lebih dari nol.');
        }

        $fullMatch = $match[0][0];
        $offset = $match[0][1];
        $description = trim(substr_replace($text, '', $offset, strlen($fullMatch)), " \t\n\r\0\x0B-,:;");
        $description = $description !== '' ? $description : 'Pengeluaran';
        $category = $this->classify($description);

        return ['amount' => $amount, 'description' => mb_substr($description, 0, 255), 'category' => $category, 'ambiguous' => $category === TransactionCategory::Other];
    }

    public function classify(string $description): TransactionCategory
    {
        $text = mb_strtolower($description);
        $keywords = [
            TransactionCategory::Food->value => ['makan', 'minum', 'kopi', 'nasi', 'jajan', 'resto', 'warung', 'gofood', 'grabfood', 'bakso', 'sate', 'mie', 'ayam', 'gorengan', 'seblak', 'martabak', 'roti', 'teh', 'boba', 'cemilan', 'snack', 'sarapan', 'gado', 'soto', 'padang', 'geprek', 'dimsum', 'sushi', 'pizza', 'burger', 'kfc', 'mcd', 'susu'],
            TransactionCategory::Transport->value => ['bensin', 'parkir', 'ojek', 'grab', 'gocar', 'gojek', 'tol', 'taksi', 'bus', 'kereta', 'mrt', 'lrt', 'transjakarta', 'angkot', 'pertalite', 'pertamax', 'solar', 'damri', 'spbu', 'etoll', 'busway'],
            TransactionCategory::Shopping->value => ['belanja', 'baju', 'sepatu', 'marketplace', 'shopee', 'tokopedia', 'supermarket', 'indomaret', 'alfamart', 'alfamidi', 'minimarket', 'mall', 'lazada', 'blibli', 'bukalapak', 'tas', 'celana', 'kaos'],
            TransactionCategory::Bills->value => ['tagihan', 'listrik', 'air', 'internet', 'wifi', 'pulsa', 'pdam', 'indihome', 'kuota', 'iuran', 'telkom'],
            TransactionCategory::Entertainment->value => ['bioskop', 'game', 'netflix', 'spotify', 'konser', 'hiburan', 'nonton', 'steam', 'youtube', 'disney', 'vidio', 'karaoke'],
            TransactionCategory::Health->value => ['obat', 'dokter', 'klinik', 'rumah sakit', 'vitamin', 'apotek', 'kesehatan', 'puskesmas', 'bidan', 'masker', 'suplemen'],
            TransactionCategory::Education->value => ['sekolah', 'kuliah', 'kursus', 'les', 'buku pelajaran', 'uang semester', 'spp', 'seminar', 'pelatihan', 'ujian'],
            TransactionCategory::Housing->value => ['kos', 'kontrakan', 'apartemen', 'sewa rumah', 'renovasi', 'perbaikan rumah'],
            TransactionCategory::Household->value => ['sabun', 'deterjen', 'perabot', 'galon', 'kebutuhan rumah', 'alat rumah', 'tisu', 'elpiji', 'gas', 'kompor', 'piring'],
            TransactionCategory::PersonalCare->value => ['salon', 'barbershop', 'skincare', 'kosmetik', 'potong rambut', 'perawatan diri', 'parfum', 'sampo', 'shampoo', 'cukur', 'spa'],
            TransactionCategory::Family->value => ['anak', 'bayi', 'popok', 'daycare', 'pengasuh', 'keluarga', 'mainan'],
            TransactionCategory::Pets->value => ['kucing', 'anjing', 'pakan hewan', 'dokter hewan', 'grooming hewan'],
            TransactionCategory::Insurance->value => ['asuransi', 'bpjs', 'premi'],
            TransactionCategory::Debt->value => ['cicilan', 'utang', 'kredit', 'pinjaman', 'paylater', 'kartu kredit'],
            TransactionCategory::Taxes->value => ['pajak', 'stnk', 'paspor', 'administrasi'],
            TransactionCategory::Savings->value => ['tabungan', 'menabung', 'dana darurat'],
            TransactionCategory::Investment->value => ['investasi', 'saham', 'reksadana', 'obligasi', 'kripto', 'emas', 'bitcoin'],
            TransactionCategory::Donation->value => ['donasi', 'sedekah', 'zakat', 'sumbangan', 'infaq'],
            TransactionCategory::Gifts->value => ['hadiah', 'kado'],
            TransactionCategory::Travel->value => ['liburan', 'hotel', 'penginapan', 'pesawat', 'visa', 'travel', 'wisata', 'tiket masuk', 'villa'],
            TransactionCategory::Work->value => ['alat kerja', 'modal usaha', 'iklan usaha', 'bisnis'],
            TransactionCategory::Sports->value => ['olahraga', 'gym', 'fitness', 'futsal', 'renang', 'badminton', 'sepeda', 'jersey', 'lari'],
            TransactionCategory::Technology->value => ['laptop', 'komputer', 'gadget', 'software', 'hosting', 'domain', 'charger', 'mouse', 'keyboard', 'headset', 'smartphone', 'powerbank'],
        ];
        foreach ($keywords as $category => $words) {
            foreach ($words as $word) {
                if (preg_match('/(?<![\pL\pN])'.preg_quote($word, '/').'(?![\pL\pN])/u', $text)) {
                    return TransactionCategory::from($category);
                }
            }
        }

        return TransactionCategory::Other;
    }
}
