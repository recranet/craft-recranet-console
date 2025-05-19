<?php

namespace recranet\craftrecranetconsole;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\helpers\App;
use Psr\Http\Client\ClientExceptionInterface;
use yii\web\User;

/**
 * Recranet Console webhook
 *
 * @method static Plugin getInstance()
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';

    public function init(): void
    {
        parent::init();

        $this->attachEventHandlers();
    }

    private function attachEventHandlers(): void
    {
        Craft::$app->getUser()->on(User::EVENT_AFTER_LOGIN, function ($event) {
            $user = $event->identity;

            if (!$user) {
                return;
            }

            $webhookUrl = App::env('RECRANET_CONSOLE_WEBHOOK');

            if (!$webhookUrl) {
                Craft::error('Webhook URL not set in environment variable.', __METHOD__);
                return;
            }

            $client = Craft::createGuzzleClient([
                'connect_timeout' => 5,
            ]);

            try {
                $response = $client->post($webhookUrl, [
                    'headers' => self::headers(),
                    'json' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'email' => $user->email,
                    ]
                ]);

                if ($response->getStatusCode() === 200) {
                    Craft::info('Webhook sent successfully!', __METHOD__);
                }
            } catch (ClientExceptionInterface $e) {
                Craft::error('Error sending webhook: ' . $e->getMessage(), __METHOD__);
            }
        });
    }

    public static function headers(): array
    {
        // Set env and system
        $headers = [
            'Accept' => 'application/json',
            'X-Craft-Env' => Craft::$app->env,
            'X-Craft-System' => sprintf('craft:%s;%s', Craft::$app->getVersion(), Craft::$app->edition->handle()),
        ];

        // Set platform
        $platform = [];
        foreach (self::platformVersions() as $name => $version) {
            $platform[] = "$name:$version";
        }
        $headers['X-Craft-Platform'] = implode(',', $platform);

        // Set host and user ip
        $request = Craft::$app->getRequest();
        if (!$request->getIsConsoleRequest()) {
            if (($host = $request->getHostInfo()) !== null) {
                $headers['X-Craft-Host'] = $host;
            }
            if (($ip = $request->getUserIP(FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) !== null) {
                $headers['X-Craft-User-Ip'] = $ip;
            }
        }

        return $headers;
    }

    public static function platformVersions(): array
    {
        $versions = [
            'php' => App::phpVersion(),
        ];

        $db = Craft::$app->getDb();
        $versions[$db->getDriverName()] = App::normalizeVersion($db->getSchema()->getServerVersion());

        return $versions;
    }
}
