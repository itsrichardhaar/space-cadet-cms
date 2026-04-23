<?php
/**
 * Space Cadet CMS — Blueprint AI Orchestrator
 * Sends HTML to an AI provider and receives a structured field schema.
 */

class Blueprint {

    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a CMS content extraction assistant. The user will provide raw HTML from a webpage.
Your task is to identify the editable content regions and convert them into a structured field schema.

Respond ONLY with a valid JSON object in this format:
{
  "title": "Detected page title",
  "fields": {
    "field_key": "field_value",
    "another_field": "another_value"
  },
  "suggested_field_defs": [
    {"key": "field_key", "name": "Human Label", "type": "text|textarea|richtext|image|number|toggle"},
    ...
  ]
}

Rules:
- Extract real content from the HTML, not placeholder text like "Lorem ipsum"
- Use snake_case for field keys
- Choose the most appropriate field type (richtext for long formatted text, text for short strings)
- Ignore navigation, footers, scripts, and ads
- Keep field values as plain text (strip HTML tags from values)
PROMPT;

    public static function analyze(string $html, string $provider, string $instructions = ''): array {
        $prompt = self::SYSTEM_PROMPT;
        if ($instructions) {
            $prompt .= "\n\nAdditional instructions from the user:\n" . $instructions;
        }

        $userMessage = "Please extract the editable content from this HTML:\n\n" . substr($html, 0, 50000);

        $result = match($provider) {
            'claude'  => self::callClaude($prompt, $userMessage),
            'openai'  => self::callOpenAI($prompt, $userMessage),
            'gemini'  => self::callGemini($prompt, $userMessage),
            default   => throw new RuntimeException("Unknown provider: {$provider}"),
        };

        $result['prompt_used'] = substr($prompt, 0, 500);
        return $result;
    }

    private static function callClaude(string $system, string $user): array {
        $apiKey = Database::queryOne("SELECT value FROM settings WHERE key='claude_api_key'")['value'] ?? '';
        if (!$apiKey) throw new RuntimeException('Claude API key not configured in Settings.');

        $payload = json_encode([
            'model'      => 'claude-sonnet-4-5',
            'max_tokens' => 2048,
            'system'     => $system,
            'messages'   => [['role' => 'user', 'content' => $user]],
        ]);

        $response = self::post('https://api.anthropic.com/v1/messages', $payload, [
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
        ]);

        $data = json_decode($response, true);
        $text = $data['content'][0]['text'] ?? '';
        return self::parseJson($text);
    }

    private static function callOpenAI(string $system, string $user): array {
        $apiKey = Database::queryOne("SELECT value FROM settings WHERE key='openai_api_key'")['value'] ?? '';
        if (!$apiKey) throw new RuntimeException('OpenAI API key not configured in Settings.');

        $payload = json_encode([
            'model'    => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
        ]);

        $response = self::post('https://api.openai.com/v1/chat/completions', $payload, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ]);

        $data = json_decode($response, true);
        $text = $data['choices'][0]['message']['content'] ?? '';
        return self::parseJson($text);
    }

    private static function callGemini(string $system, string $user): array {
        $apiKey = Database::queryOne("SELECT value FROM settings WHERE key='gemini_api_key'")['value'] ?? '';
        if (!$apiKey) throw new RuntimeException('Gemini API key not configured in Settings.');

        $payload = json_encode([
            'contents' => [[
                'parts' => [['text' => $system . "\n\n" . $user]],
            ]],
        ]);

        $url      = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?key=' . $apiKey;
        $response = self::post($url, $payload, ['Content-Type: application/json']);

        $data = json_decode($response, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        return self::parseJson($text);
    }

    private static function post(string $url, string $body, array $headers): string {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
        ]);
        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);
        if ($error) throw new RuntimeException("cURL error: {$error}");
        return (string) $response;
    }

    private static function parseJson(string $text): array {
        // Extract JSON block from markdown code fences if present
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/si', $text, $m)) {
            $text = $m[1];
        } elseif (preg_match('/(\{.*\})/si', $text, $m)) {
            $text = $m[1];
        }

        $data = json_decode($text, true);
        if (!is_array($data)) {
            throw new RuntimeException('AI returned invalid JSON.');
        }
        return $data;
    }
}
