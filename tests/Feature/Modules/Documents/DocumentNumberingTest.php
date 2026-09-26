<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Documents\Actions\UpdateDocumentFormat;
use App\Modules\Documents\Enums\DocumentType;
use App\Modules\Documents\Support\DocumentNumbers;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo('2026-09-15 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
});

function nextInvoiceNumber(): string
{
    return DB::transaction(fn () => app(DocumentNumbers::class)->next(DocumentType::Invoice, CarbonImmutable::now(), 'KMG'));
}

it('numbers with the new format and carries the counter on', function () {
    nextInvoiceNumber();
    nextInvoiceNumber();

    app(UpdateDocumentFormat::class)->handle(DocumentType::Invoice, ['format' => '{PROP}/{YY}{MM}/{SEQ:3}', 'reset_period' => 'yearly']);

    expect(app(DocumentNumbers::class)->preview(DocumentType::Invoice, CarbonImmutable::now(), 'KMG'))->toBe('KMG/2609/003')
        ->and(nextInvoiceNumber())->toBe('KMG/2609/003');
});

it('refuses formats that would repeat or cannot be read', function (string $format, string $reset, string $message) {
    expect(fn () => app(UpdateDocumentFormat::class)->handle(DocumentType::Invoice, ['format' => $format, 'reset_period' => $reset]))
        ->toThrow(ValidationException::class, $message);
})->with([
    'no sequence' => ['INV/{YYYY}/{MM}', 'monthly', 'wajib memuat satu {SEQ}'],
    'monthly without the month' => ['INV/{YYYY}/{SEQ:4}', 'monthly', 'tahun dan bulan'],
    'yearly without the year' => ['INV/{SEQ:4}', 'yearly', 'harus memuat tahun'],
    'unknown code' => ['INV/{DD}/{SEQ:4}', 'never', 'tidak dikenal'],
]);

it('lets only the owner change formats', function () {
    loginAs(staff(Role::Manager, $this->owner->tenant()->firstOrFail()));

    app(UpdateDocumentFormat::class)->handle(DocumentType::Invoice, ['format' => 'INV/{YYYY}/{SEQ:4}', 'reset_period' => 'yearly']);
})->throws(AuthorizationException::class);
