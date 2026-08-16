<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use OpenApi\Attributes as OA;

class PasswordResetController extends Controller
{
    #[OA\Post(
        path: "/forgot-password",
        summary: "Request a password reset link",
        description: "Sends a password reset link to the given email if an account with that email exists. Always returns 200 regardless of whether the email exists, to avoid leaking which emails are registered.",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "user@example.com"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Reset link sent if the account exists",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "If an account exists for that email, a reset link has been sent."),
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Validation failed"),
        ]
    )]
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Always attempt the send and always return the same generic message,
        // regardless of whether the account exists — the previous version
        // returned a distinct 422 ("No account found with that email
        // address.") when it didn't, which let a caller enumerate registered
        // emails one request at a time. Password::sendResetLink() is a no-op
        // when the email isn't registered, so this stays correct either way.
        //
        // The mail send itself is synchronous (ResetPasswordNotification
        // isn't queued) and can throw — an SMTP rejection (e.g. a provider
        // bouncing an unaligned From domain) would otherwise surface as an
        // uncaught 500 for existing accounts only, which both breaks the
        // response contract and reintroduces the account-enumeration signal
        // this generic message exists to avoid.
        try {
            Password::sendResetLink($request->only('email'));
        } catch (\Exception $e) {
            Log::error('Failed to send password reset email', [
                'email' => $request->input('email'),
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'If an account exists for that email, a reset link has been sent.',
        ]);
    }

    #[OA\Post(
        path: "/reset-password",
        summary: "Reset password using a token from the reset link",
        description: "Validates the reset token and sets a new password. Revokes all existing API tokens for the account once the password is reset.",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["token", "email", "password", "password_confirmation"],
                properties: [
                    new OA\Property(property: "token", type: "string", example: "9d8f2a3b1c..."),
                    new OA\Property(property: "email", type: "string", format: "email", example: "user@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "newSecurePass123"),
                    new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "newSecurePass123"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Password reset successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Password reset successfully. Please log in with your new password."),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Invalid or expired token, or validation failed",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string"),
                        new OA\Property(property: "errors", type: "object"),
                    ]
                )
            ),
        ]
    )]
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'This password reset link is invalid or has expired.',
                'errors' => ['email' => [__($status)]],
            ], 422);
        }

        return response()->json([
            'message' => 'Password reset successfully. Please log in with your new password.',
        ]);
    }
}
