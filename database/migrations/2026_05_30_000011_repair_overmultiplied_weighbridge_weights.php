<?php

declare(strict_types=1);

return [
    'up' => [
        'UPDATE weighbridge_records
         SET first_weight_kg = CASE WHEN first_weight_kg > 100000 THEN first_weight_kg / 1000 ELSE first_weight_kg END,
             second_weight_kg = CASE WHEN second_weight_kg > 100000 THEN second_weight_kg / 1000 ELSE second_weight_kg END,
             net_weight_kg = CASE WHEN net_weight_kg > 100000 THEN net_weight_kg / 1000 ELSE net_weight_kg END
         WHERE COALESCE(first_weight_kg, 0) > 100000
            OR COALESCE(second_weight_kg, 0) > 100000
            OR COALESCE(net_weight_kg, 0) > 100000',
    ],
    'down' => [
        'SELECT 1',
    ],
];

