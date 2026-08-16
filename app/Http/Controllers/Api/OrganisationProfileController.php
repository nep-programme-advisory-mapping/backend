<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrganisationProfileRequest;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class OrganisationProfileController extends Controller
{
    #[OA\Get(
        path: "/organisations/me",
        summary: "Get the current user's organisation profile",
        security: [["bearerAuth" => []]],
        tags: ["Organisation Profile"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Organisation profile retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 1),
                                new OA\Property(property: "name", type: "string", example: "Green Earth Nepal"),
                                new OA\Property(property: "contact_name", type: "string", example: "Ram Sharma"),
                                new OA\Property(property: "email", type: "string", example: "info@greenearth.org"),
                                new OA\Property(property: "member_since", type: "integer", example: 2020),
                            ],
                            type: "object"
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "User has no organisation"),
        ]
    )]
    public function show(Request $request)
    {
        $org = $request->user()->organisation;

        if (! $org) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json([
            'data' => $org->only('id', 'name', 'contact_name', 'email', 'member_since'),
        ]);
    }

    #[OA\Patch(
        path: "/organisations/me",
        summary: "Update the current user's organisation profile",
        security: [["bearerAuth" => []]],
        tags: ["Organisation Profile"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "contact_name", type: "string", example: "New Contact Name"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "new@example.com"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Organisation profile updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 1),
                                new OA\Property(property: "name", type: "string", example: "Green Earth Nepal"),
                                new OA\Property(property: "contact_name", type: "string", example: "New Contact Name"),
                                new OA\Property(property: "email", type: "string", example: "new@example.com"),
                                new OA\Property(property: "member_since", type: "integer", example: 2020),
                            ],
                            type: "object"
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "User has no organisation"),
            new OA\Response(response: 422, description: "Validation failed"),
        ]
    )]
    public function update(UpdateOrganisationProfileRequest $request)
    {
        $org = $request->user()->organisation;

        if (! $org) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $org->update($request->validated());

        return response()->json([
            'data' => $org->fresh()->only('id', 'name', 'contact_name', 'email', 'member_since'),
        ]);
    }
}
