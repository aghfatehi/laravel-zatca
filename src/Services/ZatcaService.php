<?php

namespace Aghfatehi\Zatca\Services;

use Aghfatehi\Zatca\DTO\InvoiceDTO;
use Aghfatehi\Zatca\Enums\Environment;
use Aghfatehi\Zatca\Enums\ZatcaPhase;
use Aghfatehi\Zatca\Logging\ZatcaLogger;

class ZatcaService
{
    private ZatcaPhase $activePhase;
    private Environment $environment;

    public function __construct(
        private Phase1Service  $phase1,
        private Phase2Service  $phase2,
        private QrCodeService  $qrService,
        private ZatcaLogger    $logger,
    ) {
        $this->activePhase = ZatcaPhase::tryFrom(config('zatca.phase', 'both')) ?? ZatcaPhase::Both;
        $this->environment = Environment::tryFrom(config('zatca.environment', 'sandbox')) ?? Environment::Sandbox;
    }

    public function phase(): ZatcaPhase
    {
        return $this->activePhase;
    }

    public function environment(): Environment
    {
        return $this->environment;
    }

    public function isPhase1Enabled(): bool
    {
        return $this->activePhase === ZatcaPhase::Phase1 || $this->activePhase === ZatcaPhase::Both;
    }

    public function isPhase2Enabled(): bool
    {
        return $this->activePhase === ZatcaPhase::Phase2 || $this->activePhase === ZatcaPhase::Both;
    }

    public function phase1(): Phase1Service
    {
        return $this->phase1;
    }

    public function phase2(): Phase2Service
    {
        return $this->phase2;
    }

    public function qr(): QrCodeService
    {
        return $this->qrService;
    }

    public function generateInvoiceQr(InvoiceDTO $invoice, array $egsUnit): string
    {
        if ($this->isPhase1Enabled()) {
            return $this->phase1->generateQrCodeFromInvoice($invoice, $egsUnit);
        }

        if ($this->isPhase2Enabled()) {
            $this->logger->warning('Phase 2 QR requires signed invoice. Use phase2().signInvoice() instead.');
        }

        return $this->phase1->generateQrCodeFromInvoice($invoice, $egsUnit);
    }

    public function generatePhase2Qr(
        string $sellerName,
        string $vatNumber,
        string $invoiceDate,
        string $totalAmount,
        string $taxAmount,
        string $invoiceHash,
        string $digitalSignature,
        string $publicKey,
        string $certificateSignature,
    ): string {
        return $this->qrService->generatePhase2Qr(
            sellerName: $sellerName,
            vatNumber: $vatNumber,
            invoiceDate: $invoiceDate,
            totalAmount: $totalAmount,
            taxAmount: $taxAmount,
            invoiceHash: $invoiceHash,
            digitalSignature: $digitalSignature,
            publicKey: $publicKey,
            certificateSignature: $certificateSignature,
        );
    }
}
