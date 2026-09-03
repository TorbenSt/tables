<?php

namespace App\Services\Xai;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GrokClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.xai.key'));
    }

    public function chat(string $system, string $user, ?string $model = null): string
    {
        $response = $this->client()->post('/chat/completions', [
            'model' => $model ?? config('services.xai.model'),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'temperature' => 0.2,
        ]);

        if (! $response->successful()) {
            Log::warning('Grok chat failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException($this->formatApiError('Grok API', $response));
        }

        return (string) data_get($response->json(), 'choices.0.message.content', '');
    }

    /**
     * @param  array<int, array{path: string, mime?: string}>  $images
     */
    public function vision(string $prompt, array $images, ?string $model = null): string
    {
        $content = [
            ['type' => 'text', 'text' => $prompt],
        ];

        foreach ($images as $image) {
            $prepared = $this->prepareImageDataUrl($image['path']);
            if ($prepared === null) {
                continue;
            }
            $content[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $prepared,
                    'detail' => 'high',
                ],
            ];
        }

        if (count($content) < 2) {
            throw new RuntimeException('Keine gültigen Bilder für Grok Vision.');
        }

        $response = $this->client()->post('/chat/completions', [
            'model' => $model ?? config('services.xai.vision_model'),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ],
            'temperature' => 0.1,
        ]);

        if (! $response->successful()) {
            Log::warning('Grok vision failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException($this->formatApiError('Grok Vision API', $response));
        }

        return (string) data_get($response->json(), 'choices.0.message.content', '');
    }

    /**
     * @return array<string, mixed>
     */
    public function chatJson(string $system, string $user, ?string $model = null): array
    {
        $raw = $this->chat(
            $system."\nAntworte ausschließlich mit gültigem JSON, ohne Markdown-Codeblöcke.",
            $user,
            $model
        );

        return $this->decodeJson($raw);
    }

    /**
     * @param  array<int, array{path: string, mime?: string}>  $images
     * @return array<string, mixed>
     */
    public function visionJson(string $prompt, array $images, ?string $model = null): array
    {
        $raw = $this->vision(
            $prompt."\nAntworte ausschließlich mit gültigem JSON, ohne Markdown-Codeblöcke.",
            $images,
            $model
        );

        return $this->decodeJson($raw);
    }

    /**
     * Resize/compress local images so multi-photo vision requests stay reliable.
     */
    private function prepareImageDataUrl(string $path): ?string
    {
        if (! is_readable($path)) {
            return null;
        }

        $binary = @file_get_contents($path);
        if ($binary === false || $binary === '') {
            return null;
        }

        $info = @getimagesizefromstring($binary);
        $mime = is_array($info) ? ($info['mime'] ?? null) : null;
        $width = is_array($info) ? (int) ($info[0] ?? 0) : 0;
        $height = is_array($info) ? (int) ($info[1] ?? 0) : 0;

        $maxEdge = 1280;
        $needsResize = $width > $maxEdge || $height > $maxEdge || strlen($binary) > 700_000;

        if ($needsResize && function_exists('imagecreatefromstring')) {
            $src = @imagecreatefromstring($binary);
            if ($src !== false) {
                $srcW = imagesx($src);
                $srcH = imagesy($src);
                $scale = min(1.0, $maxEdge / max($srcW, $srcH));
                $dstW = max(1, (int) round($srcW * $scale));
                $dstH = max(1, (int) round($srcH * $scale));
                $dst = imagecreatetruecolor($dstW, $dstH);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
                ob_start();
                imagejpeg($dst, null, 78);
                $resized = ob_get_clean();
                imagedestroy($src);
                imagedestroy($dst);
                if (is_string($resized) && $resized !== '') {
                    return 'data:image/jpeg;base64,'.base64_encode($resized);
                }
            }
        }

        $mime ??= 'image/jpeg';
        if (! in_array($mime, ['image/jpeg', 'image/png'], true)) {
            $mime = 'image/jpeg';
        }

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $raw): array
    {
        $cleaned = trim($raw);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $cleaned, $m)) {
            $cleaned = trim($m[1]);
        }

        $decoded = json_decode($cleaned, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Grok lieferte kein gültiges JSON: '.mb_substr($raw, 0, 200));
        }

        return $decoded;
    }

    private function formatApiError(string $label, Response $response): string
    {
        $detail = data_get($response->json(), 'error')
            ?? data_get($response->json(), 'message')
            ?? null;

        if (is_string($detail) && $detail !== '') {
            return sprintf('%s Fehler: %s (%s)', $label, $response->status(), $detail);
        }

        return sprintf('%s Fehler: %s', $label, $response->status());
    }

    private function client(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('XAI_API_KEY ist nicht gesetzt.');
        }

        return Http::baseUrl(rtrim((string) config('services.xai.base_url'), '/'))
            ->withToken((string) config('services.xai.key'))
            ->acceptJson()
            ->timeout((int) config('services.xai.timeout', 300));
    }
}
