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

class GirofeedsStockUpdate extends ObjectModel
{
    public $id;

    public $id_product;

    public $id_product_attribute;

    public $working;

    public $date_add;

    public static $definition = [
        'table' => 'girofeeds_stock_update',
        'primary' => 'id_girofeeds_stock_update',
        'fields' => [
            'id_product' => [
                'type' => self::TYPE_INT,
                'validate' => 'isInt',
            ],
            'id_product_attribute' => [
                'type' => self::TYPE_INT,
                'validate' => 'isInt',
            ],
            'working' => [
                'type' => self::TYPE_INT,
                'validate' => 'isInt',
            ],
            'date_add' => [
                'type' => self::TYPE_DATE,
                'validate' => 'isDateFormat',
            ],
        ],
    ];

    /**
     * @return array
     *
     * @throws PrestaShopDatabaseException
     */
    public static function getQualifiedUpdates()
    {
        $return = [];
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . self::$definition['table'] . '` WHERE working = 0';
        if ($results = Db::getInstance()->executeS($sql)) {
            foreach ($results as $row) {
                $return[] = ['id_girofeeds_stock_update' => $row['id_girofeeds_stock_update'],
                    'id_product' => $row['id_product'],
                    'id_product_attribute' => $row['id_product_attribute'],
                ];
            }
        }

        return $return;
    }

    /**
     * @param $id_product
     *
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     */
    public static function existsByIdProduct($id_product, $id_product_attribute = 0)
    {
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . self::$definition['table'] . ' WHERE id_product = ' . (int) $id_product . ' AND id_product_attribute = ' . (int) $id_product_attribute;
        if ($results = Db::getInstance()->ExecuteS($sql)) {
            return true;
        }

        return false;
    }
}
