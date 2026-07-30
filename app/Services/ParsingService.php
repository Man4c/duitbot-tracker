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
     * @return list<array{amount:int, quantity:int, description:string, category:TransactionCategory, ambiguous:bool}>
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

    /** @return array{amount:int, quantity:int, description:string, category:TransactionCategory, ambiguous:bool} */
    public function parse(string $text): array
    {
        $text = trim($text);

        // Kumpulkan semua kandidat nominal beserta posisinya agar bisa memilih yang paling tepat.
        if (! preg_match_all('/(?<!\w)(\d[\d.]*)\s*(juta|jt|rb|ribu|k)?\b/iu', $text, $matches, PREG_OFFSET_CAPTURE)) {
            throw new InvalidArgumentException('Nominal tidak ditemukan. Contoh: Makan siang 25rb');
        }

        $quantity = $this->detectQuantity($text);

        $candidates = [];
        foreach ($matches[0] as $i => $full) {
            $digits = (int) str_replace('.', '', $matches[1][$i][0]);
            $multiplier = $this->multiplierFor(strtolower($matches[2][$i][0] ?? ''));
            $candidates[] = [
                'amount' => $digits * $multiplier,
                'hasSuffix' => $multiplier > 1,
                'full' => $full[0],
                'offset' => $full[1],
            ];
        }

        // Pilih nominal: utamakan angka bersuffix mata uang, jika seri/kosong ambil yang terbesar.
        usort($candidates, function ($a, $b) {
            return [$b['hasSuffix'], $b['amount']] <=> [$a['hasSuffix'], $a['amount']];
        });
        $chosen = $candidates[0];
        $amount = $chosen['amount'];
        if ($amount < 1) {
            throw new InvalidArgumentException('Nominal harus lebih dari nol.');
        }

        $description = trim(substr_replace($text, '', $chosen['offset'], strlen($chosen['full'])), " \t\n\r\0\x0B-,:;");
        $description = $description !== '' ? $description : 'Pengeluaran';
        $category = $this->classify($description);

        return ['amount' => $amount, 'quantity' => $quantity, 'description' => mb_substr($description, 0, 255), 'category' => $category, 'ambiguous' => $category === TransactionCategory::Other];
    }

    /** Kalikan sesuai satuan nominal: ribu (rb/ribu/k) → 1.000, juta (jt/juta) → 1.000.000. */
    private function multiplierFor(string $suffix): int
    {
        return match ($suffix) {
            'juta', 'jt' => 1_000_000,
            'rb', 'ribu', 'k' => 1_000,
            default => 1,
        };
    }

    /**
     * Deteksi jumlah/porsi hanya dari penanda eksplisit agar tidak salah menebak
     * angka yang sebenarnya bagian dari nama barang (mis. "iphone 15").
     * Dikenali: "2x", "x2", atau "2 <satuan>" (porsi/pcs/bungkus/gelas/cup/buah/biji/piring/porsi).
     */
    private function detectQuantity(string $text): int
    {
        if (preg_match('/(?<!\w)(\d+)\s*x(?!\w)/iu', $text, $m) || preg_match('/(?<!\w)x\s*(\d+)(?!\w)/iu', $text, $m)) {
            return max(1, (int) $m[1]);
        }
        if (preg_match('/(?<!\w)(\d+)\s*(porsi|pcs|pc|bungkus|gelas|cup|buah|biji|piring|butir|lembar|pack)(?!\w)/iu', $text, $m)) {
            return max(1, (int) $m[1]);
        }

        return 1;
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
