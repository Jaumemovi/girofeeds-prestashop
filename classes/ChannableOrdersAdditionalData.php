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

class ChannableOrdersAdditionalData extends ObjectModel
{
    public $id;

    public $id_order;

    public $field_in_post;

    public $value_in_post;

    public $date_add;

    public static $definition = [
        'table' => 'channable_orders_additional_data',
        'primary' => 'id_channable_orders_additional_data',
        'fields' => [
            'id_order' => [
                'type' => self::TYPE_INT,
                'validate' => 'isInt',
            ],
            'field_in_post' => [
                'type' => self::TYPE_STRING,
                'size' => 255,
            ],
            'value_in_post' => [
                'type' => self::TYPE_STRING,
                'size' => 255,
            ],
            'date_add' => [
                'type' => self::TYPE_DATE,
                'validate' => 'isDateFormat',
            ],
        ],
    ];

    /**
     * @param $id_order
     *
     * @return array|bool
     *
     * @throws PrestaShopDatabaseException
     */
    public static function getByOrderId($id_order)
    {
        $return = false;
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . self::$definition['table'] . ' WHERE id_order = ' . (int) $id_order;
        if ($results = Db::getInstance()->ExecuteS($sql)) {
            foreach ($results as $row) {
                if (!$return) {
                    $return = [];
                }
                $return[] = $row;
            }
        }

        return $return;
    }
}
