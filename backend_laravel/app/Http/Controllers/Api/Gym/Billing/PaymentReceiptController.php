<?php

namespace App\Http\Controllers\Api\Gym\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Billing\PaymentReceiptResource;
use App\Models\Payment;
use App\Services\Billing\BillingAccessService;
use Illuminate\Http\Request;

class PaymentReceiptController extends Controller
{
    public function __construct(
        private readonly BillingAccessService $billingAccessService,
    ) {
    }

    public function show(Payment $payment, Request $request)
    {
        $membership = $payment->membership()->firstOrFail();
        $this->authorize('view', $membership);
        $this->billingAccessService->assertMembershipAccess($request->user(), $membership);

        return $this->success(
            PaymentReceiptResource::make($payment->load('receipt')->receipt),
            'Payment receipt fetched successfully.',
        );
    }
}
