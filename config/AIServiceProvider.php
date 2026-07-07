<?php
/**
 * OpenCode AI Service Provider
 * Implements multi-provider fallback for free AI models
 * Priority: Groq -> Mistral -> OpenRouter -> Cloudflare Workers
 */

namespace Dashboard\Config;

class AIServiceProvider
{
    private static $instance = null;
    private $activeProvider = null;
    private $providers = [];
    private $cache = [];

    public function __construct()
    {
        $this->initializeProviders();
    }

    /**
     * Initialize all available AI providers
     */
    private function initializeProviders()
    {
        // Groq - PRIMARY (Fastest)
        if (env('GROQ_ENABLED') && env('GROQ_API_KEY')) {
            $this->providers['groq'] = [
                'enabled' => true,
                'priority' => 1,
                'api_key' => env('GROQ_API_KEY'),
                'model' => env('GROQ_MODEL', 'mixtral-8x7b-32768'),
                'endpoint' => 'https://api.groq.com/openai/v1',
            ];
        }

        // Mistral - SECONDARY
        if (env('MISTRAL_ENABLED') && env('MISTRAL_API_KEY')) {
            $this->providers['mistral'] = [
                'enabled' => true,
                'priority' => 2,
                'api_key' => env('MISTRAL_API_KEY'),
                'model' => env('MISTRAL_MODEL', 'mistral-large'),
                'endpoint' => 'https://api.mistral.ai/v1',
            ];
        }

        // OpenRouter - TERTIARY
        if (env('OPENROUTER_ENABLED') && env('OPENROUTER_API_KEY')) {
            $this->providers['openrouter'] = [
                'enabled' => true,
                'priority' => 3,
                'api_key' => env('OPENROUTER_API_KEY'),
                'model' => env('OPENROUTER_MODEL', 'mistralai/mistral-7b-instruct'),
                'endpoint' => 'https://openrouter.ai/api/v1',
            ];
        }

        // Cloudflare Workers AI - FALLBACK
        if (env('CF_WORKERS_AI_ENABLED') && env('CLOUDFLARE_API_TOKEN')) {
            $this->providers['cloudflare'] = [
                'enabled' => true,
                'priority' => 4,
                'api_token' => env('CLOUDFLARE_API_TOKEN'),
                'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
                'model' => env('CF_WORKERS_AI_MODEL', '@cf/mistral/mistral-7b-instruct-v0.1'),
                'endpoint' => 'https://api.cloudflare.com/client/v4',
            ];
        }

        // Sort by priority
        uasort($this->providers, function ($a, $b) {
            return $a['priority'] - $b['priority'];
        });
    }

    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Generate AI response with fallback
     */
    public function generateResponse($prompt, $systemPrompt = null, $options = [])
    {
        // Check cache first
        $cacheKey = hash('sha256', $prompt . $systemPrompt);
        if (isset($this->cache[$cacheKey]) && (!isset($options['cache']) || $options['cache'] !== false)) {
            return $this->cache[$cacheKey];
        }

        // Try each provider in priority order
        foreach ($this->providers as $name => $config) {
            if (!$config['enabled']) continue;

            try {
                $response = match ($name) {
                    'groq' => $this->callGroq($prompt, $systemPrompt, $config, $options),
                    'mistral' => $this->callMistral($prompt, $systemPrompt, $config, $options),
                    'openrouter' => $this->callOpenRouter($prompt, $systemPrompt, $config, $options),
                    'cloudflare' => $this->callCloudflare($prompt, $systemPrompt, $config, $options),
                    default => null,
                };

                if ($response && !empty($response)) {
                    $this->activeProvider = $name;
                    $this->cache[$cacheKey] = $response;
                    return $response;
                }
            } catch (\Exception $e) {
                \Log::warning("AI Provider {$name} failed: " . $e->getMessage());
                continue;
            }
        }

        throw new \Exception("All AI providers failed");
    }

    /**
     * Call Groq API
     */
    private function callGroq($prompt, $systemPrompt, $config, $options)
    {
        $client = new \GuzzleHttp\Client();
        
        $messages = [];
        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $response = $client->post($config['endpoint'] . '/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $config['api_key'],
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $config['model'],
                'messages' => $messages,
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens' => $options['max_tokens'] ?? 1024,
            ],
            'timeout' => 30,
        ]);

        $data = json_decode($response->getBody(), true);
        return $data['choices'][0]['message']['content'] ?? null;
    }

    /**
     * Call Mistral API
     */
    private function callMistral($prompt, $systemPrompt, $config, $options)
    {
        $client = new \GuzzleHttp\Client();
        
        $messages = [];
        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $response = $client->post($config['endpoint'] . '/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $config['api_key'],
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $config['model'],
                'messages' => $messages,
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens' => $options['max_tokens'] ?? 1024,
            ],
            'timeout' => 30,
        ]);

        $data = json_decode($response->getBody(), true);
        return $data['choices'][0]['message']['content'] ?? null;
    }

    /**
     * Call OpenRouter API
     */
    private function callOpenRouter($prompt, $systemPrompt, $config, $options)
    {
        $client = new \GuzzleHttp\Client();
        
        $messages = [];
        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $response = $client->post($config['endpoint'] . '/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $config['api_key'],
                'Content-Type' => 'application/json',
                'HTTP-Referer' => env('APP_URL'),
            ],
            'json' => [
                'model' => $config['model'],
                'messages' => $messages,
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens' => $options['max_tokens'] ?? 1024,
            ],
            'timeout' => 30,
        ]);

        $data = json_decode($response->getBody(), true);
        return $data['choices'][0]['message']['content'] ?? null;
    }

    /**
     * Call Cloudflare Workers AI
     */
    private function callCloudflare($prompt, $systemPrompt, $config, $options)
    {
        $client = new \GuzzleHttp\Client();

        $payload = ['prompt' => $prompt];
        if ($systemPrompt) {
            $payload['system'] = $systemPrompt;
        }

        $response = $client->post(
            $config['endpoint'] . '/accounts/' . $config['account_id'] . '/ai/run/' . $config['model'],
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $config['api_token'],
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => 30,
            ]
        );

        $data = json_decode($response->getBody(), true);
        return $data['result']['response'] ?? null;
    }

    /**
     * Get active provider info
     */
    public function getActiveProvider()
    {
        return $this->activeProvider;
    }

    /**
     * Get all providers status
     */
    public function getStatus()
    {
        return [
            'active_provider' => $this->activeProvider,
            'providers' => array_map(function ($p) {
                return [
                    'enabled' => $p['enabled'],
                    'priority' => $p['priority'],
                    'model' => $p['model'] ?? $p['model'],
                ];
            }, $this->providers),
        ];
    }
}
