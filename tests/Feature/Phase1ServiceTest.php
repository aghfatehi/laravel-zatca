<?php

namespace Aghfatehi\Zatca\Tests\Feature;

use Aghfatehi\Zatca\DTO\InvoiceDTO;
use Aghfatehi\Zatca\Services\Phase1Service;
use Aghfatehi\Zatca\Services\QrCodeService;
use PHPUnit\Framework\TestCase;

class Phase1ServiceTest extends TestCase
{
    private Phase1Service $service;

    protected function setUp(): void
    {
        $this->service = new Phase1Service(new QrCodeService());
    }

    public function test_generates_qr_from_invoice(): void
    {
        $invoice = InvoiceDTO::fromArray([
            'invoice_serial_number' => 'TEST-001',
            'issue_date' => '2024-01-01',
            'issue_time' => '12:00:00',
            'line_items' => [
                [
                    'id' => '1',
                    'name' => 'Item',
                    'quantity' => 1,
                    'tax_exclusive_price' => 100.00,
                    'vat_percent' => 0.15,
                ],
            ],
        ]);

        $egsUnit = [
            'vat_name' => 'Test Company',
            'vat_number' => '300000000000003',
        ];

        $qr = $this->service->generateQrCodeFromInvoice($invoice, $egsUnit);

        $this->assertNotEmpty($qr);
    }
}
