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

class ChannableCarrier extends ObjectModel
{
    public $id;

    public $entity_type;

    public $id_entity;

    public $id_carrier;

    public $date_add;

    public static $definition = [
        'table' => 'channable_carriers',
        'primary' => 'id_channable_carriers',
        'fields' => [
            'entity_type' => [
                'type' => self::TYPE_STRING,
                'size' => 15,
            ],
            'id_entity' => [
                'type' => self::TYPE_INT,
                'validate' => 'isInt',
            ],
            'id_carrier' => [
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
     * @return array|null
     *
     * @throws PrestaShopDatabaseException
     */
    public static function getAllAssignements()
    {
        $return = [];
        $sql = 'SELECT cc.* FROM `' . _DB_PREFIX_ . 'channable_carriers` cc';
        if ($results = Db::getInstance()->executeS($sql)) {
            foreach ($results as $row) {
                $return[] = ['id' => $row['id_channable_carriers'],
                    'entity_type' => $row['entity_type'],
                    'id_entity' => $row['id_entity'],
                    'id_carrier' => $row['id_carrier'],
                ];
            }
        }
        return $return;
    }

    /**
     * @param $category_ids
     *
     * @return false|self
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getByCategoryIds($category_ids)
    {
        if (is_array($category_ids) && count($category_ids)) {
            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`
                 WHERE `id_entity` IN (' . join(',', $category_ids) . ') AND `entity_type` = \'category\'';
            if ($result = Db::getInstance()->getRow($sql)) {
                return new self($result['id_channable_carriers']);
            } else {
                return false;
            }
        }
        return false;
    }

    /**
     * @param $id_product
     *
     * @return false|self
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getByProductId($id_product)
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`
                 WHERE `id_entity` = \'' . (int) $id_product . '\' AND `entity_type` = \'product\'';
        if ($result = Db::getInstance()->getRow($sql)) {
            return new self($result['id_channable_carriers']);
        } else {
            return false;
        }
    }
}
