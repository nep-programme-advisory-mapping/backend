<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Single write path for the audit trail. Call ::record() from a controller
 * right before a destructive action commits (delete/deactivate) so the
 * snapshot in `metadata` still reflects the record as it existed.
 */
class AuditLogger
{
    /**
     * @param Model $subject The record being acted on (e.g. the User/Role/Permission about to be deleted).
     * @param string $action Short verb, e.g. 'deleted'.
     * @param array<string, mixed> $metadata Extra context to snapshot alongside the record (e.g. key attributes).
     */
    public static function record(
        Model $subject,
        string $action,
        ?Request $request = null,
        array $metadata = [],
        ?string $description = null
    ): AuditLog {
        $request ??= request();
        $actor = $request?->user();

        return AuditLog::create([
            'user_id' => $actor?->id,
            'user_label' => $actor ? ($actor->name ?? $actor->email) : 'system',
            'action' => $action,
            'auditable_type' => class_basename($subject),
            'auditable_id' => $subject->getKey(),
            'description' => $description ?? sprintf(
                '%s %s %s (#%s)',
                $actor ? ($actor->name ?? $actor->email) : 'System',
                $action,
                class_basename($subject),
                $subject->getKey()
            ),
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'created_at' => now(),
        ]);
    }
}
