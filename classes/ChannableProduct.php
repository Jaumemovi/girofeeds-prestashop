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

class ChannableProduct extends Product
{
    public function getPriceForFeed($qty, $id_attribute = false)
    {
        if ($qty == 0) {
            return 0;
        }
        $id_product_attribute = null;
        if ($id_attribute) {
            $id_product_attribute = $id_attribute;
        }
        $price = self::getPriceStatic(
            (int) $this->id,
            false,
            $id_product_attribute,
            6,
            null,
            false,
            true,
            (int) $qty
        );

        return $price;
    }

    /**
     * Get all available product attributes resume
     *
     * @param int $id_lang Language id
     *
     * @return array Product attributes combinations
     */
    public function getAttributesForZusammenfassungUse($id_lang)
    {
        if (!Combination::isFeatureActive()) {
            return [];
        }

        $combinationsResult = Db::getInstance()->executeS('SELECT pa.*, product_attribute_shop.*
				FROM `' . _DB_PREFIX_ . 'product_attribute` pa
				' . Shop::addSqlAssociation('product_attribute', 'pa') . '
				WHERE pa.`id_product` = ' . (int) $this->id . '
				GROUP BY pa.`id_product_attribute`');

        if (!$combinationsResult) {
            return false;
        }

        $to_leave = [
            'reference',
        ];

        $combinations = [];
        foreach ($combinationsResult as $comb) {
            $combinations[$comb['id_product_attribute']] = $comb;
        }

        $product_attributes = [];
        foreach ($combinations as $combination) {
            $product_attributes[] = (int) $combination['id_product_attribute'];
        }

        $lang = Db::getInstance()->executeS('SELECT pac.id_product_attribute, ag.`id_attribute_group`, agl.`public_name`, al.`name` as al_name
				FROM `' . _DB_PREFIX_ . 'product_attribute_combination` pac
				LEFT JOIN `' . _DB_PREFIX_ . 'attribute` a ON a.`id_attribute` = pac.`id_attribute`
				LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group` ag ON ag.`id_attribute_group` = a.`id_attribute_group`
				LEFT JOIN `' . _DB_PREFIX_ . 'attribute_lang` al ON (a.`id_attribute` = al.`id_attribute` AND al.`id_lang` = ' . (int) $id_lang . ')
				LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group_lang` agl ON (ag.`id_attribute_group` = agl.`id_attribute_group` AND agl.`id_lang` = ' . (int) $id_lang . ')
				WHERE pac.id_product_attribute IN (' . implode(',', $product_attributes) . ')');

        foreach ($lang as $k => $row) {
            $to_leave[] = $row['public_name'];
            $combinations[$row['id_product_attribute']][$row['public_name']] = $row['al_name'];
        }

        // Get quantity of each variations
        foreach ($combinations as $key => $row) {
            $cache_key = $row['id_product'] . '_' . $row['id_product_attribute'] . '_quantity';

            if (!Cache::isStored($cache_key)) {
                $result = StockAvailable::getQuantityAvailableByProduct($row['id_product'], $row['id_product_attribute']);
                Cache::store(
                    $cache_key,
                    $result
                );
                $combinations[$key]['quantity'] = $result;
            } else {
                $combinations[$key]['quantity'] = Cache::retrieve($cache_key);
            }
            foreach ($combinations[$key] as $field => $value) {
                if (!in_array($field, $to_leave)) {
                    unset($combinations[$key][$field]);
                }
                $combinations[$key]['price_without_tax'] = $this->getPriceForFeed(
                    1,
                    $row['id_product_attribute']
                );
            }
        }

        return $combinations;
    }

    public static function prepareFieldsForFeed($product)
    {
        $to_leave = [
            'id',
            'id_manufacturer',
            'id_category_default',
            'new',
            'id_tax_rules_group',
            'manufacturer_name',
            'reference',
            'width',
            'height',
            'depth',
            'weight',
            'ean13',
            'isbn',
            'upc',
            'is_virtual',
            'minimal_quantity',
            'price',
            'active',
            'available_for_order',
            'condition',
            'name',
            'description',
            'description_short',
            'combinations',
        ];
        foreach ($product as $field => $value) {
            if (!in_array($field, $to_leave)) {
                unset($product[$field]);
            }
        }

        return $product;
    }

    public function getApiFeatures()
    {
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
				SELECT fl.`name`, fvl.`value`
				FROM `' . _DB_PREFIX_ . 'feature_product` fp
				LEFT JOIN `' . _DB_PREFIX_ . 'feature_value` fv ON (fp.id_feature_value = fv.id_feature_value)
                LEFT JOIN `' . _DB_PREFIX_ . 'feature_lang` fl ON (fl.id_feature = fp.id_feature)
                LEFT JOIN `' . _DB_PREFIX_ . 'feature_value_lang` fvl ON (fp.id_feature_value = fvl.id_feature_value)
				WHERE fp.`id_product` = ' . (int) $this->id . '
                  AND (fl.id_lang = ' . (int) Context::getContext()->language->id . ')
                  AND (fvl.id_lang = ' . (int) Context::getContext()->language->id . ')');

        return $result;
    }
}
