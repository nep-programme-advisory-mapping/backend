<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\UserInvitationMail;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "User",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "organisation_id", type: "integer", example: 12, nullable: true),
        new OA\Property(property: "name", type: "string", example: "Jane Doe"),
        new OA\Property(property: "email", type: "string", format: "email", example: "jane@example.com"),
        new OA\Property(property: "role", type: "string", enum: ["nep_admin", "nep_coordinator", "member_org"]),
        new OA\Property(property: "status", type: "string", enum: ["active", "inactive"]),
        new OA\Property(property: "permissions", type: "array", nullable: true, description: "Individually assigned permissions (authoritative when present)", items: new OA\Items(ref: "#/components/schemas/Permission")),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ]
)]
class UserManagementController extends Controller
{
    #[OA\Get(
        path: "/admin/users",
        tags: ["Admin - User Management"],
        summary: "List user accounts",
        description: "Only accessible to nep_admin. Supports filtering by role and status.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "role", in: "query", required: false,
                schema: new OA\Schema(type: "string", enum: ["nep_admin", "nep_coordinator", "member_org"])
            ),
            new OA\Parameter(name: "status", in: "query", required: false,
                schema: new OA\Schema(type: "string", enum: ["active", "inactive"])
            ),
            // new OA\Parameter(name: "per_page", in: "query", required: false,
            //     schema: new OA\Schema(type: "integer", default: 25)
            // ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Paginated list of users",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/User")),
                        new OA\Property(property: "current_page", type: "integer"),
                        new OA\Property(property: "total", type: "integer"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden — not a nep_admin"),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->with(['organisation:id,name', 'roles'])
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->query('role')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 25));

        return response()->json($users);
    }

    #[OA\Get(
        path: "/admin/users/{user}",
        tags: ["Admin - User Management"],
        summary: "Show user account",
        description: "Returns a user with organisation, roles and role permissions.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "user", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(response: 200, description: "User details", content: new OA\JsonContent(ref: "#/components/schemas/User")),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 404, description: "User not found"),
        ]
    )]
    public function show(User $user): JsonResponse
    {
        return response()->json($user->load([
            'organisation:id,name',
            'roles.permissions',
            'permissions',
        ]));
    }

    #[OA\Post(
        path: "/admin/users",
        tags: ["Admin - User Management"],
        summary: "Create a user account",
        description: "Creates an NEP staff or member organisation account. If no password is supplied, a temporary password is generated and returned once in the response.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "role"],
                properties: [
                    new OA\Property(property: "organisation_id", type: "integer", nullable: true, example: 1),
                    new OA\Property(property: "name", type: "string", example: "Jane Doe"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "jane@example.com"),
                    new OA\Property(property: "password", type: "string", nullable: true, example: "optional-plain-text"),
                    new OA\Property(property: "role", type: "string", enum: ["nep_admin", "nep_coordinator", "member_org"]),
                    new OA\Property(property: "permissions", type: "array", nullable: true, description: "Permission IDs to assign directly to this user. Omit to keep role-derived permissions.", items: new OA\Items(type: "integer")),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Account created",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Account created."),
                        new OA\Property(property: "user", ref: "#/components/schemas/User"),
                        new OA\Property(property: "temporary_password", type: "string", nullable: true, example: "Xk9\$mQ2vLp4T"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden — not a nep_admin"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(User::validationRules());

        if ($error = $this->blockPrivilegeEscalation($request, $data['role'], $data['permissions'] ?? null)) {
            return $error;
        }

        $tempPassword = null;
        if (empty($data['password'])) {
            $tempPassword = Str::password(12);
            $data['password'] = $tempPassword;
        }

        $plainPassword = $data['password'];
        $permissionIds = $data['permissions'] ?? null;
        unset($data['permissions']);

        $user = DB::transaction(function () use ($data, $plainPassword, $permissionIds) {
            $user = User::create([
                ...$data,
                'password' => Hash::make($plainPassword),
                'status' => User::STATUS_ACTIVE,
            ]);
            $user->syncLegacyRole();

            if ($permissionIds !== null) {
                $user->permissions()->sync($permissionIds);
            }

            return $user;
        });

        $loginUrl = config('app.frontend_url') . '/login';

        try {
            Mail::to($user->email)->send(new UserInvitationMail(
                $user->name,
                $user->email,
                $plainPassword,
                $loginUrl
            ));
        } catch (\Exception $e) {
            Log::error('Failed to send invitation email on account creation', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Account created. Invitation email has been sent.',
            'user' => $user->fresh(['organisation', 'permissions']),
            'temporary_password' => $tempPassword,
        ], 201);
    }

    #[OA\Post(
        path: "/admin/users/invite",
        tags: ["Admin - User Management"],
        summary: "Invite a new user via email",
        description: "Creates a new user account with a default password and sends an invitation email. Prevents duplicate invitations for existing email addresses.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "role"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "John Doe"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                    new OA\Property(property: "role", type: "string", enum: ["nep_admin", "nep_coordinator", "member_org"], example: "member_org"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "User invited successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "User created successfully. Invitation email has been sent."),
                        new OA\Property(property: "user", ref: "#/components/schemas/User"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden — not a nep_admin"),
            new OA\Response(response: 422, description: "Validation error or email already exists"),
        ]
    )]
    public function invite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        if ($error = $this->blockPrivilegeEscalation($request, $data['role'], null)) {
            return $error;
        }

        $defaultPassword = Str::password(12);
        $loginUrl = config('app.frontend_url', rtrim($request->getSchemeAndHttpHost(), '/')) . '/login';

        try {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($defaultPassword),
                'role' => $data['role'],
                'status' => User::STATUS_ACTIVE,
            ]);
            $user->syncLegacyRole();

            Mail::to($user->email)->send(new UserInvitationMail(
                $user->name,
                $user->email,
                $defaultPassword,
                $loginUrl
            ));

            return response()->json([
                'message' => 'User created successfully. Invitation email has been sent.',
                'user' => $user->fresh('organisation'),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to send invitation email', [
                'email' => $data['email'],
                'error' => $e->getMessage(),
            ]);

            // If user was created but email failed, still return success
            if (isset($user)) {
                return response()->json([
                    'message' => 'User created successfully. Invitation email has been sent.',
                    'user' => $user->fresh('organisation'),
                ], 201);
            }

            return response()->json([
                'message' => 'Failed to create user.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Patch(
        path: "/admin/users/{user}",
        tags: ["Admin - User Management"],
        summary: "Edit a user account",
        description: "Update user account fields. Leave password blank to keep the current password.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "user", in: "path", required: true, description: "User ID", schema: new OA\Schema(type: "integer")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "organisation_id", type: "integer", nullable: true),
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "email", type: "string", format: "email"),
                    new OA\Property(property: "password", type: "string", nullable: true, description: "Leave blank to keep current password"),
                    new OA\Property(property: "role", type: "string", enum: ["nep_admin", "nep_coordinator", "member_org"]),
                    new OA\Property(property: "status", type: "string", enum: ["active", "inactive"]),
                    new OA\Property(property: "permissions", type: "array", nullable: true, description: "Permission IDs. An empty array clears all individually assigned permissions (falls back to role defaults).", items: new OA\Items(type: "integer")),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Account updated",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Account updated."),
                        new OA\Property(property: "user", ref: "#/components/schemas/User"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden — not a nep_admin"),
            new OA\Response(response: 404, description: "User not found"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(User::validationRules(update: true, userId: $user->id));

        // Users cannot modify their own role, status or permission grants —
        // prevents self-escalation and self-lockout via the admin API, even
        // though they hold users.update. Profile fields (name/email/password)
        // remain editable on self.
        if ($request->user()->id === $user->id) {
            $selfRestricted = array_intersect(['role', 'status', 'permissions'], array_keys($data));
            if (! empty($selfRestricted)) {
                return response()->json([
                    'message' => 'You cannot change your own role, status, or permissions.',
                ], 422);
            }
        }

        if (array_key_exists('role', $data) || array_key_exists('permissions', $data)) {
            $error = $this->blockPrivilegeEscalation(
                $request,
                $data['role'] ?? $user->role,
                $data['permissions'] ?? null,
            );
            if ($error) {
                return $error;
            }
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $permissionIds = $data['permissions'] ?? null;
        unset($data['permissions']);

        DB::transaction(function () use ($user, $data, $permissionIds) {
            $user->update($data);

            if (array_key_exists('role', $data)) {
                $user->roles()->detach();
                $user->syncLegacyRole();
            }

            if ($permissionIds !== null) {
                $user->permissions()->sync($permissionIds);
            }

            if (($data['status'] ?? null) === User::STATUS_INACTIVE) {
                $user->tokens()->delete();
            }
        });

        return response()->json([
            'message' => 'Account updated.',
            'user' => $user->fresh(['organisation', 'permissions']),
        ]);
    }

    #[OA\Post(
        path: "/admin/users/{user}/deactivate",
        tags: ["Admin - User Management"],
        summary: "Deactivate a user account",
        description: "Sets status to inactive and revokes all API tokens. An admin cannot deactivate their own account.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "user", in: "path", required: true, description: "User ID", schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Account deactivated",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Account deactivated."),
                        new OA\Property(property: "user", ref: "#/components/schemas/User"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden — not a nep_admin"),
            new OA\Response(response: 422, description: "Cannot deactivate your own account"),
            new OA\Response(response: 404, description: "User not found"),
        ]
    )]
    public function deactivate(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'You cannot deactivate your own account.',
            ], 422);
        }

        DB::transaction(function () use ($user) {
            $user->update(['status' => User::STATUS_INACTIVE]);
            $user->tokens()->delete();
        });

        return response()->json([
            'message' => 'Account deactivated.',
            'user' => $user->fresh(),
        ]);
    }

    #[OA\Post(
        path: "/admin/users/{user}/reactivate",
        tags: ["Admin - User Management"],
        summary: "Reactivate a previously deactivated user account",
        description: "Sets the account status back to active.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "user", in: "path", required: true, description: "User ID", schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Account reactivated",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Account reactivated."),
                        new OA\Property(property: "user", ref: "#/components/schemas/User"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden — not a nep_admin"),
            new OA\Response(response: 404, description: "User not found"),
        ]
    )]
    public function reactivate(User $user): JsonResponse
    {
        $user->update(['status' => User::STATUS_ACTIVE]);

        return response()->json([
            'message' => 'Account reactivated.',
            'user' => $user->fresh(),
        ]);
    }

    #[OA\Post(
        path: "/admin/users/{user}/reset-credentials",
        tags: ["Admin - User Management"],
        summary: "Reset a user's credentials",
        description: "Generates a new temporary password server-side and revokes all existing tokens. The temporary password is returned once in the response for the admin to relay securely.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "user", in: "path", required: true, description: "User ID", schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Credentials reset",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Credentials reset. Share the temporary password with the user securely."),
                        new OA\Property(property: "temporary_password", type: "string", example: "Xk9\$mQ2vLp4T"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden — not a nep_admin"),
            new OA\Response(response: 404, description: "User not found"),
        ]
    )]
    public function resetCredentials(User $user): JsonResponse
    {
        $tempPassword = Str::password(12);

        DB::transaction(function () use ($user, $tempPassword) {
            $user->update(['password' => Hash::make($tempPassword)]);
            $user->tokens()->delete();
        });

        return response()->json([
            'message' => 'Credentials reset. Share the temporary password with the user securely.',
            'temporary_password' => $tempPassword,
        ]);
    }

    #[OA\Delete(
        path: "/admin/users/{user}",
        tags: ["Admin - User Management"],
        summary: "Permanently delete a user account",
        description: "Removes the account. Blocked for self-deletion and for the last remaining super admin. Prefer deactivation when the user has historical records attached.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "user", in: "path", required: true, description: "User ID", schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Account deleted"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 404, description: "User not found"),
            new OA\Response(response: 422, description: "Cannot delete this account"),
        ]
    )]
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'You cannot delete your own account.',
            ], 422);
        }

        if ($user->isSuperAdmin()) {
            $otherSuperAdmins = User::query()
                ->where('id', '!=', $user->id)
                ->get(['id', 'role'])
                ->filter(fn (User $u) => $u->isSuperAdmin())
                ->count();

            if ($otherSuperAdmins === 0) {
                return response()->json([
                    'message' => 'Cannot delete the last super admin account.',
                ], 422);
            }
        }

        try {
            DB::transaction(function () use ($request, $user) {
                AuditLogger::record($user, 'deleted', $request, metadata: [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ]);

                $user->tokens()->delete();
                $user->roles()->detach();
                $user->permissions()->detach();
                $user->delete();
            });
        } catch (QueryException $e) {
            // The user has related records (programme entries, advisory notes,
            // etc.) that a hard delete would orphan — deactivating preserves
            // history and referential integrity instead.
            Log::warning('Blocked hard delete of user with related records', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'This account has associated records and cannot be deleted. Deactivate it instead.',
            ], 422);
        }

        return response()->json([
            'message' => 'Account deleted.',
        ]);
    }

    /**
     * Prevent a non-super-admin actor from granting a super admin role, or
     * from handing out a permission they do not themselves hold. Super
     * admins are exempt — they can manage the full permission surface.
     */
    private function blockPrivilegeEscalation(Request $request, ?string $roleName, ?array $permissionIds): ?JsonResponse
    {
        $actor = $request->user();
        if ($actor->isSuperAdmin()) {
            return null;
        }

        if ($roleName) {
            $targetRole = Role::query()->where('name', $roleName)->first();
            if ($targetRole?->is_super_admin) {
                return response()->json([
                    'message' => 'Only a super admin can assign a super admin role.',
                ], 403);
            }
        }

        if (! empty($permissionIds)) {
            $names = Permission::query()->whereIn('id', $permissionIds)->pluck('name');
            foreach ($names as $name) {
                if (! $actor->hasPermission($name)) {
                    return response()->json([
                        'message' => "You cannot grant the '{$name}' permission because you do not hold it yourself.",
                    ], 403);
                }
            }
        }

        return null;
    }
}
