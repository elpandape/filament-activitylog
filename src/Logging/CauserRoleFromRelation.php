<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Logging;

use ElPandaPe\FilamentActivitylog\Contracts\ResolvesCauserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The role an author acted with, read from whatever `roles` relation their model has.
 *
 * Still no authorization system: this asks the causer's own model, so it works with Bouncer,
 * with `spatie/laravel-permission`, and with a relation somebody wrote by hand — and answers
 * null for a model that has none. An application whose roles live somewhere else implements
 * `ResolvesCauserRole` instead and names its class in the configuration.
 *
 * It costs one query per entry written, which is the price of sealing the role rather than
 * looking it up later; `logging.causer_role` set to null is how that is turned off.
 */
final class CauserRoleFromRelation implements ResolvesCauserRole
{
    /**
     * The first is the one sealed. A causer holding several roles acted with all of them,
     * and no order in the database says which one they were exercising: taking the oldest is
     * at least stable between one entry and the next.
     */
    public function __invoke(Model $causer): ?string
    {
        $roles = $this->relation($causer);

        if (! $roles instanceof Relation) {
            return null;
        }

        // Qualified, and after dropping whatever order the relation carries: Bouncer's roles
        // arrive through a pivot, where a bare `id` is ambiguous to PostgreSQL.
        $role = $roles->reorder()->orderBy($roles->getRelated()->getQualifiedKeyName())->first();

        if (! $role instanceof Model) {
            return null;
        }

        // `getAttributes()` and not `getAttribute()`: under `preventAccessingMissingAttributes`
        // asking for a column the model does not have throws, and `title` is Bouncer's, not
        // everybody's.
        $title = $role->getAttributes()['title'] ?? null;
        $name = $role->getAttributes()['name'] ?? null;

        return match (true) {
            is_string($title) && $title !== '' => $title,
            is_string($name) && $name !== '' => $name,
            default => null,
        };
    }

    /**
     * Through the relation's own constructor, never `$causer->roles`, which would lazy load
     * and throw under `preventLazyLoading`. A `roles()` that is not a relation is not one of
     * ours to read.
     *
     * @return Relation<Model, Model, mixed>|null
     */
    private function relation(Model $causer): ?Relation
    {
        if (! method_exists($causer, 'roles')) {
            return null;
        }

        $roles = $causer->roles();

        return $roles instanceof Relation ? $roles : null;
    }
}
