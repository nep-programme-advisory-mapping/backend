<?php

use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrganisationProfileController;
use App\Http\Controllers\Api\ProgrammeEntryController;
use App\Http\Controllers\Api\ProgrammeActivityController;
use App\Http\Controllers\Api\ProgrammeGeographyController;
use App\Http\Controllers\Api\EntryKeywordController;
use App\Http\Controllers\Api\GovernmentAgreementController;
use App\Http\Controllers\Api\Admin\OrganisationController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Admin\RoleManagementController;
use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MapEntryController;
use App\Http\Controllers\Api\MapExportController;
use App\Http\Controllers\Api\MapGeoJsonController;
use App\Http\Controllers\Api\AdviserMapOverlapController;
use App\Http\Controllers\Api\TaxonomyController;
use App\Http\Controllers\Api\AdviserSubmissionController;
use App\Http\Controllers\Api\ProgrammeActivityAiController;
use App\Http\Controllers\Api\AdviserAnalysisController;
use App\Http\Controllers\Api\AdviserProgrammeEntryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PolicyDocumentController as ApiPolicyDocumentController;
use App\Http\Controllers\Api\RefdataController;
use App\Http\Controllers\Api\PasswordResetController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Throttled: unauthenticated endpoints that touch user accounts by email —
// without a limit, either is usable to brute-force/enumerate accounts.
Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])
    ->middleware('throttle:5,1');
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])
    ->middleware('throttle:5,1');
Route::get('/adviser/submissions/{advisoryNote}/file', [AdviserSubmissionController::class, 'downloadFile']);

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/provinces', [LocationController::class, 'index']);
    Route::get('/provinces/{province}/districts', [LocationController::class, 'districts']);
    Route::get('/districts/{district}/communes', [LocationController::class, 'communes']);
    Route::get('/communes/{commune}/villages', [LocationController::class, 'villages']);

    Route::get('/refdata/education-levels', [RefdataController::class, 'educationLevels']);
    Route::get('/refdata/budget-bands', [RefdataController::class, 'budgetBands']);
    Route::get('/refdata/counterpart-agencies', [RefdataController::class, 'counterpartAgencies']);

    Route::get('/user', function (Request $request) {
        $user = $request->user()->load('organisation:id,name');

        return array_merge(
            $user->only('id', 'name', 'email', 'role', 'status', 'organisation_id'),
            [
                'organisation' => $user->organisation
                    ? ['id' => $user->organisation->id, 'name' => $user->organisation->name]
                    : null,
                'permissions' => $user->effectivePermissionNames(),
                'is_super_admin' => $user->isSuperAdmin(),
            ]
        );
    });
    Route::get('/session', [AuthController::class, 'session']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::patch('/change-password', [AuthController::class, 'changePassword']);

    // Session/device management — view and revoke individual logged-in devices
    Route::get('/sessions', function (Request $request) {
        return $request->user()->tokens()
            ->select('id', 'name', 'last_used_at', 'created_at')
            ->get();
    });

    Route::delete('/sessions/{tokenId}', function (Request $request, $tokenId) {
        $deleted = $request->user()->tokens()->where('id', $tokenId)->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Session not found.'], 404);
        }

        return response()->json(['message' => 'Session revoked.']);
    });

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    // These previously carried no permission middleware at all — only
    // auth:sanctum (must be logged in) — relying entirely on the
    // controller's internal org-scoping to keep entries private. Any
    // authenticated user, including one with zero granted permissions,
    // could reach them. Explicit programmes.* gates close that gap.
    //
    // programmes.view,programmes.view-own (an "either" gate, same syntax as
    // programmes.create,programmes.update above): a holder of only the
    // narrower view-own permission (nep_staff) must still pass this
    // middleware to reach the controller, which is what actually narrows
    // the rows to their own — see ScopesProgrammeEntryAccess and
    // ProgrammeEntryController::entriesByStatus(). The two
    // organisation-wide browse routes below deliberately keep
    // programmes.view only: browsing an entire organisation's entries
    // doesn't fit "only what I personally created" (see
    // ProgrammeEntryController::index()'s own guard for a role granted both).
    Route::middleware('permission:programmes.view,programmes.view-own')->group(function () {
        Route::get('/programme-entries', [ProgrammeEntryController::class, 'getAll']);
        Route::get('/programme-entries/draft', [ProgrammeEntryController::class, 'draft']);
        Route::get('/programme-entries/my-drafts', [ProgrammeEntryController::class, 'myDrafts']);
        Route::get('/programme-entries/submitted', [ProgrammeEntryController::class, 'submitted']);
        Route::get('/programme-entries/{programmeEntry}', [ProgrammeEntryController::class, 'show']);
    });
    // Exporting is a separate capability from viewing — a role can have one
    // without the other (see programmes.export/-all in RolePermissionSeeder).
    Route::get('/programme-entries/{programmeEntry}/pdf', [ProgrammeEntryController::class, 'exportPdf'])->middleware('permission:programmes.export');
    Route::post('/programme-entries', [ProgrammeEntryController::class, 'store'])->middleware('permission:programmes.create');
    Route::put('/programme-entries/{programmeEntry}', [ProgrammeEntryController::class, 'update'])->middleware('permission:programmes.update');
    Route::get('/organisations/{organisation}/programme-entries', [ProgrammeEntryController::class, 'index'])->middleware('permission:programmes.view');
    Route::get('/organisations/{organisation}/programme-entries/pdf', [ProgrammeEntryController::class, 'exportOrganisationProgrammesPdf'])->middleware('permission:programmes.export-all');

    // Self-service — always scoped to the caller's own organisation (no id
    // in the URL to manipulate), so this is deliberately a *different*
    // permission from organisations.view/update, which is the unscoped
    // "manage any organisation" admin capability (/admin/organisations/*).
    Route::get('/organisations/me', [OrganisationProfileController::class, 'show'])->middleware('permission:organisation-profile.view');
    Route::patch('/organisations/me', [OrganisationProfileController::class, 'update'])->middleware('permission:organisation-profile.update');

    Route::patch('/programme-entries/{programmeEntry}/verify', [ProgrammeEntryController::class, 'verify'])
        ->middleware('permission:programmes.verify');

    // Core programme-entry authoring workflow — gated by the same
    // programmes.create/update permissions used elsewhere, so any role
    // (built-in or admin-created) granted those abilities can use it. This
    // used to be a hardcoded role:nep_admin,nep_coordinator,member_org list,
    // which silently 403'd any dynamically-created role.
    Route::middleware('permission:programmes.create,programmes.update')->group(function () {
        Route::get('/programme-entries/{programmeEntry}/activities', [ProgrammeActivityController::class, 'index']);
        Route::post('/programme-entries/{programmeEntry}/activities', [ProgrammeActivityController::class, 'store']);
        Route::put('/programme-entries/{programmeEntry}/keywords', [EntryKeywordController::class, 'store']);
        Route::put('/programme-entries/{programmeEntry}/geography', [ProgrammeGeographyController::class, 'store']);
        Route::put('/programme-entries/{programmeEntry}/government-agreements', [GovernmentAgreementController::class, 'store']);

        Route::post('/programme-entries/suggest-activities', [ProgrammeActivityAiController::class, 'suggestActivities'])->middleware('throttle:10,1');
        Route::post('/programme-entries/fetch-url', [ProgrammeActivityAiController::class, 'fetchUrl'])->middleware('throttle:20,1');
        Route::post('/programme-entries/ai-autofill', [ProgrammeActivityAiController::class, 'aiAutofill'])->middleware('throttle:10,1');
    });

    Route::middleware('permission:programmes.view,programmes.view-own')->group(function () {
        Route::get('/programme-entries/{programmeEntry}/geography', [ProgrammeGeographyController::class, 'index']);
        Route::get('/programme-entries/{programmeEntry}/government-agreements', [GovernmentAgreementController::class, 'index']);
    });

    // Anyone entering programme data needs to read the taxonomy to fill out
    // the form, independent of taxonomy management permissions.
    Route::get('/taxonomy/categories', [TaxonomyController::class, 'listCategories'])
        ->middleware('permission:taxonomy.view');

    // Member orgs can view the delivered advisory note for their own programme entries
    Route::get('/adviser/programme-entries/{programmeEntry}/advisory-note', [AdviserSubmissionController::class, 'showByProgrammeEntry'])
        ->middleware('permission:advisory.view');

    // Policy Library — migrated from hardcoded role:nep_admin,nep_coordinator,
    // member_org / role:nep_admin,nep_coordinator / role:nep_admin (the last
    // routes in the app still gated that way) to permission:policy.* so a
    // custom role can be granted any of these independently, the same as
    // every other resource.
    Route::middleware('permission:policy.view')->group(function () {
        Route::get('/policy-documents', [ApiPolicyDocumentController::class, 'index']);
        Route::get('/policy-documents/{policyDocument}', [ApiPolicyDocumentController::class, 'show']);
        Route::get('/policy-documents/{policyDocument}/file', [ApiPolicyDocumentController::class, 'getFile']);
    });

    Route::post('/policy-documents', [ApiPolicyDocumentController::class, 'store'])
        ->middleware('permission:policy.create');
    Route::patch('/policy-documents/{policyDocument}', [ApiPolicyDocumentController::class, 'update'])
        ->middleware('permission:policy.update');
    Route::delete('/policy-documents/{policyDocument}', [ApiPolicyDocumentController::class, 'destroy'])
        ->middleware('permission:policy.delete');

    // Dashboard — was role:nep_admin,nep_coordinator; dashboard.view is
    // already seeded to those two roles, so this preserves default access
    // while making it grantable to any role instead of hardcoded.
    Route::middleware('permission:dashboard.view')->group(function () {
        Route::get('/provinces/counts', [LocationController::class, 'provinceProgrammeCounts']);
        Route::get('/taxonomy/categories/counts', [TaxonomyController::class, 'categoryProgrammeCounts']);
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        Route::get('/dashboard/recent-activity', [DashboardController::class, 'recentActivity']);
        // Province-level GeoJSON is a dashboard-style aggregate visualization,
        // not org-scoped browsing (see /map/entries below) — kept alongside
        // dashboard.view rather than reports.view so it stays staff-only by
        // default, matching its own docs.
        Route::get('/map/entries/geojson', [MapGeoJsonController::class, 'geojson']);
    });

    // Map query/export — reports.view/reports.export are seeded to
    // nep_admin, nep_coordinator, AND member_org (BuildsMapQuery already
    // scoped member_org's results to their own organisation; the route
    // itself just never actually allowed them through before).
    Route::middleware('permission:reports.view')->group(function () {
        Route::get('/map/entries', [MapEntryController::class, 'index']);
    });
    Route::middleware('permission:reports.export')->group(function () {
        Route::get('/map/entries/export', [MapExportController::class, 'export']);
        Route::get('/map/entries/export/pdf', [MapExportController::class, 'exportPdf']);
    });

    // Adviser — advisory.view-all/create/update/deliver, previously all
    // hardcoded to role:nep_admin,nep_coordinator regardless of what a
    // custom role was actually granted. Deliberately advisory.view-all, not
    // advisory.view: the latter is what a member_org holds to see the
    // delivered note for their own entry — it must not also unlock browsing
    // every organisation's in-progress submissions here.
    Route::middleware('permission:advisory.view-all')->group(function () {
        Route::get('/adviser/submissions', [AdviserSubmissionController::class, 'index']);
        Route::get('/adviser/coordinators', [AdviserSubmissionController::class, 'coordinators']);
        Route::get('/adviser/submissions/{advisoryNote}', [AdviserSubmissionController::class, 'show']);
        Route::get('/adviser/submissions/{advisoryNote}/export-pdf', [AdviserSubmissionController::class, 'exportPdf']);
        Route::post('/adviser/submissions/{advisoryNote}/file-token', [AdviserSubmissionController::class, 'fileToken']);
        Route::get('/adviser/submissions/{id}/parse-document', [AdviserAnalysisController::class, 'parseDocument']);
    });

    Route::middleware('permission:advisory.create')->group(function () {
        Route::post('/adviser/submissions', [AdviserSubmissionController::class, 'store'])
            ->middleware('throttle:10,1');
        Route::get('/adviser/programme-entries', [AdviserSubmissionController::class, 'listProgrammeEntries']);
    });

    Route::middleware('permission:advisory.create,advisory.update')->group(function () {
        Route::post('/adviser/map/overlap-query', [AdviserMapOverlapController::class, 'match']);
        Route::post('/adviser/submissions/{id}/parse-pdf', [AdviserAnalysisController::class, 'parsePdf']);
        Route::post('/adviser/submissions/{id}/extract-profile', [AdviserAnalysisController::class, 'extractProfile'])->middleware('throttle:10,1');
    });

    Route::middleware('permission:advisory.update')->group(function () {
        Route::patch('/adviser/submissions/{advisoryNote}', [AdviserSubmissionController::class, 'update']);
        Route::post('/adviser/submissions/{id}/generate-advisory-note', [AdviserAnalysisController::class, 'generateAdvisoryNote']);
        Route::post('/adviser/submissions/{id}/create-programme-entry', [AdviserProgrammeEntryController::class, 'createProgrammeEntry']);
    });

    Route::middleware('permission:advisory.deliver')->group(function () {
        Route::patch('/adviser/submissions/{advisoryNote}/deliver', [AdviserSubmissionController::class, 'markDelivered'])
            ->middleware('throttle:10,1');
    });

    Route::prefix('admin/users')->name('admin.users.')->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])->middleware('permission:users.view')->name('index');
        Route::get('/{user}', [UserManagementController::class, 'show'])->middleware('permission:users.view')->name('show');
        Route::post('/', [UserManagementController::class, 'store'])->middleware('permission:users.create')->name('store');
        Route::post('/invite', [UserManagementController::class, 'invite'])->middleware('permission:users.create')->name('invite');
        Route::patch('/{user}', [UserManagementController::class, 'update'])->middleware('permission:users.update')->name('update');
        Route::post('/{user}/deactivate', [UserManagementController::class, 'deactivate'])->middleware('permission:users.update')->name('deactivate');
        Route::post('/{user}/reactivate', [UserManagementController::class, 'reactivate'])->middleware('permission:users.update')->name('reactivate');
        Route::post('/{user}/reset-credentials', [UserManagementController::class, 'resetCredentials'])->middleware('permission:users.update')->name('reset-credentials');
        Route::delete('/{user}', [UserManagementController::class, 'destroy'])->middleware('permission:users.delete')->name('destroy');
    });

    Route::prefix('admin/roles')->name('admin.roles.')->group(function () {
        // Also reachable with users.create/users.update: the user-management
        // form needs the role list to populate its role picker even for an
        // admin who can manage users but doesn't otherwise manage roles.
        Route::get('/', [RoleManagementController::class, 'index'])->middleware('permission:roles.view,users.create,users.update')->name('index');
        Route::get('/{role}', [RoleManagementController::class, 'show'])->middleware('permission:roles.view')->name('show');
        Route::post('/', [RoleManagementController::class, 'store'])->middleware('permission:roles.create')->name('store');
        Route::patch('/{role}', [RoleManagementController::class, 'update'])->middleware('permission:roles.update')->name('update');
        Route::delete('/{role}', [RoleManagementController::class, 'destroy'])->middleware('permission:roles.delete')->name('destroy');
        
        Route::post('/{role}/users/{user}', [RoleManagementController::class, 'assignToUser'])->middleware('permission:roles.assign')->name('assign-to-user');
        Route::delete('/{role}/users/{user}', [RoleManagementController::class, 'removeFromUser'])->middleware('permission:roles.assign')->name('remove-from-user');
    });

    Route::prefix('admin/permissions')->name('admin.permissions.')->group(function () {
        Route::get('/', [RoleManagementController::class, 'permissions'])->middleware('permission:permissions.view')->name('index');
        Route::post('/', [RoleManagementController::class, 'storePermission'])->middleware('permission:permissions.create')->name('store');
        Route::patch('/{permission}', [RoleManagementController::class, 'updatePermission'])->middleware('permission:permissions.update')->name('update');
        Route::delete('/{permission}', [RoleManagementController::class, 'destroyPermission'])->middleware('permission:permissions.delete')->name('destroy');
    });

    Route::prefix('admin/audit-logs')->name('admin.audit-logs.')->group(function () {
        Route::get('/', [AuditLogController::class, 'index'])->middleware('permission:audit-logs.view')->name('index');
    });

    Route::middleware('permission:organisations.view')->prefix('admin/organisations')->name('admin.organisations.')->group(function () {
        Route::get('/', [OrganisationController::class, 'index'])->name('index');
        Route::get('/{organisation}', [OrganisationController::class, 'show'])->name('show');
    });

    Route::prefix('admin/organisations')->name('admin.organisations.write.')->group(function () {
        Route::post('/', [OrganisationController::class, 'store'])->middleware('permission:organisations.create')->name('store');
        Route::post('/{organisation}/logo', [OrganisationController::class, 'uploadLogo'])->middleware('permission:organisations.update')->name('logo');
        Route::put('/{organisation}', [OrganisationController::class, 'update'])->middleware('permission:organisations.update')->name('update');
        Route::patch('/{organisation}/deactivate', [OrganisationController::class, 'deactivate'])->middleware('permission:organisations.update')->name('deactivate');
        Route::patch('/{organisation}/activate', [OrganisationController::class, 'activate'])->middleware('permission:organisations.update')->name('activate');
    });

    Route::prefix('taxonomy')->group(function () {
        Route::post('/categories', [TaxonomyController::class, 'createCategory'])->middleware('permission:taxonomy.create');
        Route::put('/categories/{category}', [TaxonomyController::class, 'renameCategory'])->middleware('permission:taxonomy.update');
        Route::patch('/categories/{category}/deprecate', [TaxonomyController::class, 'deprecateCategory'])->middleware('permission:taxonomy.delete');

        Route::post('/subcategories', [TaxonomyController::class, 'createSubcategory'])->middleware('permission:taxonomy.create');
        Route::put('/subcategories/{subcategory}', [TaxonomyController::class, 'renameSubcategory'])->middleware('permission:taxonomy.update');
        Route::patch('/subcategories/{subcategory}/deprecate', [TaxonomyController::class, 'deprecateSubcategory'])->middleware('permission:taxonomy.delete');

        Route::post('/items', [TaxonomyController::class, 'createItem'])->middleware('permission:taxonomy.create');
        Route::put('/items/{item}', [TaxonomyController::class, 'renameItem'])->middleware('permission:taxonomy.update');
        Route::patch('/items/{item}/deprecate', [TaxonomyController::class, 'deprecateItem'])->middleware('permission:taxonomy.delete');

        Route::get('/other-entries', [TaxonomyController::class, 'listOtherEntries'])->middleware('permission:taxonomy.review-other');
    });
});
