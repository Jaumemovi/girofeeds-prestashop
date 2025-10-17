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

class ChannableProductsQueue extends ObjectModel
{
    public $id;

    public $id_product;

    public $running;

    public $date_add;

    public static $definition = [
        'table' => 'channable_products_queue',
        'primary' => 'id_channable_products_queue',
        'fields' => [
            'id_product' => [
                'type' => self::TYPE_INT,
            ],
            'running' => [
                'type' => self::TYPE_BOOL,
            ],
            'date_add' => [
                'type' => self::TYPE_DATE,
                'validate' => 'isDateFormat',
            ],
        ],
    ];

    /**
     * @param $id_product
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public static function addToQueueIfNotExists($id_product)
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`
                 WHERE `id_product` = \'' . (int) $id_product . '\'';
        if ($result = Db::getInstance()->getRow($sql)) {
            return false;
        }
        $prodQueue = new self();
        $prodQueue->id_product = (int) $id_product;
        $prodQueue->date_add = date('Y-m-d H:i:s');
        $prodQueue->running = 0;
        $prodQueue->save();

        return true;
    }

    /**
     * @param int $limit
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     */
    public static function getNonRunningQueue($limit = 20)
    {
        $return = [];
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . self::$definition['table'] . '` WHERE `running` = 0 ORDER BY `date_add` ASC LIMIT 0,' . $limit;
        if ($results = Db::getInstance()->executeS($sql)) {
            foreach ($results as $row) {
                $return[] = ['id_channable_products_queue' => $row['id_channable_products_queue'],
                    'id_product' => $row['id_product'],
                ];
            }
        }

        return $return;
    }

    /**
     * @param int $id_channable_products_queue
     * @param int $running
     *
     * @return bool
     */
    public static function updateRunningStatus($id_channable_products_queue, $running = 1)
    {
        $sql = 'UPDATE `' . _DB_PREFIX_ . self::$definition['table'] . '` SET `running` = \'' . (int) $running . '\' WHERE `id_channable_products_queue` = ' . $id_channable_products_queue;

        return Db::getInstance()->execute($sql);
    }

    /**
     * @return bool
     */
    public static function resetRunningStatus()
    {
        $sql = 'UPDATE `' . _DB_PREFIX_ . self::$definition['table'] . '` SET `running` = \'0\'';

        return Db::getInstance()->execute($sql);
    }

    /**
     * @param int $id_channable_products_queue
     *
     * @return bool
     */
    public static function deleteById($id_channable_products_queue)
    {
        if (Channable::useCache()) {
            $sql = 'DELETE FROM `' . _DB_PREFIX_ . self::$definition['table'] . '` WHERE `id_channable_products_queue` = ' . $id_channable_products_queue;

            return Db::getInstance()->execute($sql);
        }

        return false;
    }
}
