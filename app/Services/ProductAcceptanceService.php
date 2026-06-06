<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class ProductAcceptanceService
{
    public function activeCriteria(int $productId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM product_acceptance_criteria
             WHERE product_id = :product_id AND is_active = 1 AND approved_at IS NOT NULL
             ORDER BY approved_at DESC, id DESC
             LIMIT 1'
        );
        $statement->execute(['product_id' => $productId]);
        $criteria = $statement->fetch(PDO::FETCH_ASSOC);

        return $criteria === false ? null : $criteria;
    }

    public function evaluate(int $productId, array $analysis): array
    {
        $criteria = $this->activeCriteria($productId);

        if ($criteria === null) {
            return ['status' => 'requires_approval', 'criteria_id' => null, 'message' => 'Aktif ürün kabul değeri bulunamadı.'];
        }

        $checks = [
            ['key' => 'protein', 'limit' => 'min_protein', 'mode' => 'min'],
            ['key' => 'moisture', 'limit' => 'max_moisture', 'mode' => 'max'],
            ['key' => 'hectoliter', 'limit' => 'min_hectoliter', 'mode' => 'min'],
            ['key' => 'sunn_pest_rate', 'limit' => 'max_sunn_pest_rate', 'mode' => 'max'],
            ['key' => 'foreign_material', 'limit' => 'max_foreign_matter', 'mode' => 'max'],
            ['key' => 'broken_grain', 'limit' => 'max_broken_grain', 'mode' => 'max'],
            ['key' => 'gluten', 'limit' => 'min_gluten', 'mode' => 'min'],
        ];

        $borderline = false;
        foreach ($checks as $check) {
            if ($analysis[$check['key']] === null || $criteria[$check['limit']] === null) {
                continue;
            }

            $value = (float) $analysis[$check['key']];
            $limit = (float) $criteria[$check['limit']];

            if ($check['mode'] === 'min' && $value < $limit) {
                return ['status' => 'rejected', 'criteria_id' => (int) $criteria['id'], 'message' => 'Alıma uygun değil.'];
            }

            if ($check['mode'] === 'max' && $value > $limit) {
                return ['status' => 'rejected', 'criteria_id' => (int) $criteria['id'], 'message' => 'Alıma uygun değil.'];
            }

            if (abs($value - $limit) < 0.00001) {
                $borderline = true;
            }
        }

        return [
            'status' => $borderline ? 'requires_approval' : 'accepted',
            'criteria_id' => (int) $criteria['id'],
            'message' => $borderline ? 'Yetkili onayı gerekli.' : 'Alıma uygun.',
        ];
    }
}
