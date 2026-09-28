<?php

namespace App\Modules\Lease\Events;

use App\Modules\Lease\Models\Inspection;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The resident checked in; the room's condition was recorded (FR-SIK-01).
 */
final class CheckedIn
{
    use Dispatchable;

    public function __construct(public readonly Inspection $inspection) {}
}
