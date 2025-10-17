<?php
/**
 * 2007-2017 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2017 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

$sql = [];

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'channable` (
    `id_channable` int(11) NOT NULL AUTO_INCREMENT,
    PRIMARY KEY  (`id_channable`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'channable_webhooks` (
    `id_channable_webhook` int(11) NOT NULL AUTO_INCREMENT,
	`active` int(11) NOT NULL,
	`action` VARCHAR(255) NOT NULL,
	`address` VARCHAR(255) NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_channable_webhook`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'channable_feedfields` (
    `id_channable_feedfields` int(11) NOT NULL AUTO_INCREMENT,
    `tablename` VARCHAR(255) NOT NULL,
	`field_in_db` VARCHAR(255) NOT NULL,
	`field_in_feed` VARCHAR(255) NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_channable_feedfields`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'channable_orders_additional_data` (
    `id_channable_orders_additional_data` int(11) NOT NULL AUTO_INCREMENT,
    `id_order` int(11) NOT NULL,
	`field_in_post` VARCHAR(255) NOT NULL,
	`value_in_post` VARCHAR(255) NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_channable_orders_additional_data`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'channable_orders_additional_data` (
    `id_channable_orders_additional_data` int(11) NOT NULL AUTO_INCREMENT,
    `id_order` int(11) NOT NULL,
	`field_in_post` VARCHAR(255) NOT NULL,
	`value_in_post` VARCHAR(255) NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_channable_orders_additional_data`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'channable_stock_update` (
    `id_channable_stock_update` int(11) NOT NULL AUTO_INCREMENT,
    `id_product` int(11) NOT NULL,
    `id_product_attribute` int(11) NOT NULL,
    `working` int(11) NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_channable_stock_update`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'channable_cache` (
    `id_channable_cache` int(11) NOT NULL AUTO_INCREMENT,
    `cache_key` VARCHAR(255) NOT NULL,
    `cache_value` MEDIUMTEXT NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_channable_cache`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'channable_carriers` (
    `id_channable_carriers` int(11) NOT NULL AUTO_INCREMENT,
    `entity_type` VARCHAR(15) NOT NULL,
    `id_entity` int(11) NOT NULL,
    `id_carrier` int(11) NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_channable_carriers`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'channable_cache` ADD INDEX (`cache_key`)';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'channable_products_queue` (
    `id_channable_products_queue` int(11) NOT NULL AUTO_INCREMENT,
    `id_product` int(11) NOT NULL,
    `running` int(2) DEFAULT 0,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_channable_products_queue`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'INSERT INTO  `' . _DB_PREFIX_ . 'channable_products_queue`
        (id_product, running, date_add)
        SELECT id_product, 0, NOW() FROM `' . _DB_PREFIX_ . 'product`
    ';

$sql[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'channable_cache` ADD id_lang INT(11) NOT NULL';
$sql[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'channable_cache` ADD INDEX (`id_lang`)';
$sql[] = 'UPDATE `' . _DB_PREFIX_ . 'channable_cache` SET id_lang = 1';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
