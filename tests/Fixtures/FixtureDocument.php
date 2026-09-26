<?php

namespace Tests\Fixtures;

use App\Support\States\EnforcesStateTransitions;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\HasStates;
use Tests\Fixtures\States\DocumentState;

/**
 * Stored in a temporary table created by the test that uses it.
 *
 * @property DocumentState $status
 */
class FixtureDocument extends Model
{
    use EnforcesStateTransitions, HasStates, HasUlids;

    protected $table = 'fixture_documents';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocumentState::class,
        ];
    }
}
