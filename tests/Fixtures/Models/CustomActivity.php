<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Tests\Fixtures\Models;

use Spatie\Activitylog\Models\Activity;

/**
 * The activity model an application swapped in through `activitylog.activity_model`, which is
 * what its policy is then registered against.
 */
final class CustomActivity extends Activity {}
