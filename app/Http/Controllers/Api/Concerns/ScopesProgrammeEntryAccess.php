<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\ProgrammeEntry;
use Illuminate\Http\Request;

/**
 * Shared "which specific entry can this caller touch" scoping rule for the
 * programme-entry family of controllers (entry itself, activities,
 * geography, keywords, government agreements).
 *
 * This is deliberately NOT the action gate — "can this user create/update/
 * view programme entries at all" is enforced by the route's
 * permission:programmes.* middleware. This trait only decides which rows a
 * user who already passed that gate may reach.
 *
 * The previous version of this check was
 * `in_array($user->role, ['nep_admin', 'nep_coordinator'])`, duplicated
 * identically across five controllers, which only ever recognized the two
 * built-in staff role names — a custom role's users (organisation_id null,
 * exactly like nep_admin/nep_coordinator today) failed it regardless of
 * what permissions they'd been granted.
 *
 * The data-driven replacement: a user who belongs to an organisation may
 * only touch that organisation's entries; a user who doesn't belong to one
 * (built-in staff today, any "staff-like" custom role tomorrow) may touch
 * any entry, exactly like nep_admin/nep_coordinator do now.
 *
 * programmes.view-own (see nep_staff in RolePermissionSeeder) narrows this
 * further and is checked first: a holder of it may only touch entries they
 * personally created, regardless of what hasOrganisationWideAccess() would
 * otherwise allow.
 */
trait ScopesProgrammeEntryAccess
{
    protected function userCanAccessProgrammeEntry(Request $request, ProgrammeEntry $programmeEntry): bool
    {
        $user = $request->user();

        if ($user->wantsOwnProgrammeEntriesOnly()) {
            return (int) $programmeEntry->created_by === (int) $user->id;
        }

        if ($user->hasOrganisationWideAccess()) {
            return true;
        }

        return (int) $programmeEntry->organisation_id === (int) $user->organisation_id;
    }
}
