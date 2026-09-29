<?php

namespace App\Modules\Access\Filament\App\Auth;

use App\Modules\Access\Actions\AcceptStaffInvitation;
use App\Modules\Access\Models\StaffInvitation;
use App\Support\Actors\Actor;
use App\Support\Actors\ActorContext;
use App\Support\Filament\DomainActions;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Locked;

/**
 * Where an invited staff member lands from the email (FR-USR-03): they see
 * who invited them and as what, pick a password, and are logged in.
 *
 * @property-read Schema $form
 */
class AcceptInvitation extends SimplePage
{
    use WithRateLimiting;

    #[Locked]
    public string $token = '';

    #[Locked]
    public ?string $invitee = null;

    #[Locked]
    public ?string $businessName = null;

    #[Locked]
    public ?string $roleLabel = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(string $token): void
    {
        if (Filament::auth()->check()) {
            $this->redirect(Filament::getUrl());

            return;
        }

        $this->token = $token;
        $invitation = StaffInvitation::findByToken($token);

        if ($invitation !== null && $invitation->isPending()) {
            $this->invitee = $invitation->email;
            $this->businessName = (string) $invitation->tenant()->value('name');
            $this->roleLabel = $invitation->role->getLabel();
        }

        $this->form->fill();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Terima undangan';
    }

    public function getHeading(): string|Htmlable
    {
        return $this->invitee !== null ? "Bergabung dengan {$this->businessName}" : 'Undangan tidak berlaku';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->invitee !== null
            ? "Anda diundang sebagai {$this->roleLabel}. Buat password untuk masuk dengan {$this->invitee}."
            : null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->rule(Password::default())
                    ->same('password_confirmation')
                    ->autofocus(),
                TextInput::make('password_confirmation')
                    ->label('Ulangi password')
                    ->password()
                    ->revealable()
                    ->required(),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        if ($this->invitee === null) {
            return $schema->components([
                Text::make('Tautan ini sudah dipakai, dibatalkan, atau kedaluwarsa. Minta owner kost mengirim ulang undangannya.'),
            ]);
        }

        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('accept')
                ->footer([
                    Actions::make([
                        Action::make('accept')->label('Buat password dan masuk')->submit('accept'),
                    ])->fullWidth(),
                ]),
        ]);
    }

    public function accept(): void
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->danger()
                ->title("Terlalu banyak percobaan. Coba lagi dalam {$exception->secondsUntilAvailable} detik.")
                ->send();

            return;
        }

        $data = $this->form->getState();

        $user = DomainActions::forForm(fn () => app(ActorContext::class)->actingAs(
            Actor::system(),
            fn () => app(AcceptStaffInvitation::class)->handle($this->token, $data),
        ));

        Filament::auth()->login($user);
        session()->regenerate();

        $this->redirect(Filament::getUrl());
    }
}
