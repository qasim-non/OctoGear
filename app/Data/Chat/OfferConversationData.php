<?php

namespace App\Data\Chat;

use App\Models\OrderOffer;

final readonly class OfferConversationData
{
    public function __construct(
        public OrderOffer $offer,
        public ?ConversationData $conversation,
        public bool $canSend,
    ) {}
}
