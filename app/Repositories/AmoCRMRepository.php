<?php

namespace App\Repositories;

use App\Models\Services\amoCRM;
use App\Services\amoAPI\amoAPIHub;
use App\Traits\Middleware\Services\AmoCRM\AmoTokenExpirationControlTrait;
use Illuminate\Support\Facades\Log;

class AmoCRMRepository
{
    use AmoTokenExpirationControlTrait;

    private static $amoAPIHub;

    public static function addWebhookAfterAuth(string $webhookUrl, array $settings)
    {
        Log::info(__METHOD__); //DELETE

        if (self::amoTokenExpirationControl()) {
            self::$amoAPIHub = new amoAPIHub(amoCRM::getAuthData());

            $webhooks = self::$amoAPIHub->webhookList();

            if ($webhooks) {
                $webhook = self::findWebhookByUrl(
                    $webhooks['_embedded']['webhooks'],
                    $webhookUrl
                );

                if (!$webhook) {
                    Log::info(__METHOD__, ['Must add']); //DELETE

                    self::$amoAPIHub->addWebhook($webhookUrl, $settings);
                }
            }
        }
    }
    public static function deleteWebhookAfterSignout(string $webhookUrl) {
        Log::info(__METHOD__); //DELETE

        if (self::amoTokenExpirationControl()) {
            self::$amoAPIHub = new amoAPIHub(amoCRM::getAuthData());

            self::$amoAPIHub->deleteWebhook($webhookUrl);
        }
    }

    private static function findWebhookByUrl(array $webhooks, string $url): ?array
    {
        Log::info(__METHOD__); //DELETE

        $data = null;

        foreach ($webhooks as $webhook) {
            if ($webhook['destination'] === $url) {
                $data = $webhook;

                break;
            }
        }

        return $data;
    }
}
