<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Services\AuditLogger;
use App\Services\OfficialAcceptanceCriteriaService;
use PDO;

final class ProductController extends Controller
{
    public function __construct(private readonly OfficialAcceptanceCriteriaService $officialCriteria = new OfficialAcceptanceCriteriaService())
    {
    }

    public function index(): void
    {
        $products = Database::connection()
            ->query('SELECT * FROM products ORDER BY is_active DESC, name ASC')
            ->fetchAll();

        $this->view('products/index', [
            'title' => 'Ürün Tanımları',
            'products' => $products,
        ]);
    }

    public function create(): void
    {
        $this->view('products/form', [
            'title' => 'Ürün Ekle',
            'product' => $this->emptyProduct(),
            'criteria' => $this->emptyCriteria(),
            'action' => '/products/store',
        ]);
    }

    public function store(): void
    {
        if (trim((string) $this->input('name')) === '') {
            $this->redirect('/products/create');
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO products
                (name, code, description, is_active)
             VALUES
                (:name, :code, :description, :is_active)'
        );

        $statement->execute($this->payload());
        $productId = (int) Database::connection()->lastInsertId();
        $this->saveCriteria($productId);

        $this->redirect('/products');
    }

    public function edit(): void
    {
        $product = $this->findProduct((int) $this->input('id'));

        if ($product === null) {
            http_response_code(404);
            echo 'Ürün bulunamadı.';
            return;
        }

        $this->view('products/form', [
            'title' => 'Ürün Düzenle',
            'product' => $product,
            'criteria' => $this->activeCriteria((int) $product['id']) ?? $this->emptyCriteria(),
            'action' => '/products/update',
        ]);
    }

    public function update(): void
    {
        $id = (int) $this->input('id');

        if (trim((string) $this->input('name')) === '') {
            $this->redirect('/products/edit?id=' . $id);
        }

        $payload = $this->payload();
        $payload['id'] = $id;

        $statement = Database::connection()->prepare(
            'UPDATE products
             SET name = :name,
                 code = :code,
                 description = :description,
                 is_active = :is_active
             WHERE id = :id'
        );

        $statement->execute($payload);
        $this->saveCriteria($id);

        $this->redirect('/products');
    }

    public function officialCriteriaPreview(): void
    {
        $productName = trim((string) $this->input('product_name'));

        header('Content-Type: application/json; charset=utf-8');

        if ($productName === '') {
            echo json_encode(['success' => false], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode(['success' => true, 'criteria' => $this->officialCriteria->preview($productName)], JSON_UNESCAPED_UNICODE);
    }

    public function toggleStatus(): void
    {
        $product = $this->findProduct((int) $this->input('id'));

        if ($product !== null) {
            $statement = Database::connection()->prepare('UPDATE products SET is_active = :is_active WHERE id = :id');
            $statement->execute([
                'id' => $product['id'],
                'is_active' => (int) ! (bool) $product['is_active'],
            ]);
        }

        $this->redirect('/products');
    }

    private function payload(): array
    {
        return [
            'name' => trim((string) $this->input('name')),
            'code' => $this->nullableInput('code'),
            'description' => $this->nullableInput('description'),
            'is_active' => $this->boolInput('is_active'),
        ];
    }

    private function findProduct(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM products WHERE id = :id');
        $statement->execute(['id' => $id]);
        $product = $statement->fetch(PDO::FETCH_ASSOC);

        return $product === false ? null : $product;
    }

    private function activeCriteria(int $productId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM product_acceptance_criteria
             WHERE product_id = :product_id AND is_active = 1
             ORDER BY approved_at DESC, id DESC
             LIMIT 1'
        );
        $statement->execute(['product_id' => $productId]);
        $criteria = $statement->fetch(PDO::FETCH_ASSOC);

        return $criteria === false ? null : $criteria;
    }

    private function saveCriteria(int $productId): void
    {
        $hasAny = false;
        foreach (['min_protein', 'max_moisture', 'min_hectoliter', 'max_sunn_pest_rate', 'max_foreign_matter', 'max_broken_grain', 'min_gluten'] as $key) {
            if ($this->decimalInputOrNull($key) !== null) {
                $hasAny = true;
                break;
            }
        }

        if (! $hasAny) {
            return;
        }

        Database::connection()
            ->prepare('UPDATE product_acceptance_criteria SET is_active = 0 WHERE product_id = :product_id')
            ->execute(['product_id' => $productId]);

        $payload = [
            'product_id' => $productId,
            'min_protein' => $this->decimalInputOrNull('min_protein'),
            'max_moisture' => $this->decimalInputOrNull('max_moisture'),
            'min_hectoliter' => $this->decimalInputOrNull('min_hectoliter'),
            'max_sunn_pest_rate' => $this->decimalInputOrNull('max_sunn_pest_rate'),
            'max_foreign_matter' => $this->decimalInputOrNull('max_foreign_matter'),
            'max_broken_grain' => $this->decimalInputOrNull('max_broken_grain'),
            'min_gluten' => $this->decimalInputOrNull('min_gluten'),
            'source_type' => in_array((string) $this->input('source_type'), ['manual', 'official_source'], true) ? (string) $this->input('source_type') : 'manual',
            'source_name' => $this->nullableInput('source_name'),
            'source_url' => $this->nullableInput('source_url'),
            'source_date' => $this->nullableInput('source_date'),
            'approved_by' => Auth::user()['id'] ?? null,
            'notes' => $this->nullableInput('criteria_notes'),
        ];

        Database::connection()->prepare(
            'INSERT INTO product_acceptance_criteria
                (product_id, min_protein, max_moisture, min_hectoliter, max_sunn_pest_rate,
                 max_foreign_matter, max_broken_grain, min_gluten, source_type, source_name,
                 source_url, source_date, approved_by, approved_at, is_active, notes)
             VALUES
                (:product_id, :min_protein, :max_moisture, :min_hectoliter, :max_sunn_pest_rate,
                 :max_foreign_matter, :max_broken_grain, :min_gluten, :source_type, :source_name,
                 :source_url, :source_date, :approved_by, NOW(), 1, :notes)'
        )->execute($payload);

        AuditLogger::log('product_acceptance_criteria.approved', 'product_acceptance_criteria', (int) Database::connection()->lastInsertId(), $payload);
    }

    private function emptyProduct(): array
    {
        return [
            'id' => null,
            'name' => '',
            'code' => '',
            'description' => '',
            'is_active' => 1,
        ];
    }

    private function emptyCriteria(): array
    {
        return [
            'min_protein' => '',
            'max_moisture' => '',
            'min_hectoliter' => '',
            'max_sunn_pest_rate' => '',
            'max_foreign_matter' => '',
            'max_broken_grain' => '',
            'min_gluten' => '',
            'source_type' => 'manual',
            'source_name' => '',
            'source_url' => '',
            'source_date' => '',
            'notes' => '',
        ];
    }
}
