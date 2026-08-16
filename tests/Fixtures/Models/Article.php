<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A record named by something other than `name`, and shown by no resource: what the suite
 * uses to reach the configured naming attribute and the places with nowhere to link to.
 */
final class Article extends Model
{
    protected $table = 'articles';

    protected $guarded = [];

    /**
     * Not a relation, on purpose: what the default role resolver has to refuse to read.
     */
    public function roles(): string
    {
        return 'this is not a relation';
    }
}
