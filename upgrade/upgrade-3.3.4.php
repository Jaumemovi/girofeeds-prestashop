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

function upgrade_module_3_3_4($module)
{
    $sql = 'SHOW INDEX FROM `' . _DB_PREFIX_ . 'girofeeds_orders_additional_data` WHERE Key_name = \'id_order_idx\'';
    if (!Db::getInstance()->getRow($sql)) {
        Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'girofeeds_orders_additional_data` ADD INDEX `id_order_idx` (`id_order`)');
    }

    $sql = 'SHOW INDEX FROM `' . _DB_PREFIX_ . 'girofeeds_orders_additional_data` WHERE Key_name = \'field_in_post_idx\'';
    if (!Db::getInstance()->getRow($sql)) {
        Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'girofeeds_orders_additional_data` ADD INDEX `field_in_post_idx` (`field_in_post`)');
    }

    return $module;
}
