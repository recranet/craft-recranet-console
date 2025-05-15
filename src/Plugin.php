<?php

namespace recranet\craftrecranetconsolecraftcmsversion;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\helpers\App;
use craft\web\User;

/**
 * Recranet Console CraftCMS Version plugin
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

            if (!$user->admin) {
                return;
            }

            $webhookUrl = App::env('RECRANET_CONSOLE_CRAFTCMS_VERSION_WEBHOOK');

            if (!$webhookUrl) {
                Craft::error('Webhook URL not set in environment variable.', __METHOD__);
                return;
            }

            $client = Craft::createGuzzleClient([
                'connect_timeout' => 5,
            ]);

            $response = $client->post($webhookUrl, [
                'json' => [
                    'username' => $user->username,
                    'datetime' => date('Y-m-d H:i:s'),
                    'cmsVersion' => Craft::$app->getVersion(),
                    'cmsLicenseKey' => App::licenseKey(),
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                Craft::info('Webhook sent successfully!', __METHOD__);
            } else {
                Craft::error('Error sending webhook: ' . $response->getStatusCode(), __METHOD__);
            }
        });
    }
}
