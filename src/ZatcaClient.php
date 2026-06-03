<?php

namespace Aghfatehi\Zatca;

use Aghfatehi\Zatca\DTO\InvoiceDTO;
use Aghfatehi\Zatca\Enums\Environment;
use Aghfatehi\Zatca\Enums\ZatcaPhase;
use Aghfatehi\Zatca\Services\Phase1Service;
use Aghfatehi\Zatca\Services\Phase2Service;
use Aghfatehi\Zatca\Services\QrCodeService;
use Aghfatehi\Zatca\Services\ZatcaService;

class ZatcaClient
{
    public function __construct(
        private ZatcaService $service,
        private Phase1Service $phase1,
        private Phase2Service $phase2,
        private QrCodeService $qr,
    ) {}

    public function service(): ZatcaService
    {
        return $this->service;
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
        return $this->qr;
    }

    public function isPhase1Enabled(): bool
    {
        return $this->service->isPhase1Enabled();
    }

    public function isPhase2Enabled(): bool
    {
        return $this->service->isPhase2Enabled();
    }

    public function phase(): ZatcaPhase
    {
        return $this->service->phase();
    }

    public function environment(): Environment
    {
        return $this->service->environment();
    }

    public function generateQrForInvoice(InvoiceDTO $invoice, array $egsUnit): string
    {
        return $this->service->generateInvoiceQr($invoice, $egsUnit);
    }
}
