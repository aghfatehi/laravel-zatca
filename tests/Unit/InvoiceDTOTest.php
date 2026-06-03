<?php

namespace Aghfatehi\Zatca\Tests\Unit;

use Aghfatehi\Zatca\DTO\InvoiceDTO;
use PHPUnit\Framework\TestCase;

class InvoiceDTOTest extends TestCase
{
    public function test_creates_from_array(): void
    {
        $data = [
            'invoice_serial_number' => 'TEST-001',
            'invoice_counter_number' => 1,
            'issue_date' => '2024-01-01',
            'issue_time' => '12:00:00',
            'currency' => 'SAR',
            'line_items' => [
                [
                    'id' => '1',
                    'name' => 'Test Item',
                    'quantity' => 2,
                    'tax_exclusive_price' => 50.00,
                    'vat_percent' => 0.15,
                ],
            ],
        ];

        $dto = InvoiceDTO::fromArray($data);

        $this->assertEquals('TEST-001', $dto->invoiceSerialNumber);
        $this->assertEquals(1, $dto->invoiceCounterNumber);
        $this->assertCount(1, $dto->lineItems);
    }

    public function test_uses_default_values(): void
    {
        $dto = InvoiceDTO::fromArray([]);

        $this->assertNotEmpty($dto->issueDate);
        $this->assertNotEmpty($dto->issueTime);
        $this->assertEquals('SAR', $dto->currency);
        $this->assertEmpty($dto->lineItems);
    }
}
