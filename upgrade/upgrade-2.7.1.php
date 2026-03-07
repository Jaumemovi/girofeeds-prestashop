<?php
/**
 * Original work: 2007-2025 patworx multimedia GmbH (patworx.de)
 * Modifications: 2025-2026 Moviendote (https://girofeeds.com/)
 *
 * Based on the Channable PrestaShop addon developed by patworx multimedia GmbH
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Girofeeds to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    patworx multimedia GmbH <service@patworx.de>
 *  @author    Moviendote <hello@girofeeds.com>
 *  @copyright 2007-2025 patworx multimedia GmbH
 *  @copyright 2025-2026 Moviendote
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_7_1($module)
{
    Configuration::updateValue('GIROFEEDS_DO_CRON_FROM_BACKEND', 1);
    Configuration::updateValue('GIROFEEDS_CRON_BACKEND_TIMEDIFF_MIN', 5);

    $sql = [];
    $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'girofeeds_stock_update` (
    `id_girofeeds_stock_update` int(11) NOT NULL AUTO_INCREMENT,
    `id_product` int(11) NOT NULL,
    `id_product_attribute` int(11) NOT NULL,
    `working` int(11) NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_girofeeds_stock_update`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

    foreach ($sql as $query) {
        if (Db::getInstance()->execute($query) == false) {
            return false;
        }
    }

    return $module;
}
