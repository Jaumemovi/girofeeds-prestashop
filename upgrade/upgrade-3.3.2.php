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

function upgrade_module_3_3_2($module)
{
    $sql = [];
    $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'channable_carriers` (
    `id_channable_carriers` int(11) NOT NULL AUTO_INCREMENT,
    `entity_type` VARCHAR(15) NOT NULL,
    `id_entity` int(11) NOT NULL,
    `id_carrier` int(11) NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_channable_carriers`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

    foreach ($sql as $query) {
        if (Db::getInstance()->execute($query) == false) {
            return false;
        }
    }

    return $module;
}
