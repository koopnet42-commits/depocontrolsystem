<?php

declare(strict_types=1);

return [
    'up' => [
        'ALTER TABLE users MODIFY role ENUM("admin", "operator", "lab", "reporter", "weighbridge", "silo", "manager") NOT NULL DEFAULT "operator"',
        'UPDATE users SET role = "weighbridge" WHERE role = "operator"',
        'UPDATE users SET role = "manager" WHERE role = "reporter"',
        'ALTER TABLE users MODIFY role ENUM("admin", "weighbridge", "lab", "silo", "manager") NOT NULL DEFAULT "weighbridge"',
    ],
    'down' => [
        'ALTER TABLE users MODIFY role ENUM("admin", "operator", "lab", "reporter", "weighbridge", "silo", "manager") NOT NULL DEFAULT "weighbridge"',
        'UPDATE users SET role = "operator" WHERE role IN ("weighbridge", "silo")',
        'UPDATE users SET role = "reporter" WHERE role = "manager"',
        'ALTER TABLE users MODIFY role ENUM("admin", "operator", "lab", "reporter") NOT NULL DEFAULT "operator"',
    ],
];
