<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Smalot\PdfParser\Parser as PdfParser;

class ProgrammeDescriptionExtractor
{
    public function fromFile(UploadedFile $file): string
    {
        $realPath = $file->getRealPath();

        if ($realPath === false) {
            return '';
        }

        return match ($file->getClientOriginalExtension()) {
            'txt' => trim((string) file_get_contents($realPath)),
            'pdf' => $this->fromPdf($realPath),
            default => '',
        };
    }

    public function fromPdf(string $path): string
    {
        try {
            $parser     = new PdfParser();
            $parsedText = trim(preg_replace('/\s+/', ' ', $parser->parseFile($path)->getText()) ?? '');

            if ($parsedText !== '') {
                return $parsedText;
            }
        } catch (\Throwable) {
            // fall through to stream scanner
        }

        $safePath = realpath($path);
        if ($safePath === false) {
            return '';
        }

        $contents = (string) file_get_contents($safePath);
        $text     = '';

        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $contents, $streams)) {
            foreach ($streams[1] as $stream) {
                $decoded = @gzuncompress($stream);
                $text   .= ' ' . $this->extractTextFromPdfStream($decoded !== false ? $decoded : $stream);
            }
        }

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    public function looksLikeRawPdf(string $text): bool
    {
        $trimmed = ltrim($text);

        return str_starts_with($trimmed, '%PDF-')
            || str_contains($trimmed, '%PDF-')
            || (str_contains($trimmed, '/FlateDecode') && str_contains($trimmed, 'endstream'))
            || (str_contains($trimmed, 'endobj') && str_contains($trimmed, 'startxref'))
            || str_contains($trimmed, '%%EOF');
    }

    private function extractTextFromPdfStream(string $stream): string
    {
        $text = '';

        if (preg_match_all('/\((?:\\\\.|[^\\\\()])*\)\s*T[Jj]/', $stream, $matches)) {
            foreach ($matches[0] as $match) {
                if (preg_match('/\(((?:\\\\.|[^\\\\()])*)\)\s*T[Jj]/', $match, $textMatch)) {
                    $text .= ' ' . stripcslashes($textMatch[1]);
                }
            }
        }

        return $text;
    }
}
