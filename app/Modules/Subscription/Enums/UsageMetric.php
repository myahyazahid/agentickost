<?php

namespace App\Modules\Subscription\Enums;

/**
 * Monthly quotas counted per tenant (schema §13.4).
 */
enum UsageMetric: string
{
    case Messages = 'messages';
    case AiCredits = 'ai_credits';
}
