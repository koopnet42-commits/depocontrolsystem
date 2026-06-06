<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use PDO;

final class SiloRuleController extends Controller
{
    public function index(): void
    {
        $this->view('silo_rules/index', [
            'title' => 'Silo Yönlendirme Kuralları',
            'rules' => $this->rules(),
        ]);
    }

    public function create(): void
    {
        $this->view('silo_rules/form', [
            'title' => 'Kural Ekle',
            'rule' => $this->emptyRule(),
            'products' => $this->products(),
            'silos' => $this->silos(),
            'action' => '/silo-rules/store',
        ]);
    }

    public function store(): void
    {
        if (! $this->requiredFieldsAreValid()) {
            $this->redirect('/silo-rules/create');
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO silo_rules
                (name, product_id, silo_id, min_moisture, max_moisture, min_protein,
                 min_hectoliter, max_foreign_material, max_sunn_pest_rate, priority, is_active)
             VALUES
                (:name, :product_id, :silo_id, :min_moisture, :max_moisture, :min_protein,
                 :min_hectoliter, :max_foreign_material, :max_sunn_pest_rate, :priority, :is_active)'
        );
        $statement->execute($this->payload());

        $this->redirect('/silo-rules');
    }

    public function edit(): void
    {
        $rule = $this->findRule((int) $this->input('id'));

        if ($rule === null) {
            http_response_code(404);
            echo 'Silo kuralı bulunamadı.';
            return;
        }

        $this->view('silo_rules/form', [
            'title' => 'Kural Düzenle',
            'rule' => $rule,
            'products' => $this->products(),
            'silos' => $this->silos(),
            'action' => '/silo-rules/update',
        ]);
    }

    public function update(): void
    {
        $id = (int) $this->input('id');

        if (! $this->requiredFieldsAreValid()) {
            $this->redirect('/silo-rules/edit?id=' . $id);
        }

        $payload = $this->payload();
        $payload['id'] = $id;

        $statement = Database::connection()->prepare(
            'UPDATE silo_rules
             SET name = :name,
                 product_id = :product_id,
                 silo_id = :silo_id,
                 min_moisture = :min_moisture,
                 max_moisture = :max_moisture,
                 min_protein = :min_protein,
                 min_hectoliter = :min_hectoliter,
                 max_foreign_material = :max_foreign_material,
                 max_sunn_pest_rate = :max_sunn_pest_rate,
                 priority = :priority,
                 is_active = :is_active
             WHERE id = :id'
        );
        $statement->execute($payload);

        $this->redirect('/silo-rules');
    }

    public function toggleStatus(): void
    {
        $rule = $this->findRule((int) $this->input('id'));

        if ($rule !== null) {
            $statement = Database::connection()->prepare('UPDATE silo_rules SET is_active = :is_active WHERE id = :id');
            $statement->execute([
                'id' => (int) $rule['id'],
                'is_active' => (int) ! (bool) $rule['is_active'],
            ]);
        }

        $this->redirect('/silo-rules');
    }

    public function delete(): void
    {
        $statement = Database::connection()->prepare('UPDATE silo_rules SET is_active = 0 WHERE id = :id');
        $statement->execute(['id' => (int) $this->input('id')]);

        $this->redirect('/silo-rules');
    }

    private function rules(): array
    {
        $statement = Database::connection()->query(
            'SELECT sr.*, p.name AS product_name, s.code AS silo_code, s.name AS silo_name
             FROM silo_rules sr
             INNER JOIN products p ON p.id = sr.product_id
             INNER JOIN silos s ON s.id = sr.silo_id
             ORDER BY sr.is_active DESC, sr.priority ASC, sr.id ASC'
        );

        return $statement->fetchAll();
    }

    private function products(): array
    {
        return Database::connection()
            ->query('SELECT id, name FROM products WHERE is_active = 1 ORDER BY name ASC')
            ->fetchAll();
    }

    private function silos(): array
    {
        return Database::connection()
            ->query('SELECT id, code, name FROM silos WHERE is_active = 1 ORDER BY code ASC')
            ->fetchAll();
    }

    private function findRule(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM silo_rules WHERE id = :id');
        $statement->execute(['id' => $id]);
        $rule = $statement->fetch(PDO::FETCH_ASSOC);

        return $rule === false ? null : $rule;
    }

    private function payload(): array
    {
        return [
            'name' => trim((string) $this->input('name')),
            'product_id' => (int) $this->input('product_id'),
            'silo_id' => (int) $this->input('silo_id'),
            'min_moisture' => $this->decimalInputOrNull('min_moisture'),
            'max_moisture' => $this->decimalInputOrNull('max_moisture'),
            'min_protein' => $this->decimalInputOrNull('min_protein'),
            'min_hectoliter' => $this->decimalInputOrNull('min_hectoliter'),
            'max_foreign_material' => $this->decimalInputOrNull('max_foreign_material'),
            'max_sunn_pest_rate' => $this->decimalInputOrNull('max_sunn_pest_rate'),
            'priority' => max(1, (int) $this->input('priority', 100)),
            'is_active' => $this->boolInput('is_active'),
        ];
    }

    private function requiredFieldsAreValid(): bool
    {
        return trim((string) $this->input('name')) !== ''
            && (int) $this->input('product_id') > 0
            && (int) $this->input('silo_id') > 0;
    }

    private function emptyRule(): array
    {
        return [
            'id' => null,
            'name' => '',
            'product_id' => '',
            'silo_id' => '',
            'min_moisture' => '',
            'max_moisture' => '',
            'min_protein' => '',
            'min_hectoliter' => '',
            'max_foreign_material' => '',
            'max_sunn_pest_rate' => '',
            'priority' => 100,
            'is_active' => 1,
        ];
    }
}
