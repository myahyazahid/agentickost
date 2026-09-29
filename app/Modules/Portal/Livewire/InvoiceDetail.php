<?php

namespace App\Modules\Portal\Livewire;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Support\InvoiceDocument;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Payment\Actions\SubmitPaymentProof;
use App\Modules\Payment\States\Payment\Pending;
use App\Modules\Portal\Support\PortalQueries;
use App\Modules\Tenancy\Support\TenantStorage;
use App\Support\Subscriptions\ReadOnlyMode;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\WithFileUploads;

/**
 * One bill: what it is made of, where to transfer, and a form to send the
 * transfer proof (FR-PRT-02, FR-PRT-03). Payment through a gateway comes
 * with M1.5.2.
 */
class InvoiceDetail extends PortalPage
{
    use WithFileUploads;

    /**
     * Shown when the business's own subscription is read-only (FR-SUB-04).
     */
    public const PAUSED = 'Portal sedang tidak menerima kiriman. Hubungi pengelola kost langsung untuk pembayaran ini.';

    #[Locked]
    public string $invoiceId = '';

    public string $amount = '';

    public string $paidOn = '';

    public string $bankAccountId = '';

    public string $note = '';

    /**
     * @var UploadedFile|null
     */
    public $proof = null;

    public bool $sent = false;

    public function mount(string $invoice): void
    {
        $this->invoiceId = $invoice;
        $record = $this->invoice();

        $this->amount = (string) $record->balance_amount;
        $this->paidOn = CarbonImmutable::now($this->tenant()->default_timezone)->toDateString();
        $this->bankAccountId = (string) $this->bankAccounts($record)->first()?->id;
    }

    public function submitProof(TenantStorage $storage): void
    {
        $record = $this->invoice();

        $this->validate([
            'amount' => ['required'],
            'paidOn' => ['required', 'date'],
            'bankAccountId' => ['required', 'string'],
            'proof' => ['required', 'image', 'max:5120'],
            'note' => ['nullable', 'string', 'max:100'],
        ], [
            'proof.required' => 'Lampirkan foto atau tangkapan layar bukti transfer.',
            'proof.image' => 'Bukti transfer harus berupa gambar (JPG, PNG, atau WEBP).',
            'proof.max' => 'Ukuran gambar paling besar 5 MB.',
        ]);

        $path = $this->proof?->store($storage->path(AttachmentCollection::PaymentProof->directory()));

        if (! is_string($path)) {
            throw ValidationException::withMessages(['proof' => 'Bukti transfer gagal diunggah. Coba lagi.']);
        }

        // Only the day is asked; midday of that day, or now for today.
        $paidAt = CarbonImmutable::parse($this->paidOn.' 12:00:00', $this->tenant()->default_timezone)->utc();
        $paidAt = $paidAt->isFuture() ? CarbonImmutable::now() : $paidAt;

        try {
            app(SubmitPaymentProof::class)->handle($record->contract()->firstOrFail(), [
                'amount' => (int) preg_replace('/\D/', '', $this->amount),
                'paid_at' => $paidAt->toDateTimeString(),
                'bank_account_id' => $this->bankAccountId,
                'proofs' => [$path],
                'note' => $this->note !== '' ? $this->note : null,
            ]);
        } catch (ValidationException $exception) {
            Storage::delete($path);

            throw ValidationException::withMessages(self::fieldErrors($exception));
        } catch (ReadOnlyMode) {
            Storage::delete($path);

            throw ValidationException::withMessages(['proof' => self::PAUSED]);
        }

        $this->reset('proof', 'note');
        $this->sent = true;
    }

    public function render(): View
    {
        $record = $this->invoice();
        $record->loadMissing(['items', 'penalties', 'property', 'contract.room']);

        return $this->page('portal::livewire.invoice', 'Tagihan '.($record->number ?? ''), [
            'invoice' => $record,
            'penalties' => $record->penalties->whereNull('waived_at'),
            'accounts' => $this->bankAccounts($record),
            'pdfUrl' => InvoiceDocument::canShare($record) ? app(InvoiceDocument::class)->shareUrl($record) : null,
            'pending' => PortalQueries::payments($this->access())
                ->where('contract_id', $record->contract_id)
                ->where('status', Pending::$name)
                ->orderByDesc('paid_at')
                ->get(),
        ]);
    }

    private function invoice(): Invoice
    {
        return PortalQueries::invoices($this->access())->whereKey($this->invoiceId)->firstOrFail();
    }

    /**
     * @return Collection<int, BankAccount>
     */
    private function bankAccounts(Invoice $invoice): Collection
    {
        return BankAccount::query()
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('property_id')->orWhere('property_id', $invoice->property_id))
            ->orderByDesc('is_default')
            ->orderBy('provider_name')
            ->get();
    }

    /**
     * Errors from the Action, moved onto the form's field names.
     *
     * @return array<string, list<string>>
     */
    private static function fieldErrors(ValidationException $exception): array
    {
        $fields = ['amount' => 'amount', 'paid_at' => 'paidOn', 'bank_account_id' => 'bankAccountId', 'proofs' => 'proof', 'note' => 'note'];
        $errors = [];

        foreach ($exception->errors() as $key => $messages) {
            $field = $fields[explode('.', $key)[0]] ?? 'proof';
            $errors[$field] = [...($errors[$field] ?? []), ...array_values($messages)];
        }

        return $errors;
    }
}
