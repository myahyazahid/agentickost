<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Lease\Models\Payer;
use App\Modules\Lease\Models\Resident;
use App\Modules\Portal\Actions\RequestPortalCode;
use App\Modules\Portal\Actions\VerifyPortalCode;
use App\Modules\Portal\Livewire\Login;
use App\Modules\Portal\Models\OtpCode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\FakeMessageChannel;
use Tests\Support\PortalScenario;

beforeEach(function () {
    $this->travelTo('2026-10-05 03:00:00');
    loginAs(staff(Role::Owner));
    $this->tenant = tenancy()->tenant();
    $this->messages = FakeMessageChannel::install();
    PortalScenario::useTenantUrls();
});

it('shows the login page of the tenant in the URL', function () {
    $this->get(PortalScenario::url('portal.login'))
        ->assertOk()
        ->assertSee(['Masuk ke portal penghuni', $this->tenant->name]);

    $this->get('/p/tidak-ada/masuk')->assertNotFound();
});

it('sends a code by WhatsApp and logs the resident in with it', function () {
    $contract = PortalScenario::residentContract('+6281200000001');

    $login = Livewire::test(Login::class)
        ->set('phone', '0812-0000-0001')
        ->call('sendCode')
        ->assertHasNoErrors()
        ->assertSet('sentTo', '+6281200000001')
        ->assertSee('kode 6 angka sudah dikirim');

    $code = $this->messages->lastCode('+6281200000001');
    expect($code)->not->toBeNull()
        ->and($this->messages->sent[0]['text'])->toContain($this->tenant->name)
        ->and(OtpCode::query()->sole()->code_hash)->not->toContain((string) $code);

    $login->set('code', (string) $code)
        ->call('verify')
        ->assertRedirect(PortalScenario::url('portal.home'));

    expect(Auth::guard('resident')->id())->toBe(PortalScenario::resident($contract)->id);
});

it('answers the same for a number that is not registered, without sending', function () {
    Livewire::test(Login::class)
        ->set('phone', '081299999999')
        ->call('sendCode')
        ->assertHasNoErrors()
        ->assertSet('sentTo', '+6281299999999');

    expect($this->messages->sent)->toBe([])
        ->and(OtpCode::query()->count())->toBe(0);
});

it('refuses a wrong code, and stops the code after five wrong tries', function () {
    PortalScenario::residentContract('+6281200000001');
    app(RequestPortalCode::class)->handle(['phone' => '081200000001']);
    $code = (string) $this->messages->lastCode('+6281200000001');
    $wrong = $code === '000000' ? '111111' : '000000';

    for ($i = 0; $i < VerifyPortalCode::MAX_ATTEMPTS; $i++) {
        expect(fn () => app(VerifyPortalCode::class)->handle(['phone' => '081200000001', 'code' => $wrong]))
            ->toThrow(ValidationException::class, 'Kode salah');
    }

    expect(fn () => app(VerifyPortalCode::class)->handle(['phone' => '081200000001', 'code' => $code]))
        ->toThrow(ValidationException::class, 'Kode salah');
});

it('refuses an expired code and a code used twice', function () {
    PortalScenario::residentContract('+6281200000001');
    app(RequestPortalCode::class)->handle(['phone' => '081200000001']);
    $code = (string) $this->messages->lastCode('+6281200000001');

    $this->travel(RequestPortalCode::CODE_MINUTES + 1)->minutes();
    expect(fn () => app(VerifyPortalCode::class)->handle(['phone' => '081200000001', 'code' => $code]))->toThrow(ValidationException::class);

    app(RequestPortalCode::class)->handle(['phone' => '081200000001']);
    $fresh = (string) $this->messages->lastCode('+6281200000001');

    expect(app(VerifyPortalCode::class)->handle(['phone' => '081200000001', 'code' => $fresh]))->toBeInstanceOf(Resident::class)
        ->and(fn () => app(VerifyPortalCode::class)->handle(['phone' => '081200000001', 'code' => $fresh]))->toThrow(ValidationException::class);
});

it('limits how often a code can be requested for one number', function () {
    PortalScenario::residentContract('+6281200000001');

    for ($i = 0; $i < RequestPortalCode::REQUESTS_PER_PHONE; $i++) {
        app(RequestPortalCode::class)->handle(['phone' => '081200000001']);
    }

    expect(fn () => app(RequestPortalCode::class)->handle(['phone' => '081200000001']))
        ->toThrow(ValidationException::class, 'Terlalu sering meminta kode');

    $this->travel(RequestPortalCode::WINDOW_SECONDS + 1)->seconds();
    app(RequestPortalCode::class)->handle(['phone' => '081200000001']);

    expect($this->messages->sent)->toHaveCount(RequestPortalCode::REQUESTS_PER_PHONE + 1);
});

it('logs in a parent who pays but lives elsewhere as a payer', function () {
    $contract = PortalScenario::parentPaidContract('+6281300000002');
    app(RequestPortalCode::class)->handle(['phone' => '081300000002']);

    $account = app(VerifyPortalCode::class)->handle(['phone' => '081300000002', 'code' => (string) $this->messages->lastCode('+6281300000002')]);

    expect($account)->toBeInstanceOf(Payer::class)
        ->and($account->id)->toBe($contract->payer_id);
});

it('does not let a resident who moved out of a room someone else pays for log in', function () {
    $contract = PortalScenario::parentPaidContract();
    $resident = PortalScenario::resident($contract);
    $contract->residents()->updateExistingPivot($resident->id, ['left_on' => '2026-10-01']);

    app(RequestPortalCode::class)->handle(['phone' => $resident->phone]);

    expect($this->messages->sent)->toBe([]);
});

it('logs in with the phone number alone when codes are switched off for development', function () {
    config(['agentickost.portal_otp_required' => false]);
    $contract = PortalScenario::residentContract('+6281200000001');

    Livewire::test(Login::class)
        ->assertSee('Mode pengembangan')
        ->set('phone', '081299999999')
        ->call('sendCode')
        ->assertHasErrors(['phone'])
        ->set('phone', '081200000001')
        ->call('sendCode')
        ->assertRedirect(PortalScenario::url('portal.home'));

    expect(Auth::guard('resident')->id())->toBe(PortalScenario::resident($contract)->id)
        ->and($this->messages->sent)->toBe([]);
});
