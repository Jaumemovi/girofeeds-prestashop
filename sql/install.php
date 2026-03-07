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

$sql = [];

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'girofeeds` (
    `id_girofeeds` int(11) NOT NULL AUTO_INCREMENT,
    PRIMARY KEY  (`id_girofeeds`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'girofeeds_webhooks` (
    `id_girofeeds_webhook` int(11) NOT NULL AUTO_INCREMENT,
	`active` int(11) NOT NULL,
	`action` VARCHAR(255) NOT NULL,
	`address` VARCHAR(255) NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_girofeeds_webhook`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'girofeeds_feedfields` (
    `id_girofeeds_feedfields` int(11) NOT NULL AUTO_INCREMENT,
    `tablename` VARCHAR(255) NOT NULL,
	`field_in_db` VARCHAR(255) NOT NULL,
	`field_in_feed` VARCHAR(255) NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_girofeeds_feedfields`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'girofeeds_orders_additional_data` (
    `id_girofeeds_orders_additional_data` int(11) NOT NULL AUTO_INCREMENT,
    `id_order` int(11) NOT NULL,
	`field_in_post` VARCHAR(255) NOT NULL,
	`value_in_post` VARCHAR(255) NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_girofeeds_orders_additional_data`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'girofeeds_orders_additional_data` (
    `id_girofeeds_orders_additional_data` int(11) NOT NULL AUTO_INCREMENT,
    `id_order` int(11) NOT NULL,
	`field_in_post` VARCHAR(255) NOT NULL,
	`value_in_post` VARCHAR(255) NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_girofeeds_orders_additional_data`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'girofeeds_stock_update` (
    `id_girofeeds_stock_update` int(11) NOT NULL AUTO_INCREMENT,
    `id_product` int(11) NOT NULL,
    `id_product_attribute` int(11) NOT NULL,
    `working` int(11) NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_girofeeds_stock_update`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'girofeeds_cache` (
    `id_girofeeds_cache` int(11) NOT NULL AUTO_INCREMENT,
    `cache_key` VARCHAR(255) NOT NULL,
    `cache_value` MEDIUMTEXT NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_girofeeds_cache`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'girofeeds_carriers` (
    `id_girofeeds_carriers` int(11) NOT NULL AUTO_INCREMENT,
    `entity_type` VARCHAR(15) NOT NULL,
    `id_entity` int(11) NOT NULL,
    `id_carrier` int(11) NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_girofeeds_carriers`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'girofeeds_cache` ADD INDEX (`cache_key`)';

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

$sql[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'girofeeds_cache` ADD id_lang INT(11) NOT NULL';
$sql[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'girofeeds_cache` ADD INDEX (`id_lang`)';
$sql[] = 'UPDATE `' . _DB_PREFIX_ . 'girofeeds_cache` SET id_lang = 1';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
