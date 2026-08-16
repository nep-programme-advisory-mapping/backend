<?php

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqService
{
    private string $apiKey;
    private string $model;
    private string $endpoint = 'https://api.groq.com/openai/v1/chat/completions';
    private int $timeout;
    private int $retryAttempts;
    private int $retryDelay;

    public function __construct()
    {
        $this->apiKey = config('services.groq.api_key') ?? '';
        $this->model = config('services.groq.model') ?? 'llama-3.3-70b-versatile';
        $this->timeout = (int) (config('services.groq.timeout') ?? 30);
        $this->retryAttempts = (int) (config('services.groq.retry_attempts') ?? 2);
        $this->retryDelay = (int) (config('services.groq.retry_delay') ?? 500);

        if (empty($this->apiKey)) {
            throw new \RuntimeException('Groq API key is not configured. Please set GROQ_API_KEY in your environment variables.', 500);
        }
    }

    public function generateContent(string $prompt, array $options = []): array
    {
        $payload = array_merge([
            'model' => $this->model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.7,
            'max_tokens' => 2048,
        ], $options);

        $attempt = 0;
        $maxAttempts = $this->retryAttempts + 1;

        while ($attempt < $maxAttempts) {
            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $this->apiKey,
                    ])
                    ->post($this->endpoint, $payload);

                if ($response->successful()) {
                    $data = $response->json();
                    if (empty($data)) {
                        throw new \RuntimeException('Groq API returned an empty response.');
                    }
                    return $this->parseResponse($data);
                }

                $statusCode = $response->status();

                Log::error('Groq API request failed', [
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
                    throw new \RuntimeException('Unable to connect to Groq AI service. Please try again later.', 503);
                }
                usleep($this->retryDelay * 1000);
            } catch (\RuntimeException $e) {
                throw $e;
            } catch (\Exception $e) {
                Log::error('Unexpected error in Groq service', ['message' => $e->getMessage()]);
                throw new \RuntimeException('An unexpected error occurred while generating advisory content.', 500);
            }
        }

        throw new \RuntimeException('AI service request failed after multiple attempts.', 503);
    }

    private function parseResponse(array $data): array
    {
        $text = $data['choices'][0]['message']['content'] ?? null;

        if ($text === null) {
            throw new \RuntimeException('AI response did not contain expected content.');
        }

        $parsed = $this->parseJsonFromText($text);

        // Advisory note 4-section format (section_a/b/c/d)
        if (is_array($parsed) && isset($parsed['section_a'])) {
            return $parsed;
        }

        // Sequential array (e.g. ["B1.1.1", "B2.3.2"]) — return as-is under _raw_array
        if (is_array($parsed) && array_is_list($parsed)) {
            return ['_raw_array' => $parsed];
        }

        // Structured object with "codes" key — return directly
        if (is_array($parsed) && isset($parsed['codes'])) {
            return $parsed;
        }

        // Profile extraction format (activities/geography)
        if (is_array($parsed) && (isset($parsed['activities']) || isset($parsed['geography']))) {
            return $parsed;
        }

        if ($parsed === null) {
            Log::warning('AI response could not be parsed as JSON', [
                'raw_length' => strlen($text),
                'raw_preview' => mb_substr($text, 0, 300),
            ]);
            throw new \RuntimeException('AI returned a non-JSON response. The prompt may be too large or the response was truncated.', 503);
        }

        return $parsed;
    }

    private function parseJsonFromText(string $text): ?array
    {
        // Strip markdown code fences first
        $clean = $text;
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $matches)) {
            $clean = trim($matches[1]);
        }

        $decoded = json_decode($clean, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Try raw text as-is
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Extract any JSON object from the text
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
            401, 403 => 'Authentication failed. Check GROQ_API_KEY configuration.',
            404 => 'AI model not found. Check model configuration.',
            429 => 'Rate limit exceeded. Please wait and try again.',
            500, 502, 503 => 'AI service is temporarily unavailable. Please try again later.',
            default => 'Unexpected error: ' . $message,
        };
    }
}
