<?php

namespace Aghfatehi\Zatca\DTO;

class InvoiceDTO
{
    public function __construct(
        public readonly string  $invoiceSerialNumber,
        public readonly int     $invoiceCounterNumber,
        public readonly string  $issueDate,
        public readonly string  $issueTime,
        public readonly string  $currency = 'SAR',
        public readonly string  $previousInvoiceHash = '',
        public readonly string  $invoiceType = 'INVOICE',
        public readonly ?string $customerName = null,
        public readonly ?string $customerVatNumber = null,
        public readonly array   $lineItems = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            invoiceSerialNumber: $data['invoice_serial_number'] ?? '',
            invoiceCounterNumber: $data['invoice_counter_number'] ?? 1,
            issueDate: $data['issue_date'] ?? date('Y-m-d'),
            issueTime: $data['issue_time'] ?? date('H:i:s'),
            currency: $data['currency'] ?? 'SAR',
            previousInvoiceHash: $data['previous_invoice_hash'] ?? '',
            invoiceType: $data['invoice_type'] ?? 'INVOICE',
            customerName: $data['customer_name'] ?? null,
            customerVatNumber: $data['customer_vat_number'] ?? null,
            lineItems: $data['line_items'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'invoice_serial_number' => $this->invoiceSerialNumber,
            'invoice_counter_number' => $this->invoiceCounterNumber,
            'issue_date' => $this->issueDate,
            'issue_time' => $this->issueTime,
            'currency' => $this->currency,
            'previous_invoice_hash' => $this->previousInvoiceHash,
            'invoice_type' => $this->invoiceType,
            'customer_name' => $this->customerName,
            'customer_vat_number' => $this->customerVatNumber,
            'line_items' => $this->lineItems,
        ];
    }
}
