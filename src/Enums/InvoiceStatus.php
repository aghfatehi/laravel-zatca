<?php

namespace Aghfatehi\Zatca\Enums;

enum InvoiceStatus: string
{
    case Pending    = 'pending';
    case Reported   = 'reported';
    case Cleared    = 'cleared';
    case Failed     = 'failed';
    case CompliancePassed = 'compliance_passed';
    case ComplianceFailed = 'compliance_failed';
}
