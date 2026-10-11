<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes an append-only audit row for every create / update / delete.
 *
 * Sensitive columns are never stored. Failures are logged and swallowed so an
 * audit problem can never block a business transaction.
 */
class AuditLogObserver
{
    /** @var list<string> */
    private const REDACTED = ['password', 'remember_token', 'token'];

    /** @var list<string> */
    private const IGNORED = ['created_at', 'updated_at'];

    public function created(Model $model): void
    {
        $this->record('created', $model, null, $this->sanitize($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $changes = $this->sanitize($model->getChanges());

        if ($changes === []) {
            return;
        }

        $original = [];
        foreach (array_keys($changes) as $key) {
            $original[$key] = $model->getOriginal($key);
        }

        $this->record('updated', $model, $this->sanitize($original), $changes);
    }

    public function deleted(Model $model): void
    {
        $this->record('deleted', $model, $this->sanitize($model->getOriginal()), null);
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    private function record(string $event, Model $model, ?array $old, ?array $new): void
    {
        try {
            $request = app()->runningInConsole() ? null : request();

            AuditLog::query()->create([
                'event' => $event,
                'auditable_type' => $model->getMorphClass(),
                'auditable_id' => $model->getKey(),
                'user_id' => Auth::id(),
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
                'url' => $request ? mb_substr($request->method().' '.$request->path(), 0, 255) : null,
            ]);
        } catch (Throwable $e) {
            Log::warning('Audit log write failed', [
                'model' => $model::class,
                'id' => $model->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function sanitize(array $attributes): array
    {
        foreach (self::IGNORED as $key) {
            unset($attributes[$key]);
        }

        foreach (self::REDACTED as $key) {
            if (array_key_exists($key, $attributes)) {
                $attributes[$key] = '[redacted]';
            }
        }

        return $attributes;
    }
}
