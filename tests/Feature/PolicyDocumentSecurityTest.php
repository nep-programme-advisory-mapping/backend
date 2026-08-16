<?php

namespace Tests\Feature;

use App\Models\PolicyDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Policy document file-type restriction and Content-Type safety on download (BUG-02).
 */
class PolicyDocumentSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'nep_admin']);
    }

    public function test_uploading_an_html_file_is_rejected(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/policy-documents', [
            'title'     => 'Malicious Upload',
            'authority' => 'Test',
            'version'   => '1.0',
            'date'      => '2026-01-01',
            'file'      => UploadedFile::fake()->create('payload.html', 5),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('file');
    }

    public function test_uploading_an_svg_file_is_rejected(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/policy-documents', [
            'title'     => 'Malicious SVG',
            'authority' => 'Test',
            'version'   => '1.0',
            'date'      => '2026-01-01',
            'file'      => UploadedFile::fake()->create('payload.svg', 5),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('file');
    }

    public function test_uploading_a_pdf_is_accepted(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/policy-documents', [
            'title'     => 'National Policy',
            'authority' => 'MoEYS',
            'version'   => '1.0',
            'date'      => '2026-01-01',
            'file'      => UploadedFile::fake()->create('policy.pdf', 100, 'application/pdf'),
        ]);

        $response->assertStatus(201);
    }

    public function test_download_ignores_a_spoofed_stored_mime_type_and_serves_a_safe_content_type(): void
    {
        // Simulates a record whose mime_type field claims text/html (either a
        // pre-fix record, or a client that lied about its MIME type at
        // upload time) — the download response must not trust it.
        $document = PolicyDocument::create([
            'title' => 'Legacy Doc', 'authority' => 'Test', 'version' => '1.0', 'date' => '2026-01-01',
            'file_name' => 'report.pdf',
            'mime_type' => 'text/html', // spoofed / stale
            'file_data' => base64_encode('%PDF-1.4 fake pdf bytes'),
            'file_size' => 20,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')->get("/api/policy-documents/{$document->id}/file");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf'); // derived from the .pdf extension, not the stored mime_type
    }

    public function test_download_of_a_non_pdf_document_forces_attachment_disposition(): void
    {
        $document = PolicyDocument::create([
            'title' => 'Word Doc', 'authority' => 'Test', 'version' => '1.0', 'date' => '2026-01-01',
            'file_name' => 'annex.docx',
            'mime_type' => 'text/html', // spoofed
            'file_data' => base64_encode('fake docx bytes'),
            'file_size' => 20,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')->get("/api/policy-documents/{$document->id}/file");

        $response->assertStatus(200);
        $this->assertStringStartsWith('attachment', $response->headers->get('Content-Disposition'));
    }
}
