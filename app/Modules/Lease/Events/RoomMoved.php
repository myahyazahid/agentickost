<?php

namespace App\Modules\Lease\Events;

use App\Modules\Lease\Models\RoomMove;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A contract moved to another room (PRD §8.8).
 */
final class RoomMoved
{
    use Dispatchable;

    public function __construct(public readonly RoomMove $move) {}
}
