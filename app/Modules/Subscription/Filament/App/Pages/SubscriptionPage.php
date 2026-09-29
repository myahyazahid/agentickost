<?php

namespace App\Modules\Subscription\Filament\App\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Subscription\Actions\CancelSubscription;
use App\Modules\Subscription\Actions\ChoosePlan;
use App\Modules\Subscription\Actions\RequestDataExport;
use App\Modules\Subscription\Actions\ResumeSubscription;
use App\Modules\Subscription\Enums\BillingCycle;
use App\Modules\Subscription\Jobs\ExportTenantData;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Subscription\Models\SubscriptionInvoice;
use App\Modules\Subscription\States\Subscription\Active;
use App\Modules\Subscription\States\Subscription\Cancelled;
use App\Modules\Subscription\States\SubscriptionInvoice\Unpaid;
use App\Modules\Subscription\Support\BillingSettings;
use App\Modules\Subscription\Support\CurrentSubscription;
use App\Modules\Subscription\Support\SubscriptionBilling;
use App\Modules\Subscription\Support\SubscriptionUsage;
use App\Modules\Tenancy\TenantContext;
use App\Support\Filament\DomainActions;
use App\Support\Filament\MoneyColumn;
use App\Support\Money\Rupiah;
use App\Support\Subscriptions\SubscriptionGate;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * The owner's view of the subscription (FR-SUB-01 to FR-SUB-06): status,
 * plan and usage, invoices with how to pay them, choosing or cancelling a
 * plan, and exporting all data.
 */
class SubscriptionPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Properti';

    protected static ?string $navigationLabel = 'Langganan';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'langganan';

    protected static ?string $title = 'Langganan';

    public static function canAccess(): bool
    {
        return User::current()->can('manage', CurrentSubscription::get());
    }

    private function subscription(): Subscription
    {
        return CurrentSubscription::get();
    }

    public function content(Schema $schema): Schema
    {
        $subscription = $this->subscription();
        $plan = $subscription->plan()->first();
        $tenant = app(TenantContext::class)->tenant();
        $trialEnd = SubscriptionBilling::trialEndsOn($tenant);
        $instructions = BillingSettings::paymentInstructions();
        $hasUnpaid = $subscription->invoices()->where('status', Unpaid::$name)->exists();

        return $schema->components([
            Section::make('Status')
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->state($subscription->status),
                    TextEntry::make('plan')
                        ->label('Paket')
                        ->state($plan === null ? null : $plan->name.', '.$subscription->billing_cycle?->getLabel())
                        ->placeholder('Belum memilih paket'),
                    TextEntry::make('period')
                        ->label($subscription->current_period_end === null ? 'Trial sampai' : 'Dibayar sampai')
                        ->state(($subscription->current_period_end ?? $trialEnd)?->translatedFormat('j F Y'))
                        ->placeholder('-'),
                ]),
            Section::make('Pemakaian')
                ->description($plan === null ? 'Selama trial tidak ada batas.' : "Batas paket {$plan->name}.")
                ->columns(['default' => 1, 'md' => 3])
                ->schema(array_map(fn (string $resource): TextEntry => TextEntry::make("usage_{$resource}")
                    ->label(ucfirst(SubscriptionUsage::label($resource)))
                    ->state(self::usageLine($plan, $resource)), [SubscriptionGate::PROPERTIES, SubscriptionGate::ROOMS, SubscriptionGate::STAFF])),
            Section::make('Cara bayar')
                ->visible($hasUnpaid)
                ->schema([
                    TextEntry::make('instructions')
                        ->hiddenLabel()
                        ->state($instructions ?? 'Hubungi tim Agentic Kost untuk cara pembayaran.')
                        ->formatStateUsing(fn (string $state): string => nl2br(e($state)))
                        ->html(),
                    TextEntry::make('confirmation')
                        ->hiddenLabel()
                        ->color('gray')
                        ->state('Langganan aktif setelah tim Agentic Kost memeriksa transfer Anda, biasanya pada hari kerja yang sama.'),
                ]),
            EmbeddedTable::make(),
        ]);
    }

    private static function usageLine(?Plan $plan, string $resource): string
    {
        $used = SubscriptionUsage::of($resource);
        $limit = $plan === null ? null : SubscriptionUsage::limit($plan, $resource);

        return $limit === null ? (string) $used : "{$used} dari {$limit}";
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Tagihan langganan')
            ->query(fn () => SubscriptionInvoice::query()->with('plan'))
            ->columns([
                TextColumn::make('number')->label('Nomor'),
                TextColumn::make('plan.name')
                    ->label('Paket')
                    ->description(fn (SubscriptionInvoice $record): string => $record->billing_cycle->getLabel()),
                TextColumn::make('period_start')
                    ->label('Periode')
                    ->formatStateUsing(fn (SubscriptionInvoice $record): string => $record->period_start->translatedFormat('j M Y').' sampai '.$record->period_end->translatedFormat('j M Y')),
                MoneyColumn::make('amount')->label('Nominal'),
                TextColumn::make('due_date')->label('Jatuh tempo')->date('j M Y'),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->defaultSort('due_date', 'desc')
            ->emptyStateIcon(Heroicon::OutlinedReceiptPercent)
            ->emptyStateHeading('Belum ada tagihan langganan')
            ->emptyStateDescription('Tagihan terbit setelah Anda memilih paket.');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->choosePlanAction(),
            $this->resumeAction(),
            $this->cancelAction(),
            $this->exportAction(),
        ];
    }

    private function choosePlanAction(): Action
    {
        $subscription = $this->subscription();
        $running = $subscription->status->equals(Active::class) || $subscription->status->equals(Cancelled::class);

        return Action::make('choosePlan')
            ->label($subscription->plan_id === null ? 'Pilih paket' : 'Ganti paket')
            ->icon(Heroicon::OutlinedRectangleStack)
            ->modalHeading($subscription->plan_id === null ? 'Pilih paket' : 'Ganti paket')
            ->modalDescription($running
                ? 'Paket baru langsung berlaku. Tagihan perpanjangan berikutnya memakai harga paket baru.'
                : 'Tagihan untuk periode pertama terbit setelah Anda memilih paket.')
            ->fillForm(fn (): array => [
                'plan_id' => $subscription->plan_id,
                'billing_cycle' => ($subscription->billing_cycle ?? BillingCycle::Monthly)->value,
            ])
            ->schema([
                Radio::make('plan_id')
                    ->label('Paket')
                    ->options(fn (): array => self::plans()->mapWithKeys(fn (Plan $plan): array => [$plan->id => $plan->name])->all())
                    ->descriptions(fn (): array => self::plans()->mapWithKeys(fn (Plan $plan): array => [$plan->id => self::planSummary($plan)])->all())
                    ->required(),
                Radio::make('billing_cycle')
                    ->label('Bayar')
                    ->options(BillingCycle::class)
                    ->inline()
                    ->required(),
            ])
            ->action(function (Action $action, array $data): void {
                $invoice = null;

                DomainActions::forAction($action, function () use (&$invoice, $data): void {
                    $invoice = app(ChoosePlan::class)->handle($data);
                });

                Notification::make()
                    ->success()
                    ->title('Paket disimpan')
                    ->body($invoice === null ? null : "Tagihan {$invoice->number} sebesar ".Rupiah::format($invoice->amount).' terbit, jatuh tempo '.$invoice->due_date->translatedFormat('j F Y').'.')
                    ->send();
            });
    }

    private function cancelAction(): Action
    {
        $subscription = $this->subscription();

        return Action::make('cancel')
            ->label('Hentikan langganan')
            ->color('danger')
            ->outlined()
            ->visible($subscription->status->equals(Active::class))
            ->requiresConfirmation()
            ->modalHeading('Hentikan langganan?')
            ->modalDescription('Layanan tetap berjalan sampai '.$subscription->current_period_end?->translatedFormat('j F Y').' dan tidak diperpanjang. Setelah itu akun menjadi baca saja: data bisa dilihat dan diekspor, tetapi tidak bisa diubah.')
            ->modalSubmitActionLabel('Hentikan')
            ->action(function (Action $action): void {
                DomainActions::forAction($action, fn () => app(CancelSubscription::class)->handle());

                Notification::make()->success()->title('Langganan dihentikan')->send();
            });
    }

    private function resumeAction(): Action
    {
        return Action::make('resume')
            ->label('Lanjutkan langganan')
            ->visible($this->subscription()->status->equals(Cancelled::class))
            ->action(function (Action $action): void {
                DomainActions::forAction($action, fn () => app(ResumeSubscription::class)->handle());

                Notification::make()->success()->title('Langganan dilanjutkan')->send();
            });
    }

    private function exportAction(): Action
    {
        return Action::make('export')
            ->label('Ekspor semua data')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->visible(fn (): bool => User::current()->can('exportData', $this->subscription()))
            ->requiresConfirmation()
            ->modalHeading('Ekspor semua data?')
            ->modalDescription('Semua data usaha dikemas dalam satu berkas zip: tabel dalam format CSV dan semua lampiran, termasuk foto KTP penghuni. Tautan unduhan dikirim ke notifikasi Anda setelah selesai dan berlaku '.ExportTenantData::DOWNLOAD_DAYS.' hari.')
            ->modalSubmitActionLabel('Mulai ekspor')
            ->action(function (Action $action): void {
                DomainActions::forAction($action, fn () => app(RequestDataExport::class)->handle());

                Notification::make()->success()->title('Ekspor sedang disiapkan')->body('Tautan unduhan muncul di notifikasi setelah selesai.')->send();
            });
    }

    /**
     * @return Collection<int, Plan>
     */
    private static function plans(): Collection
    {
        return Plan::query()->where('is_active', true)->orderBy('sort_order')->orderBy('monthly_price_amount')->get();
    }

    private static function planSummary(Plan $plan): string
    {
        $limits = array_filter([
            $plan->max_properties === null ? null : "{$plan->max_properties} properti",
            $plan->max_rooms === null ? null : "{$plan->max_rooms} kamar",
            $plan->max_staff === null ? null : "{$plan->max_staff} pengguna",
        ]);

        $price = Rupiah::format($plan->monthly_price_amount).' per bulan';

        if ($plan->yearly_price_amount !== null) {
            $price .= ' atau '.Rupiah::format($plan->yearly_price_amount).' per tahun';
        }

        return $price.'. '.($limits === [] ? 'Tanpa batas.' : 'Sampai '.implode(', ', $limits).'.');
    }
}
