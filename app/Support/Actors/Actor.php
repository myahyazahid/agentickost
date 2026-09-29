<?php

namespace App\Support\Actors;

use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;

/**
 * Who performs an operation: recorded in the audit log and used to authorize actions.
 */
final readonly class Actor
{
    public function __construct(
        public ActorType $type,
        public ?string $id = null,
        public ?string $ipAddress = null,
        public ?string $impersonationLogId = null,
        public ?Authenticatable $user = null,
    ) {}

    public static function system(): self
    {
        return new self(ActorType::System);
    }

    public static function user(Authenticatable $user, ?string $ipAddress = null): self
    {
        return new self(ActorType::User, (string) $user->getAuthIdentifier(), $ipAddress, user: $user);
    }

    /**
     * A super admin in the admin panel, authorized as the admin.
     */
    public static function platformAdmin(Authenticatable $admin, ?string $ipAddress = null): self
    {
        return new self(ActorType::PlatformAdmin, (string) $admin->getAuthIdentifier(), $ipAddress, user: $admin);
    }

    /**
     * A super admin working inside a tenant (FR-TNT-05). The audit log names
     * the admin and the session; permissions are those of the owner account
     * the admin entered with.
     */
    public static function impersonating(Authenticatable $admin, Authenticatable $user, string $impersonationLogId, ?string $ipAddress = null): self
    {
        return new self(ActorType::PlatformAdmin, (string) $admin->getAuthIdentifier(), $ipAddress, $impersonationLogId, $user);
    }

    /**
     * A resident logged in to the portal (FR-PRT-01).
     */
    public static function resident(Authenticatable $resident, ?string $ipAddress = null): self
    {
        return new self(ActorType::Resident, (string) $resident->getAuthIdentifier(), $ipAddress, user: $resident);
    }

    /**
     * Someone paying for a room they do not live in, logged in to the portal
     * (FR-PRT-07).
     */
    public static function payer(Authenticatable $payer, ?string $ipAddress = null): self
    {
        return new self(ActorType::Payer, (string) $payer->getAuthIdentifier(), $ipAddress, user: $payer);
    }

    public static function agent(string $agentId): self
    {
        return new self(ActorType::Agent, $agentId);
    }

    /**
     * @return array{type: string, id: ?string, ip_address: ?string, impersonation_log_id: ?string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'id' => $this->id,
            'ip_address' => $this->ipAddress,
            'impersonation_log_id' => $this->impersonationLogId,
        ];
    }

    /**
     * Rebuild an actor from toArray() output, for example after it travelled
     * through a queued job's context.
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $type = $data['type'] ?? null;

        if (! is_string($type)) {
            throw new InvalidArgumentException('Data aktor tidak memiliki tipe.');
        }

        return new self(
            ActorType::from($type),
            self::optionalString($data, 'id'),
            self::optionalString($data, 'ip_address'),
            self::optionalString($data, 'impersonation_log_id'),
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function optionalString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
