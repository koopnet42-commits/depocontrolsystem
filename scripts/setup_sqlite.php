<?php

declare(strict_types=1);

use App\Core\Database;

require dirname(__DIR__) . '/bootstrap/app.php';

$database = Database::connection();
$database->exec('PRAGMA foreign_keys = ON');

$schema = [
    'CREATE TABLE IF NOT EXISTS companies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        tax_number TEXT NULL,
        tax_office TEXT NULL,
        phone TEXT NULL,
        contact_person TEXT NULL,
        email TEXT NULL,
        address TEXT NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        code TEXT NULL UNIQUE,
        unit TEXT NOT NULL DEFAULT "kg",
        description TEXT NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT "weighbridge",
        is_active INTEGER NOT NULL DEFAULT 1,
        is_locked INTEGER NOT NULL DEFAULT 0,
        must_change_password INTEGER NOT NULL DEFAULT 0,
        last_login_at TEXT NULL,
        failed_login_count INTEGER NOT NULL DEFAULT 0,
        locked_until TEXT NULL,
        two_factor_enabled INTEGER NOT NULL DEFAULT 0,
        two_factor_secret_encrypted TEXT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE IF NOT EXISTS vehicles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        plate_number TEXT NOT NULL UNIQUE,
        normalized_plate TEXT NULL UNIQUE,
        brand TEXT NULL,
        model TEXT NULL,
        driver_name TEXT NULL,
        driver_phone TEXT NULL,
        company_id INTEGER NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE IF NOT EXISTS drivers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        full_name TEXT NULL,
        phone TEXT NULL,
        identity_number TEXT NULL UNIQUE,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE IF NOT EXISTS driver_vehicle_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        driver_id INTEGER NOT NULL,
        vehicle_id INTEGER NOT NULL,
        entry_id INTEGER NULL,
        company_id INTEGER NULL,
        used_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE IF NOT EXISTS silos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        code TEXT NOT NULL UNIQUE,
        product_id INTEGER NULL,
        visual_type TEXT NOT NULL DEFAULT "vertical",
        capacity_kg REAL NOT NULL DEFAULT 0,
        current_stock_kg REAL NOT NULL DEFAULT 0,
        min_moisture REAL NULL,
        max_moisture REAL NULL,
        min_protein REAL NULL,
        max_protein REAL NULL,
        min_hectoliter REAL NULL,
        max_hectoliter REAL NULL,
        min_gluten REAL NULL,
        max_gluten REAL NULL,
        min_sunn_pest_rate REAL NULL,
        max_sunn_pest_rate REAL NULL,
        min_foreign_material REAL NULL,
        max_foreign_material REAL NULL,
        min_broken_grain REAL NULL,
        max_broken_grain REAL NULL,
        location TEXT NULL,
        description TEXT NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE IF NOT EXISTS product_acceptance_criteria (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        min_protein REAL NULL,
        max_moisture REAL NULL,
        min_hectoliter REAL NULL,
        max_sunn_pest_rate REAL NULL,
        max_foreign_matter REAL NULL,
        max_broken_grain REAL NULL,
        min_gluten REAL NULL,
        source_type TEXT NOT NULL DEFAULT "manual",
        source_name TEXT NULL,
        source_url TEXT NULL,
        source_date TEXT NULL,
        approved_by INTEGER NULL,
        approved_at TEXT NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        notes TEXT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE IF NOT EXISTS delivery_notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        notification_number TEXT NOT NULL UNIQUE,
        company_id INTEGER NOT NULL,
        product_id INTEGER NOT NULL,
        vehicle_id INTEGER NULL,
        expected_quantity_kg REAL NULL,
        loading_date TEXT NULL,
        expected_arrival_date TEXT NULL,
        entry_type TEXT NOT NULL DEFAULT "pre_notified",
        sender_type TEXT NOT NULL DEFAULT "company",
        dispatch_number TEXT NULL,
        identity_number TEXT NULL,
        sender_name TEXT NULL,
        sender_tax_number TEXT NULL,
        sender_phone TEXT NULL,
        sender_address TEXT NULL,
        status TEXT NOT NULL DEFAULT "pending",
        notes TEXT NULL,
        created_by INTEGER NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE IF NOT EXISTS weighbridge_records (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ticket_number TEXT NOT NULL UNIQUE,
        delivery_notification_id INTEGER NULL,
        company_id INTEGER NOT NULL,
        product_id INTEGER NOT NULL,
        vehicle_id INTEGER NOT NULL,
        assigned_silo_id INTEGER NULL,
        first_weight_kg REAL NULL,
        first_weighed_at TEXT NULL,
        second_weight_kg REAL NULL,
        second_weighed_at TEXT NULL,
        net_weight_kg REAL NULL,
        status TEXT NOT NULL DEFAULT "entry",
        first_weighed_by INTEGER NULL,
        second_weighed_by INTEGER NULL,
        notes TEXT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE IF NOT EXISTS sample_analysis (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        analysis_number TEXT NOT NULL UNIQUE,
        weighbridge_record_id INTEGER NOT NULL UNIQUE,
        product_id INTEGER NOT NULL,
        moisture REAL NULL,
        protein REAL NULL,
        hectoliter REAL NULL,
        gluten REAL NULL,
        sunn_pest_rate REAL NULL,
        foreign_material REAL NULL,
        broken_grain REAL NULL,
        result TEXT NOT NULL DEFAULT "accepted",
        acceptance_status TEXT NOT NULL DEFAULT "requires_approval",
        acceptance_criteria_id INTEGER NULL,
        status TEXT NOT NULL DEFAULT "completed",
        analyzed_by INTEGER NULL,
        analyzed_at TEXT NULL,
        notes TEXT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE IF NOT EXISTS silo_rules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        product_id INTEGER NOT NULL,
        silo_id INTEGER NOT NULL,
        min_moisture REAL NULL,
        max_moisture REAL NULL,
        min_protein REAL NULL,
        max_protein REAL NULL,
        min_hectoliter REAL NULL,
        max_hectoliter REAL NULL,
        max_foreign_material REAL NULL,
        max_sunn_pest_rate REAL NULL,
        priority INTEGER NOT NULL DEFAULT 100,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE IF NOT EXISTS barcode_tickets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        barcode TEXT NOT NULL UNIQUE,
        entry_id INTEGER NULL,
        weighbridge_record_id INTEGER NOT NULL UNIQUE,
        sample_analysis_id INTEGER NULL,
        silo_id INTEGER NOT NULL,
        issued_by INTEGER NULL,
        issued_at TEXT NULL,
        status TEXT NOT NULL DEFAULT "active",
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE IF NOT EXISTS unloading_operations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        operation_number TEXT NOT NULL UNIQUE,
        barcode_ticket_id INTEGER NOT NULL UNIQUE,
        weighbridge_record_id INTEGER NOT NULL,
        silo_id INTEGER NOT NULL,
        started_at TEXT NULL,
        completed_at TEXT NULL,
        unloaded_weight_kg REAL NULL,
        status TEXT NOT NULL DEFAULT "waiting",
        operator_id INTEGER NULL,
        notes TEXT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE IF NOT EXISTS audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NULL,
        user_name TEXT NULL,
        user_role TEXT NULL,
        target_user_id INTEGER NULL,
        action TEXT NOT NULL,
        table_name TEXT NULL,
        record_id INTEGER NULL,
        ip_address TEXT NULL,
        user_agent TEXT NULL,
        description TEXT NULL,
        old_values TEXT NULL,
        new_values TEXT NULL,
        payload TEXT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE IF NOT EXISTS company_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        company_name TEXT NOT NULL,
        logo_path TEXT NULL,
        address TEXT NULL,
        tax_office TEXT NULL,
        tax_number TEXT NULL,
        phone TEXT NULL,
        email TEXT NULL,
        website TEXT NULL,
        contact_person TEXT NULL,
        license_number TEXT NULL,
        letterhead_text TEXT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE IF NOT EXISTS camera_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        usage_location TEXT NOT NULL,
        camera_type TEXT NOT NULL,
        connection_url TEXT NULL,
        port INTEGER NULL,
        username TEXT NULL,
        password_encrypted TEXT NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE IF NOT EXISTS scale_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        usage_location TEXT NOT NULL,
        communication_type TEXT NOT NULL,
        ip_address TEXT NULL,
        port INTEGER NULL,
        com_port TEXT NULL,
        baud_rate INTEGER NULL,
        data_bits INTEGER NULL,
        stop_bits TEXT NULL,
        parity TEXT NULL,
        read_format TEXT NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE IF NOT EXISTS barrier_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        usage_location TEXT NOT NULL,
        control_type TEXT NOT NULL,
        ip_address TEXT NULL,
        port INTEGER NULL,
        relay_number TEXT NULL,
        open_command TEXT NULL,
        close_command TEXT NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE IF NOT EXISTS system_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        operation_mode TEXT NOT NULL DEFAULT "simulation",
        default_printer TEXT NULL,
        barcode_type TEXT NOT NULL DEFAULT "QR",
        auto_plate_recognition INTEGER NOT NULL DEFAULT 0,
        manual_weight_allowed INTEGER NOT NULL DEFAULT 1,
        manual_weight_reason_required INTEGER NOT NULL DEFAULT 1,
        critical_confirmation_enabled INTEGER NOT NULL DEFAULT 1,
        auto_backup_enabled INTEGER NOT NULL DEFAULT 0,
        dashboard_silo_view TEXT NOT NULL DEFAULT "vertical",
        qr_content_fields TEXT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE IF NOT EXISTS vehicle_process_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        entry_id INTEGER NOT NULL,
        old_status TEXT NULL,
        new_status TEXT NOT NULL,
        action_name TEXT NOT NULL,
        description TEXT NULL,
        user_id INTEGER NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
];

foreach ($schema as $statement) {
    $database->exec($statement);
}

$userColumns = array_column($database->query('PRAGMA table_info(users)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
$addUserColumn = static function (string $name, string $definition) use ($database, &$userColumns): void {
    if (! in_array($name, $userColumns, true)) {
        $database->exec('ALTER TABLE users ADD COLUMN ' . $name . ' ' . $definition);
        $userColumns[] = $name;
    }
};
$addUserColumn('is_locked', 'INTEGER NOT NULL DEFAULT 0');
$addUserColumn('must_change_password', 'INTEGER NOT NULL DEFAULT 0');
$addUserColumn('failed_login_count', 'INTEGER NOT NULL DEFAULT 0');
$addUserColumn('locked_until', 'TEXT NULL');
$addUserColumn('two_factor_enabled', 'INTEGER NOT NULL DEFAULT 0');
$addUserColumn('two_factor_secret_encrypted', 'TEXT NULL');

$auditColumns = array_column($database->query('PRAGMA table_info(audit_logs)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
$addAuditColumn = static function (string $name, string $definition) use ($database, &$auditColumns): void {
    if (! in_array($name, $auditColumns, true)) {
        $database->exec('ALTER TABLE audit_logs ADD COLUMN ' . $name . ' ' . $definition);
        $auditColumns[] = $name;
    }
};
$addAuditColumn('target_user_id', 'INTEGER NULL');
$addAuditColumn('description', 'TEXT NULL');
$addAuditColumn('old_values', 'TEXT NULL');
$addAuditColumn('new_values', 'TEXT NULL');

$deliveryColumns = array_column($database->query('PRAGMA table_info(delivery_notifications)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
$addDeliveryColumn = static function (string $name, string $definition) use ($database, &$deliveryColumns): void {
    if (! in_array($name, $deliveryColumns, true)) {
        $database->exec('ALTER TABLE delivery_notifications ADD COLUMN ' . $name . ' ' . $definition);
        $deliveryColumns[] = $name;
    }
};
$addDeliveryColumn('entry_type', 'TEXT NOT NULL DEFAULT "pre_notified"');
$addDeliveryColumn('sender_type', 'TEXT NOT NULL DEFAULT "company"');
$addDeliveryColumn('dispatch_number', 'TEXT NULL');
$addDeliveryColumn('identity_number', 'TEXT NULL');
$addDeliveryColumn('sender_name', 'TEXT NULL');
$addDeliveryColumn('sender_tax_number', 'TEXT NULL');
$addDeliveryColumn('sender_phone', 'TEXT NULL');
$addDeliveryColumn('sender_address', 'TEXT NULL');

$siloColumns = array_column($database->query('PRAGMA table_info(silos)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
if (! in_array('visual_type', $siloColumns, true)) {
    $database->exec('ALTER TABLE silos ADD COLUMN visual_type TEXT NOT NULL DEFAULT "vertical"');
}

$analysisColumns = array_column($database->query('PRAGMA table_info(sample_analysis)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
if (! in_array('acceptance_status', $analysisColumns, true)) {
    $database->exec('ALTER TABLE sample_analysis ADD COLUMN acceptance_status TEXT NOT NULL DEFAULT "requires_approval"');
}
if (! in_array('acceptance_criteria_id', $analysisColumns, true)) {
    $database->exec('ALTER TABLE sample_analysis ADD COLUMN acceptance_criteria_id INTEGER NULL');
}

$systemColumns = array_column($database->query('PRAGMA table_info(system_settings)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
if (! in_array('dashboard_silo_view', $systemColumns, true)) {
    $database->exec('ALTER TABLE system_settings ADD COLUMN dashboard_silo_view TEXT NOT NULL DEFAULT "vertical"');
}
if (! in_array('qr_content_fields', $systemColumns, true)) {
    $database->exec('ALTER TABLE system_settings ADD COLUMN qr_content_fields TEXT NULL');
}

$barcodeColumns = array_column($database->query('PRAGMA table_info(barcode_tickets)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
if (! in_array('entry_id', $barcodeColumns, true)) {
    $database->exec('ALTER TABLE barcode_tickets ADD COLUMN entry_id INTEGER NULL');
}

$adminExists = (int) $database
    ->query('SELECT COUNT(*) FROM users WHERE email = "admin@depo.local"')
    ->fetchColumn();

if ($adminExists === 0) {
    $statement = $database->prepare(
        'INSERT INTO users (name, email, password, role, is_active)
         VALUES (:name, :email, :password, "admin", 1)'
    );
    $statement->execute([
        'name' => 'Sistem Admin',
        'email' => 'admin@depo.local',
        'password' => password_hash('admin123', PASSWORD_DEFAULT),
    ]);
}

$masterEmail = (string) App\Core\Config::env('MASTER_EMAIL', 'master@depo.local');
$masterPassword = (string) App\Core\Config::env('MASTER_PASSWORD', 'master123!');
$masterName = (string) App\Core\Config::env('MASTER_NAME', 'Üretici Master');
$statement = $database->prepare('SELECT COUNT(*) FROM users WHERE role = "master" OR email = :email');
$statement->execute(['email' => $masterEmail]);

if ((int) $statement->fetchColumn() === 0) {
    $statement = $database->prepare(
        'INSERT INTO users
            (name, email, password, role, is_active, is_locked, must_change_password, failed_login_count)
         VALUES
            (:name, :email, :password, "master", 1, 0, 1, 0)'
    );
    $statement->execute([
        'name' => $masterName,
        'email' => $masterEmail,
        'password' => password_hash($masterPassword, PASSWORD_DEFAULT),
    ]);
}

echo "SQLite veritabanı hazırlandı.\n";
