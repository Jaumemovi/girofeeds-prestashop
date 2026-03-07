<?php
/**
 * Girofeeds - Feed management module for PrestaShop
 * Based on the Channable addon by patworx multimedia GmbH (2007-2025, patworx.de)
 *
 *  @author    Moviendote <hello@girofeeds.com>
 *  @copyright 2025-2026 Moviendote
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}
class GirofeedsWebhooksModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        if (!Tools::getValue('key')) {
            header('HTTP/1.1 401 Unauthorized');
            exit('Not authenticated');
        }
        if (!WebserviceKey::keyExists(Tools::getValue('key')) || !WebserviceKey::isKeyActive(Tools::getValue('key'))) {
            header('HTTP/1.1 401 Unauthorized');
            exit('Not authenticated');
        }

        $postData = Girofeeds::fetchPhpInput();
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $jsonData = GirofeedsWebhook::getAllWebhooks();
        } elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
            if (!Tools::getValue('id')) {
                exit('ID not submitted');
            }
            $webhook = new GirofeedsWebhook((int) Tools::getValue('id'));
            $webhook->delete();
            $jsonData = ['status' => 'OK'];
        } elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $postData = json_decode($postData);
            if ($postData != null) {
                $webhook = GirofeedsWebhook::getExistingOrNewWebhook((string) $postData->address);
                $webhook->action = (string) $postData->action;
                $webhook->active = (string) $postData->active;
                $webhook->save();
                $jsonData = ['status' => 'OK'];
            }
        }

        if (isset($jsonData)) {
            header('Content-Type: application/json');
            echo json_encode($jsonData);
        }
        exit;
    }
}
