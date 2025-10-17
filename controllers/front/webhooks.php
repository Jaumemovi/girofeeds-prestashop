<?php
/**
 * 2007-2025 patworx.de
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade AmazonPay to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    patworx multimedia GmbH <service@patworx.de>
 *  @copyright 2007-2025 patworx multimedia GmbH
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}
class ChannableWebhooksModuleFrontController extends ModuleFrontController
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

        $postData = Channable::fetchPhpInput();
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $jsonData = ChannableWebhook::getAllWebhooks();
        } elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
            if (!Tools::getValue('id')) {
                exit('ID not submitted');
            }
            $webhook = new ChannableWebhook((int) Tools::getValue('id'));
            $webhook->delete();
            $jsonData = ['status' => 'OK'];
        } elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $postData = json_decode($postData);
            if ($postData != null) {
                $webhook = ChannableWebhook::getExistingOrNewWebhook((string) $postData->address);
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
