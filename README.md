<p align="center">
    <img src="https://img.shields.io/badge/php-^8.1-8892BF.svg?style=for-the-badge&logo=php" alt="PHP Version">
    <img src="https://img.shields.io/badge/Laravel-9|10|11|12|13-FF2D20.svg?style=for-the-badge&logo=laravel" alt="Laravel Version">
    <img src="https://img.shields.io/badge/ZATCA-Phase_1_%2B_Phase_2-00A859.svg?style=for-the-badge" alt="ZATCA Phase 1 & 2">
    <img src="https://img.shields.io/badge/license-MIT-blue.svg?style=for-the-badge" alt="License">
    <img src="https://img.shields.io/github/actions/workflow/status/aghfatehi/laravel-zatca/laravel.yml?style=for-the-badge&label=Tests" alt="Tests">
    <img src="https://img.shields.io/packagist/v/aghfatehi/laravel-zatca.svg?style=for-the-badge" alt="Packagist">
    <img src="https://img.shields.io/packagist/dt/aghfatehi/laravel-zatca.svg?style=for-the-badge" alt="Downloads">
    <img src="https://img.shields.io/badge/Author-AL--AGHBARI%20Fatehi-blue.svg?style=for-the-badge" alt="Author">
</p>

<h1 align="center">Laravel ZATCA (Fatoora) Package</h1>
<h3 align="center">Saudi Arabian e-Invoicing Compliance — Phase 1 & Phase 2</h3>
<h3 align="center">المرحلة الأولى والثانية للفاتورة الإلكترونية السعودية لهيئة الزكاة والضريبة والجمارك</h3>
<h4 align="center">By <a href="https://github.com/aghfatehi">AL-AGHBARI Fatehi</a> &mdash; <strong>فتحي الأغبري</strong></h4>

<p align="center">
    <strong>ZATCA integration for Laravel — QR code generation, TLV encoding, invoice signing, clearance & reporting. دمج الفاتورة الإلكترونية مع لارافيل: المرحلة الأولى (QR) والمرحلة الثانية (التوقيع والإرسال) لهيئة الزكاة والضريبة والجمارك السعودية</strong>
</p>

---

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Version Matrix](#version-matrix)
- [Installation](#installation)
- [Configuration](#configuration)
- [Integration Scenarios](#integration-scenarios)
- [Phase 1 -- QR Code Generation](#phase-1--qr-code-generation-basic-compliance)
- [Phase 2 -- FATOORA API Integration](#phase-2--fatoora-api-integration-full-compliance)
- [QR Code Display on PDF / View](#qr-code-display-on-pdf--view)
- [API Routes](#api-routes)
- [Postman Collection](#postman-collection)
- [Offline Mode & Queue Sync](#offline-mode--queue-sync)
- [Events](#events)
- [Artisan Commands](#artisan-commands)
- [Testing](#testing)
- [Security & Logging](#security--logging)
- [Project Map](./PROJECT_MAP.md)
- [Support](#support)

---

## Overview

**laravel-zatca** is a production-grade Laravel package for integrating with the **ZATCA (Zakat, Tax and Customs Authority)** e-invoicing system — also known as **Fatoora** — in the Kingdom of Saudi Arabia.

The package covers both phases of the ZATCA e-invoicing mandate:

| Phase | Description | Status |
|-------|-------------|--------|
| **Phase 1** | Generate and display QR code on invoices (TLV Base64 format) | Production Ready |
| **Phase 2** | Full compliance: CSR, Certificate, Signing, Clearance & Reporting via FATOORA API | Production Ready |

### Flexible Integration

You can use this package in any of these modes:

1. **Phase 1 only** — Just generate QR codes for display on PDF/View (no API calls)
2. **Phase 2 only** — Full API integration (requires pre-existing Phase 1 QR or external QR generation)
3. **Both phases** — Full lifecycle from QR → Signing → Submission
4. **Offline → Online** — Generate QR codes offline, sync invoices via queue when online

### What is Required vs Optional

**For Phase 1 (QR generation only):**
| Step | Required? |
|------|-----------|
| Install package (`composer require`) | **Required** |
| Set `ZATCA_PHASE` and `ZATCA_VAT_*` in `.env` | **Required** |
| Call `Zatca::phase1()->generateQrCodeText()` in your controller | **Required** |
| Display QR in your Blade view | **Required** |
| Publish config / views | Optional |
| Install `endroid/qr-code` for PNG output | Optional |
| Use Model Trait for automatic QR generation | Optional |
| API Routes (`/zatca/onboard`, etc.) | Optional — not needed |
| Offline Mode & Queue Sync | Optional — not needed |
| Events & Logging | Optional — not needed |

**For Phase 2 (API integration):**
| Step | Required? |
|------|-----------|
| Everything from Phase 1 | **Required** (if using `both`) |
| Set `ZATCA_PHASE=phase_2` or `=both` | **Required** |
| Complete onboarding (keys + CSR + certificate) | **Required** |
| Set `ZATCA_CERTIFICATE`, `ZATCA_PRIVATE_KEY`, `ZATCA_SECRET` | **Required** |
| Call `Zatca::phase2()->signInvoice()` + `->submitInvoice()` | **Required** |
| Publish migrations for audit logging | Optional |
| Use Queue for async sync | Optional |
| API Routes (`/zatca/onboard`) | Optional — alternative to CLI |
| Postman Collection | Optional — testing tool |
| Events & custom listeners | Optional |

---

## Features

- **Phase 1**: TLV Base64 QR code (5 tags: Seller, VAT, Date, Total, Tax)
- **Phase 2**: UBL 2.1 XML invoice building & XAdES signing
- **Phase 2**: ECDSA secp256k1 key pair generation (OpenSSL)
- **Phase 2**: CSR generation for ZATCA compliance certificate
- **Phase 2**: Compliance check (Sandbox)
- **Phase 2**: Clearance & Reporting (Production)
- **cURL-based** HTTP client (no Guzzle dependency)
- **Queue support** for async invoice sync with retry logic
- **Offline mode** -- Generate signed XML locally, sync later
- **Artisan commands** for onboarding & syncing
- **Event-driven** architecture (InvoiceCleared, InvoiceReported, InvoiceFailed)
- **PSR-4 autoloading**, Service Provider auto-discovery
- **Logging** with PII masking, non-blocking design
- **No UI/frontend assumptions** -- Bring your own views
- **Configurable phases** via single `.env` variable

---

## External References

This package implements technical specifications for e-invoicing. Below are links to the relevant standards and portals for your own compliance verification.

| Resource | Link |
|----------|------|
| ZATCA Developer Portal (Sandbox) | [https://sandbox.zatca.gov.sa](https://sandbox.zatca.gov.sa) |
| ZATCA Production Portal | [https://zatca.gov.sa](https://zatca.gov.sa) |
| E-invoicing regulations (Saudi Arabia) | [https://zatca.gov.sa](https://zatca.gov.sa) |


> This package is built by implementing publicly available technical specifications. For official compliance requirements, always refer to ZATCA's documentation and consult with legal advisors.

---

## Version Matrix

| Component | Version |
|-----------|---------|
| **PHP** | `^8.1`, `^8.2`, `^8.3`, `^8.4` |
| **Laravel** | `^9.0`, `^10.0`, `^11.0`, `^12.0`, `^13.0` |
| **ZATCA API** | V2 (2024+) |
| **UBL Standard** | 2.1 |
| **Signature Algorithm** | ECDSA secp256k1 + SHA-256 |
| **XAdES** | EPES v1.3.2 |
| **QR Encoding** | TLV Base64 (GS1-compatible) |
| **OpenSSL** | Required (for key & CSR generation) |
| **cURL** | Required extension |

### Optional QR Dependencies

The package works **out of the box** without any extra packages. When you call `render()`, it generates an SVG QR code using a built-in Blade view.

If you need PNG output or advanced QR features, install one of:

| Package | Purpose | Notes |
|---------|---------|-------|
| `endroid/qr-code` | Renders QR as PNG or SVG (requires ext-gd for PNG) | `composer require endroid/qr-code` |
| `simplesoftwareio/simple-qrcode` | Alternative QR rendering (BaconQrCode wrapper) | `composer require simplesoftwareio/simple-qrcode` |

**How it works:** If one of these packages is installed, `render()` uses it automatically. If not, it falls back to the built-in SVG view. No configuration needed.

---



## Installation

```bash
composer require aghfatehi/laravel-zatca
```

**That's it for Phase 1** — QR codes work immediately. For Phase 2 you also need OpenSSL installed on your server and a ZATCA developer account.

### Publish Configuration

```bash
php artisan vendor:publish --tag=zatca-config
```

### Publish Migrations (Optional — for Phase 2 API & audit logging)

Publish and run the migrations only if you are using the optional Phase 2 API routes (onboarding, invoice clearance/reporting):

```bash
php artisan vendor:publish --tag=zatca-migrations
php artisan migrate
```

This creates two tables:

| Table | Purpose |
|---|---|
| `zatca_certificates` | Stores EGS certificate and private key after ZATCA onboarding (used for invoice signing) |
| `zatca_invoice_logs` | Logs every invoice submission request/response with `invoice_serial_number` for clearance & reporting audit trail |

You do **not** need these migrations if you only use Phase 1 (QR code generation).

### Publish Views (Optional — to customize QR fallback)

```bash
php artisan vendor:publish --tag=zatca-views
```

Copies `qr-code.blade.php` to `resources/views/vendor/zatca/` so you can customize the default SVG layout.

> **Note:** This view is only used as a fallback when `endroid/qr-code` is not installed. If you install `endroid/qr-code`, the view is ignored.

### Verify Installation

```bash
php artisan zatca:check
```

---

## Configuration

Set these in your `.env` file:

```env
# --- Phase Selection ---
ZATCA_PHASE=both                   # phase_1, phase_2, both

# --- Environment ---
ZATCA_ENVIRONMENT=sandbox          # sandbox | production
```

### Env Variables Reference

| Variable | Required | Description | Where to get it |
|----------|----------|-------------|-----------------|
| `ZATCA_PHASE` | Yes | Which phase to enable: `phase_1`, `phase_2`, or `both` | You choose |
| `ZATCA_ENVIRONMENT` | Yes | `sandbox` for testing, `production` for live | You choose |
| `ZATCA_EGS_UUID` | Phase 2 | Unique ID for your ERP/Government System | Generated by you (any UUID v4). Used to identify your system to ZATCA. |
| `ZATCA_VAT_NUMBER` | Yes | Your company VAT number (15 digits in Saudi Arabia) | Your company tax registration |
| `ZATCA_VAT_NAME` | Yes | Your company legal name as registered with ZATCA | Your company registration |
| `ZATCA_CRN_NUMBER` | Phase 2 | Commercial Registration Number | Your company commercial registry |
| `ZATCA_INDUSTRY` | Phase 2 | Business industry (e.g., Retail, Healthcare) | Your company profile |
| `ZATCA_CITY` | Phase 2 | City name (e.g., Riyadh, Jeddah) | Your business address |
| `ZATCA_CITY_SUBDIVISION` | Phase 2 | City district or suburb | Your business address |
| `ZATCA_STREET` | Phase 2 | Street name | Your business address |
| `ZATCA_BUILDING` | Phase 2 | Building number | Your business address |
| `ZATCA_PLOT_ID` | Phase 2 | Plot identification number | Your business address |
| `ZATCA_POSTAL_ZONE` | Phase 2 | Postal/ZIP code | Your business address |
| `ZATCA_BRANCH_NAME` | Phase 2 | Branch name (e.g., Main Branch) | Your business structure |
| `ZATCA_QUEUE_CONNECTION` | Optional | Queue driver for async sync (`sync`, `redis`, `database`) | Your Laravel queue config |
| `ZATCA_QUEUE_NAME` | Optional | Queue name for ZATCA jobs | You choose |
| `ZATCA_QUEUE_TRIES` | Optional | Max retry attempts on failure | You choose |
| `ZATCA_QUEUE_TIMEOUT` | Optional | Job timeout in seconds | You choose |
| `ZATCA_RETRY_DELAY_MINUTES` | Optional | Delay between retries in minutes | You choose |
| `ZATCA_API_MIDDLEWARE` | Optional | Middleware group for API routes (default: `api`) | You choose |
| `ZATCA_CERTIFICATE` | Phase 2 | Base64-encoded compliance certificate from ZATCA | **ZATCA Developer Portal** → after running `zatca:onboard` with OTP. The certificate is the `binarySecurityToken` returned by the compliance API. |
| `ZATCA_PRIVATE_KEY` | Phase 2 | Base64-encoded EC private key (secp256k1) | **Generated by you** via `zatca:onboard` or `Zatca::phase2()->generateKeysAndCsr()`. Store securely — this is your secret key for signing invoices. |
| `ZATCA_SECRET` | Phase 2 | Secret string returned by ZATCA during onboarding | **ZATCA Developer Portal** → returned alongside the certificate when you issue a compliance certificate with OTP. |

### How the onboarding flow works

```
1. You run:  php artisan zatca:onboard --otp=123456 --save
2. Package generates EC key pair (private_key + public_key)
3. Package creates a CSR (Certificate Signing Request)
4. Package sends CSR + OTP to ZATCA API
5. ZATCA returns:
   - binarySecurityToken → save as ZATCA_CERTIFICATE
   - secret              → save as ZATCA_SECRET
6. Your private_key      → save as ZATCA_PRIVATE_KEY
```

The OTP is obtained from the [ZATCA Developer Portal](https://sandbox.zatca.gov.sa) (sandbox) or ZATCA production portal.

### Full config reference

See [`config/zatca.php`](config/zatca.php) for all available options with documentation.

---

## Phase 1 -- QR Code Generation (Basic Compliance)

Phase 1 requires **no API calls**. It generates a TLV-encoded Base64 QR string containing:

| Tag | Field | Example |
|-----|-------|---------|
| 1 | Seller Name | `شركة التقنية` |
| 2 | VAT Number | `300000000000003` |
| 3 | Date/Time (ISO 8601) | `2024-01-01T12:00:00Z` |
| 4 | Invoice Total (SAR) | `115.00` |
| 5 | VAT Total (SAR) | `15.00` |

### Usage

```php
use Aghfatehi\Zatca\Facades\Zatca;

// Simple QR text generation
$qrText = Zatca::phase1()->generateQrCodeText(
    sellerName: 'شركة التقنية',
    vatNumber: '300000000000003',
    invoiceDate: '2024-01-01T12:00:00Z',
    totalAmount: '115.00',
    taxAmount: '15.00',
);

// Base64-encoded TLV string ready for embedding
echo $qrText;
```

### Using with Invoice DTO

```php
use Aghfatehi\Zatca\DTO\InvoiceDTO;

$invoice = InvoiceDTO::fromArray([
    'invoice_serial_number' => 'INV-001',
    'issue_date' => '2024-01-01',
    'issue_time' => '12:00:00',
    'line_items' => [
        [
            'id' => '1',
            'name' => 'Product A',
            'quantity' => 2,
            'tax_exclusive_price' => 100.00,
            'vat_percent' => 0.15,
        ],
    ],
]);

$egsUnit = [
    'vat_name' => 'شركة التقنية',
    'vat_number' => '300000000000003',
];

$qrText = Zatca::phase1()->generateQrCodeFromInvoice($invoice, $egsUnit);
```

---

## Phase 2 -- FATOORA API Integration (Full Compliance)

Phase 2 requires completing the ZATCA onboarding process to obtain a compliance certificate, then signing and submitting invoices.

### Step 1: Onboarding (One-time setup)

Generate EC key pair, CSR, and get compliance certificate from ZATCA:

```bash
php artisan zatca:onboard --otp=123456 --solution-name=ERP --save
```

Or programmatically:

```php
// Generate keys & CSR
$keys = Zatca::phase2()->generateKeysAndCsr($egsUnit, 'ERP');

// Issue compliance certificate with OTP from ZATCA portal
$result = Zatca::phase2()->issueComplianceCertificate($keys['csr'], $otp);

if ($result->success) {
    // Save these securely
    $certificate = $result->binarySecurityToken;
    $secret = $result->secret;
    $privateKey = $keys['private_key'];
    
    // Store in .env or database
    \Illuminate\Support\Facades\Env::set('ZATCA_CERTIFICATE', base64_encode($certificate));
    \Illuminate\Support\Facades\Env::set('ZATCA_SECRET', $secret);
    \Illuminate\Support\Facades\Env::set('ZATCA_PRIVATE_KEY', base64_encode($privateKey));
}
```

### Step 2: Sign & Submit Invoice

```php
// Build invoice data
$invoice = InvoiceDTO::fromArray([
    'invoice_serial_number' => 'EGS1-886431145-1',
    'invoice_counter_number' => 2,
    'issue_date' => '2024-01-01',
    'issue_time' => '14:40:40',
    'previous_invoice_hash' => '',
    'line_items' => [
        [
            'id' => '1',
            'name' => 'Product A',
            'quantity' => 10,
            'tax_exclusive_price' => 100.00,
            'vat_percent' => 0.15,
        ],
    ],
]);

$egsUnit = [
    'uuid' => '6f4d20e0-6bfe-4a80-9389-7dabe6620f12',
    'custom_id' => 'EGS1-886431145',
    'model' => 'Desktop',
    'vat_number' => '300000000000003',
    'vat_name' => 'شركة التقنية',
    'crn_number' => '454634645645654',
    'location' => [
        'city' => 'Riyadh',
        'city_subdivision' => 'West',
        'street' => 'King Fahd Road',
        'building' => '1234',
        'plot_identification' => '0000',
        'postal_zone' => '11564',
    ],
    'branch_name' => 'Main Branch',
    'branch_industry' => 'Retail',
];

// 1. Sign invoice (generates XML + hash + QR)
$signed = Zatca::phase2()->signInvoice(
    invoice: $invoice,
    egsUnit: $egsUnit,
    certificate: $certificate,
    privateKey: $privateKey,
);

// 2. Submit to ZATCA (auto-detects sandbox vs production)
$result = Zatca::phase2()->submitInvoice(
    signedInvoiceXml: $signed['signed_xml'],
    invoiceHash: $signed['invoice_hash'],
    certificate: $certificate,
    secret: $secret,
);

if ($result->success) {
    echo 'Invoice submitted successfully! Request ID: ' . $result->requestID;
}
```

### Using Queue for Async Sync

```php
use Aghfatehi\Zatca\Jobs\SyncInvoiceToZatcaJob;

SyncInvoiceToZatcaJob::dispatch(
    invoiceData: $invoice->toArray(),
    egsUnit: $egsUnit,
    certificate: $certificate,
    privateKey: $privateKey,
    secret: $secret,
);
```

---

## QR Code Display on PDF / View

### Method 1: Blade View (Direct Rendering)

**1. In the Controller — generate QR TLV:**

```php
<?php

namespace App\Http\Controllers;

use Aghfatehi\Zatca\Facades\Zatca;

class InvoiceController extends Controller
{
    public function show(Invoice $invoice)
    {
        $qrTlv = Zatca::phase1()->generateQrCodeFromInvoice(
            invoice: $invoice->toInvoiceDto(),
            egsUnit: [
                'vat_name' => config('zatca.egs.vat_name'),
                'vat_number' => config('zatca.egs.vat_number'),
            ],
        );

        return view('invoice.show', compact('invoice', 'qrTlv'));
    }
}
```

**2. In the Blade file — render the QR:**

```blade
{{-- resources/views/invoice/show.blade.php --}}
@extends('layouts.app')

@section('content')
    <div class="invoice">
        <h1>invoice No: {{ $invoice->number }}</h1>

        <table>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->price }}</td>
                </tr>
            @endforeach
        </table>

        {{-- QR Code output — SVG (no deps) or PNG (with endroid/qr-code) --}}
        <div class="qr-section" style="text-align: center; margin-top: 20px;">
            <img src="data:image/png;base64,{{ base64_encode(Zatca::qr()->render($qrTlv, 200)) }}"
                 alt="ZATCA QR Code"
                 style="width: 200px; height: 200px;">
        </div>
    </div>
@endsection
```

**How it works:** `render()` returns:
- **SVG** — if `endroid/qr-code` is NOT installed (default, no extra deps)
- **PNG binary** — if `endroid/qr-code` IS installed

Both work with `<img src="data:image/...;base64,...">`.

### Method 2: Using the Model Trait

Add the trait to your invoice model:

```php
use Aghfatehi\Zatca\Traits\HasZatcaQrCode;

class Invoice extends Model
{
    use HasZatcaQrCode;

    // Customize field names (optional)
    protected $zatcaSellerField = 'company_name';
    protected $zatcaVatField = 'vat_number';
    protected $zatcaDateField = 'invoice_date';
    protected $zatcaTotalField = 'total_amount';
    protected $zatcaTaxField = 'tax_amount';
}
```

Then in your view:

```blade
{{-- Automatically generates QR from model fields --}}
{!! $invoice->getZatcaQrCode(200) !!}
```

### Method 3: PDF Generation with barryvdh/laravel-dompdf

```php
use Barryvdh\DomPDF\Facade\Pdf;

$qrText = Zatca::phase1()->generateQrCodeText(
    sellerName: $invoice->company_name,
    vatNumber: $invoice->vat_number,
    invoiceDate: $invoice->invoice_date->format('Y-m-d\TH:i:s\Z'),
    totalAmount: (string)$invoice->total_amount,
    taxAmount: (string)$invoice->tax_amount,
);

// Generate QR as base64 image
$qrBase64 = base64_encode(Zatca::qr()->render($qrText, 150));

$pdf = Pdf::loadView('invoice.pdf', compact('invoice', 'qrBase64'));
return $pdf->download('invoice.pdf');
```

In `invoice/pdf.blade.php`:

```blade
<html>
<head>
    <style>
        .qr-code { position: fixed; bottom: 20px; right: 20px; width: 150px; }
    </style>
</head>
<body>
    <h1>{{ $invoice->invoice_serial_number }}</h1>
    <table>
        <tr><th>Item</th><th>Price</th><th>VAT</th></tr>
        @foreach($invoice->items as $item)
        <tr><td>{{ $item->name }}</td><td>{{ $item->price }}</td><td>{{ $item->vat }}</td></tr>
        @endforeach
    </table>
    <div class="qr-code">
        <img src="data:image/png;base64,{{ $qrBase64 }}" alt="ZATCA QR">
    </div>
</body>
</html>
```

### Method 4: PDF with mpdf (for ERP System or E-commerce)

```php
use Mpdf\Mpdf;

$mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
$qrText = Zatca::phase1()->generateQrCodeText(...);
$qrBase64 = base64_encode(Zatca::qr()->render($qrText, 150));

$html = '<div style="position: absolute; bottom: 10mm; right: 10mm;">
    <img src="@' . $qrBase64 . '" width="150" height="150"/>
</div>';

$mpdf->WriteHTML($html);
$mpdf->Output('invoice.pdf', 'D');
```

### Method 5: Advanced Output (Base64, Data URI, File)

```php
// Base64-encoded image (SVG or PNG depending on installed packages)
$base64 = Zatca::qr()->renderAsBase64($qrText, 200);

// Data URI ready for <img> tag
$dataUri = Zatca::qr()->renderAsDataUri($qrText, 200);

// Save directly to file (SVG or PNG)
Zatca::qr()->renderToFile($qrText, storage_path('app/public/qr/invoice.svg'), 200);
```

These methods work automatically whether or not `endroid/qr-code` is installed.

---

## API Routes (Optional)

The package registers HTTP API endpoints. **You do not need these for basic Phase 1 or Phase 2 usage** — they are an alternative to calling the package methods directly from PHP code or Artisan commands.

These routes are separate from the QR rendering methods above. By default they use the `api` middleware group — change it with `ZATCA_API_MIDDLEWARE` in your `.env`.

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/zatca/onboard` | Onboard via API — requires `otp` and optional `solution_name` |
| `POST` | `/zatca/invoice/sync` | Dispatch a sync job — requires `invoice_serial_number` |
| `GET` | `/zatca/status` | Returns current phase, environment, and enabled status |

> These routes are completely independent from the QR rendering methods above. You can use them with any HTTP client (Postman, cURL, your frontend app, etc.).

### Testing with cURL

Test the routes directly with cURL:

```bash
# Check package status
curl -X GET http://localhost:8000/zatca/status \
  -H "Accept: application/json"

# Onboard with OTP
curl -X POST http://localhost:8000/zatca/onboard \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"otp": "123456", "solution_name": "ERP"}'

# Dispatch invoice sync
curl -X POST http://localhost:8000/zatca/invoice/sync \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"invoice_serial_number": "INV-001"}'
```

---

## Postman Collection (ZATCA Official)

The official **ZATCA Fatoora API Postman collection** covers all ZATCA endpoints (onboarding, compliance, clearance, reporting):

1. Go to [ZATCA Developer Portal](https://sandbox.zatca.gov.sa)
2. Log in with your developer account
3. Navigate to **API Documentation** → **Postman Collection**
4. Download and import into Postman
5. Set environment variables (base URL, OTP, certificates, etc.)

This collection is maintained by ZATCA and contains all the endpoints that this package calls internally.

For the package's own optional routes (`/zatca/onboard`, `/zatca/invoice/sync`, `/zatca/status`), use the cURL examples above.

---

## Offline Mode & Queue Sync (Optional)

The package natively supports **offline invoice preparation** with **queue-based synchronization**. **You do not need this for basic Phase 1 or Phase 2** — it is only useful if you want to generate and sign invoices when offline, then sync them to ZATCA later.

### Offline Flow

```
┌─────────────────────────────────────────────────────────┐
│                    OFFLINE MODE                         │
│                                                         │
│  ERP System  ──►  Generate QR (Phase 1)                 │
│              ──►  Sign Invoice (Phase 2)                │
│              ──►  Save Signed XML Locally               │
│              ──►  Dispatch SyncInvoiceToZatcaJob        │
│                                                         │
│  When online ──►  Queue Worker picks up job             │
│              ──►  Submits to ZATCA API                  │
│              ──►  Fires InvoiceCleared/InvoiceReported  │
│              ──►  Logs result                           │
└─────────────────────────────────────────────────────────┘
```

### Configuration

```env
# Use database queue for persistence across restarts
ZATCA_QUEUE_CONNECTION=database
ZATCA_QUEUE_NAME=zatca

# Retry settings
ZATCA_QUEUE_TRIES=5
ZATCA_RETRY_DELAY_MINUTES=60
```

### Run the Queue Worker

```bash
php artisan queue:work --queue=zatca --tries=3 --delay=3600
```

### Sync Pending Invoices Manually

```bash
# Sync a specific invoice
php artisan zatca:sync --invoice=INV-001

# Sync all pending invoices
php artisan zatca:sync --all
```

---

## Data Flow

```
Phase 1 Flow:
  Invoice Data (5 tags) --> TLV Encoder --> Base64 --> QR Image

Phase 2 Flow:
  1. Generate EC Key Pair (secp256k1)
  2. Generate CSR
  3. Submit CSR + OTP --> ZATCA API --> Compliance Certificate
  4. Build UBL 2.1 XML Invoice
  5. Hash Invoice (SHA-256)
  6. Create Digital Signature (ECDSA)
  7. Generate TLV QR (9 tags)
  8. Embed XAdES Signature
  9. Submit to ZATCA:
       - Sandbox:    POST /compliance/invoices
       - Production: POST /invoices/clearance OR /reporting
  10. Handle Response --> Fire Events --> Log
```

---

## Events (Optional)

The package fires events that you can listen to in your application. **Not needed for basic usage** — only useful if you want to react to invoice submission results (e.g., update your database when an invoice is cleared).

| Event | Description | Payload |
|-------|-------------|---------|
| `InvoiceCleared` | Invoice successfully cleared (production) | `invoiceData`, `ComplianceResultDTO` |
| `InvoiceReported` | Invoice reported successfully (sandbox) | `invoiceData`, `ComplianceResultDTO` |
| `InvoiceComplianceChecked` | Compliance check completed | `invoiceData`, `ComplianceResultDTO` |
| `InvoiceFailed` | Invoice submission failed | `invoiceData`, `errorMessage` |

### Example Listener

```php
namespace App\Listeners;

use Aghfatehi\Zatca\Events\InvoiceCleared;

class UpdateInvoiceStatus
{
    public function handle(InvoiceCleared $event): void
    {
        $serial = $event->invoiceData['invoice_serial_number'];
        
        // Update your invoice status in DB
        Invoice::where('serial_number', $serial)
            ->update(['zatca_status' => 'cleared']);
    }
}
```

Register in `EventServiceProvider`:

```php
protected $listen = [
    \Aghfatehi\Zatca\Events\InvoiceCleared::class => [
        \App\Listeners\UpdateInvoiceStatus::class,
    ],
    \Aghfatehi\Zatca\Events\InvoiceFailed::class => [
        \App\Listeners\MarkInvoiceAsFailed::class,
    ],
];
```

---

## Artisan Commands

| Command | Required | Description |
|---------|----------|-------------|
| `php artisan zatca:onboard` | **Phase 2** | Interactive onboarding wizard (generates keys, CSR, gets certificate) |
| `php artisan zatca:check` | **Phase 1 & 2** | Check package readiness (OpenSSL, config, etc.). Run after install. |
| `php artisan zatca:sync` | Optional | Sync invoices to ZATCA via queue (single or all pending) |

---

## Testing

```bash
composer test
```

Or with PHPUnit directly:

```bash
vendor/bin/phpunit
```

## Security & Logging (Optional)

Logging is enabled by default but **not required** for basic Phase 1 or Phase 2. It is useful for audit trails and debugging.

### Logging Design

- **Levels**: `info`, `warning`, `error` only
- **Non-blocking**: Uses Laravel's async-safe log channel
- **PII Masking**: Automatically masks `otp`, `secret`, `password`, `private_key`, `csr`
- **Truncation**: Values exceeding 500 chars are truncated
- **Audit trail**: Optional `zatca_invoice_logs` database table for compliance tracking

### Security Best Practices

1. **Never** store private keys or secrets in code
2. Use `.env` variables or a secrets manager for credentials
3. Restrict queue worker access to authorized personnel
4. Enable PII masking in production (`ZATCA_LOG_MASK_PII=true`)
5. Use HTTPS for all ZATCA API communication (enforced by cURL)
6. Rotate OTP and secrets according to ZATCA guidelines

---

## Integration Scenarios

### Scenario 1: New Project — Both Phases

```env
ZATCA_PHASE=both
ZATCA_ENVIRONMENT=sandbox
```

Start with Phase 1 QR codes immediately, then add Phase 2 when ready.

### Scenario 2: Phase 1 Only (Existing System)

```env
ZATCA_PHASE=phase_1
```

Just generate QR codes. No API keys needed.

### Scenario 3: Phase 2 Only (Already have Phase 1)

```env
ZATCA_PHASE=phase_2
```

You already generate QR codes elsewhere (or via another package). This package handles the API integration.

### Scenario 4: External API Integration

If your ERP exposes an API, you can use the package's internal services directly:

```php
use Aghfatehi\Zatca\Services\Phase2Service;

class YourController
{
    public function sync(Request $request, Phase2Service $phase2)
    {
        $invoice = InvoiceDTO::fromArray($request->all());
        $result = $phase2->signInvoice($invoice, ...);
        // ...
    }
}
```

---

## Support

- **Issues**: [github.com/aghfatehi/laravel-zatca/issues](https://github.com/aghfatehi/laravel-zatca/issues)
- **Source**: [github.com/aghfatehi/laravel-zatca](https://github.com/aghfatehi/laravel-zatca)
- **ZATCA Portal**: [sandbox.zatca.gov.sa](https://sandbox.zatca.gov.sa)


