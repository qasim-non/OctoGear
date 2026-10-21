<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentIndexRequest;
use App\Http\Resources\AdminPaymentResource;
use App\Models\Payment;
use App\Services\AdminPaymentQueryService;
use Illuminate\Http\JsonResponse;

class AdminPaymentController extends Controller
{
    public function __construct(private AdminPaymentQueryService $payments) {}

    public function index(PaymentIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAdminDirectory', Payment::class);

        return $this->paginated($this->payments->index($request->validated())
            ->through(fn (Payment $payment) => new AdminPaymentResource($payment)))
            ->header('Cache-Control', 'no-store, private');
    }
}
