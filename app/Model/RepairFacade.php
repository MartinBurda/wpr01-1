<?php

declare(strict_types=1);

namespace App\Model;

use Nette\Database\Explorer;
use Nette\Database\UniqueConstraintViolationException;
use App\Model\UserFacade;

class RepairFacade
{
    private Explorer $database;
    private UserFacade $userFacade;

    public function __construct(Explorer $database, UserFacade $userFacade)
    {
        $this->database = $database;
        $this->userFacade = $userFacade;
    }

    // ========================== Repairs Retrieval ========================== //

    public function getAllRepairs(): array
    {
        return $this->database->table('repairs')
            ->order('id DESC') // Sort by latest first
            ->fetchAll();
    }

    public function getRepairsBySearch(?string $search = null, int $page = 1, int $itemsPerPage = 5): array
    {
        $query = $this->database->table('repairs');
        if (!empty($search)) {
            $query->where('device LIKE ? OR name LIKE ? OR surname LIKE ?', 
                          '%' . $search . '%', '%' . $search . '%', '%' . $search . '%');
        }
        return $query->order('id DESC')
                     ->limit($itemsPerPage, ($page - 1) * $itemsPerPage)
                     ->fetchAll();
    }
    
    public function countRepairs(?string $search = null): int
    {
        $query = $this->database->table('repairs');
        if (!empty($search)) {
            $query->where('device LIKE ? OR name LIKE ? OR surname LIKE ?', 
                          '%' . $search . '%', '%' . $search . '%', '%' . $search . '%');
        }
        return $query->count('*');
    }

    public function getRepairById(int $repairId): ?\Nette\Database\Table\ActiveRow
    {
        return $this->database->table('repairs')->get($repairId);
    }

    public function getRepairByCode(string $repairCode): ?\Nette\Database\Table\ActiveRow
    {
        return $this->database->table('repairs')
            ->where('repair_code', $repairCode)
            ->fetch();
    }

    public function getRepairsByTechnician(int $technicianId): array
    {
        return $this->database->table('repairs')
            ->where('technician_id', $technicianId)
            ->fetchAll();
    }

    public function getRepairsByTechnicianPaginated(int $technicianId, int $page, int $itemsPerPage): array
    {
        return $this->database->table('repairs')
            ->select('id, device, issue, name, surname, status, priority, created_at, technician_id, customer_id')
            ->where('technician_id', $technicianId)
            ->limit($itemsPerPage, ($page - 1) * $itemsPerPage)
            ->fetchAll();
    }

    public function countRepairsByTechnician(int $technicianId): int
    {
        return $this->database->table('repairs')
            ->where('technician_id', $technicianId)
            ->count('*');
    }

    public function getRepairsByIds(array $ids): array
    {
        $repairs = $this->database->table('repairs')
            ->select('id, device, issue, name, surname, status, priority, created_at, technician_id, customer_id, status_description, email, phone')
            ->where('id', $ids)
            ->fetchAll();

        return array_column($repairs, null, 'id');
    }

    public function getPhotosByRepairId(int $repairId): array
    {
        return $this->database->table('images')
            ->where('repair_id', $repairId)
            ->fetchAll();
    }

    public function deleteOldRepairs(): void
    {
        $sevenDaysAgo = new \DateTime('-7 days');

        $this->database->table('repairs')
            ->where('created_at < ?', $sevenDaysAgo)
            ->where('status', 'pending')
            ->delete();
    }

    // ========================== Device Info Retrieval ========================== //

    public function getTypes(): array
    {
        return $this->database->table('device_types')->fetchPairs('id', 'name');
    }

    public function getManufacturers(): array
    {
        return $this->database->table('manufacturers')->fetchPairs('id', 'name');
    }

    public function getManufacturersByType(int $typeId): array
    {
        return $this->database->table('manufacturers')
            ->where('id IN', $this->database->table('models')
                ->where('type_id', $typeId)
                ->select('manufacturer_id'))
            ->fetchPairs('id', 'name');
    }

    public function getModelsByManufacturer(int $manufacturerId): array
    {
        return $this->database->table('models')
            ->where('manufacturer_id', $manufacturerId)
            ->fetchPairs('id', 'name');
    }

    public function getFaults(): array 
    {
        return $this->database->table('faults')->fetchPairs('id', 'name');
    }

    public function getRepairPrice(int $modelId, int $faultId): ?float
    {
        return $this->database->table('repair_prices')
            ->where('model_id', $modelId)
            ->where('fault_id', $faultId)
            ->fetchField('price');
    }

    // ========================== Adding Records ========================== //

    public function addRepair(
        int $manufacturerId,
        int $modelId,
        int $faultId,
        int $customerId,
        string $name,
        string $surname,
        string $email,
        int $phone
    ): int|bool {
        try {
            $manufacturer = $this->database->table('manufacturers')->get($manufacturerId)->name ?? 'Unknown Manufacturer';
            $model = $this->database->table('models')->get($modelId)->name ?? 'Unknown Model';
            $fault = $this->database->table('faults')->get($faultId)->name ?? 'Unknown Fault';

            $technicianId = $this->userFacade->getTechnicianWithLeastRepairs();
            $repairCode = $this->generateUniqueCode();

            $row = $this->database->table('repairs')->insert([
                'customer_id' => $customerId,
                'device' => "$manufacturer $model",
                'issue' => $fault,
                'name' => $name,
                'surname' => $surname,
                'email' => $email,
                'phone' => $phone,
                'technician_id' => $technicianId,
                'repair_code' => $repairCode,
                'created_at' => new \DateTime(),
            ]);

            return $row->id;
        } catch (\Exception $e) {
            bdump('Error during repair insertion: ' . $e->getMessage());
            return false;
        }
    }

    public function addDeviceType(string $name): bool
    {
        try {
            $this->database->table('device_types')->insert(['name' => $name]);
            return true;
        } catch (\Exception $e) {
            bdump('Error adding device type: ' . $e->getMessage());
            return false;
        }
    }

    public function addManufacturer(string $name): bool
    {
        try {
            $this->database->table('manufacturers')->insert(['name' => $name]);
            return true;
        } catch (\Exception $e) {
            bdump('Error adding manufacturer: ' . $e->getMessage());
            return false;
        }
    }

    public function addModel(string $name, int $manufacturerId, int $typeId, ?int $releaseYear = null): bool
    {
        try {
            $this->database->table('models')->insert([
                'name' => $name,
                'manufacturer_id' => $manufacturerId,
                'type_id' => $typeId,
                'release_year' => $releaseYear,
            ]);
            return true;
        } catch (\Exception $e) {
            bdump('Error adding model: ' . $e->getMessage());
            return false;
        }
    }

    public function addPhoto(int $repairId, string $photoPath): void
    {
        $this->database->table('images')->insert([
            'repair_id' => $repairId,
            'photo_path' => $photoPath,
            'uploaded_at' => new \DateTime(),
        ]);
    }

    // ========================== Updating Records ========================== //

    public function updateRepair(int $id, array $values): void
    {
        $repair = $this->database->table('repairs')->get($id);

        if (!$repair) {
            throw new \Exception('Oprava nenalezena.');
        }

        $repair->update($values);
    }

    public function updateRepairStatus(int $id, string $status, string $statusDescription): void
    {
        $repair = $this->database->table('repairs')->get($id);

        if (!$repair) {
            throw new \Exception('Oprava nenalezena.');
        }

        $repair->update([
            'status' => $status,
            'status_description' => $statusDescription,
        ]);
    }

    public function updateRepairPriority(int $repairId, string $priority): void
    {
        $validPriorities = ['low', 'normal', 'high'];
        if (!in_array($priority, $validPriorities)) {
            throw new \Exception("Neplatná priorita: $priority");
        }

        $this->database->table('repairs')
            ->where('id', $repairId)
            ->update(['priority' => $priority]);
    }


    // ========================== Chat Related ========================== //

    public function getChatMessagesForRepairs(array $repairIds): array
    {
        return $this->database->table('chat_messages')
            ->where('repair_id', $repairIds)
            ->order('created_at ASC')
            ->fetchAll();
    }

    // ========================== Helpers ========================== //

    private function generateUniqueCode(): string
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $maxAttempts = 10;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $code = '';
            for ($i = 0; $i < 5; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }

            $exists = $this->database->table('repairs')
                ->where('repair_code', $code)
                ->count('*') > 0;

            if (!$exists) {
                return $code;
            }
        }

        throw new \Exception('Unable to generate unique repair code after maximum attempts');
    }
}