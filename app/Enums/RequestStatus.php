<?php

namespace App\Enums;

/**
 * Defines the status of an approval request.
 *
 * Used in: service_provider_requests.request_status + store_requests.request_status
 *
 * Both tables use the same statuses because both follow the same workflow:
 *   pending → accepted OR rejected
 *
 * - Pending: Waiting for admin to review
 * - Accepted: Admin approved the request
 * - Rejected: Admin denied the request
 */
enum RequestStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
