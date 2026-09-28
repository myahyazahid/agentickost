<?php

namespace App\Modules\Onboarding\Actions;

use App\Modules\Onboarding\Enums\OnboardingPermission;
use App\Modules\Onboarding\Import\ImportResult;
use App\Modules\Onboarding\Import\ImportRolledBack;
use App\Modules\Onboarding\Import\ImportSheet;
use App\Modules\Onboarding\Import\OnboardingImporter;
use App\Modules\Onboarding\Import\SpreadsheetReader;
use App\Modules\Property\Models\Property;
use App\Support\Actions\Action;

/**
 * Imports rooms, residents, and running contracts from the template into one
 * property (FR-ONB-02, FR-ONB-03). A preview runs the whole import and rolls
 * it back, so every row is checked by the same rules as a real import. The
 * import itself keeps the data only when every row succeeds: one bad row
 * and nothing is saved.
 */
final class ImportOnboardingData extends Action
{
    public function __construct(private readonly OnboardingImporter $importer) {}

    public function handle(Property $property, string $path, string $fileName, bool $commit, ?ImportSheet $csvSheet = null): ImportResult
    {
        $this->authorize(OnboardingPermission::Manage->value);
        $this->authorize('manageRooms', $property);

        $file = SpreadsheetReader::read($path, $fileName, $csvSheet);
        $result = new ImportResult;

        foreach ($file['errors'] as $error) {
            $result->addFileError($error);
        }

        if ($file['sheets'] === []) {
            return $result;
        }

        try {
            return $this->transaction(function () use ($property, $file, $result, $commit): ImportResult {
                $this->importer->run($property, $file['sheets'], $result);

                if (! $commit || ! $result->isClean()) {
                    throw new ImportRolledBack;
                }

                $result->committed = true;

                return $result;
            });
        } catch (ImportRolledBack) {
            return $result;
        }
    }
}
