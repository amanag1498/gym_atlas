<?php

namespace App\Services\Billing;

use App\Models\Payment;

class PaymentInvoicePdfService
{
    /**
     * @return array{filename: string, content: string}
     */
    public function generate(Payment $payment): array
    {
        $payment->loadMissing([
            'gym',
            'branch',
            'member',
            'membership.membershipPlan',
            'collector',
            'receipt',
        ]);

        $receiptNumber = $payment->receipt_number ?: ($payment->receipt?->receipt_number ?: sprintf('RCT-%06d', $payment->id));
        $filename = 'invoice-'.$receiptNumber.'.pdf';

        $lines = [
            'Gym Invoice / Receipt',
            '',
            'Receipt Number: '.$receiptNumber,
            'Payment ID: '.$payment->id,
            'Generated At: '.now()->format('d M Y, h:i A'),
            '',
            'Gym: '.($payment->gym?->name ?? 'Gym'),
            'Branch: '.($payment->branch?->name ?? 'Gym-wide'),
            '',
            'Member: '.($payment->member?->name ?? 'Member'),
            'Email: '.($payment->member?->email ?? 'No email'),
            'Plan: '.($payment->membership?->membershipPlan?->name ?? 'Membership'),
            '',
            'Amount Collected: INR '.number_format((float) $payment->amount, 2, '.', ''),
            'Payment Mode: '.strtoupper((string) $payment->payment_mode),
            'Paid At: '.($payment->paid_at?->format('d M Y, h:i A') ?? 'Not available'),
            'Collector: '.($payment->collector?->name ?? 'System'),
            'External Reference: '.($payment->external_reference ?: 'Not provided'),
            '',
            'Membership Snapshot',
            'Status: '.ucfirst((string) ($payment->membership?->status ?? 'unknown')),
            'Payment Status: '.ucfirst((string) ($payment->membership?->payment_status ?? 'unknown')),
            'Final Payable: INR '.number_format((float) ($payment->membership?->final_payable_amount ?? 0), 2, '.', ''),
            'Amount Paid Total: INR '.number_format((float) ($payment->membership?->amount_paid ?? 0), 2, '.', ''),
            'Due Amount: INR '.number_format((float) ($payment->membership?->due_amount ?? 0), 2, '.', ''),
            'Due Date: '.($payment->membership?->due_date?->format('d M Y') ?? 'Not set'),
            '',
            'Notes:',
            ...$this->wrapText($payment->notes ?: 'No notes added.', 82),
        ];

        $content = $this->buildPdf($lines);

        return [
            'filename' => $filename,
            'content' => $content,
        ];
    }

    /**
     * @param  list<string>  $lines
     */
    private function buildPdf(array $lines): string
    {
        $escapedLines = array_map(fn (string $line): string => '('.$this->escapeText($line).') Tj', $lines);
        $stream = "BT\n/F1 11 Tf\n14 TL\n1 0 0 1 48 804 Tm\n".implode("\nT*\n", $escapedLines)."\nET";

        $objects = [
            1 => "<< /Type /Catalog /Pages 2 0 R >>",
            2 => "<< /Type /Pages /Count 1 /Kids [3 0 R] >>",
            3 => "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>",
            4 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
            5 => "<< /Length ".strlen($stream)." >>\nstream\n".$stream."\nendstream",
        ];

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$body."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach ($objects as $number => $_body) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$number])."\n";
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xrefOffset."\n%%EOF";

        return $pdf;
    }

    /**
     * @return list<string>
     */
    private function wrapText(string $text, int $limit): array
    {
        $clean = preg_replace('/\s+/', ' ', trim($text)) ?: '';

        if ($clean === '') {
            return [''];
        }

        $lines = wordwrap($clean, $limit, "\n", true);

        return explode("\n", $lines);
    }

    private function escapeText(string $text): string
    {
        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\(', '\)'],
            preg_replace('/[^\x20-\x7E]/', ' ', $text) ?? $text,
        );
    }
}
