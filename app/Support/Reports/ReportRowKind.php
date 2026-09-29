<?php

namespace App\Support\Reports;

enum ReportRowKind: string
{
    case Line = 'line';
    case Heading = 'heading';
    case Total = 'total';
}
