<?php

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeService
{
    private string $apiKey;
    private string $model;
    private string $endpoint = 'https://api.anthropic.com/v1/messages';
    private int $timeout;
    private int $retryAttempts;
    private int $retryDelay;

    public function __construct()
    {
        $this->apiKey = config('services.claude.api_key') ?? '';
        $this->model = config('services.claude.model') ?? 'claude-sonnet-4-5';
        $this->timeout = (int) (config('services.claude.timeout') ?? 30);
        $this->retryAttempts = (int) (config('services.claude.retry_attempts') ?? 2);
        $this->retryDelay = (int) (config('services.claude.retry_delay') ?? 500);

        if (empty($this->apiKey)) {
            throw new \RuntimeException('Claude API key is not configured. Please set CLAUDE_API_KEY in your environment variables.', 500);
        }
    }

    public function generateContent(string $prompt, array $options = []): array
    {
        // `response_format` is an OpenAI-compatible option used by the former
        // Groq integration. Claude is already instructed to return JSON in the
        // prompt, so do not forward this unsupported field to Anthropic.
        unset($options['response_format']);

        $payload = array_merge([
            'model' => $this->model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.7,
            'max_tokens' => 4096,
        ], $options);

        $attempt = 0;
        $maxAttempts = $this->retryAttempts + 1;

        while ($attempt < $maxAttempts) {
            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'x-api-key' => $this->apiKey,
                        'anthropic-version' => '2023-06-01',
                    ])
                    ->post($this->endpoint, $payload);

                if ($response->successful()) {
                    $data = $response->json();
                    if (empty($data)) {
                        throw new \RuntimeException('Claude API returned an empty response.');
                    }
                    return $this->parseResponse($data);
                }

                $statusCode = $response->status();

                Log::error('Claude API request failed', [
                    'status_code' => $statusCode,
                    'attempt' => $attempt + 1,
                    'error' => mb_substr($response->body(), 0, 500),
                ]);

                if (in_array($statusCode, [400, 401, 403, 404])) {
                    throw new \RuntimeException(
                        $this->getErrorMessage($response->json() ?? [], $statusCode),
                        $statusCode
                    );
                }

                $attempt++;
                if ($attempt >= $maxAttempts) {
                    throw new \RuntimeException(
                        $this->getErrorMessage($response->json() ?? [], $statusCode),
                        $statusCode
                    );
                }

                usleep($this->retryDelay * 1000);

            } catch (ConnectionException $e) {
                $attempt++;
                if ($attempt >= $maxAttempts) {
                    throw new \RuntimeException('Unable to connect to Claude AI service. Please try again later.', 503);
                }
                usleep($this->retryDelay * 1000);
            } catch (\RuntimeException $e) {
                throw $e;
            } catch (\Exception $e) {
                Log::error('Unexpected error in Claude service', ['message' => $e->getMessage()]);
                throw new \RuntimeException('An unexpected error occurred while generating advisory content.', 500);
            }
        }

        throw new \RuntimeException('AI service request failed after multiple attempts.', 503);
    }

    private function parseResponse(array $data): array
    {
        $text = $data['content'][0]['text'] ?? null;

        if ($text === null) {
            throw new \RuntimeException('AI response did not contain expected content.');
        }

        $parsed = $this->parseJsonFromText($text);

        if (is_array($parsed) && isset($parsed['section_a'])) {
            return $parsed;
        }

        if (is_array($parsed) && array_is_list($parsed)) {
            return ['_raw_array' => $parsed];
        }

        if (is_array($parsed) && isset($parsed['codes'])) {
            return $parsed;
        }

        if ($parsed === null) {
            return [
                'executive_summary' => $text,
                'similar_or_overlapping_programmes' => [],
                'potential_duplication' => 'Unable to parse structured response.',
                'coverage_gaps' => 'Unable to parse structured response.',
                'recommendations' => 'Unable to parse structured response.',
                'confidence_notes' => 'The AI response could not be parsed into a structured format.',
            ];
        }

        return array_merge([
            'executive_summary' => 'No executive summary was generated.',
            'similar_or_overlapping_programmes' => [],
            'potential_duplication' => 'No duplication assessment was generated.',
            'coverage_gaps' => 'No coverage gap analysis was generated.',
            'recommendations' => 'No recommendations were generated.',
            'confidence_notes' => '',
        ], $parsed);
    }

    private function parseJsonFromText(string $text): ?array
    {
        $clean = $text;
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $matches)) {
            $clean = trim($matches[1]);
        }

        $decoded = json_decode($clean, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{[\s\S]+\}/s', $text, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function getErrorMessage(array $errorBody, int $statusCode): string
    {
        $message = $errorBody['error']['message'] ?? 'Unknown error';

        return match ($statusCode) {
            400 => 'Invalid request: ' . $message,
            401, 403 => 'Authentication failed. Check CLAUDE_API_KEY configuration.',
            404 => 'AI model not found. Check model configuration.',
            429 => 'Rate limit exceeded. Please wait and try again.',
            500, 502, 503 => 'AI service is temporarily unavailable. Please try again later.',
            default => 'Unexpected error: ' . $message,
        };
    }
}
