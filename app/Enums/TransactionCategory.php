<?php

namespace App\Enums;

enum TransactionCategory: string
{
    case Food = 'Makanan';
    case Transport = 'Transport';
    case Shopping = 'Belanja';
    case Bills = 'Tagihan';
    case Entertainment = 'Hiburan';
    case Health = 'Kesehatan';
    case Education = 'Pendidikan';
    case Housing = 'Tempat Tinggal';
    case Household = 'Rumah Tangga';
    case PersonalCare = 'Perawatan Pribadi';
    case Family = 'Keluarga & Anak';
    case Pets = 'Hewan Peliharaan';
    case Insurance = 'Asuransi';
    case Debt = 'Cicilan & Utang';
    case Taxes = 'Pajak & Administrasi';
    case Savings = 'Tabungan';
    case Investment = 'Investasi';
    case Donation = 'Donasi & Sosial';
    case Gifts = 'Hadiah';
    case Travel = 'Perjalanan';
    case Work = 'Pekerjaan & Bisnis';
    case Sports = 'Olahraga';
    case Technology = 'Teknologi';
    case Other = 'Lainnya';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
