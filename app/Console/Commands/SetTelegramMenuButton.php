<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetTelegramMenuButton extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:set-menu-button {url? : Optional URL for the web app (defaults to APP_URL/tma)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set or update the Telegram Bot Menu Button to open the TMA Web App URL';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $botToken = config('services.telegram.bot_token');
        if (empty($botToken)) {
            $this->error('TELEGRAM_BOT_TOKEN is not set in your .env file.');
            return 1;
        }

        $baseUrl = $this->argument('url') ?: rtrim(config('app.url', 'http://localhost:8000'), '/');
        $tmaUrl = str_ends_with($baseUrl, '/tma') ? $baseUrl : "{$baseUrl}/tma";

        $this->info("Updating Telegram Bot menu button to: {$tmaUrl}");

        $response = Http::timeout(10)->post("https://api.telegram.org/bot{$botToken}/setChatMenuButton", [
            'menu_button' => [
                'type' => 'web_app',
                'text' => 'IT Help Desk',
                'web_app' => [
                    'url' => $tmaUrl,
                ],
            ],
        ]);

        if ($response->successful() && ($response->json('ok') === true)) {
            $this->info('✅ Telegram Bot Menu Button successfully updated!');
            return 0;
        }

        $this->error('❌ Telegram API error: ' . $response->body());
        return 1;
    }
}
