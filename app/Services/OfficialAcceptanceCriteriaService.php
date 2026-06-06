<?php

declare(strict_types=1);

namespace App\Services;

final class OfficialAcceptanceCriteriaService
{
    public function preview(string $productName): array
    {
        $name = mb_strtolower($productName);
        $durumWheat = str_contains($name, 'makarn') || str_contains($name, 'kiziltan') || str_contains($name, 'kızıl');

        return [
            'min_protein' => $durumWheat ? 12.00 : 10.50,
            'max_moisture' => 13.50,
            'min_hectoliter' => $durumWheat ? 78.00 : 76.00,
            'max_sunn_pest_rate' => 2.00,
            'max_foreign_matter' => 1.00,
            'max_broken_grain' => 5.00,
            'min_gluten' => $durumWheat ? 24.00 : 22.00,
            'source_type' => 'official_source',
            'source_name' => 'Simülasyon Resmi Kaynak Servisi',
            'source_url' => 'https://example.gov.tr/simulated-product-acceptance',
            'source_date' => date('Y-m-d'),
            'notes' => 'Simülasyon verisidir. Admin onayı ile aktif kabul değeri olarak kaydedilir.',
        ];
    }
}
