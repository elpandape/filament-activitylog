<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A role of the shape both Bouncer and spatie/laravel-permission give a model: reachable
 * through a `roles` relation, named, and titled only sometimes.
 */
final class Role extends Model
{
    public $timestamps = false;

    protected $table = 'roles';

    protected $guarded = [];
}
