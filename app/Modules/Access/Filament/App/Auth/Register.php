<?php

namespace App\Modules\Access\Filament\App\Auth;

use App\Modules\Access\Actions\RegisterTenant;
use App\Modules\Tenancy\Support\PlatformSettings;
use App\Support\Actors\Actor;
use App\Support\Actors\ActorContext;
use App\Support\Filament\DomainActions;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use SensitiveParameter;

/**
 * Self-registration for kost owners (FR-TNT-01): the business, the owner,
 * and a WhatsApp number. Creates the tenant and owner account through
 * RegisterTenant; the owner confirms the email address before the panel
 * opens.
 */
class Register extends BaseRegister
{
    public function getHeading(): string|Htmlable
    {
        return 'Daftarkan kost Anda';
    }

    public function getSubheading(): string|Htmlable|null
    {
        $trial = 'Coba gratis selama '.PlatformSettings::trialDays().' hari.';

        return new HtmlString(e($trial).' '.__('filament-panels::auth/pages/register.actions.login.before').' '.$this->loginAction->toHtml());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('business_name')
                ->label('Nama usaha kost')
                ->placeholder('Misal: Kost Melati')
                ->required()
                ->maxLength(150)
                ->autofocus(),
            TextInput::make('name')
                ->label('Nama Anda')
                ->required()
                ->maxLength(100),
            $this->getEmailFormComponent(),
            TextInput::make('phone')
                ->label('Nomor WhatsApp')
                ->tel()
                ->placeholder('0812 3456 7890')
                ->required()
                ->maxLength(20),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
        ]);
    }

    /**
     * The Action hashes the password; the field passes it through as typed.
     */
    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()->dehydrateStateUsing(fn (#[SensitiveParameter] mixed $state): mixed => $state);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        return DomainActions::forForm(fn (): Model => app(ActorContext::class)->actingAs(
            Actor::system(),
            fn (): Model => app(RegisterTenant::class)->handle($data),
        ));
    }
}
