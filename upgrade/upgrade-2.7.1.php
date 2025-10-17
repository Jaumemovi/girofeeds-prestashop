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

function upgrade_module_2_7_1($module)
{
    Configuration::updateValue('CHANNABLE_DO_CRON_FROM_BACKEND', 1);
    Configuration::updateValue('CHANNABLE_CRON_BACKEND_TIMEDIFF_MIN', 5);

    $sql = [];
    $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'channable_stock_update` (
    `id_channable_stock_update` int(11) NOT NULL AUTO_INCREMENT,
    `id_product` int(11) NOT NULL,
    `id_product_attribute` int(11) NOT NULL,
    `working` int(11) NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_channable_stock_update`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

    foreach ($sql as $query) {
        if (Db::getInstance()->execute($query) == false) {
            return false;
        }
    }

    return $module;
}
