<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Cliente mínimo de la API de mensajes de Anthropic. */
class Anthropic
{
    /**
     * @return array{text:string,input:int,output:int}
     */
    public function message(string $system, string $user, int $maxTokens = 900): array
    {
        $key = config('services.anthropic.key');
        if (! $key) {
            throw new RuntimeException('Falta ANTHROPIC_API_KEY en la configuración.');
        }

        $response = Http::withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])
            ->timeout(60)
            ->retry(2, 800, throw: false)
            ->post(config('services.anthropic.url'), [
                'model' => config('services.anthropic.model'),
                'max_tokens' => $maxTokens,
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $user]],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('La API de IA respondió con error '.$response->status().'.');
        }

        $text = collect($response->json('content', []))->where('type', 'text')->pluck('text')->implode('');

        return [
            'text' => $text,
            'input' => (int) $response->json('usage.input_tokens', 0),
            'output' => (int) $response->json('usage.output_tokens', 0),
        ];
    }

    /** Extrae el primer objeto JSON de una respuesta (tolera bloques ```json). */
    public static function json(string $text): ?array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end < $start) {
            return null;
        }
        $data = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($data) ? $data : null;
    }
}
