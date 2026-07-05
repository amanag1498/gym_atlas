<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'receipt_number' => $this->receipt_number,
            'status' => $this->status,
            'generated_at' => $this->generated_at?->toIso8601String(),
            'file_path' => $this->file_path,
        ];
    }
}
