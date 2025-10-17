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

function upgrade_module_1_1_4($module)
{
    $module->registerHook('actionUpdateQuantity');
    $module->registerHook('actionProductUpdate');
    $module->registerHook('actionProductAttributeUpdate');
    $module->registerHook('displayBackOfficeHeader');

    $sql = [];
    $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'channable_webhooks` (
    `id_channable_webhook` int(11) NOT NULL AUTO_INCREMENT,
	`active` int(11) NOT NULL,
	`action` VARCHAR(255) NOT NULL,
	`address` VARCHAR(255) NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_channable_webhook`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

    foreach ($sql as $query) {
        if (Db::getInstance()->execute($query) == false) {
            return false;
        }
    }

    return $module;
}
