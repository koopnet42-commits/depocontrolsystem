<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AuditLogger;
use App\Services\ProductAcceptanceService;
use PDO;

final class SiloController extends Controller
{
    public function __construct(private readonly ProductAcceptanceService $acceptanceService = new ProductAcceptanceService())
    {
    }

    public function index(): void
    {
        $this->view('silos/index', [
            'title' => 'Silo Tanımları',
            'silos' => $this->silos(),
            'message' => (string) $this->input('message', ''),
        ]);
    }

    public function create(): void
    {
        $validation = $this->consumeValidation();
        $silo = $validation['old'] === [] ? $this->emptySilo() : array_merge($this->emptySilo(), $validation['old']);

        $this->view('silos/form', [
            'title' => 'Silo Ekle',
            'silo' => $silo,
            'products' => $this->products(),
            'criteriaByProduct' => $this->criteriaByProduct(),
            'action' => '/silos/store',
            'message' => (string) $this->input('message', ''),
            'validation' => $validation,
        ]);
    }

    public function store(): void
    {
        $payload = $this->payload();

        if ($payload['code'] === '' && $payload['product_id'] !== null) {
            $payload['code'] = $this->nextSiloCode((int) $payload['product_id']);
        }

        $errors = $this->validationErrors($payload);

        if ($errors !== []) {
            $this->redirectWithValidation('/silos/create?message=invalid', $errors, $_POST + $payload);
        }

        if (! $this->productHasCode((int) $payload['product_id'])) {
            $this->redirectWithValidation('/silos/create?message=missing_product_code', ['product_id' => 'Bu ürün için ürün kodu tanımlanmamış.'], $_POST + $payload);
        }

        if ($this->siloCodeExists($payload['code'])) {
            $this->redirectWithValidation('/silos/create?message=duplicate_code', ['code' => 'Bu silo kodu zaten kullanılıyor.'], $_POST + $payload);
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO silos
                (code, name, product_id, capacity_kg, current_stock_kg,
                 visual_type,
                 min_moisture, max_moisture, min_protein, max_protein,
                 min_hectoliter, max_hectoliter, min_gluten, max_gluten,
                 min_sunn_pest_rate, max_sunn_pest_rate,
                 min_foreign_material, max_foreign_material,
                 min_broken_grain, max_broken_grain,
                 description, is_active)
             VALUES
                (:code, :name, :product_id, :capacity_kg, :current_stock_kg,
                 :visual_type,
                 :min_moisture, :max_moisture, :min_protein, :max_protein,
                 :min_hectoliter, :max_hectoliter, :min_gluten, :max_gluten,
                 :min_sunn_pest_rate, :max_sunn_pest_rate,
                 :min_foreign_material, :max_foreign_material,
                 :min_broken_grain, :max_broken_grain,
                 :description, :is_active)'
        );
        $statement->execute($payload);
        AuditLogger::log('silo.created', 'silos', (int) Database::connection()->lastInsertId(), $payload);

        $this->redirect('/silos');
    }

    public function edit(): void
    {
        $silo = $this->findSilo((int) $this->input('id'));

        if ($silo === null) {
            http_response_code(404);
            echo 'Silo bulunamadı.';
            return;
        }

        $validation = $this->consumeValidation();
        if ($validation['old'] !== []) {
            $silo = array_merge($silo, $validation['old']);
        }

        $this->view('silos/form', [
            'title' => 'Silo Düzenle',
            'silo' => $silo,
            'products' => $this->products(),
            'criteriaByProduct' => $this->criteriaByProduct(),
            'action' => '/silos/update',
            'message' => (string) $this->input('message', ''),
            'validation' => $validation,
        ]);
    }

    public function update(): void
    {
        $id = (int) $this->input('id');

        $payload = $this->payload();

        if ($payload['code'] === '' && $payload['product_id'] !== null) {
            $payload['code'] = $this->nextSiloCode((int) $payload['product_id'], $id);
        }

        $errors = $this->validationErrors($payload);

        if ($errors !== []) {
            $this->redirectWithValidation('/silos/edit?id=' . $id . '&message=invalid', $errors, $_POST + $payload);
        }

        if (! $this->productHasCode((int) $payload['product_id'])) {
            $this->redirectWithValidation('/silos/edit?id=' . $id . '&message=missing_product_code', ['product_id' => 'Bu ürün için ürün kodu tanımlanmamış.'], $_POST + $payload);
        }

        $payload['id'] = $id;

        if ($this->siloCodeExists($payload['code'], $id)) {
            $this->redirectWithValidation('/silos/edit?id=' . $id . '&message=duplicate_code', ['code' => 'Bu silo kodu zaten kullanılıyor.'], $_POST + $payload);
        }

        $statement = Database::connection()->prepare(
            'UPDATE silos
             SET code = :code,
                 name = :name,
                 product_id = :product_id,
                 capacity_kg = :capacity_kg,
                 current_stock_kg = :current_stock_kg,
                 visual_type = :visual_type,
                 min_moisture = :min_moisture,
                 max_moisture = :max_moisture,
                 min_protein = :min_protein,
                 max_protein = :max_protein,
                 min_hectoliter = :min_hectoliter,
                 max_hectoliter = :max_hectoliter,
                 min_gluten = :min_gluten,
                 max_gluten = :max_gluten,
                 min_sunn_pest_rate = :min_sunn_pest_rate,
                 max_sunn_pest_rate = :max_sunn_pest_rate,
                 min_foreign_material = :min_foreign_material,
                 max_foreign_material = :max_foreign_material,
                 min_broken_grain = :min_broken_grain,
                 max_broken_grain = :max_broken_grain,
                 description = :description,
                 is_active = :is_active
             WHERE id = :id'
        );
        $statement->execute($payload);
        AuditLogger::log('silo.updated', 'silos', $id, $payload);

        $this->redirect('/silos');
    }

    public function nextCode(): void
    {
        $productId = (int) $this->input('product_id');
        $excludeId = (int) $this->input('exclude_id');

        header('Content-Type: application/json; charset=utf-8');

        if ($productId <= 0) {
            echo json_encode(['code' => ''], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'code' => $this->nextSiloCode($productId, $excludeId > 0 ? $excludeId : null),
        ], JSON_UNESCAPED_UNICODE);
    }

    public function toggleStatus(): void
    {
        $silo = $this->findSilo((int) $this->input('id'));

        if ($silo !== null) {
            $statement = Database::connection()->prepare('UPDATE silos SET is_active = :is_active WHERE id = :id');
            $statement->execute([
                'id' => (int) $silo['id'],
                'is_active' => (int) ! (bool) $silo['is_active'],
            ]);
        }

        $this->redirect('/silos');
    }

    public function delete(): void
    {
        $id = (int) $this->input('id');

        $statement = Database::connection()->prepare('UPDATE silos SET is_active = 0 WHERE id = :id');
        $statement->execute(['id' => $id]);

        $this->redirect('/silos');
    }

    public function destroy(): void
    {
        $silo = $this->findSilo((int) $this->input('id'));

        if ($silo === null) {
            $this->redirect('/silos?message=not_found');
        }

        if ((float) $silo['current_stock_kg'] > 0) {
            $this->redirect('/silos?message=not_empty');
        }

        Database::connection()->prepare('DELETE FROM silos WHERE id = :id')->execute(['id' => (int) $silo['id']]);
        AuditLogger::log('silo.permanent_deleted', 'silos', (int) $silo['id'], $silo);

        $this->redirect('/silos?message=deleted');
    }

    private function silos(): array
    {
        $statement = Database::connection()->query(
            'SELECT s.*, p.name AS product_name
             FROM silos s
             LEFT JOIN products p ON p.id = s.product_id
             ORDER BY s.is_active DESC, s.code ASC'
        );

        return $statement->fetchAll();
    }

    private function products(): array
    {
        return Database::connection()
            ->query('SELECT id, name, code FROM products WHERE is_active = 1 ORDER BY name ASC')
            ->fetchAll();
    }

    private function findSilo(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM silos WHERE id = :id');
        $statement->execute(['id' => $id]);
        $silo = $statement->fetch(PDO::FETCH_ASSOC);

        return $silo === false ? null : $silo;
    }

    private function payload(): array
    {
        $capacityKg = $this->decimalInputOrNull('capacity_ton') !== null
            ? (string) ((float) $this->decimalInputOrNull('capacity_ton') * 1000)
            : $this->decimalInputOrZero('capacity_kg');
        $stockKg = $this->decimalInputOrNull('current_stock_ton') !== null
            ? (string) ((float) $this->decimalInputOrNull('current_stock_ton') * 1000)
            : $this->decimalInputOrZero('current_stock_kg');

        return [
            'code' => trim((string) $this->input('code')),
            'name' => trim((string) $this->input('name')),
            'product_id' => $this->intInputOrNull('product_id'),
            'visual_type' => in_array((string) $this->input('visual_type'), ['vertical', 'horizontal'], true) ? (string) $this->input('visual_type') : 'vertical',
            'capacity_kg' => $capacityKg,
            'current_stock_kg' => $stockKg,
            'min_moisture' => $this->decimalInputOrNull('min_moisture'),
            'max_moisture' => $this->decimalInputOrNull('max_moisture'),
            'min_protein' => $this->decimalInputOrNull('min_protein'),
            'max_protein' => $this->decimalInputOrNull('max_protein'),
            'min_hectoliter' => $this->decimalInputOrNull('min_hectoliter'),
            'max_hectoliter' => $this->decimalInputOrNull('max_hectoliter'),
            'min_gluten' => $this->decimalInputOrNull('min_gluten'),
            'max_gluten' => $this->decimalInputOrNull('max_gluten'),
            'min_sunn_pest_rate' => $this->decimalInputOrNull('min_sunn_pest_rate'),
            'max_sunn_pest_rate' => $this->decimalInputOrNull('max_sunn_pest_rate'),
            'min_foreign_material' => $this->decimalInputOrNull('min_foreign_material'),
            'max_foreign_material' => $this->decimalInputOrNull('max_foreign_material'),
            'min_broken_grain' => $this->decimalInputOrNull('min_broken_grain'),
            'max_broken_grain' => $this->decimalInputOrNull('max_broken_grain'),
            'description' => $this->nullableInput('description'),
            'is_active' => $this->boolInput('is_active'),
        ];
    }

    private function validationErrors(array $payload): array
    {
        $capacity = (string) $payload['capacity_kg'];
        $stock = (string) $payload['current_stock_kg'];

        $qualityChanged = (string) $this->input('quality_overridden', '0') === '1';
        $errors = [];

        if ((int) ($payload['product_id'] ?? 0) <= 0) {
            $errors['product_id'] = 'Ürün tipi boş olamaz.';
        }

        if (trim((string) $payload['code']) === '') {
            $errors['code'] = 'Silo kodu zorunludur.';
        }

        if (trim((string) $payload['name']) === '') {
            $errors['name'] = 'Silo adı zorunludur.';
        }

        if ((float) $capacity < 0) {
            $errors['capacity_ton'] = 'Kapasite sıfırdan küçük olamaz.';
        }

        if ((float) $stock < 0) {
            $errors['current_stock_ton'] = 'Mevcut doluluk sıfırdan küçük olamaz.';
        }

        if ((float) $capacity > 0.0 && (float) $stock > (float) $capacity) {
            $errors['current_stock_ton'] = 'Mevcut doluluk kapasiteden büyük olamaz.';
        }

        if ($qualityChanged && $payload['description'] === null) {
            $errors['description'] = 'Kalite sınırları değiştirildiyse açıklama zorunludur.';
        }

        return $errors;
    }

    private function nextSiloCode(int $productId, ?int $excludeSiloId = null): string
    {
        $product = $this->findProduct($productId);

        if ($product === null || trim((string) ($product['code'] ?? '')) === '') {
            return '';
        }

        $baseCode = $this->siloBaseCode($product);
        $statement = Database::connection()->prepare(
            'SELECT id, code FROM silos WHERE code LIKE :prefix ORDER BY code ASC'
        );
        $statement->execute(['prefix' => $baseCode . '-%']);
        $max = 0;

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $silo) {
            if ($excludeSiloId !== null && (int) $silo['id'] === $excludeSiloId) {
                continue;
            }

            if (preg_match('/^' . preg_quote($baseCode, '/') . '-(\d{3})$/', (string) $silo['code'], $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        do {
            $max++;
            $candidate = $baseCode . '-' . str_pad((string) $max, 3, '0', STR_PAD_LEFT);
        } while ($this->siloCodeExists($candidate, $excludeSiloId));

        return $candidate;
    }

    private function findProduct(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT id, name, code FROM products WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $product = $statement->fetch(PDO::FETCH_ASSOC);

        return $product === false ? null : $product;
    }

    private function siloBaseCode(array $product): string
    {
        $source = (string) $product['code'];

        $source = strtoupper(trim($source));
        $source = strtr($source, [
            'Ç' => 'C',
            'Ğ' => 'G',
            'İ' => 'I',
            'Ö' => 'O',
            'Ş' => 'S',
            'Ü' => 'U',
        ]);
        $source = preg_replace('/[^A-Z0-9]+/', '-', $source) ?? $source;
        $source = trim($source, '-');

        return $source === '' ? 'SILO' : $source;
    }

    private function productHasCode(int $productId): bool
    {
        $product = $this->findProduct($productId);

        return $product !== null && trim((string) ($product['code'] ?? '')) !== '';
    }

    private function siloCodeExists(string $code, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM silos WHERE code = :code';
        $params = ['code' => $code];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    private function emptySilo(): array
    {
        return [
            'id' => null,
            'code' => '',
            'name' => '',
            'product_id' => '',
            'capacity_kg' => '',
            'current_stock_kg' => '',
            'visual_type' => 'vertical',
            'min_moisture' => '',
            'max_moisture' => '',
            'min_protein' => '',
            'max_protein' => '',
            'min_hectoliter' => '',
            'max_hectoliter' => '',
            'min_gluten' => '',
            'max_gluten' => '',
            'min_sunn_pest_rate' => '',
            'max_sunn_pest_rate' => '',
            'min_foreign_material' => '',
            'max_foreign_material' => '',
            'min_broken_grain' => '',
            'max_broken_grain' => '',
            'description' => '',
            'is_active' => 1,
        ];
    }

    private function criteriaByProduct(): array
    {
        $rows = Database::connection()->query(
            'SELECT * FROM product_acceptance_criteria
             WHERE is_active = 1 AND approved_at IS NOT NULL
             ORDER BY approved_at DESC, id DESC'
        )->fetchAll();
        $criteria = [];

        foreach ($rows as $row) {
            $productId = (int) $row['product_id'];
            if (! isset($criteria[$productId])) {
                $criteria[$productId] = $row;
            }
        }

        return $criteria;
    }
}
