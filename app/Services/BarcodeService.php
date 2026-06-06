<?php

declare(strict_types=1);

namespace App\Services;

final class BarcodeService
{
    private const CODE39 = [
        '0' => 'nnnwwnwnn',
        '1' => 'wnnwnnnnw',
        '2' => 'nnwwnnnnw',
        '3' => 'wnwwnnnnn',
        '4' => 'nnnwwnnnw',
        '5' => 'wnnwwnnnn',
        '6' => 'nnwwwnnnn',
        '7' => 'nnnwnnwnw',
        '8' => 'wnnwnnwnn',
        '9' => 'nnwwnnwnn',
        'A' => 'wnnnnwnnw',
        'B' => 'nnwnnwnnw',
        'C' => 'wnwnnwnnn',
        'D' => 'nnnnwwnnw',
        'E' => 'wnnnwwnnn',
        'F' => 'nnwnwwnnn',
        'G' => 'nnnnnwwnw',
        'H' => 'wnnnnwwnn',
        'I' => 'nnwnnwwnn',
        'J' => 'nnnnwwwnn',
        'K' => 'wnnnnnnww',
        'L' => 'nnwnnnnww',
        'M' => 'wnwnnnnwn',
        'N' => 'nnnnwnnww',
        'O' => 'wnnnwnnwn',
        'P' => 'nnwnwnnwn',
        'Q' => 'nnnnnnwww',
        'R' => 'wnnnnnwwn',
        'S' => 'nnwnnnwwn',
        'T' => 'nnnnwnwwn',
        'U' => 'wwnnnnnnw',
        'V' => 'nwwnnnnnw',
        'W' => 'wwwnnnnnn',
        'X' => 'nwnnwnnnw',
        'Y' => 'wwnnwnnnn',
        'Z' => 'nwwnwnnnn',
        '-' => 'nwnnnnwnw',
        '.' => 'wwnnnnwnn',
        ' ' => 'nwwnnnwnn',
        '$' => 'nwnwnwnnn',
        '/' => 'nwnwnnnwn',
        '+' => 'nwnnnwnwn',
        '%' => 'nnnwnwnwn',
        '*' => 'nwnnwnwnn',
    ];

    public function generateTicketCode(array $record = [], int $sequence = 1): string
    {
        $company = $this->segment((string) ($record['company_name'] ?? 'LIDAS'), 3);
        $silo = $this->segment((string) ($record['silo_code'] ?? $record['final_silo_code'] ?? 'SILO'), 6);
        $date = date('Ymd');
        $sequenceText = str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

        return $company . '-' . $date . '-' . $silo . '-' . $sequenceText;
    }

    public function svg(string $code): string
    {
        $code = '*' . strtoupper($code) . '*';
        $narrow = 2;
        $wide = 5;
        $height = 82;
        $gap = 2;
        $x = 10;
        $bars = '';

        foreach (str_split($code) as $char) {
            $pattern = self::CODE39[$char] ?? self::CODE39['-'];

            foreach (str_split($pattern) as $index => $widthKey) {
                $width = $widthKey === 'w' ? $wide : $narrow;

                if ($index % 2 === 0) {
                    $bars .= '<rect x="' . $x . '" y="0" width="' . $width . '" height="' . $height . '" fill="#111827" />';
                }

                $x += $width;
            }

            $x += $gap;
        }

        $width = $x + 10;

        return '<svg class="barcode-svg" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="' . htmlspecialchars($code) . '" xmlns="http://www.w3.org/2000/svg">' . $bars . '</svg>';
    }

    private function segment(string $value, int $length): string
    {
        $value = strtr($value, [
            'ç' => 'c', 'Ç' => 'C',
            'ğ' => 'g', 'Ğ' => 'G',
            'ı' => 'i', 'İ' => 'I',
            'ö' => 'o', 'Ö' => 'O',
            'ş' => 's', 'Ş' => 'S',
            'ü' => 'u', 'Ü' => 'U',
        ]);
        $value = strtoupper($value);
        $value = preg_replace('/[^A-Z0-9]+/', '', $value) ?: '';

        return substr($value, 0, $length) ?: 'X';
    }
}
