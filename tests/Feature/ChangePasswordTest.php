<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $adminUser;
    protected User $coordinatorUser;
    protected User $memberUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'password' => bcrypt('TempPass#2024!'),
        ]);

        $this->adminUser = User::factory()->create([
            'password' => bcrypt('Admin#2024!'),
            'role' => 'nep_admin',
        ]);

        $this->coordinatorUser = User::factory()->create([
            'password' => bcrypt('Coord#2024!'),
            'role' => 'nep_coordinator',
        ]);

        $this->memberUser = User::factory()->create([
            'password' => bcrypt('Member#2024!'),
            'role' => 'member_org',
        ]);
    }

    public function test_unauthenticated_user_cannot_change_password(): void
    {
        $response = $this->patchJson('/api/change-password', [
            'current_password' => 'TempPassword123!',
            'new_password' => 'MyNewPassword@123',
            'new_password_confirmation' => 'MyNewPassword@123',
        ]);

        $response->assertUnauthorized();
        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    }

    public function test_user_can_change_password_with_valid_data(): void
    {
        $response = $this->actingAs($this->user)
            ->patchJson('/api/change-password', [
                'current_password' => 'TempPass#2024!',
                'new_password' => 'NewPass#2024!',
                'new_password_confirmation' => 'NewPass#2024!',
            ]);

        $response->assertOk();
        $response->assertJson([
            'message' => 'Password changed successfully.',
        ]);

        // Verify the password was actually changed in the database
        $this->user->refresh();
        $this->assertTrue(
            password_verify('NewPass#2024!', $this->user->password),
            'Password should be updated in the database'
        );
    }

    public function test_user_cannot_change_password_with_incorrect_current_password(): void
    {
        $response = $this->actingAs($this->user)
            ->patchJson('/api/change-password', [
                'current_password' => 'WrongPass#2024!',
                'new_password' => 'NewPass#2024!',
                'new_password_confirmation' => 'NewPass#2024!',
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'The current password is incorrect.',
        ]);

        // Verify the password was NOT changed in the database
        $this->user->refresh();
        $this->assertTrue(
            password_verify('TempPass#2024!', $this->user->password),
            'Password should remain unchanged'
        );
    }

    public function test_user_cannot_change_password_with_mismatched_confirmation(): void
    {
        $response = $this->actingAs($this->user)
            ->patchJson('/api/change-password', [
                'current_password' => 'TempPass#2024!',
                'new_password' => 'NewPass#2024!',
                'new_password_confirmation' => 'Different#2024!',
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'The new password confirmation does not match.',
        ]);

        // Verify the password was NOT changed in the database
        $this->user->refresh();
        $this->assertTrue(
            password_verify('TempPass#2024!', $this->user->password),
            'Password should remain unchanged'
        );
    }

    public function test_user_cannot_change_password_with_weak_password(): void
    {
        $response = $this->actingAs($this->user)
            ->patchJson('/api/change-password', [
                'current_password' => 'TempPass#2024!',
                'new_password' => 'weak',
                'new_password_confirmation' => 'weak',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['new_password']);

        // Verify the password was NOT changed in the database
        $this->user->refresh();
        $this->assertTrue(
            password_verify('TempPass#2024!', $this->user->password),
            'Password should remain unchanged'
        );
    }

    public function test_user_cannot_change_password_with_missing_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->patchJson('/api/change-password', [
                'current_password' => 'TempPass#2024!',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['new_password']);
    }

    public function test_user_can_change_password_multiple_times(): void
    {
        // First password change
        $response = $this->actingAs($this->user)
            ->patchJson('/api/change-password', [
                'current_password' => 'TempPass#2024!',
                'new_password' => 'First#2024!',
                'new_password_confirmation' => 'First#2024!',
            ]);

        $response->assertOk();
        $this->user->refresh();
        $this->assertTrue(password_verify('First#2024!', $this->user->password));

        // Second password change
        $response = $this->actingAs($this->user)
            ->patchJson('/api/change-password', [
                'current_password' => 'First#2024!',
                'new_password' => 'Second#2024!',
                'new_password_confirmation' => 'Second#2024!',
            ]);

        $response->assertOk();
        $this->user->refresh();
        $this->assertTrue(password_verify('Second#2024!', $this->user->password));
    }

    public function test_all_authenticated_roles_can_change_password(): void
    {
        // Test admin
        $response = $this->actingAs($this->adminUser)
            ->patchJson('/api/change-password', [
                'current_password' => 'Admin#2024!',
                'new_password' => 'NewAdmin#2024!',
                'new_password_confirmation' => 'NewAdmin#2024!',
            ]);
        $response->assertOk();

        // Test coordinator
        $response = $this->actingAs($this->coordinatorUser)
            ->patchJson('/api/change-password', [
                'current_password' => 'Coord#2024!',
                'new_password' => 'NewCoord#2024!',
                'new_password_confirmation' => 'NewCoord#2024!',
            ]);
        $response->assertOk();

        // Test member
        $response = $this->actingAs($this->memberUser)
            ->patchJson('/api/change-password', [
                'current_password' => 'Member#2024!',
                'new_password' => 'NewMember#2024!',
                'new_password_confirmation' => 'NewMember#2024!',
            ]);
        $response->assertOk();
    }
}