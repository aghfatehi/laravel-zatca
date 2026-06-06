{{--
    QR Code Renderer
    ────────────────
    Requires one of these packages for ZATCA-compatible output:
      - composer require simplesoftwareio/simple-qrcode  (recommended)
      - composer require endroid/qr-code
    
    The built-in SvgQrGenerator is a visual-only fallback and is NOT
    compatible with the official ZATCA (Fatoora) app.
--}}
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
        $fallbackSvg = app(\Aghfatehi\Zatca\Services\SvgQrGenerator::class)->generate($qrData, $size);
        $fallbackSvg = str_replace(
            '</svg>',
            '<text x="'.($size/2).'" y="'.($size-5).'" text-anchor="middle" font-size="8" fill="red">⚠ Install simple-qrcode or endroid/qr-code</text></svg>',
            $fallbackSvg
        );
        echo $fallbackSvg;
    }
@endphp