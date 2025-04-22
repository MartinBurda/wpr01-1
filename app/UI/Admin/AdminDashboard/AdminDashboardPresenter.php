<?php

namespace App\UI\Admin\AdminDashboard;

use Nette\Application\UI\Presenter;
use App\Model\RepairFacade;
use App\Model\ChatFacade;
use App\Model\UserFacade;

class AdminDashboardPresenter extends Presenter
{
    private RepairFacade $repairFacade;
    private ChatFacade $chatFacade;
    private UserFacade $userFacade;

    public function __construct(RepairFacade $repairFacade, ChatFacade $chatFacade, UserFacade $userFacade)
    {
        parent::__construct();
        $this->repairFacade = $repairFacade;
        $this->chatFacade = $chatFacade;
        $this->userFacade = $userFacade;
    }

    protected function startup(): void
    {
        parent::startup();
        if (!$this->getUser()->isInRole('admin')) {
            $this->flashMessage('Pro přístup na tuto stránku musíte být administrátor.');
            $this->redirect(':Front:Home:default');
        }
    }

    public function renderDefault(): void
    {
        // Get user role
        $user = $this->getUser();
    
        // Check if the user has permission (must be admin or opravar)
        if (!$user->isLoggedIn() || !in_array($user->getIdentity()->role, ['admin', 'repair'])) {
            // Flash message for unauthorized access
            $this->flashMessage('Nemáš oprávnění');
    
            // Redirect to home page
            $this->redirect(':Front:Home:default');
            return;
        }
    
        // Fetch all repairs
        $repairs = $this->repairFacade->getAllRepairs();
    
        if (empty($repairs)) {
            bdump('No data found in query!'); // Debug output
        } else {
            bdump($repairs); // Check the content
        }
    
        // Assign repairs to template
        $this->template->repairs = $repairs;
    }
    
    
    public function renderDetail(int $id): void
    {
        // Use the getRepairById method from RepairFacade
        $repair = $this->repairFacade->getRepairById($id);
    
        // If the repair doesn't exist, throw an error
        if (!$repair) {
            $this->error('Oprava nenalezena.');
        }

        // Fetch associated technician (if any)
        $technician = $repair->technician_id 
            ? $repair->ref('users', 'technician_id') 
            : null;

        // Fetch chat messages for this repair
        $chatMessages = $this->chatFacade->getChatMessages($id);

        // Pass data to the template
        $this->template->repair = $repair;
        $this->template->technician = $technician;
        $this->template->chatMessages = $chatMessages;
    }

    public function handleUpdateStatus(): void
    {
        $id = $this->getHttpRequest()->getPost('id');  // Read ID
        $status = $this->getHttpRequest()->getPost('status'); // Read status
        $statusDescription = $this->getHttpRequest()->getPost('status_description'); // Read status description
    
        if (!$id || !$status) {
            $this->flashMessage("Neplatné údaje", 'danger');
            $this->redirect('this'); 
        }
    
        try {
            $this->repairFacade->updateRepairStatus((int) $id, $status, $statusDescription);
            $this->flashMessage("Stav opravy byl aktualizován", 'success');
        } catch (\Exception $e) {
            $this->flashMessage("Chyba: {$e->getMessage()}", 'danger');
        }
    
        $this->redirect('this'); 
    }

    public function handleGetMessages(int $repairId): void
    {
        $messages = $this->chatFacade->getChatMessages($repairId);
        $formattedMessages = [];
    
        foreach ($messages as $msg) {
            $sender = $this->userFacade->getUserById($msg->sender_id);
            $receiver = $this->userFacade->getUserById($msg->receiver_id);
    
            $formattedMessages[] = [
                'sender_id' => $msg->sender_id,
                'receiver_id' => $msg->receiver_id,
                'sender_name' => $sender ? explode(" ", $sender->name)[0] : "Neznámý", // Extract first name
                'receiver_name' => $receiver ? explode(" ", $receiver->name)[0] : "Neznámý",
                'message' => $msg->message,
                'created_at' => $msg->created_at->format('Y-m-d H:i:s'),
            ];
        }
    
        $this->sendResponse(new \Nette\Application\Responses\JsonResponse($formattedMessages));
    }
    
    

    public function handleSendMessage(): void
    {
        $message = $this->getHttpRequest()->getPost('message');
        $repairId = $this->getHttpRequest()->getPost('repairId');
        $senderId = $this->getUser()->getId(); // The logged-in admin/technician
    
        $repair = $this->repairFacade->getRepairById((int)$repairId);
        if (!$repair) {
            $this->sendResponse(new \Nette\Application\Responses\JsonResponse([
                'status' => 'error', 
                'message' => 'Repair not found'
            ]));
            return;
        }
    
        // Determine receiver: Find the latest message sender and assume the other party is the receiver
        $latestMessage = $this->chatFacade->getLatestMessageForRepair($repairId);
    
        if ($latestMessage) {
            $receiverId = ($latestMessage->sender_id === $senderId) ? $latestMessage->receiver_id : $latestMessage->sender_id;
        } else {
            // If no previous messages exist, assume the customer is the first recipient
            $receiverId = ($senderId === $repair->technician_id) ? $repair->customer_id : $repair->technician_id;
        }
    
        if ($message && $repairId && $senderId && $receiverId) {
            $this->chatFacade->addChatMessage((int)$repairId, $senderId, $receiverId, $message);
            $this->sendResponse(new \Nette\Application\Responses\JsonResponse(['status' => 'success']));
        } else {
            $this->sendResponse(new \Nette\Application\Responses\JsonResponse([
                'status' => 'error',
                'message' => 'Invalid chat data'
            ]));
        }
    }
    
}