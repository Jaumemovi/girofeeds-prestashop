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

class ChannableWebhook extends ObjectModel
{
    public $id;

    public $active;

    public $action;

    public $address;

    public $date_add;

    public static $definition = [
        'table' => 'channable_webhooks',
        'primary' => 'id_channable_webhook',
        'fields' => [
            'active' => [
                'type' => self::TYPE_INT,
                'validate' => 'isInt',
            ],
            'action' => [
                'type' => self::TYPE_STRING,
                'size' => 255,
            ],
            'address' => [
                'type' => self::TYPE_STRING,
                'size' => 255,
            ],
            'date_add' => [
                'type' => self::TYPE_DATE,
                'validate' => 'isDateFormat',
            ],
        ],
    ];

    public static function getAllWebhooks()
    {
        $return = [];
        $sql = 'SELECT w.* FROM `' . _DB_PREFIX_ . 'channable_webhooks` w';
        if ($results = Db::getInstance()->executeS($sql)) {
            foreach ($results as $row) {
                $return[] = ['id' => $row['id_channable_webhook'],
                    'active' => $row['active'],
                    'action' => $row['action'],
                    'address' => $row['address'],
                ];
            }
        }

        return $return;
    }

    public static function getExistingOrNewWebhook($address)
    {
        $sql = 'SELECT w.* FROM `' . _DB_PREFIX_ . 'channable_webhooks` w
                 WHERE w.`address` = \'' . pSQL($address) . '\'';
        if ($result = Db::getInstance()->getRow($sql)) {
            return new self($result['id_channable_webhook']);
        }
        $webhook = new self();
        $webhook->address = $address;
        $webhook->date_add = date('Y-m-d H:i:s');

        return $webhook;
    }
}
