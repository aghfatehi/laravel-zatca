<?php

namespace Aghfatehi\Zatca\Contracts;

use Aghfatehi\Zatca\DTO\InvoiceDTO;

interface InvoiceSignerInterface
{
    public function sign(InvoiceDTO $invoice, array $egsUnit, string $certificate, string $privateKey): array;
}
