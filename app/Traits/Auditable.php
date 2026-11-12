<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Records create / update / delete of a model in the audit log.
 *
 * - Updates store only the fields that changed (before and after), not the whole row.
 * - Hidden attributes (passwords, access codes…) are stored as "********".
 * - Models without an organisation_id can define auditOrganisationId().
 */
trait Auditable
{
    /** Changes to these alone are not worth an audit entry. */
    protected static array $auditIgnored = ['updated_at', 'created_at', 'remember_token'];

    public static function bootAuditable()
    {
        static::created(function (Model $model) {
            $model->audit('created', null, $model->getAttributes());
        });

        static::updated(function (Model $model) {
            $changes = array_diff_key($model->getChanges(), array_flip(static::$auditIgnored));

            if ($changes === []) {
                return;
            }

            $model->audit('updated', array_intersect_key($model->getOriginal(), $changes), $changes);
        });

        static::deleted(function (Model $model) {
            $model->audit('deleted', $model->getOriginal(), null);
        });
    }

    protected function audit(string $action, ?array $oldValues, ?array $newValues)
    {
        $organisationId = method_exists($this, 'auditOrganisationId')
            ? $this->auditOrganisationId()
            : ($this->organisation_id ?? auth()->user()?->organisation_id);

        AuditLog::create([
            'user_id' => auth()->id(),
            'organisation_id' => $organisationId,
            'action' => $action,
            'auditable_type' => get_class($this),
            'auditable_id' => $this->getKey(),
            'old_values' => $oldValues ? $this->filterAuditValues($oldValues) : null,
            'new_values' => $newValues ? $this->filterAuditValues($newValues) : null,
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 500),
        ]);
    }

    protected function filterAuditValues(array $values): array
    {
        foreach ($this->getHidden() as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = '********';
            }
        }

        return $values;
    }
}
