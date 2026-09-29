<?php

namespace App\Support\Actors;

enum ActorType: string
{
    case User = 'user';
    case Resident = 'resident';
    case Payer = 'payer';
    case System = 'system';
    case Agent = 'agent';
    case PlatformAdmin = 'platform_admin';
}
