<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Billing\Actions\CreateAdhocInvoice;
use App\Modules\Billing\Support\InvoiceDocument;
use App\Modules\Tenancy\Models\Tenant;
use Tests\Support\BillingScenario;
use Tests\Support\LeaseScenario;

beforeEach(function () {
    $this->travelTo('2026-09-15 03:00:00');
    loginAs(staff(Role::Owner));
    [$this->invoice] = BillingScenario::issueDue(LeaseScenario::active());
});

it('prints the invoice as a PDF for staff of the tenant only', function () {
    $this->get(route('billing.invoices.pdf', ['invoice' => $this->invoice->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    tenancy()->forget();

    $this->actingAs(staff(Role::Owner, Tenant::factory()->create()))
        ->get(route('billing.invoices.pdf', ['invoice' => $this->invoice->id]))
        ->assertNotFound();
});

it('opens a shared link without login until it expires', function () {
    $url = app(InvoiceDocument::class)->shareUrl($this->invoice);
    tenancy()->forget();
    auth()->logout();

    $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');

    $this->travel(InvoiceDocument::SHARE_DAYS + 1)->days();

    $this->get($url)->assertForbidden();
});

it('refuses a link whose signature was tampered with', function () {
    $url = app(InvoiceDocument::class)->shareUrl($this->invoice);
    auth()->logout();

    $this->get($url.'x')->assertForbidden();
    $this->get(route('billing.invoices.shared', ['invoice' => $this->invoice->id]))->assertForbidden();
});

it('does not share drafts', function () {
    $draft = app(CreateAdhocInvoice::class)->handle($this->invoice->property()->firstOrFail(), [
        'contract_id' => $this->invoice->contract_id,
        'due_date' => '2026-09-30',
        'items' => [['type' => 'other', 'description' => 'Kebersihan', 'amount' => 20_000]],
    ]);
    $url = app(InvoiceDocument::class)->shareUrl($draft);
    tenancy()->forget();
    auth()->logout();

    $this->get($url)->assertNotFound();
});
