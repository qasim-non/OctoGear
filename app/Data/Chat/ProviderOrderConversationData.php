<?php

namespace App\Data\Chat;

use App\Models\Order;

readonly class ProviderOrderConversationData
{
    public function __construct(
        public Order $order,
        public ?ConversationData $conversation,
    ) {}
}
