<?php

namespace App\Console\Commands;

use App\Services\Chatbot\KnowledgeBase;
use Illuminate\Console\Command;

/** Re-read the shop's own pages into what the assistant knows. New chats pick it up; open ones keep theirs. */
class ChatbotKnowledge extends Command
{
    protected $signature = 'chatbot:knowledge';
    protected $description = 'Rebuild the UDS Assistant knowledge base from the live site pages';

    public function handle(KnowledgeBase $kb): int
    {
        [$chars, $pages] = $kb->build();
        $this->info("Knowledge base rebuilt: {$pages} pages, " . number_format($chars) . ' characters.');
        return self::SUCCESS;
    }
}
