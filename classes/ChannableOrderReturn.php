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

class ChannableOrderReturn extends ObjectModel
{
    public $id;

    public $id_order;

    public $return_code;

    public $date_add;

    public static $definition = [
        'table' => 'channable_order_return',
        'primary' => 'id_channable_order_return',
        'fields' => [
            'id_order' => [
                'type' => self::TYPE_INT,
            ],
            'return_code' => [
                'type' => self::TYPE_STRING,
            ],
            'date_add' => [
                'type' => self::TYPE_DATE,
                'validate' => 'isDateFormat',
            ],
        ],
    ];

    /**
     * @param $id_order
     * @param $return_code
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public static function addOrUpdateToOrder($id_order, $return_code)
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`
                 WHERE `id_order` = \'' . (int) $id_order . '\'';
        if ($result = Db::getInstance()->getRow($sql)) {
            $orderReturn = new self($result['id_channable_order_return']);
        } else {
            $orderReturn = new self();
        }
        $orderReturn->id_order = (int) $id_order;
        $orderReturn->return_code = $return_code;
        $orderReturn->date_add = date('Y-m-d H:i:s');
        $orderReturn->save();

        return true;
    }

    /**
     * @param $id_order
     *
     * @return false|self
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getByOrderId($id_order)
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`
                 WHERE `id_order` = \'' . (int) $id_order . '\'';
        if ($result = Db::getInstance()->getRow($sql)) {
            return new self($result['id_channable_order_return']);
        } else {
            return false;
        }
    }

    /**
     * @param int $id_channable_products_queue
     *
     * @return bool
     */
    public static function deleteById($id_channable_order_return)
    {
        if (Channable::useCache()) {
            $sql = 'DELETE FROM `' . _DB_PREFIX_ . self::$definition['table'] . '` WHERE `id_channable_order_return` = ' . (int) $id_channable_order_return;

            return Db::getInstance()->execute($sql);
        }

        return false;
    }
}
