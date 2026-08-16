<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AuditLogController extends Controller
{
    #[OA\Get(
        path: '/admin/audit-logs',
        summary: 'List audit log entries',
        description: 'Returns a paginated, most-recent-first log of destructive actions (deletes) taken through the API. Requires audit-logs.view.',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Audit Logs'],
        parameters: [
            new OA\Parameter(name: 'auditable_type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Audit log entries retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 25), 100) ?: 25;

        $query = AuditLog::query()->orderByDesc('created_at');

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', $request->input('auditable_type'));
        }

        return response()->json($query->paginate($perPage));
    }
}
