<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Pencatat audit_logs (append-only). Dipanggil di dalam transaksi DB yang sama dengan perubahan datanya,
 * sehingga perubahan tanpa jejak audit tidak pernah tersimpan.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function log(string $action, ?Model $subject = null, ?array $before = null, ?array $after = null, ?string $reason = null, ?User $actor = null): AuditLog
    {
        $actor ??= auth()->user();
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return AuditLog::create([
            'actor_id'     => $actor?->id,
            'actor_role'   => $actor ? ($actor->role instanceof UserRole ? $actor->role->value : (string) $actor->role) : 'system',
            'action'       => $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id'   => $subject?->getKey(),
            'before'       => $before,
            'after'        => $after,
            'reason'       => $reason !== null ? mb_substr($reason, 0, 1000) : null,
            'ip'           => $request?->ip(),
            'user_agent'   => $request ? mb_substr((string) $request->userAgent(), 0, 500) : null,
        ]);
    }
}
