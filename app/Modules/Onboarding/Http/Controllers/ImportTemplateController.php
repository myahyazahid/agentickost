<?php

namespace App\Modules\Onboarding\Http\Controllers;

use App\Modules\Onboarding\Enums\OnboardingPermission;
use App\Modules\Onboarding\Import\ImportTemplate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads the empty import workbook (FR-ONB-02).
 */
final class ImportTemplateController
{
    public function __invoke(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can(OnboardingPermission::Manage->value) ?? false, 403);

        return response()->streamDownload(
            fn () => ImportTemplate::write('php://output'),
            ImportTemplate::FILENAME,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
