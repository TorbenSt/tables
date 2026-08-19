<?php

namespace App\Services\Xai;

use Illuminate\Http\Client\PendingRequest;
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
            throw new RuntimeException('Grok API Fehler: '.$response->status());
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
            $binary = file_get_contents($image['path']);
            if ($binary === false) {
                continue;
            }
            $mime = $image['mime'] ?? mime_content_type($image['path']) ?: 'image/jpeg';
            $content[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => 'data:'.$mime.';base64,'.base64_encode($binary),
                    'detail' => 'high',
                ],
            ];
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
            throw new RuntimeException('Grok Vision API Fehler: '.$response->status());
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

    private function client(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('XAI_API_KEY ist nicht gesetzt.');
        }

        return Http::baseUrl(rtrim((string) config('services.xai.base_url'), '/'))
            ->withToken((string) config('services.xai.key'))
            ->acceptJson()
            ->timeout((int) config('services.xai.timeout', 120));
    }
}
