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
