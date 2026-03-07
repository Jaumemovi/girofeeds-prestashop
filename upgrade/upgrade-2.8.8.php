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

function upgrade_module_2_8_8($module)
{
    $sql = [];

    $sql[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'girofeeds_cache` ADD id_lang INT(11) NOT NULL';
    $sql[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'girofeeds_cache` ADD INDEX (`id_lang`)';
    $sql[] = 'UPDATE `' . _DB_PREFIX_ . 'girofeeds_cache` SET id_lang = 1';

    foreach ($sql as $query) {
        if (Db::getInstance()->execute($query) == false) {
            return false;
        }
    }

    return $module;
}
