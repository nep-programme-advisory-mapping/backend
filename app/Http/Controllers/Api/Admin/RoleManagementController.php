<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin - Roles & Permissions')]
#[OA\Schema(
    schema: 'Role',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'nep_admin'),
        new OA\Property(property: 'display_name', type: 'string', example: 'NEP Administrator'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Full system access'),
        new OA\Property(property: 'is_system', type: 'boolean', example: true),
        new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(ref: '#/components/schemas/Permission')),
        new OA\Property(property: 'users_count', type: 'integer', example: 3),
    ]
)]
#[OA\Schema(
    schema: 'Permission',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'users.view'),
        new OA\Property(property: 'display_name', type: 'string', example: 'View Users'),
        new OA\Property(property: 'group', type: 'string', example: 'Users'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'View user accounts'),
    ]
)]
class RoleManagementController extends Controller
{
    #[OA\Get(
        path: '/admin/roles',
        summary: 'List roles',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Roles & Permissions'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Roles with permissions',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Role'))
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $roles = Role::query()
            ->withCount('users')
            ->with('permissions')
            ->orderBy('name')
            ->get();

        return response()->json($roles);
    }

    #[OA\Get(
        path: '/admin/roles/{role}',
        summary: 'Show role',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Roles & Permissions'],
        parameters: [
            new OA\Parameter(name: 'role', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Role details', content: new OA\JsonContent(ref: '#/components/schemas/Role')),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Role not found'),
        ]
    )]
    public function show(Role $role): JsonResponse
    {
        $role->load('permissions', 'users');

        return response()->json($role);
    }

    #[OA\Post(
        path: '/admin/roles',
        summary: 'Create role',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Roles & Permissions'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'display_name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'programme_manager'),
                    new OA\Property(property: 'display_name', type: 'string', example: 'Programme Manager'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'is_system', type: 'boolean', example: false),
                    new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2]),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Role created', content: new OA\JsonContent(ref: '#/components/schemas/Role')),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'display_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_system' => ['boolean'],
            'is_super_admin' => ['boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        // Only an existing super admin may mint another super admin role —
        // otherwise any actor with roles.create could hand themselves
        // unlimited access via a freshly-created role.
        if (($data['is_super_admin'] ?? false) && ! $request->user()->isSuperAdmin()) {
            return response()->json([
                'message' => 'Only a super admin can create another super admin role.',
            ], 403);
        }

        $role = DB::transaction(function () use ($data) {
            $role = Role::create([
                'name' => $data['name'],
                'display_name' => $data['display_name'],
                'description' => $data['description'] ?? null,
                'is_system' => $data['is_system'] ?? false,
                'is_super_admin' => $data['is_super_admin'] ?? false,
            ]);

            if (isset($data['permissions'])) {
                $role->permissions()->sync($data['permissions']);
            }

            return $role->load('permissions');
        });

        return response()->json($role, 201);
    }

    #[OA\Patch(
        path: '/admin/roles/{role}',
        summary: 'Update role',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Roles & Permissions'],
        parameters: [
            new OA\Parameter(name: 'role', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'display_name', type: 'string', example: 'Programme Manager'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'is_system', type: 'boolean', example: false),
                    new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2]),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Role updated', content: new OA\JsonContent(ref: '#/components/schemas/Role')),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Role not found'),
            new OA\Response(response: 422, description: 'Validation failed, or modifying a system role identity (system roles may only have their permissions updated)'),
        ]
    )]
    public function update(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_system' => ['sometimes', 'boolean'],
            'is_super_admin' => ['sometimes', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $isSystem = $role->is_system;
        $updatingPermissions = array_key_exists('permissions', $data);
        $updatingSuperAdminFlag = array_key_exists('is_super_admin', $data);

        // System roles keep their identity — only their permission set (and,
        // for a super admin, the is_super_admin flag) may change.
        if ($isSystem && ! $updatingPermissions && ! $updatingSuperAdminFlag) {
            return response()->json([
                'message' => 'System roles cannot be modified.',
            ], 422);
        }

        if ($updatingSuperAdminFlag) {
            if (! $request->user()->isSuperAdmin()) {
                return response()->json([
                    'message' => 'Only a super admin can change the super admin flag on a role.',
                ], 403);
            }

            if ($role->is_super_admin && ! $data['is_super_admin'] && Role::superAdmin()->count() <= 1) {
                return response()->json([
                    'message' => 'Cannot remove super admin status from the last remaining super admin role.',
                ], 422);
            }
        }

        $role = DB::transaction(function () use ($role, $data, $isSystem, $updatingPermissions, $updatingSuperAdminFlag) {
            if (! $isSystem) {
                $role->update([
                    'display_name' => $data['display_name'] ?? $role->display_name,
                    'description' => $data['description'] ?? $role->description,
                    'is_system' => $data['is_system'] ?? $role->is_system,
                ]);
            }

            if ($updatingSuperAdminFlag) {
                $role->update(['is_super_admin' => $data['is_super_admin']]);
            }

            if ($updatingPermissions) {
                // A super admin role always holds every permission — its
                // permission set isn't independently editable.
                $role->permissions()->sync($role->is_super_admin ? Permission::pluck('id') : ($data['permissions'] ?? []));
            }

            return $role->load('permissions');
        });

        return response()->json($role);
    }

    #[OA\Delete(
        path: '/admin/roles/{role}',
        summary: 'Delete role',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Roles & Permissions'],
        parameters: [
            new OA\Parameter(name: 'role', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Role deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Role not found'),
            new OA\Response(response: 422, description: 'System role cannot be deleted'),
        ]
    )]
    public function destroy(Role $role): JsonResponse
    {
        if ($role->name === 'nep_admin') {
            return response()->json([
                'message' => 'The NEP Administrator role cannot be deleted.',
            ], 422);
        }

        if ($role->is_super_admin && Role::superAdmin()->count() <= 1) {
            return response()->json([
                'message' => 'Cannot delete the last remaining super admin role.',
            ], 422);
        }

        // A role still assigned to users is not "safe to delete" — the pivot
        // rows would cascade-delete silently and those users would fall back
        // to their legacy `role` string with no permissions attached. Require
        // reassignment first so nobody is accidentally left without access.
        $usersCount = $role->users()->count();
        if ($usersCount > 0) {
            return response()->json([
                'message' => "This role is still assigned to {$usersCount} user(s). Reassign them to another role before deleting.",
            ], 422);
        }

        AuditLogger::record($role, 'deleted', metadata: [
            'name' => $role->name,
            'display_name' => $role->display_name,
        ]);

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully.',
        ]);
    }

    #[OA\Post(
        path: '/admin/roles/{role}/users/{user}',
        summary: 'Assign role to user',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Roles & Permissions'],
        parameters: [
            new OA\Parameter(name: 'role', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Role assigned'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Role or user not found'),
        ]
    )]
    public function assignToUser(Request $request, Role $role, User $user): JsonResponse
    {
        if ($role->is_super_admin && ! $request->user()->isSuperAdmin()) {
            return response()->json([
                'message' => 'Only a super admin can assign a super admin role.',
            ], 403);
        }

        $user->roles()->syncWithoutDetaching([$role->id]);

        // The `role` column is the user's single primary role (drives legacy
        // business logic and display); any dynamic role — not just the
        // original three — can become the primary role.
        $user->update(['role' => $role->name]);

        return response()->json([
            'message' => 'Role assigned to user successfully.',
            'user' => $user->load('roles'),
        ]);
    }

    #[OA\Delete(
        path: '/admin/roles/{role}/users/{user}',
        summary: 'Remove role from user',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Roles & Permissions'],
        parameters: [
            new OA\Parameter(name: 'role', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Role removed'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Role or user not found'),
            new OA\Response(response: 422, description: 'Primary role cannot be removed'),
        ]
    )]
    public function removeFromUser(Request $request, Role $role, User $user): JsonResponse
    {
        if ($user->role === $role->name) {
            return response()->json([
                'message' => 'The user primary role cannot be removed. Assign another system role first.',
            ], 422);
        }

        $user->roles()->detach($role->id);

        return response()->json([
            'message' => 'Role removed from user successfully.',
            'user' => $user->load('roles'),
        ]);
    }

    #[OA\Get(
        path: '/admin/permissions',
        summary: 'List permissions',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Roles & Permissions'],
        responses: [
            new OA\Response(response: 200, description: 'Permissions grouped by group'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function permissions(): JsonResponse
    {
        $permissions = Permission::orderBy('group')
            ->orderBy('name')
            ->get(['id', 'name', 'display_name', 'group', 'description']);

        $grouped = $permissions->groupBy('group');

        return response()->json($grouped);
    }

    #[OA\Post(
        path: '/admin/permissions',
        summary: 'Create permission',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Roles & Permissions'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'display_name', 'group'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'reports.export'),
                    new OA\Property(property: 'display_name', type: 'string', example: 'Export Reports'),
                    new OA\Property(property: 'group', type: 'string', example: 'Reports'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Permission created', content: new OA\JsonContent(ref: '#/components/schemas/Permission')),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
    public function storePermission(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name'],
            'display_name' => ['required', 'string', 'max:255'],
            'group' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $permission = Permission::create($data);

        return response()->json($permission, 201);
    }

    #[OA\Patch(
        path: '/admin/permissions/{permission}',
        summary: 'Update permission',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Roles & Permissions'],
        parameters: [
            new OA\Parameter(name: 'permission', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'display_name', type: 'string', example: 'Export Reports'),
                    new OA\Property(property: 'group', type: 'string', example: 'Reports'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Permission updated', content: new OA\JsonContent(ref: '#/components/schemas/Permission')),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Permission not found'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
    public function updatePermission(Request $request, Permission $permission): JsonResponse
    {
        $data = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:255'],
            'group' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $permission->update($data);

        return response()->json($permission);
    }

    #[OA\Delete(
        path: '/admin/permissions/{permission}',
        summary: 'Delete permission',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Roles & Permissions'],
        parameters: [
            new OA\Parameter(name: 'permission', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Permission deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Permission not found'),
        ]
    )]
    public function destroyPermission(Permission $permission): JsonResponse
    {
        AuditLogger::record($permission, 'deleted', metadata: [
            'name' => $permission->name,
            'display_name' => $permission->display_name,
        ]);

        $permission->delete();

        return response()->json([
            'message' => 'Permission deleted successfully.',
        ]);
    }
}
