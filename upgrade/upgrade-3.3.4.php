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

function upgrade_module_3_3_4($module)
{
    $sql = 'SHOW INDEX FROM `' . _DB_PREFIX_ . 'channable_orders_additional_data` WHERE Key_name = \'id_order_idx\'';
    if (!Db::getInstance()->getRow($sql)) {
        Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'channable_orders_additional_data` ADD INDEX `id_order_idx` (`id_order`)');
    }

    $sql = 'SHOW INDEX FROM `' . _DB_PREFIX_ . 'channable_orders_additional_data` WHERE Key_name = \'field_in_post_idx\'';
    if (!Db::getInstance()->getRow($sql)) {
        Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'channable_orders_additional_data` ADD INDEX `field_in_post_idx` (`field_in_post`)');
    }

    return $module;
}
