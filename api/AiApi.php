<?php
/**
 * AI API Controller
 */

require_once __DIR__ . '/BaseApi.php';
require_once __DIR__ . '/CloudflareAi.php';
require_once __DIR__ . '/MonitorApi.php';

class AiApi extends BaseApi {
    private $ai;

    public function __construct(CacheManager $cache = null) {
        parent::__construct($cache);
        $cf = Config::get('cloudflare');
        $this->ai = new CloudflareAi([
            'account_id' => $cf['account_id'],
            'api_token'  => $cf['api_token'],
            'model'      => '@cf/meta/llama-3-8b-instruct'
        ]);
    }

    public function handleChat() {
        $input = json_decode(file_get_contents("php://input"), true);
        $messages = $input['messages'] ?? [];

        if (empty($messages)) {
            $this->sendBadRequest("No messages provided");
        }

        $result = $this->ai->chat($messages);
        $this->sendResponse($result);
    }

    public function getStatusReport() {
        $monitor = new MonitorApi($this->cache);
        $stats = $monitor->getMasterStats();
        
        $result = $this->ai->generateReport($stats);
        $this->sendResponse($result);
    }

    public function sendAiTelegramReport() {
        // Fetch stats
        $monitor = new MonitorApi($this->cache);
        $stats = $monitor->getMasterStats();
        
        // Generate AI analysis
        $report = $this->ai->generateReport($stats);
        
        if (!$report['success']) {
            $this->sendError("Failed to generate AI report");
        }

        $text = "🤖 *AI INSIGHT REPORT*\n\n" . $report['response'];

        // Send via Telegram
        require_once __DIR__ . '/telegram/BotHandler.php';
        $telegramConfig = Config::get('telegram');
        $bot = new BotHandler(['alerts' => $telegramConfig], 'server');
        
        $result = $bot->sendAlert('ai_report', 'general', $text);
        
        $this->sendResponse([
            'success' => $result,
            'report' => $report['response']
        ]);
    }
}
