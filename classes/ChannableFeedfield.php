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

class ChannableFeedfield extends ObjectModel
{
    public $id;

    public $tablename;

    public $field_in_db;

    public $field_in_feed;

    public $date_add;

    public static $fields_in_feed = [
        'product' => [
            'id_product',
            'condition',
            'visibility',
            'active',
            'ean13',
            'reference',
            'upc',
            'id_supplier',
            'price',
            'ecotax',
            'weight',
            'height',
            'width',
            'depth',
            'id_category_default'],
        'product_shop' => [
            'id_product',
            'visibility',
            'active',
            'price'],
        'product_lang' => [
            'name',
            'description',
            'description_short',
            'meta_title',
            'meta_description'],
        'product_attribute' => [
            'id_product_attribute',
            'ean13',
            'reference',
            'price'],
        'product_attribute_shop' => [
        ],
        'manufacturer' => [
            'name',
        ],
        'specific_price' => [
            'id_specific_price',
            'id_specific_price_rule',
            'id_cart',
            'id_product',
            'id_shop',
            'id_shop_group',
            'id_currency',
            'id_country',
            'id_group',
            'id_customer',
            'id_product_attribute',
            'reduction_tax',
            'reduction_type',
        ],
        'orders' => [
            'orders_last_7days',
            'orders_last_30days',
            'orders_last_365days',
            'orders_all_time',
        ],
    ];

    public static $definition = [
        'table' => 'channable_feedfields',
        'primary' => 'id_channable_feedfields',
        'fields' => [
            'tablename' => [
                'type' => self::TYPE_STRING,
                'size' => 255,
            ],
            'field_in_db' => [
                'type' => self::TYPE_STRING,
                'size' => 255,
            ],
            'field_in_feed' => [
                'type' => self::TYPE_STRING,
                'size' => 255,
            ],
            'date_add' => [
                'type' => self::TYPE_DATE,
                'validate' => 'isDateFormat',
            ],
        ],
    ];

    public static $cached_fields;

    /**
     * @return array|null
     *
     * @throws PrestaShopDatabaseException
     */
    public static function getAllFeedfields()
    {
        if (is_null(self::$cached_fields)) {
            $return = [];
            $sql = 'SELECT cf.* FROM `' . _DB_PREFIX_ . 'channable_feedfields` cf';
            if ($results = Db::getInstance()->executeS($sql)) {
                foreach ($results as $row) {
                    $return[] = ['id' => $row['id_channable_feedfields'],
                        'tablename' => $row['tablename'],
                        'field_in_db' => $row['field_in_db'],
                        'field_in_feed' => $row['field_in_feed'],
                    ];
                }
            }
            self::$cached_fields = $return;
        }

        return self::$cached_fields;
    }

    /**
     * @return array
     *
     * @throws PrestaShopDatabaseException
     */
    public static function getAllFieldsOfProductTables()
    {
        $fields = [];
        foreach (self::$fields_in_feed as $tablename => $fields_in_feed) {
            $fields[$tablename] = [];
            if ($tablename === 'orders') {
                $fields[$tablename] = [
                    'orders_last_7days',
                    'orders_last_30days',
                    'orders_last_365days',
                    'orders_all_time',
                ];
            } else {
                $sql = 'SHOW COLUMNS FROM `' . _DB_PREFIX_ . pSQL($tablename) . '`';
                if ($results = Db::getInstance()->executeS($sql)) {
                    foreach ($results as $row) {
                        $fields[$tablename][] = $row['Field'];
                    }
                }
            }
        }

        return $fields;
    }

    /**
     * @param array $fields
     *
     * @return array
     */
    public static function excludeFieldsAlreadyInFeedController(array $fields)
    {
        foreach ($fields as $fieldgroup => $groupfields) {
            if ($fieldgroup === 'orders') {
                continue;
            }
            foreach ($groupfields as $fieldkey => $fieldname) {
                if (isset(self::$fields_in_feed[$fieldgroup]) && in_array($fieldname, self::$fields_in_feed[$fieldgroup])) {
                    unset($fields[$fieldgroup][$fieldkey]);
                }
            }
        }

        return $fields;
    }

    /**
     * @return array
     *
     * @throws PrestaShopDatabaseException
     */
    public static function getAvailableFieldsFiltered()
    {
        $fields = self::getAllFieldsOfProductTables();
        $fields = self::excludeFieldsAlreadyInFeedController($fields);

        return $fields;
    }

    /**
     * @return bool
     */
    public static function removeAllFeedfields()
    {
        $sql = 'TRUNCATE TABLE `' . _DB_PREFIX_ . 'channable_feedfields`';

        return Db::getInstance()->execute($sql);
    }
}
