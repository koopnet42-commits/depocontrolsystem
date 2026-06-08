<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AuditLogger;
use App\Services\SenderPersonRegistry;
use PDO;
use Throwable;

final class SenderRecordController extends Controller
{
    public function store(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $type = in_array((string) $this->input('type', 'company'), ['company', 'person'], true)
            ? (string) $this->input('type', 'company')
            : 'company';

        try {
            $record = $type === 'company' ? $this->storeCompany() : SenderPersonRegistry::findOrCreate([
                'full_name' => $this->input('sender_name', ''),
                'identity_number' => $this->input('identity_number', ''),
                'phone' => $this->input('sender_phone', ''),
                'address' => $this->input('sender_address', ''),
            ]);

            AuditLogger::log('sender_record_created_or_selected', $type === 'company' ? 'companies' : 'sender_people', (int) ($record['id'] ?? 0), [
                'service_note' => $type === 'company' ? 'Firma kaydı form içinden seçildi/oluşturuldu.' : 'Şahıs kaydı form içinden seçildi/oluşturuldu.',
                'type' => $type,
                'new' => $record,
            ]);

            echo json_encode(['ok' => true, 'type' => $type, 'record' => $record], JSON_UNESCAPED_UNICODE);
        } catch (\InvalidArgumentException $exception) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
        } catch (Throwable) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Kayıt sırasında beklenmeyen bir hata oluştu.'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function storeCompany(): array
    {
        $name = trim((string) $this->input('company_name', ''));
        $taxNumber = $this->nullableInput('sender_tax_number');
        $phone = $this->nullableInput('sender_phone');
        $address = $this->nullableInput('sender_address');

        if ($name === '') {
            throw new \InvalidArgumentException('Firma adı zorunludur.');
        }

        $existing = $this->findCompany($name, $taxNumber);
        if ($existing !== null) {
            return $existing;
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO companies (name, tax_number, phone, address, is_active, created_at, updated_at)
             VALUES (:name, :tax_number, :phone, :address, 1, NOW(), NOW())'
        );
        $statement->execute([
            'name' => $name,
            'tax_number' => $taxNumber,
            'phone' => $phone,
            'address' => $address,
        ]);

        return $this->findCompanyById((int) Database::connection()->lastInsertId());
    }

    private function findCompany(string $name, ?string $taxNumber): ?array
    {
        if ($taxNumber !== null) {
            $statement = Database::connection()->prepare('SELECT id, name, tax_number, address, phone FROM companies WHERE tax_number = :tax_number AND is_active = 1 LIMIT 1');
            $statement->execute(['tax_number' => $taxNumber]);
            $record = $statement->fetch(PDO::FETCH_ASSOC);
            if ($record !== false) {
                return $record;
            }
        }

        $statement = Database::connection()->prepare('SELECT id, name, tax_number, address, phone FROM companies WHERE lower(name) = lower(:name) AND is_active = 1 LIMIT 1');
        $statement->execute(['name' => $name]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    private function findCompanyById(int $id): array
    {
        $statement = Database::connection()->prepare('SELECT id, name, tax_number, address, phone FROM companies WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record ?: [];
    }
}
