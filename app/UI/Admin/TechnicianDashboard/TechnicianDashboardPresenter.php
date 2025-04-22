<?php

namespace App\UI\Admin\TechnicianDashboard;

use Nette\Application\UI\Presenter;
use App\Model\RepairFacade;
use App\Model\UserFacade;
use App\Model\ChatFacade;

class TechnicianDashboardPresenter extends Presenter
{
    private RepairFacade $repairFacade;
    private UserFacade $userFacade;
    private ChatFacade $chatFacade;

    public function __construct(RepairFacade $repairFacade, UserFacade $userFacade, ChatFacade $chatFacade)
    {
        parent::__construct();
        $this->repairFacade = $repairFacade;
        $this->userFacade = $userFacade;
        $this->chatFacade = $chatFacade;
    }

    public function renderDefault(): void
    {
        $user = $this->getUser();

        if (!$user->isLoggedIn() || $user->getIdentity()->role !== 'repair') {
            $this->flashMessage('Nemáš oprávnění k této stránce');
            $this->redirect(':Front:Home:default');
            return;
        }

        $technicianId = $user->getId();
        $repairs = $this->repairFacade->getRepairsByTechnician($technicianId);
        $technician = $this->userFacade->getUserById($technicianId);
        $activeRepairsCount = count(array_filter($repairs, fn($r) => $r->status !== 'completed'));
        $completedToday = count(array_filter($repairs, fn($r) => $r->status === 'completed' && $r->updated_at->format('Y-m-d') === date('Y-m-d')));

        // Manage selected repairs (up to 3)
        $session = $this->getSession('technicianDashboard');
        $selectedRepairs = $session->selectedRepairs ?? [null, null, null]; // For div1, div2, div3

        // Fetch chat messages for selected repairs
        $chatMessages = [];
        foreach ($selectedRepairs as $index => $repairId) {
            if ($repairId) {
                $chatMessages[$index] = $this->chatFacade->getChatMessages($repairId);
            }
        }

        $this->template->technician = $technician;
        $this->template->repairs = $repairs;
        $this->template->activeRepairsCount = $activeRepairsCount;
        $this->template->completedToday = $completedToday;
        $this->template->selectedRepairs = array_map(
            fn($id) => $id ? $this->repairFacade->getRepairById($id) : null,
            $selectedRepairs
        );
        $this->template->chatMessages = $chatMessages;
        $this->template->currentUserId = $user->getId();
    }

    public function handleSelectRepair(int $repairId): void
    {
        $session = $this->getSession('technicianDashboard');
        $selectedRepairs = $session->selectedRepairs ?? [null, null, null];

        // Find the first available slot (or overwrite the oldest one)
        $index = array_search(null, $selectedRepairs, true);
        if ($index === false) {
            // No empty slot, overwrite the first one (shift others)
            $selectedRepairs = [$repairId, $selectedRepairs[0], $selectedRepairs[1]];
        } else {
            $selectedRepairs[$index] = $repairId;
        }

        $session->selectedRepairs = $selectedRepairs;
        $this->redirect('this');
    }

    public function handleCloseRepair(int $slot): void
    {
        $session = $this->getSession('technicianDashboard');
        $selectedRepairs = $session->selectedRepairs ?? [null, null, null];
        $selectedRepairs[$slot] = null;
        $session->selectedRepairs = $selectedRepairs;
        $this->redirect('this');
    }

    public function handleUpdateStatus(): void
    {
        $id = $this->getHttpRequest()->getPost('id');
        $status = $this->getHttpRequest()->getPost('status');
        $statusDescription = $this->getHttpRequest()->getPost('status_description') ?? '';
    
        if (!$id || !$status) {
            $this->flashMessage("Neplatné údaje", 'danger');
            $this->redirect('this');
        }
    
        try {
            $this->repairFacade->updateRepairStatus((int)$id, $status, $statusDescription);
            $this->flashMessage("Stav opravy byl aktualizován", 'success');
        } catch (\Exception $e) {
            $this->flashMessage("Chyba: {$e->getMessage()}", 'danger');
        }
    
        $this->redirect('this');
    }
    
    public function handleSendMessage(int $slot): void
    {
        $message = $this->getHttpRequest()->getPost('message');
        $repairId = $this->getHttpRequest()->getPost('repairId');
        $senderId = $this->getUser()->getId();
    
        $repair = $this->repairFacade->getRepairById($repairId);
        if (!$repair) {
            $this->flashMessage('Oprava nenalezena', 'error');
            $this->redirect('this');
        }
    
        // Determine receiver using customer_id and technician_id from repairs table
        $receiverId = $senderId === $repair->technician_id ? $repair->customer_id : $repair->technician_id;
    
        if ($message && $repairId && $senderId && $receiverId) {
            $this->chatFacade->addChatMessage($repairId, $senderId, $receiverId, $message);
            $this->flashMessage('Zpráva odeslána', 'success');
        } else {
            $this->flashMessage('Chyba při odesílání zprávy', 'error');
        }
        $this->redirect('this');
    }
}