{{-- QR code renderer: auto-detects the best available library --}}
@php
    $qrData = $qrData ?? '';
    $size = $size ?? 200;

    if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
        echo \SimpleSoftwareIO\QrCode\Facades\QrCode::size($size)->generate($qrData);
    } elseif (class_exists(\Endroid\QrCode\QrCode::class)) {
        $qr = \Endroid\QrCode\QrCode::create($qrData)
            ->setSize($size)
            ->setMargin(4)
            ->setErrorCorrectionLevel(\Endroid\QrCode\ErrorCorrectionLevel::High);
        $writer = new \Endroid\QrCode\Writer\SvgWriter();
        echo $writer->write($qr)->getString();
    } else {
        echo app(\Aghfatehi\Zatca\Services\SvgQrGenerator::class)->generate($qrData, $size);
    }
@endphp