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

function upgrade_module_2_8_7($module)
{
    $sql = [];
    $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'girofeeds_products_queue` (
    `id_girofeeds_products_queue` int(11) NOT NULL AUTO_INCREMENT,
    `id_product` int(11) NOT NULL,
    `running` int(2) DEFAULT 0,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_girofeeds_products_queue`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

    $sql[] = 'INSERT INTO  `' . _DB_PREFIX_ . 'girofeeds_products_queue`
        (id_product, running, date_add)
        SELECT id_product, 0, NOW() FROM `' . _DB_PREFIX_ . 'product`
    ';

    foreach ($sql as $query) {
        if (Db::getInstance()->execute($query) == false) {
            return false;
        }
    }

    $module->registerHook('actionProductAdd');

    return $module;
}
