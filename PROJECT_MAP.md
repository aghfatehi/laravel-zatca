# PROJECT MAP — Laravel ZATCA Package

## TECH_STACK

| Component | Version | Notes |
|-----------|---------|-------|
| **PHP** | `^8.1`, `^8.2`, `^8.3`, `^8.4` | Enums, readonly properties, match expressions |
| **Laravel** | `^9.0`, `^10.0`, `^11.0`, `^12.0`, `^13.0` | Service provider auto-discovery, queue, events |
| **ZATCA API** | V2 | FATOORA platform (sandbox & production) |
| **UBL** | 2.1 | XML invoice schema (urn:oasis:names:specification:ubl:schema:xsd:Invoice-2) |
| **XAdES** | EPES v1.3.2 | Enveloped digital signature |
| **EC Curve** | secp256k1 | Elliptic curve used by ZATCA |
| **Hash** | SHA-256 | Invoice hashing & certificate digest |
| **QR** | TLV Base64 | Tag-Length-Value encoding |

### Dependencies

| Package | Required | Purpose |
|---------|----------|---------|
| `illuminate/support` | ✅ Yes | Laravel service provider, config, facade |
| `illuminate/console` | ✅ Yes | Artisan commands |
| `ext-openssl` | ✅ Yes | EC key generation, CSR, signing |
| `ext-curl` | ✅ Yes | HTTP client for ZATCA API |
| `ext-json` | ✅ Yes | API request/response parsing |
| `ext-dom` | ✅ Yes | XML manipulation |
| `ext-mbstring` | ✅ Yes | Multi-byte string handling |
| `endroid/qr-code` | ⬜ Optional | QR image rendering (PNG/SVG) |
| `simplesoftwareio/simple-qrcode` | ⬜ Optional | Alternative QR rendering |

## SYSTEM_FLOW

### Phase 1 — QR Code Generation Flow

```
┌──────────────┐     ┌──────────────────┐     ┌─────────────────┐
│  Invoice     │────►│  TLV Encoder     │────►│  Base64 Output  │
│  Data        │     │  (5 Tags)        │     │  (QR String)    │
│  - Seller    │     │                  │     │                 │
│  - VAT #     │     │  Tag 1: Seller   │     │  Display in:    │
│  - Date/Time │     │  Tag 2: VAT #    │     │  - Blade View   │
│  - Total     │     │  Tag 3: DateTime │     │  - PDF          │
│  - Tax       │     │  Tag 4: Total    │     │  - Mobile App   │
└──────────────┘     │  Tag 5: Tax      │     └─────────────────┘
                     └──────────────────┘
```

### Phase 2 — Full Compliance Flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         ONBOARDING (ONE-TIME)                           │
│                                                                         │
│  Generate EC Key Pair (secp256k1)                                       │
│         │                                                               │
│  Generate CSR (OpenSSL)                                                 │
│         │                                                               │
│  Submit CSR + OTP ───► POST /compliance ───► Compliance Certificate    │
│                                                                         │
│  Store: Private Key, Certificate, Secret                                │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                      INVOICE LIFECYCLE                                  │
│                                                                         │
│  Build UBL 2.1 XML Invoice                                              │
│         │                                                               │
│  Compute Pure Invoice Hash (SHA-256)                                    │
│         │                                                               │
│  Create Digital Signature (ECDSA-SHA256)                                │
│         │                                                               │
│  Generate TLV QR (9 Tags)                                               │
│         │                                                               │
│  Embed XAdES Signature + QR in XML                                      │
│         │                                                               │
│  Submit to ZATCA:                                                       │
│         │                                                               │
│  ┌──────┴──────┐                                                       │
│  │  Sandbox    │                  │  Production                         │
│  │             │                  │                                     │
│  │ POST /compl │                  │ POST /clearance (standard)          │
│  │ iance/inv   │                  │ POST /reporting (simplified)        │
│  │ oices       │                  │                                     │
│  └──────┬──────┘                  └──────────┬──────────────────────────┘
│         │                                    │                           │
│         ▼                                    ▼                           │
│  ┌──────────────┐                  ┌──────────────────┐                 │
│  │  Response    │                  │  Response        │                 │
│  │  - Status    │                  │  - Cleared       │                 │
│  │  - Warnings  │                  │  - Reported      │                 │
│  │  - Errors    │                  │  - Errors        │                 │
│  └──────────────┘                  └──────────────────┘                 │
│         │                                    │                           │
│         ▼                                    ▼                           │
│  Fire Event ───► InvoiceComplianceChecked    InvoiceCleared/Reported    │
│                                                                         │
│  Log to Database (optional)                                             │
│                                                                         │
│  Update Invoice Status in ERP                                           │
└─────────────────────────────────────────────────────────────────────────┘
```

### Invoice Signing Detail

```
UBL 2.1 XML Invoice
│
├── ext:UBLExtensions (XAdES Signature)
│   ├── ds:SignedInfo
│   │   ├── Reference → Invoice Hash
│   │   └── Reference → Signed Properties Hash
│   ├── ds:SignatureValue (ECDSA)
│   └── ds:KeyInfo (X509 Certificate)
│
├── cbc:ID (Invoice Serial Number)
├── cbc:UUID (EGS UUID)
├── cbc:IssueDate / cbc:IssueTime
├── cbc:InvoiceTypeCode (388/381/383)
├── cac:AdditionalDocumentReference (ICV, PIH, QR)
├── cac:Signature (Reference)
├── cac:AccountingSupplierParty (Seller)
├── cac:AccountingCustomerParty (Buyer)
├── cac:TaxTotal (VAT Summary)
├── cac:LegalMonetaryTotal
└── cac:InvoiceLine (Line Items)
```

### Phase 1 vs Phase 2 Separation

| Aspect | Phase 1 | Phase 2 |
|--------|---------|---------|
| **QR Tags** | 5 (Seller, VAT, Date, Total, Tax) | 9 (+ Invoice Hash, Signature, Public Key, Cert Signature) |
| **API Calls** | None | Required (compliance, clearance, reporting) |
| **Certificate** | Not required | Required (from onboarding) |
| **Key Pair** | Not required | EC secp256k1 required |
| **XML** | Not required | UBL 2.1 required |
| **Signing** | None | XAdES enveloped signature |
| **Queue** | Not needed | Supported for async sync |
| **Cost** | Free | API usage (no additional cost from ZATCA) |
| **Setup Time** | Minutes | Hours (onboarding + integration) |

## ARCHITECTURE
## 🏗️ Architecture

```
src/
├── Commands/          # Artisan commands (onboard, sync, check)
├── Contracts/         # Interfaces (loose coupling)
├── DTO/               # Data Transfer Objects
├── Enums/             # PHP 8.1 enums (phase,
environment, status)
├── Events/            # Domain events
├── Exceptions/        # Custom exceptions
├── Facades/           # Laravel facade
├── Jobs/              # Queue jobs (async sync)
├── Listeners/         # Event listeners
├── Logging/           # PII-safe async logger
├── Models/            # Eloquent models (certificates, logs)
├── Services/          # Core business logic
│   ├── ApiClient.php          # cURL-based ZATCA HTTP client
│   ├── CertificateService.php # EC key & CSR generation
│   ├── InvoiceSignerService.php # UBL XML builder & signer
│   ├── Phase1Service.php      # Phase 1: QR code generation
│   ├── Phase2Service.php      # Phase 2: Compliance & clearance
│   ├── QrCodeService.php      # TLV encoding & rendering
│   └── ZatcaService.php       # Facade orchestration
├── Traits/            # Reusable model traits
├── ZatcaClient.php             # Main entry point
├── ZatcaServiceProvider.php    # Service provider with DI
├── config/zatca.php   # Configuration file
├── database/migrations/ # Database migrations
├── resources/views/   # Blade templates
└── routes/api.php     # API routes
```

### Package Structure

```
laravel-zatca/
├── .github/
│   └── workflows/
│       ├── laravel.yml          # PHPUnit tests matrix
│       └── php.yml              # Syntax check
├── config/
│   └── zatca.php                # Package configuration
├── database/
│   └── migrations/
│       ├── 2024_01_01_000001_create_zatca_certificates_table.php
│       └── 2024_01_01_000002_create_zatca_invoice_logs_table.php
├── resources/
│   └── views/
│       └── qr-code.blade.php    # Fallback QR view
├── routes/
│   └── api.php                  # API routes
├── src/
│   ├── Commands/
│   │   ├── ZatcaOnboardCommand.php   # php artisan zatca:onboard
│   │   ├── ZatcaSyncCommand.php      # php artisan zatca:sync
│   │   └── ZatcaCheckCommand.php     # php artisan zatca:check
│   ├── Contracts/
│   │   ├── InvoiceSignerInterface.php
│   │   ├── QrCodeGeneratorInterface.php
│   │   └── ZatcaClientInterface.php
│   ├── DTO/
│   │   ├── ComplianceResultDTO.php
│   │   ├── EgsUnitDTO.php
│   │   ├── InvoiceDTO.php
│   │   └── LineItemDTO.php
│   ├── Enums/
│   │   ├── Environment.php
│   │   ├── InvoiceStatus.php
│   │   ├── InvoiceType.php
│   │   ├── LogLevel.php
│   │   └── ZatcaPhase.php
│   ├── Events/
│   │   ├── InvoiceCleared.php
│   │   ├── InvoiceComplianceChecked.php
│   │   ├── InvoiceFailed.php
│   │   └── InvoiceReported.php
│   ├── Exceptions/
│   │   ├── CertificateException.php
│   │   ├── ComplianceException.php
│   │   └── ZatcaException.php
│   ├── Facades/
│   │   └── Zatca.php
│   ├── Jobs/
│   │   └── SyncInvoiceToZatcaJob.php
│   ├── Listeners/
│   │   └── LogZatcaEvent.php
│   ├── Logging/
│   │   └── ZatcaLogger.php
│   ├── Models/
│   │   ├── ZatcaCertificate.php
│   │   └── ZatcaInvoiceLog.php
│   ├── Services/
│   │   ├── ApiClient.php              # cURL HTTP client
│   │   ├── CertificateService.php     # EC keys & CSR
│   │   ├── InvoiceSignerService.php   # UBL XML & XAdES
│   │   ├── Phase1Service.php          # Phase 1 QR
│   │   ├── Phase2Service.php          # Phase 2 API
│   │   ├── QrCodeService.php          # TLV encoder
│   │   └── ZatcaService.php           # Orchestrator
│   ├── Traits/
│   │   └── HasZatcaQrCode.php
│   ├── ZatcaClient.php                # Public API
│   └── ZatcaServiceProvider.php       # Service Provider
├── tests/
│   ├── Feature/
│   │   └── Phase1ServiceTest.php
│   ├── Unit/
│   │   ├── InvoiceDTOTest.php
│   │   └── QrCodeServiceTest.php
│   └── TestCase.php
├── composer.json
├── README.md
└── PROJECT_MAP.md
```

### Module Responsibilities

| Module | Responsibility |
|--------|---------------|
| **Services/ApiClient** | cURL-based HTTP requests to ZATCA API with auth headers |
| **Services/CertificateService** | EC key generation, CSR creation, certificate parsing |
| **Services/InvoiceSignerService** | UBL 2.1 XML building, hashing, signing, QR embedding |
| **Services/Phase1Service** | Simplified QR generation (5 tags, no API) |
| **Services/Phase2Service** | Full lifecycle: keys → CSR → cert → sign → submit |
| **Services/QrCodeService** | TLV encoding, Base64, rendering (SVG/PNG/endroid) |
| **Logging/ZatcaLogger** | Non-blocking PII-safe logging |
| **Jobs/SyncInvoiceToZatcaJob** | Queue job for async invoice sync |
| **Commands/ZatcaOnboardCommand** | Interactive onboarding wizard |
| **Models/ZatcaCertificate** | Certificate persistence |
| **Models/ZatcaInvoiceLog** | Audit trail persistence |

### Dependency Injection Flow

```
ZatcaClient (facade accessor 'zatca')
    │
    ├── ZatcaService (orchestrator)
    │   ├── Phase1Service
    │   │   └── QrCodeService
    │   └── Phase2Service
    │       ├── ApiClient
    │       │   └── ZatcaLogger
    │       ├── CertificateService
    │       │   └── ZatcaLogger
    │       └── InvoiceSignerService
    │           ├── QrCodeService
    │           ├── CertificateService
    │           └── ZatcaLogger
    └── ZatcaLogger
```

## ORPHANS & PENDING

### Missing APIs (ZATCA)
| Endpoint | Status | Notes |
|----------|--------|-------|
| `POST /compliance` | ✅ Implemented | Issue compliance certificate |
| `POST /compliance/invoices` | ✅ Implemented | Compliance check (sandbox) |
| `POST /invoices/clearance` | ✅ Implemented | Production clearance |
| `POST /invoices/reporting` | ✅ Implemented | Production reporting |
| `GET /invoices/status` | ❌ Pending | Invoice status inquiry |
| `PUT /invoices/cancel` | ❌ Pending | Cancel/void invoice |
| `GET /compliance/certificate` | ❌ Pending | Certificate renewal |

### Unresolved ZATCA Requirements
| Requirement | Status | Notes |
|-------------|--------|-------|
| Production onboarding (compliance portal) | ⚠️ Manual | User must obtain OTP from portal |
| Certificate renewal before expiry | ❌ TODO | Implement renewal workflow |
| Standard invoice type (non-simplified) | ❌ TODO | Currently simplified only |
| Batch submission | ❌ TODO | Submit multiple invoices |
| Invoice status webhook | ❌ TODO | Receive status updates |

### External Dependencies Not Yet Confirmed
| Dependency | Status | Notes |
|------------|--------|-------|
| `endroid/qr-code` | ⬜ Optional | Install separately for image rendering |
| OpenSSL on production servers | ✅ Required | Ensure OpenSSL with secp256k1 support |

## MILESTONES

### Milestone 1: Stack Validation ✅
- [x] PHP 8.1+ confirmed
- [x] Laravel 10/11 confirmed
- [x] OpenSSL with secp256k1 confirmed
- [x] ZATCA V2 API endpoints identified

### Milestone 2: Package Skeleton ✅
- [x] Composer package with PSR-4 autoloading
- [x] Service Provider with auto-discovery
- [x] Configuration file with env overrides
- [x] Facade registration

### Milestone 3: Phase 1 Working ✅
- [x] TLV encoder (5 tags)
- [x] Base64 output
- [x] QR rendering (endroid/Fallback SVG)
- [x] Model trait for easy integration
- [x] Blade view support

### Milestone 4: Phase 2 Working ✅
- [x] EC key generation (secp256k1)
- [x] CSR generation (OpenSSL)
- [x] Compliance certificate issuance
- [x] UBL 2.1 XML builder
- [x] Invoice hashing (SHA-256)
- [x] Digital signature (ECDSA)
- [x] XAdES embedding
- [x] TLV QR (9 tags)
- [x] Compliance check (sandbox)
- [x] Clearance (production)
- [x] Reporting (production)

### Milestone 5: Production Hardening ✅
- [x] PII-safe logging
- [x] Queue-based async sync
- [x] Event-driven architecture
- [x] Retry with backoff
- [x] Error handling & exceptions
- [x] Comprehensive README
