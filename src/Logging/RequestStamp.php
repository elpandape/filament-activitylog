<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Logging;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Request;

/**
 * Writes down where a request came from, in the three keys the entry page's context rail reads.
 *
 * The rail is this package's own, and nothing was filling it: every entry said it happened outside
 * a web request until the application stamped the keys by hand, in every place it logged. Reading
 * them and never writing them was the one asymmetry left in this package.
 *
 * What decides whether there is a request to describe is the client address, and not
 * `runningInConsole()`: a whole test suite runs under the CLI, so asking about the SAPI takes the
 * empty branch in every test and never in production — behaviour that differs between being tested
 * and being used. A console command still carries a synthesised request whose method and path read
 * as a bare `GET /`, so the address is the one part only a real caller brings.
 *
 * Keys the caller already set are left alone: `withProperties(['ip' => ...])` is a deliberate
 * statement about where something came from, and this is only here to fill a silence.
 */
final class RequestStamp
{
    public static function apply(Model $activity): void
    {
        $address = Request::ip();

        if (! is_string($address)) {
            return;
        }

        $properties = $activity->getAttribute('properties');

        $stamp = array_filter([
            'ip' => $address,
            'via' => Request::method().' '.Request::path(),
            'agent' => Request::userAgent(),
        ], is_string(...));

        $activity->setAttribute('properties', array_merge(
            $stamp,
            $properties instanceof Collection ? $properties->all() : [],
        ));
    }
}
