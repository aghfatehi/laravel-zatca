<?php

namespace Aghfatehi\Zatca\Services;

use Aghfatehi\Zatca\Contracts\QrCodeGeneratorInterface;

class QrCodeService implements QrCodeGeneratorInterface
{
    public function generateTlv(array $tags): string
    {
        $tlv = '';

        foreach ($tags as $index => $value) {
            $tag = $index + 1;
            $value = (string) $value;
            $length = strlen($value);
            $tlv .= $this->toHex($tag) . $this->toHex($length) . $value;
        }

        return base64_encode($tlv);
    }

    public function render(string $tlvData, int $size = 200): string
    {
        if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
            return \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                ->size($size)
                ->generate($tlvData);
        }

        if (class_exists(\Endroid\QrCode\QrCode::class)) {
            return $this->renderWithEndroid($tlvData, $size);
        }

        return $this->renderHtmlSvg($tlvData, $size);
    }

    public function renderWithEndroid(string $tlvData, int $size = 200): string
    {
        $qrCode = \Endroid\QrCode\QrCode::create($tlvData)
            ->setSize($size)
            ->setMargin(10)
            ->setErrorCorrectionLevel(\Endroid\QrCode\ErrorCorrectionLevel::High);

        $writer = new \Endroid\QrCode\Writer\PngWriter();
        $result = $writer->write($qrCode);

        return $result->getString();
    }

    public function renderHtmlSvg(string $tlvData, int $size = 200): string
    {
        $generator = new SvgQrGenerator();

        return $generator->generate($tlvData, $size);
    }

    public function renderAsBase64(string $tlvData, int $size = 200): string
    {
        return base64_encode($this->render($tlvData, $size));
    }

    public function renderAsDataUri(string $tlvData, int $size = 200): string
    {
        $output = $this->render($tlvData, $size);

        if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
            return 'data:image/svg+xml;base64,' . base64_encode($output);
        }

        if (class_exists(\Endroid\QrCode\QrCode::class)) {
            return 'data:image/png;base64,' . base64_encode($output);
        }

        return 'data:image/svg+xml;base64,' . base64_encode($output);
    }

    public function renderToFile(string $tlvData, string $path, int $size = 200): void
    {
        $output = $this->render($tlvData, $size);
        file_put_contents($path, $output);
    }

    private function toHex(int $value): string
    {
        return pack('H*', sprintf('%02X', $value));
    }

    public function generatePhase1Qr(
        string $sellerName,
        string $vatNumber,
        string $invoiceDate,
        string $totalAmount,
        string $taxAmount,
    ): string {
        return $this->generateTlv([
            $sellerName,
            $vatNumber,
            $invoiceDate,
            $totalAmount,
            $taxAmount,
        ]);
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
        return $this->generateTlv([
            $sellerName,
            $vatNumber,
            $invoiceDate,
            $totalAmount,
            $taxAmount,
            $invoiceHash,
            $digitalSignature,
            $publicKey,
            $certificateSignature,
        ]);
    }
}
