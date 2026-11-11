<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    public static function bootAuditable()
    {
        static::created(function (Model $model) {
            $model->audit('created', null, $model->getAttributes());
        });

        static::updated(function (Model $model) {
            $model->audit('updated', $model->getOriginal(), $model->getChanges());
        });

        static::deleted(function (Model $model) {
            $model->audit('deleted', $model->getOriginal(), null);
        });
    }

    protected function audit(string $action, ?array $oldValues, ?array $newValues)
    {
        // Don't audit if running from console/seeding without user context
        // though we allow it if we want system logs. Let's capture if there is auth context.
        
        $userId = auth()->id();
        $ip = request()?->ip();
        $userAgent = request()?->userAgent();
        
        // Extract organisation_id. If model has it, grab it. If not, maybe grab from user.
        $organisationId = $this->organisation_id ?? auth()->user()?->organisation_id;

        AuditLog::create([
            'user_id' => $userId,
            'organisation_id' => $organisationId,
            'action' => $action,
            'auditable_type' => get_class($this),
            'auditable_id' => $this->getKey(),
            'old_values' => $oldValues ? $this->filterAuditValues($oldValues) : null,
            'new_values' => $newValues ? $this->filterAuditValues($newValues) : null,
            'ip_address' => $ip,
            'user_agent' => substr($userAgent, 0, 500),
        ]);
    }

    protected function filterAuditValues(array $values): array
    {
        $hidden = $this->getHidden();
        
        foreach ($hidden as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = '********';
            }
        }
        
        return $values;
    }
}
