<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Tests\Fixtures\Roles;

use ElPandaPe\FilamentActivitylog\Contracts\ResolvesCauserRole;
use Illuminate\Database\Eloquent\Model;

final class FixedCauserRole implements ResolvesCauserRole
{
    public static ?string $role = 'Super admin';

    public function __invoke(Model $causer): ?string
    {
        return self::$role;
    }
}
