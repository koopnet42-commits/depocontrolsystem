<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class SiloRoutingService
{
    public function findTargetSilo(int $analysisId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT
                sr.*,
                s.code AS silo_code,
                s.name AS silo_name
             FROM sample_analysis sa
             INNER JOIN silo_rules sr ON sr.product_id = sa.product_id
             INNER JOIN silos s ON s.id = sr.silo_id
             WHERE sa.id = :analysis_id
                AND sr.is_active = 1
                AND s.is_active = 1
                AND s.product_id = sa.product_id
                AND (sr.min_moisture IS NULL OR (sa.moisture IS NOT NULL AND sa.moisture >= sr.min_moisture))
                AND (sr.max_moisture IS NULL OR (sa.moisture IS NOT NULL AND sa.moisture <= sr.max_moisture))
                AND (sr.min_protein IS NULL OR (sa.protein IS NOT NULL AND sa.protein >= sr.min_protein))
                AND (sr.min_hectoliter IS NULL OR (sa.hectoliter IS NOT NULL AND sa.hectoliter >= sr.min_hectoliter))
                AND (sr.max_foreign_material IS NULL OR (sa.foreign_material IS NOT NULL AND sa.foreign_material <= sr.max_foreign_material))
                AND (sr.max_sunn_pest_rate IS NULL OR (sa.sunn_pest_rate IS NOT NULL AND sa.sunn_pest_rate <= sr.max_sunn_pest_rate))
             ORDER BY sr.priority ASC, sr.id ASC
             LIMIT 1'
        );
        $statement->execute(['analysis_id' => $analysisId]);
        $rule = $statement->fetch(PDO::FETCH_ASSOC);

        return $rule === false ? null : $rule;
    }
}
