<?php

namespace App\Modules\Property\Actions;

use App\Modules\Property\Models\Property;
use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;

/**
 * Soft-deletes a property that has no rooms left.
 */
final class DeleteProperty extends Action
{
    public function handle(Property $property): void
    {
        $this->authorize('delete', $property);

        if ($property->rooms()->exists()) {
            throw ValidationException::withMessages([
                'property' => 'Properti masih punya kamar. Hapus kamarnya lebih dulu.',
            ]);
        }

        $this->transaction(fn () => $property->delete());
    }
}
