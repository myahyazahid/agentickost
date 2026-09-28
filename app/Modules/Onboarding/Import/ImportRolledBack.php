<?php

namespace App\Modules\Onboarding\Import;

use RuntimeException;

/**
 * Unwinds the import transaction after a preview, or when a row failed.
 */
final class ImportRolledBack extends RuntimeException {}
