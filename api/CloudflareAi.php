<?php
/**
 * Cloudflare AI Service
 * Interface for Cloudflare Workers AI (Llama-3)
 */

class CloudflareAi {
    private $accountId;
    private $apiToken;
    private $model;

    public function __construct($config) {
        $this->accountId = $config['account_id'];
        $this->apiToken = $config['api_token'];
        $this->model = $config['model'] ?? '@cf/meta/llama-3-8b-instruct';
    }

    private function getEndpoint() {
        return "https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/ai/run/{$this->model}";
    }

    /**
     * Run a chat completion
     */
    public function chat($messages) {
        $ch = curl_init($this->getEndpoint());
        
        $payload = json_encode(['messages' => $messages]);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->apiToken}",
                "Content-Type: application/json"
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Cloudflare AI Error ($httpCode): " . $response);
            return ['success' => false, 'error' => "AI API returned code $httpCode"];
        }

        $data = json_decode($response, true);
        return [
            'success' => true,
            'result' => $data['result'] ?? null,
            'response' => $data['result']['response'] ?? ''
        ];
    }

    /**
     * Generate a system report based on current data
     */
    public function generateReport($systemData) {
        $systemPrompt = "You are a Senior Server Reliability Engineer for TechnoStationery. 
        Analyze the following real-time server data and provide a concise 3-paragraph executive summary for the owner. 
        Focus on:
        1. Current health and load bottlenecks.
        2. E-commerce performance (Magento).
        3. Clear recommendations or 'All clear' signal.
        Use professional but direct language. Format for Telegram (use bold for emphasis).";

        $userData = "CURRENT SERVER SNAPSHOT:\n" . json_encode($systemData, JSON_PRETTY_PRINT);

        return $this->chat([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userData]
        ]);
    }
}
