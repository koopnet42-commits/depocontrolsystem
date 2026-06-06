<?php

declare(strict_types=1);

return [
    'up' => [
        'ALTER TABLE delivery_notifications ADD COLUMN operation_status VARCHAR(40) NOT NULL DEFAULT "open" AFTER status',
        'ALTER TABLE delivery_notifications ADD COLUMN operation_closed_at TIMESTAMP NULL AFTER operation_status',
    ],
    'down' => [
        'ALTER TABLE delivery_notifications DROP COLUMN operation_closed_at',
        'ALTER TABLE delivery_notifications DROP COLUMN operation_status',
    ],
];
