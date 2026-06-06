<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use PDO;

final class CompanyController extends Controller
{
    public function index(): void
    {
        $companies = Database::connection()
            ->query('SELECT * FROM companies ORDER BY is_active DESC, name ASC')
            ->fetchAll();

        $this->view('companies/index', [
            'title' => 'Firma Tanımları',
            'companies' => $companies,
            'message' => (string) $this->input('message', ''),
        ]);
    }

    public function create(): void
    {
        $this->view('companies/form', [
            'title' => 'Firma Ekle',
            'company' => $this->emptyCompany(),
            'action' => '/companies/store',
        ]);
    }

    public function store(): void
    {
        if (trim((string) $this->input('name')) === '') {
            $this->redirect('/companies/create?message=invalid');
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO companies
                (name, tax_number, phone, contact_person, address, is_active)
             VALUES
                (:name, :tax_number, :phone, :contact_person, :address, :is_active)'
        );

        $statement->execute($this->payload());

        $this->redirect('/companies?message=saved');
    }

    public function edit(): void
    {
        $company = $this->findCompany((int) $this->input('id'));

        if ($company === null) {
            http_response_code(404);
            echo 'Firma bulunamadı.';
            return;
        }

        $this->view('companies/form', [
            'title' => 'Firma Düzenle',
            'company' => $company,
            'action' => '/companies/update',
        ]);
    }

    public function update(): void
    {
        $id = (int) $this->input('id');

        if (trim((string) $this->input('name')) === '') {
            $this->redirect('/companies/edit?id=' . $id . '&message=invalid');
        }

        $payload = $this->payload();
        $payload['id'] = $id;

        $statement = Database::connection()->prepare(
            'UPDATE companies
             SET name = :name,
                 tax_number = :tax_number,
                 phone = :phone,
                 contact_person = :contact_person,
                 address = :address,
                 is_active = :is_active
             WHERE id = :id'
        );

        $statement->execute($payload);

        $this->redirect('/companies?message=updated');
    }

    public function toggleStatus(): void
    {
        $company = $this->findCompany((int) $this->input('id'));

        if ($company !== null) {
            $statement = Database::connection()->prepare('UPDATE companies SET is_active = :is_active WHERE id = :id');
            $statement->execute([
                'id' => $company['id'],
                'is_active' => (int) ! (bool) $company['is_active'],
            ]);
        }

        $this->redirect('/companies?message=status');
    }

    private function payload(): array
    {
        return [
            'name' => trim((string) $this->input('name')),
            'tax_number' => $this->nullableInput('tax_number'),
            'phone' => $this->nullableInput('phone'),
            'contact_person' => $this->nullableInput('contact_person'),
            'address' => $this->nullableInput('address'),
            'is_active' => $this->boolInput('is_active'),
        ];
    }

    private function findCompany(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM companies WHERE id = :id');
        $statement->execute(['id' => $id]);
        $company = $statement->fetch(PDO::FETCH_ASSOC);

        return $company === false ? null : $company;
    }

    private function emptyCompany(): array
    {
        return [
            'id' => null,
            'name' => '',
            'tax_number' => '',
            'phone' => '',
            'contact_person' => '',
            'address' => '',
            'is_active' => 1,
        ];
    }
}
