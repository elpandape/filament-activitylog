<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Tests\Fixtures\Policies;

use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * Declares `viewAny` and `view` and nothing else, which is what the package documents:
 * under strict authorization a missing method is not a denied permission but an impossible
 * action, and that is what keeps an audit trail from being editable.
 */
final class ActivityPolicy
{
    public static bool $viewAny = true;

    public static bool $view = true;

    public function viewAny(User $user): bool
    {
        return self::$viewAny;
    }

    public function view(User $user, Activity $activity): bool
    {
        return self::$view;
    }
}
