<?php

namespace App\Filament\Concerns;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * In Filament, overriding canEdit()/canDelete() only hides buttons: the edit page and delete
 * action check getEditAuthorizationResponse()/getDeleteAuthorizationResponse() instead, so a
 * record could still be opened by typing its URL. Resources that restrict editing with
 * canEdit()/canDelete() must use this trait so the same rules are enforced everywhere.
 *
 * Only use it on resources that override BOTH canEdit() and canDelete() (the default
 * implementations call these methods, which would loop).
 */
trait EnforcesRecordAccess
{
    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return static::canEdit($record)
            ? Response::allow()
            : Response::deny('You are not allowed to edit this record.');
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return static::canDelete($record)
            ? Response::allow()
            : Response::deny('You are not allowed to delete this record.');
    }
}
