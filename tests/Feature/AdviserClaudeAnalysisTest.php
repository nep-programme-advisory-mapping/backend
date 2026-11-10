<?php

namespace Tests\Feature;

use App\Models\AdvisoryNote;
use App\Models\User;
use App\Services\AI\ClaudeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdviserClaudeAnalysisTest extends TestCase
{
    use DatabaseTransactions;

    private const NOTE_JSON = '{"section_a":"Profile.","section_b":[],"section_c":"Gaps.","section_d":"No flags."}';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.claude.api_key' => 'test-key',
            'services.claude.model' => 'claude-opus-5-5',
            'services.claude.retry_attempts' => 0,
        ]);
    }

    private function fakeClaude(array $content, string $stopReason = 'end_turn', array $extra = []): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(array_merge([
                'content' => $content,
                'stop_reason' => $stopReason,
            ], $extra)),
        ]);
    }

    public function test_request_uses_the_configured_model_and_omits_rejected_parameters(): void
    {
        $this->fakeClaude([['type' => 'text', 'text' => self::NOTE_JSON]]);

        app(ClaudeService::class)->generateContent('prompt', [
            'temperature' => 0.1,
            'response_format' => ['type' => 'json_object'],
        ]);

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return $request->hasHeader('x-api-key', 'test-key')
                && $body['model'] === 'claude-opus-5-5'
                && ! array_key_exists('temperature', $body)
                && ! array_key_exists('response_format', $body)
                && $body['fallbacks'] === 'default';
        });
    }

    public function test_reads_the_answer_when_a_thinking_block_comes_first(): void
    {
        $this->fakeClaude([
            ['type' => 'thinking', 'thinking' => ''],
            ['type' => 'text', 'text' => self::NOTE_JSON],
        ]);

        $this->assertSame('Profile.', app(ClaudeService::class)->generateContent('prompt')['section_a']);
    }

    public function test_ignores_text_written_before_a_fallback(): void
    {
        $this->fakeClaude([
            ['type' => 'text', 'text' => 'partial answer from the declining model'],
            ['type' => 'fallback'],
            ['type' => 'text', 'text' => self::NOTE_JSON],
        ]);

        $this->assertSame('Profile.', app(ClaudeService::class)->generateContent('prompt')['section_a']);
    }

    public function test_a_refusal_raises_an_error(): void
    {
        $this->fakeClaude([], 'refusal', ['stop_details' => ['category' => 'cyber']]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(503);

        app(ClaudeService::class)->generateContent('prompt');
    }

    public function test_a_non_json_answer_raises_an_error_instead_of_saving_blank_sections(): void
    {
        $this->fakeClaude([['type' => 'text', 'text' => 'Sorry, here is some plain text.']], 'max_tokens');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(503);

        app(ClaudeService::class)->generateContent('prompt');
    }

    public function test_adviser_analysis_saves_the_claude_response(): void
    {
        $this->fakeClaude([['type' => 'text', 'text' => self::NOTE_JSON]]);
        $admin = User::factory()->create(['role' => 'nep_admin']);
        $note = AdvisoryNote::factory()->create(['programme_entry_id' => null]);

        $this->actingAs($admin)
            ->postJson("/api/adviser/submissions/{$note->id}/generate-advisory-note")
            ->assertStatus(200)
            ->assertJsonPath('data.section_a', 'Profile.');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'api.anthropic.com'));
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'api.groq.com'));

        $note->refresh();
        $this->assertSame('analysed', $note->status);
        $this->assertSame('Profile.', $note->section_profile);
        $this->assertSame('Gaps.', $note->section_gaps);
    }
}
