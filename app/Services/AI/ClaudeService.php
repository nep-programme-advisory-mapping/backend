<?php

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeService
{
    private string $apiKey;
    private string $model;
    private string $effort;
    private string $endpoint = 'https://api.anthropic.com/v1/messages';
    private int $timeout;
    private int $retryAttempts;
    private int $retryDelay;

    public function __construct()
    {
        $this->apiKey = config('services.claude.api_key') ?? '';
        $this->model = config('services.claude.model') ?? 'claude-opus-5-5';
        $this->effort = config('services.claude.effort') ?? 'medium';
        $this->timeout = (int) (config('services.claude.timeout') ?? 120);
        $this->retryAttempts = (int) (config('services.claude.retry_attempts') ?? 2);
        $this->retryDelay = (int) (config('services.claude.retry_delay') ?? 500);

        if (empty($this->apiKey)) {
            throw new \RuntimeException('Claude API key is not configured. Please set CLAUDE_API_KEY in your environment variables.', 500);
        }
    }

    public function generateContent(string $prompt, array $options = []): array
    {
        // Current Claude models reject `temperature` with a 400, and
        // `response_format` is an OpenAI-style option they don't accept either.
        // The prompts already ask for JSON, so neither is forwarded.
        unset($options['response_format'], $options['temperature']);

        $payload = array_merge([
            'model' => $this->model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => 16000,
            'output_config' => ['effort' => $this->effort],
            // If the model declines on safety grounds, let the API retry on a
            // suitable fallback model inside the same request.
            'fallbacks' => 'default',
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
                        'anthropic-beta' => 'server-side-fallback-2026-07-01',
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
        $stopReason = $data['stop_reason'] ?? null;

        if ($stopReason === 'refusal') {
            Log::warning('Claude declined the request', ['category' => $data['stop_details']['category'] ?? null]);
            throw new \RuntimeException('The AI declined to analyse this content. Please review the document and try again.', 503);
        }

        // A response can include thinking and fallback blocks, so the answer isn't
        // always the first block. Text written before a fallback belongs to the
        // model that declined, so only the text after the last fallback is kept.
        $text = '';
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'fallback') {
                $text = '';
            } elseif (($block['type'] ?? null) === 'text') {
                $text .= $block['text'] ?? '';
            }
        }

        if (trim($text) === '') {
            throw new \RuntimeException('AI response did not contain expected content.', 503);
        }

        $parsed = $this->parseJsonFromText($text);

        if ($parsed === null) {
            Log::warning('AI response could not be parsed as JSON', [
                'stop_reason' => $stopReason,
                'raw_length' => strlen($text),
            ]);
            throw new \RuntimeException('AI returned a non-JSON response. The response may have been cut off; please try again.', 503);
        }

        return array_is_list($parsed) ? ['_raw_array' => $parsed] : $parsed;
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
