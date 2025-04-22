<?php

declare(strict_types=1);

namespace App\Model;

use Nette\Database\Explorer;
use Nette\Database\UniqueConstraintViolationException;

class ChatFacade
{
    private Explorer $database;

    public function __construct(Explorer $database)
    {
        $this->database = $database;
    }
    
    public function getChatMessages(int $repairId): array
    {
        return $this->database->table('chats')
            ->where('repair_id', $repairId)
            ->order('created_at ASC')
            ->fetchAll();
    }
    
    public function addChatMessage(int $repairId, int $senderId, int $receiverId, string $message): bool
    {
        try {
            $this->database->table('chats')->insert([
                'repair_id' => $repairId,
                'sender_id' => $senderId,
                'receiver_id' => $receiverId,
                'message' => $message,
                'created_at' => new \DateTime(),
            ]);
    
            bdump("Message saved: Sender $senderId -> Receiver $receiverId");
            return true;
        } catch (\Exception $e) {
            bdump("DB Insert Error: " . $e->getMessage());
            return false;
        }
    }

    public function getLatestMessageForRepair(int $repairId)
{
    return $this->database->table('chats')
        ->where('repair_id', $repairId)
        ->order('created_at DESC')
        ->fetch();
}

    
}
