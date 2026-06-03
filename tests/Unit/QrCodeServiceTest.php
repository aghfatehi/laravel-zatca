<?php

namespace Aghfatehi\Zatca\Tests\Unit;

use Aghfatehi\Zatca\Services\QrCodeService;
use Aghfatehi\Zatca\Tests\TestCase;

class QrCodeServiceTest extends TestCase
{
    private QrCodeService $service;

    protected function setUp(): void
    {
        $this->service = new QrCodeService();
    }

    public function test_generates_phase1_tlv(): void
    {
        $tlv = $this->service->generatePhase1Qr(
            sellerName: 'Test Company',
            vatNumber: '300000000000003',
            invoiceDate: '2024-01-01T12:00:00Z',
            totalAmount: '115.00',
            taxAmount: '15.00',
        );

        $this->assertNotEmpty($tlv);
        $this->assertEquals(base64_encode(base64_decode($tlv)), $tlv);
    }

    public function test_generates_phase2_tlv(): void
    {
        $tlv = $this->service->generatePhase2Qr(
            sellerName: 'Test Company',
            vatNumber: '300000000000003',
            invoiceDate: '2024-01-01T12:00:00Z',
            totalAmount: '115.00',
            taxAmount: '15.00',
            invoiceHash: base64_encode('test_hash'),
            digitalSignature: base64_encode('test_sig'),
            publicKey: base64_encode('test_key'),
            certificateSignature: base64_encode('test_cert_sig'),
        );

        $this->assertNotEmpty($tlv);
    }

    public function test_renders_svg_fallback(): void
    {
        $output = $this->service->render('test-data', 200);

        $this->assertStringContainsString('svg', $output);
    }
}
