<?php

namespace App\Services;

class TelegramAuthService
{
    /**
     * Validate Telegram WebApp initData string and return user data if valid.
     */
    public function validateInitData(string $initData): ?array
    {
        parse_str($initData, $data);

        // Check required fields
        if (!isset($data['hash'], $data['auth_date'])) {
            return null;
        }

        // 1. Replay attack mitigation: reject initData older than 24 hours
        $authDate = (int) $data['auth_date'];
        if (time() - $authDate > 86400) {
            return null;
        }

        $checkHash = $data['hash'];
        unset($data['hash']);

        // 2. Sort keys alphabetically
        ksort($data);

        // 3. Build data check string: key=value\n
        $dataCheckString = collect($data)
            ->map(fn($val, $key) => "{$key}={$val}")
            ->implode("\n");

        // 4. Calculate HMAC-SHA-256 with "WebAppData" secret key
        $botToken = config('services.telegram.bot_token', '');
        if (empty($botToken)) {
            return null;
        }

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (!hash_equals($calculatedHash, $checkHash)) {
            return null;
        }

        // 5. Decode user JSON object
        if (!isset($data['user'])) {
            return null;
        }

        $user = json_decode($data['user'], true);

        return is_array($user) ? $user : null;
    }

    /**
     * Helper to generate a valid test initData string (useful for unit tests and local dev).
     */
    public function generateTestInitData(array $userData, ?string $botToken = null, ?int $authDate = null): string
    {
        $botToken = $botToken ?? config('services.telegram.bot_token', 'mock_token_for_local_testing');
        $authDate = $authDate ?? time();

        $data = [
            'auth_date' => (string) $authDate,
            'query_id' => 'AAHdF6IQAAAAAN0XohD72r6Q',
            'user' => json_encode($userData),
        ];

        ksort($data);
        $dataCheckString = collect($data)
            ->map(fn($val, $key) => "{$key}={$val}")
            ->implode("\n");

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

        $data['hash'] = $hash;

        return http_build_query($data);
    }
}
