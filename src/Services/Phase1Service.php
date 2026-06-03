<?php

namespace Aghfatehi\Zatca\Services;

use Aghfatehi\Zatca\DTO\InvoiceDTO;

class Phase1Service
{
    public function __construct(
        private QrCodeService $qrService,
    ) {}

    public function generateQrCodeText(
        string $sellerName,
        string $vatNumber,
        string $invoiceDate,
        string $totalAmount,
        string $taxAmount,
    ): string {
        return $this->qrService->generatePhase1Qr(
            sellerName: $sellerName,
            vatNumber: $vatNumber,
            invoiceDate: $invoiceDate,
            totalAmount: $totalAmount,
            taxAmount: $taxAmount,
        );
    }

    public function generateQrCodeFromInvoice(InvoiceDTO $invoice, array $egsUnit): string
    {
        return $this->qrService->generatePhase1Qr(
            sellerName: $egsUnit['vat_name'] ?? '',
            vatNumber: $egsUnit['vat_number'] ?? '',
            invoiceDate: date('Y-m-d\TH:i:s\Z', strtotime($invoice->issueDate . ' ' . $invoice->issueTime)),
            totalAmount: (string)$this->calculateTotal($invoice),
            taxAmount: (string)$this->calculateVat($invoice),
        );
    }

    public function renderQrCode(string $tlvData, int $size = 200): string
    {
        return $this->qrService->render($tlvData, $size);
    }

    private function calculateTotal(InvoiceDTO $invoice): float
    {
        $total = 0;

        foreach ($invoice->lineItems as $item) {
            $subtotal = ($item['tax_exclusive_price'] ?? 0) * ($item['quantity'] ?? 0);
            $discounts = array_sum(array_column($item['discounts'] ?? [], 'amount'));
            $taxable = $subtotal - $discounts;
            $vat = $taxable * ($item['vat_percent'] ?? 0);
            $total += $taxable + $vat;
        }

        return round($total, 2);
    }

    private function calculateVat(InvoiceDTO $invoice): float
    {
        $vat = 0;

        foreach ($invoice->lineItems as $item) {
            $subtotal = ($item['tax_exclusive_price'] ?? 0) * ($item['quantity'] ?? 0);
            $discounts = array_sum(array_column($item['discounts'] ?? [], 'amount'));
            $taxable = $subtotal - $discounts;
            $vat += $taxable * ($item['vat_percent'] ?? 0);
        }

        return round($vat, 2);
    }
}
