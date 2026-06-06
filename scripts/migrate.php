<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\MigrationRunner;

require dirname(__DIR__) . '/bootstrap/app.php';

$runner = new MigrationRunner(Database::connection());
$path = BASE_PATH . '/database/migrations';

if (($argv[1] ?? '') === 'rollback') {
    $runner->rollbackLast($path);
    echo "Son migration geri alındı.\n";
    exit;
}

$runner->migrate($path);

echo "Migration işlemi tamamlandı.\n";
