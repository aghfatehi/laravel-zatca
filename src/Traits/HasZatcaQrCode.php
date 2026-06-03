<?php

namespace Aghfatehi\Zatca\Traits;

use Aghfatehi\Zatca\Facades\Zatca;

trait HasZatcaQrCode
{
    public function getZatcaQrCode(int $size = 200): string
    {
        $sellerName = $this->{$this->zatcaSellerNameField() ?? 'seller_name'} ?? '';
        $vatNumber = $this->{$this->zatcaVatNumberField() ?? 'vat_number'} ?? '';
        $invoiceDate = $this->{$this->zatcaDateField() ?? 'created_at'} ?? now();
        $totalAmount = (string)($this->{$this->zatcaTotalField() ?? 'total'} ?? 0);
        $taxAmount = (string)($this->{$this->zatcaTaxField() ?? 'tax'} ?? 0);

        if ($invoiceDate instanceof \Carbon\Carbon || $invoiceDate instanceof \DateTime) {
            $invoiceDate = $invoiceDate->format('Y-m-d\TH:i:s\Z');
        } elseif (is_string($invoiceDate)) {
            $invoiceDate = date('Y-m-d\TH:i:s\Z', strtotime($invoiceDate));
        }

        $tlv = Zatca::phase1()->generateQrCodeText(
            sellerName: $sellerName,
            vatNumber: $vatNumber,
            invoiceDate: $invoiceDate,
            totalAmount: $totalAmount,
            taxAmount: $taxAmount,
        );

        return Zatca::qr()->render($tlv, $size);
    }

    protected function zatcaSellerNameField(): ?string
    {
        return property_exists($this, 'zatcaSellerField') ? $this->zatcaSellerField : null;
    }

    protected function zatcaVatNumberField(): ?string
    {
        return property_exists($this, 'zatcaVatField') ? $this->zatcaVatField : null;
    }

    protected function zatcaDateField(): ?string
    {
        return property_exists($this, 'zatcaDateField') ? $this->zatcaDateField : null;
    }

    protected function zatcaTotalField(): ?string
    {
        return property_exists($this, 'zatcaTotalField') ? $this->zatcaTotalField : null;
    }

    protected function zatcaTaxField(): ?string
    {
        return property_exists($this, 'zatcaTaxField') ? $this->zatcaTaxField : null;
    }
}
