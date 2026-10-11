<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only access to the audit trail (admins only, enforced by route middleware).
 */
class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'auditable_type' => ['nullable', 'string', 'max:120'],
            'auditable_id' => ['nullable', 'integer', 'min:1'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'event' => ['nullable', 'in:created,updated,deleted'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = AuditLog::query()
            ->with('user:id,name,email')
            ->when($validated['auditable_type'] ?? null, fn ($q, $type) => $q->where('auditable_type', 'like', '%'.class_basename($type)))
            ->when($validated['auditable_id'] ?? null, fn ($q, $id) => $q->where('auditable_id', $id))
            ->when($validated['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($validated['event'] ?? null, fn ($q, $event) => $q->where('event', $event))
            ->when($validated['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($validated['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', $to))
            ->latest('id');

        return ApiResponse::success($query->paginate(Pagination::perPage($request)));
    }
}
