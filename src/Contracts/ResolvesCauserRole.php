<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Answers which role somebody acted with, so the seal can write it down.
 *
 * The package knows no authorization system; the application implements this and names the
 * class in `filament-activitylog.logging.causer_role`.
 */
interface ResolvesCauserRole
{
    public function __invoke(Model $causer): ?string;
}
