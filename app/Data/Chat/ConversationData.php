<?php

namespace App\Data\Chat;

use App\Models\Conversation;

final readonly class ConversationData
{
    public function __construct(
        public Conversation $conversation,
        public bool $canSend,
    ) {}
}
