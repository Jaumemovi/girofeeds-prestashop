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

function upgrade_module_2_8_8($module)
{
    $sql = [];

    $sql[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'channable_cache` ADD id_lang INT(11) NOT NULL';
    $sql[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'channable_cache` ADD INDEX (`id_lang`)';
    $sql[] = 'UPDATE `' . _DB_PREFIX_ . 'channable_cache` SET id_lang = 1';

    foreach ($sql as $query) {
        if (Db::getInstance()->execute($query) == false) {
            return false;
        }
    }

    return $module;
}
