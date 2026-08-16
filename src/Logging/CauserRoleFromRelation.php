<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Logging;

use ElPandaPe\FilamentActivitylog\Contracts\ResolvesCauserRole;
use ElPandaPe\FilamentActivitylog\Support\RecordName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The role an author acted with, read from a `roles` relation on their own model.
 *
 * It knows nothing about where those roles come from: it asks the causer's model for the
 * relation, and asks the role it finds for its name the same way every other record in this
 * package is named — through `records`, so a role called by something other than `name` is
 * declared once and read everywhere.
 *
 * A model with no such relation answers null, and an application whose roles are not a
 * relation at all implements `ResolvesCauserRole` instead.
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

        // Qualified, and after dropping whatever order the relation carries: roles reached
        // through a pivot make a bare `id` ambiguous to PostgreSQL.
        $role = $roles->reorder()->orderBy($roles->getRelated()->getQualifiedKeyName())->first();

        return $role instanceof Model ? RecordName::of($role) : null;
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
