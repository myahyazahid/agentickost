<?php

namespace App\Modules\Access\Console;

use App\Modules\Access\Actions\CreateUser;
use App\Modules\Access\Enums\Role;
use App\Modules\Tenancy\Actions\CreateTenant;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actors\Actor;
use App\Support\Actors\ActorContext;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

final class CreateTenantCommand extends Command
{
    protected $signature = 'tenant:create
        {name? : Nama usaha kost}
        {--owner-name= : Nama owner}
        {--owner-email= : Email login owner}
        {--owner-password= : Password owner}';

    protected $description = 'Buat tenant baru beserta user owner-nya';

    public function handle(
        ActorContext $actors,
        TenantContext $tenants,
        CreateTenant $createTenant,
        CreateUser $createUser,
    ): int {
        $input = [
            'name' => $this->argument('name') ?? text('Nama usaha kost', required: true),
            'owner_name' => $this->option('owner-name') ?? text('Nama owner', required: true),
            'owner_email' => $this->option('owner-email') ?? text('Email owner', required: true),
            'owner_password' => $this->option('owner-password') ?? password('Password owner', required: true),
        ];

        $tenant = $actors->actingAs(Actor::system(), function () use ($input, $tenants, $createTenant, $createUser) {
            $tenant = $createTenant->handle(['name' => $input['name']]);

            $tenants->run($tenant, fn () => $createUser->handle([
                'name' => $input['owner_name'],
                'email' => $input['owner_email'],
                'password' => $input['owner_password'],
                'role' => Role::Owner->value,
            ]));

            return $tenant;
        });

        $this->components->info("Tenant [{$tenant->name}] dibuat dengan slug [{$tenant->slug}]. Owner login di /app dengan {$input['owner_email']}.");

        return self::SUCCESS;
    }
}
