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

class GirofeedsOrdersAdditionalData extends ObjectModel
{
    public $id;

    public $id_order;

    public $field_in_post;

    public $value_in_post;

    public $date_add;

    public static $definition = [
        'table' => 'girofeeds_orders_additional_data',
        'primary' => 'id_girofeeds_orders_additional_data',
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
