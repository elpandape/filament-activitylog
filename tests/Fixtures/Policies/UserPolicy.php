<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Tests\Fixtures\Policies;

use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Models\User;

final class UserPolicy
{
    public static bool $viewAny = true;

    public static bool $view = true;

    public function viewAny(User $user): bool
    {
        return self::$viewAny;
    }

    public function view(User $user, User $record): bool
    {
        return self::$view;
    }
}
