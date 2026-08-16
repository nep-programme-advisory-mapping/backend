<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganisationRequest;
use App\Http\Requests\UpdateOrganisationRequest;
use App\Models\Organisation;
use App\Services\ImageKitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "Organisation",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "name", type: "string", example: "Green Earth Nepal"),
        new OA\Property(property: "contact_name", type: "string", example: "Ram Sharma"),
        new OA\Property(property: "email", type: "string", format: "email", example: "info@greenearth.org"),
        new OA\Property(property: "member_since", type: "integer", example: 2020),
        new OA\Property(property: "status", type: "string", enum: ["active", "inactive"]),
        new OA\Property(property: "last_inactive_at", type: "string", format: "date-time", nullable: true),
        new OA\Property(property: "users_count", type: "integer", example: 5),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
        new OA\Property(property: "logo_path", type: "string", nullable: true, example: "6789abc123def456"),
        new OA\Property(property: "logo_url", type: "string", nullable: true, example: "https://ik.imagekit.io/rn6hppesw/organisation-logos/6789abc123def456"),
    ]
)]
class OrganisationController extends Controller
{
    #[OA\Get(
        path: "/admin/organisations",
        tags: ["Admin - Organisations"],
        summary: "List all organisations",
        description: "Only accessible to nep_admin. Supports filtering by status and search.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "status",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string", enum: ["active", "inactive"])
            ),
            new OA\Parameter(
                name: "search",
                in: "query",
                required: false,
                description: "Search by organisation name",
                schema: new OA\Schema(type: "string")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Paginated list of organisations",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/Organisation")),
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
        $organisations = Organisation::query()
            ->withCount('users')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . $request->query('search') . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('name', 'like', $term)
                        ->orWhere('contact_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('acronym', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 16));

        return response()->json($organisations);
    }

    #[OA\Post(
        path: "/admin/organisations/{organisation}/logo",
        tags: ["Admin - Organisations"],
        summary: "Upload or replace an organisation's logo",
        description: "Accepts an image file (jpg, png, or webp, max 10MB). Replaces any existing logo. Only accessible to nep_admin.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "organisation", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["logo"],
                    properties: [
                        new OA\Property(property: "logo", type: "string", format: "binary"),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Logo uploaded successfully",
                content: new OA\JsonContent(ref: "#/components/schemas/Organisation")
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden — not a nep_admin"),
            new OA\Response(response: 404, description: "Organisation not found"),
            new OA\Response(response: 422, description: "Validation failed — invalid file type or size"),
        ]
    )]

    public function uploadLogo(Request $request, Organisation $organisation, ImageKitService $imageKit): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        if ($organisation->logo_path) {
            $imageKit->delete($organisation->logo_path);
        }

        $file   = $request->file('logo');
        $fileId = $imageKit->upload($file->getRealPath(), $file->getClientOriginalName(), 'organisation-logos');

        $organisation->update(['logo_path' => $fileId]);

        Cache::forget("organisation:{$organisation->id}");

        return response()->json($organisation->fresh());
    }
    
    #[OA\Post(
        path: "/admin/organisations",
        tags: ["Admin - Organisations"],
        summary: "Create a new organisation",
        description: "Creates a new organisation. Optionally accepts a logo file (jpg, png, or webp, max 2MB) in the same request. Only accessible to nep_admin.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["name", "contact_name", "email", "member_since"],
                    properties: [
                        new OA\Property(property: "name", type: "string", example: "Green Earth Nepal"),
                        new OA\Property(property: "contact_name", type: "string", example: "Ram Sharma"),
                        new OA\Property(property: "email", type: "string", format: "email", example: "info@greenearth.org"),
                        new OA\Property(property: "member_since", type: "integer", example: 2020),
                        new OA\Property(property: "status", type: "string", enum: ["active", "inactive"], example: "active"),
                        new OA\Property(property: "logo", type: "string", format: "binary", description: "Optional logo image (jpg, png, webp, max 10MB)"),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Organisation created successfully", content: new OA\JsonContent(ref: "#/components/schemas/Organisation")),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden — not a nep_admin"),
            new OA\Response(response: 422, description: "Validation failed"),
        ]
    )]
    public function store(StoreOrganisationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['status'] = $validated['status'] ?? 'active';

        if ($request->hasFile('logo')) {
            $imageKit = app(ImageKitService::class);
            $file = $request->file('logo');
            $validated['logo_path'] = $imageKit->upload($file->getRealPath(), $file->getClientOriginalName(), 'organisation-logos');
        }

        $organisation = Organisation::create($validated);

        return response()->json($organisation, 201);
    }

    #[OA\Get(
        path: "/admin/organisations/{organisation}",
        tags: ["Admin - Organisations"],
        summary: "Get a single organisation",
        description: "Only accessible to nep_admin.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "organisation", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Organisation details", content: new OA\JsonContent(ref: "#/components/schemas/Organisation")),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden — not a nep_admin"),
            new OA\Response(response: 404, description: "Organisation not found"),
        ]
    )]
    public function show(Organisation $organisation): JsonResponse
    {
        $cacheKey = "organisation:{$organisation->id}";

        $data = Cache::remember($cacheKey, now()->addHours(6), function () use ($organisation) {
            return $organisation->loadCount('users');
        });

        return response()->json($data);
    }

    #[OA\Put(
        path: "/admin/organisations/{organisation}",
        tags: ["Admin - Organisations"],
        summary: "Update an organisation",
        description: "Only accessible to nep_admin.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "organisation", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Green Earth Nepal"),
                    new OA\Property(property: "contact_name", type: "string", example: "Ram Sharma"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "info@greenearth.org"),
                    new OA\Property(property: "member_since", type: "integer", example: 2026),
                    new OA\Property(property: "status", type: "string", enum: ["active", "inactive"]),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Organisation updated successfully", content: new OA\JsonContent(ref: "#/components/schemas/Organisation")),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden — not a nep_admin"),
            new OA\Response(response: 404, description: "Organisation not found"),
            new OA\Response(response: 422, description: "Validation failed"),
        ]
    )]
    public function update(UpdateOrganisationRequest $request, Organisation $organisation): JsonResponse
    {
        $organisation->update($request->validated());

        Cache::forget("organisation:{$organisation->id}");

        return response()->json($organisation->fresh());
    }

    #[OA\Patch(
        path: "/admin/organisations/{organisation}/deactivate",
        tags: ["Admin - Organisations"],
        summary: "Deactivate an organisation",
        description: "Soft-deactivates an organisation (sets status to inactive and records the timestamp). Does not delete data — preserves history for existing programme entries and users. Only accessible to nep_admin.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "organisation", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Organisation deactivated successfully", content: new OA\JsonContent(ref: "#/components/schemas/Organisation")),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden — not a nep_admin"),
            new OA\Response(response: 404, description: "Organisation not found"),
        ]
    )]
    public function deactivate(Organisation $organisation): JsonResponse
    {
        $organisation->update([
            'status' => 'inactive',
            'last_inactive_at' => now(),
        ]);

        Cache::forget("organisation:{$organisation->id}");

        return response()->json($organisation->fresh());
    }

    #[OA\Patch(
        path: "/admin/organisations/{organisation}/activate",
        tags: ["Admin - Organisations"],
        summary: "Reactivate an organisation",
        description: "Sets status back to active and clears the last_inactive_at timestamp. Only accessible to nep_admin.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "organisation", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Organisation reactivated successfully", content: new OA\JsonContent(ref: "#/components/schemas/Organisation")),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden — not a nep_admin"),
            new OA\Response(response: 404, description: "Organisation not found"),
        ]
    )]
    public function activate(Organisation $organisation): JsonResponse
    {
        $organisation->update([
            'status' => 'active',
            'last_inactive_at' => null,
        ]);

        Cache::forget("organisation:{$organisation->id}");

        return response()->json($organisation->fresh());
    }
}
