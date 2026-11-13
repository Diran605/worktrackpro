<?php

namespace App\Filament\Concerns;

use App\Support\Permissions;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * An admin panel resource for a list with View / Add / Edit / Delete permissions
 * ("<list>.view", ".create", ".update", ".delete"; see Permissions::ENTITIES).
 *
 * Filament only uses canCreate()/canEdit()/canDelete() to hide buttons; pages and actions check the
 * get*AuthorizationResponse() methods. Both are defined here, so typing an address can't get round
 * a missing permission. Resources may override can*() to add more conditions (e.g. same organisation);
 * the responses follow those overrides.
 *
 * Replaces EnforcesRecordAccess on resources that use it (don't use both).
 */
trait HasCrudPermissions
{
    /** The list key, e.g. 'users'. */
    abstract public static function permissionEntity(): string;

    /** Does the signed-in user hold "<list>.<action>"? */
    public static function allowsAction(string $action): bool
    {
        return auth()->user()?->can(Permissions::crud(static::permissionEntity(), $action)) ?? false;
    }

    public static function canViewAny(): bool
    {
        return static::allowsAction('view');
    }

    public static function canCreate(): bool
    {
        return static::allowsAction('create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::allowsAction('update');
    }

    public static function canDelete(Model $record): bool
    {
        return static::allowsAction('delete');
    }

    public static function canDeleteAny(): bool
    {
        return static::allowsAction('delete');
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return static::canViewAny() ? Response::allow() : Response::deny('You are not allowed to view this list.');
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return static::canCreate() ? Response::allow() : Response::deny('You are not allowed to add records here.');
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return static::canEdit($record) ? Response::allow() : Response::deny('You are not allowed to edit this record.');
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return static::canDelete($record) ? Response::allow() : Response::deny('You are not allowed to delete this record.');
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return static::canDeleteAny() ? Response::allow() : Response::deny('You are not allowed to delete records here.');
    }
}
