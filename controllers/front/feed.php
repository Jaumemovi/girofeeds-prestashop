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

class ChannableFeedModuleFrontController extends ModuleFrontController
{
    protected static $combination_separator = '||||';
    protected static $combination_desc_separator = '####';
    protected static $imgurl_separator = '!!!!';

    private static $categories_tree_cache = [];
    private static $all_categories_cache = false;

    private $time_debug = false;
    private $sql_debug = false;
    private $sql_optimization_mode = 'id_product'; // or 'id_product_and_attribute';
    private $multiquerymode = false;
    private $track_cached = false;

    public $objPcFieldValue;
    public $objPcFieldOptions;

    protected $feedHasSpecificPrices;

    private static $cache_lifetime_categories = 259200; // 3 days in seconds
    private static $cache_lifetime_products = 2419200; // 28 days in seconds

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function postProcess()
    {
        if (!Tools::getValue('key')) {
            header('HTTP/1.1 401 Unauthorized');
            exit('Not authenticated');
        }
        if (!WebserviceKey::keyExists(Tools::getValue('key')) || !WebserviceKey::isKeyActive(Tools::getValue('key'))) {
            header('HTTP/1.1 401 Unauthorized');
            exit('Not authenticated');
        }

        if (Tools::getValue('multiquerymode') == 'true' || Configuration::get('CHANNABLE_MULTIQUERY_MODE')) {
            $this->multiquerymode = true;
        }

        if (Tools::getValue('time_debug') == 'true') {
            $this->time_debug = true;
        }
        if (Tools::getValue('sql_debug') == 'true') {
            $this->sql_debug = true;
        }
        if (Tools::getValue('track_cached') == 'true') {
            $this->track_cached = true;
        }
        if (Channable::useCache()) {
            $this->sql_optimization_mode = 'id_product_and_attribute';
        }

        if (Configuration::get('CHANNABLE_DISABLE_VARIANTS') == '1') {
            $this->sql_optimization_mode = 'id_product';
        }

        if (Tools::getValue('sql_optimization_mode') == 'id_product' || Tools::getValue('sql_optimization_mode') == 'id_product_and_attribute') {
            $this->sql_optimization_mode = Tools::getValue('sql_optimization_mode');
        }

        $currency = false;
        if (isset(Context::getContext()->currency)) {
            $currency = Context::getContext()->currency;
            $currency = (string) $currency->iso_code;
        }

        $default_country = new Country(Configuration::get('PS_COUNTRY_DEFAULT'), Configuration::get('PS_LANG_DEFAULT'));
        $id_zone = (int) $default_country->id_zone;
        $id_carrier = (int) Configuration::get('PS_CARRIER_DEFAULT');
        $default_carrier = new Carrier((int) Configuration::get('PS_CARRIER_DEFAULT'));

        $channable_customer_id = false;
        if ((int) Configuration::get('CHANNABLE_CUSTOMER_ID') > 0) {
            $channable_customer_id = (int) Configuration::get('CHANNABLE_CUSTOMER_ID');
            if (Customer::getAddressesTotalById($channable_customer_id) > 0) {
                $customer = new Customer($channable_customer_id);
                $address = $customer->getAddresses((int) Context::getContext()->language->id);
                $channable_default_address_id = $address[0]['id_address'];
                $default_country = new Country((int) $address[0]['id_country']);
                $id_zone = (int) $default_country->id_zone;
            }
        }

        $shipping_time_available = '';
        $shipping_time_outofstock = '';

        if (Module::isInstalled('advancedeucompliance') && Module::isEnabled('advancedeucompliance')) {
            $shipping_time_available = Configuration::get('AEUC_LABEL_DELIVERY_TIME_AVAILABLE', (int) Context::getContext()->language->id);
            $shipping_time_outofstock = Configuration::get('AEUC_LABEL_DELIVERY_TIME_OOS', (int) Context::getContext()->language->id);
        }

        $starttime = microtime(true);
        $limit_string = false;

        $sql_optimization_mode = Configuration::get('CHANNABLE_SQL_OPTIMIZATION_MODE');

        if (Tools::getValue('limit')) {
            $limit_string = ' LIMIT ' . implode(',', array_map('intval', explode(',', Tools::getValue('limit'))));
        } else {
            $limit_string = ' LIMIT 0, ' . (int) Configuration::get('CHANNABLE_DEFAULT_PAGE_SIZE');
        }
        $product_ids_in = [];
        if ($sql_optimization_mode) {
            if ($this->sql_optimization_mode == 'id_product') {
                $product_ids_sql = '
                SELECT
                       p.id_product
                  FROM
                       ' . _DB_PREFIX_ . 'product p
				  JOIN ' . _DB_PREFIX_ . 'product_shop ps ON (p.id_product = ps.id_product AND ps.id_shop = \'' . (int) Context::getContext()->shop->id . '\' ' . (Configuration::get('CHANNABLE_DISABLE_INACTIVE') == '1' ? ' AND ps.active = 1' : '') . ')
                       ' . (Configuration::get('CHANNABLE_DISABLE_OUT_OF_STOCK') == '1' ?
                           ' LEFT JOIN ' . _DB_PREFIX_ . 'stock_available sav ON (sav.`id_product` = p.`id_product` AND sav.`id_product_attribute` = 0 ' . StockAvailable::addSqlShopRestriction(null, null, 'sav') . ')
                       ' : '') . '
                 WHERE
        	           ' . (isset($_GET['manual_product_id']) ? ' p.id_product IN (\'' . pSQL($_GET['manual_product_id']) . '\')  ' : '1') . '
                       ' . (Configuration::get('CHANNABLE_DISABLE_INACTIVE') == '1' ? ' AND (ps.active = 1) ' : '') . '
            		   ' . (Configuration::get('CHANNABLE_DISABLE_OUT_OF_STOCK') == '1' ? ' AND (sav.quantity > 0) ' : '') . '
                       ' . $this->sqlHook('product_ids_where') . '
              GROUP BY p.id_product
              ORDER BY p.id_product ' . $limit_string;

                if ($product_ids_results = Db::getInstance()->ExecuteS($product_ids_sql)) {
                    foreach ($product_ids_results as $product_ids_row) {
                        $product_ids_in[] = (int) $product_ids_row['id_product'];
                    }
                }
            } elseif ($this->sql_optimization_mode == 'id_product_and_attribute') {
                $product_ids_sql = '
                SELECT
                       if(pa.id_product_attribute IS NULL,
                          p.id_product,
                          concat(p.id_product, \'_\', pa.id_product_attribute)
                       ) as product_id_in
                  FROM
                       ' . _DB_PREFIX_ . 'product p
                  JOIN
                  	   ' . _DB_PREFIX_ . 'product_shop ps ON (p.id_product = ps.id_product AND ps.id_shop = \'' . (int) Context::getContext()->shop->id . '\' ' . (Configuration::get('CHANNABLE_DISABLE_INACTIVE') == '1' ? ' AND ps.active = 1' : '') . ')
             LEFT JOIN
                       ' . _DB_PREFIX_ . 'product_attribute pa ON (p.id_product = pa.id_product ' . $this->getAttributesDisabled('pa') . ')
             LEFT JOIN
                       ' . _DB_PREFIX_ . 'product_attribute_shop pas ON (pa.id_product_attribute = pas.id_product_attribute OR pa.id_product_attribute IS NULL)
                       ' . (Configuration::get('CHANNABLE_DISABLE_OUT_OF_STOCK') == '1' ? ' LEFT JOIN ' . _DB_PREFIX_ . 'stock_available pq ON (p.id_product = pq.id_product) ' : '') . '
                 WHERE 
        	           ' . (isset($_GET['manual_product_id']) ? ' p.id_product IN (\'' . pSQL($_GET['manual_product_id']) . '\')  ' : '1') . '
                   AND (pas.id_shop = \'' . (int) Context::getContext()->shop->id . '\' OR pas.id_shop IS NULL)
                       ' . (Configuration::get('CHANNABLE_DISABLE_INACTIVE') == '1' ? ' AND (ps.active = 1) ' : '') . '
                       ' . (Configuration::get('CHANNABLE_DISABLE_OUT_OF_STOCK') == '1' ? ' AND (pq.quantity > 0) ' : '') . '
                       ' . $this->sqlHook('product_ids_where') . '
              GROUP BY p.id_product, COALESCE(pa.id_product_attribute, 0)
              ORDER BY p.id_product ' . $limit_string;

                if ($product_ids_results = Db::getInstance()->ExecuteS($product_ids_sql)) {
                    foreach ($product_ids_results as $product_ids_row) {
                        $product_ids_in[] = '\'' . $product_ids_row['product_id_in'] . '\'';
                    }
                }
            }
            if (sizeof($product_ids_in) == 0) {
                $product_ids_in[] = '12345678987654321'; // dummy ID to receive empty result
            }
        }

        if ($this->multiquerymode) {
            $sql = $this->getMultiqueryMode(!Channable::useCache(), $product_ids_in, $limit_string);
        } else {
            $sql = $this->getDefaultMode(!Channable::useCache(), $product_ids_in, $limit_string, $default_country);
        }

        if ($this->time_debug) {
            $results = Db::getInstance()->ExecuteS($sql);
            echo 'Results: ' . sizeof($results) . '<br />';
            $endtime = microtime(true);
            $duration = $endtime - $starttime;
            echo $duration;
            exit;
        }
        if ($this->sql_debug) {
            echo '<pre>';
            echo $sql;
            exit;
        }

        header('Content-Type: application/json');
        $items = [];
        header('Page-Size: ' . Configuration::get('CHANNABLE_DEFAULT_PAGE_SIZE'));
        $tracked_cache = [];
        $tracked_cache_new = [];

        if ($results = Db::getInstance()->ExecuteS($sql)) {
            foreach ($results as $row) {
                $productJsonCache = ChannableCache::getByKey('PRODUCT_JSON_' . $row['id'], self::$cache_lifetime_products, true, (int) Context::getContext()->language->id);
                if ((int) $productJsonCache->id > 0 && !isset($_GET['rebuild_cache']) && $productJsonCache->cache_value != '' && !is_null(json_decode($productJsonCache->cache_value, true))) {
                    $items[] = json_decode(
                        $productJsonCache->cache_value, true
                    );
                    if ($this->track_cached) {
                        $tracked_cache[] = (int) $productJsonCache->id;
                    }
                } else {
                    if (Channable::useCache()) {
                        $row = $this->getProductDetails($row['id']);
                    }
                    if (!array_key_exists('parent_id', $row)) {
                        continue;
                    }
                    $treeCache = ChannableCache::getByKey('CAT_TREE_' . $row['id_category_default'], self::$cache_lifetime_categories, true, (int) Context::getContext()->language->id);
                    if ((int) $treeCache->id > 0) {
                        $row['product_category'] = $treeCache->cache_value;
                    } else {
                        $defaultTree = $this->getParentsCategories($row['id_category_default'], $this->getAllCategories());
                        $tmp = [];
                        foreach ($defaultTree as $cnt => $d) {
                            $tmp[] = $d['name'];
                        }
                        $treeCache->cache_value = join(' > ', $tmp);
                        $treeCache->save();
                        $row['product_category'] = join(' > ', $tmp);
                    }

                    $productCategoriesCache = ChannableCache::getByKey('PROD_CATS_' . $row['parent_id'], self::$cache_lifetime_categories, true, (int) Context::getContext()->language->id);
                    if ((int) $productCategoriesCache->id > 0) {
                        $row['categories'] = json_decode($productCategoriesCache->cache_value, true);
                    } else {
                        if (Configuration::get('CHANNABLE_FEEDMODE_ALTERNATIVE') == 1 || !isset($row['categories_id'])) {
                            $row['categories_ids'] = $this->fetchCategories($row['parent_id']);
                        }
                        $product_categories_raw_titles = [];
                        $product_categories_raw = [];
                        foreach (explode(',', $row['categories_ids']) as $category_id) {
                            $treeCache = ChannableCache::getByKey('CAT_TREE_' . (int) $category_id, self::$cache_lifetime_categories, true, (int) Context::getContext()->language->id);
                            if ((int) $treeCache->id > 0) {
                                $product_categories_raw_title = $treeCache->cache_value;
                            } else {
                                $defaultTree = $this->getParentsCategories((int) $category_id, $this->getAllCategories());
                                $tmp = [];
                                foreach ($defaultTree as $cnt => $d) {
                                    $tmp[] = $d['name'];
                                }
                                $treeCache->cache_value = join(' > ', $tmp);
                                $treeCache->save();
                                $product_categories_raw_title = join(' > ', $tmp);
                            }
                            $product_categories_raw_titles[] = $product_categories_raw_title;
                            $product_categories_raw[] = [
                                'id' => (int) $category_id,
                                'tree' => $product_categories_raw_title,
                                'found' => false,
                            ];
                        }

                        foreach ($product_categories_raw as $pcr_key => $product_category_raw) {
                            foreach ($product_categories_raw_titles as $pcrt_key => $product_categories_raw_title) {
                                if ($pcr_key != $pcrt_key) {
                                    if (substr($product_categories_raw_title, 0, strlen($product_category_raw['tree'])) == $product_category_raw['tree']) {
                                        unset($product_categories_raw[$pcr_key]);
                                    }
                                }
                            }
                        }

                        $row['categories'] = [];
                        if (sizeof($product_categories_raw) > 0) {
                            foreach ($product_categories_raw as $product_category_raw) {
                                $row['categories'][] = $product_category_raw['tree'];
                            }
                        }
                        $productCategoriesCache->cache_value = json_encode($row['categories']);
                        $productCategoriesCache->save();
                    }

                    unset($product_categories_raw);
                    unset($row['id_category_default']);
                    unset($row['categories_ids']);

                    if (!isset($row['product_supplier_reference'])) {
                        $row['product_supplier_reference'] = '';
                        if ((int) $row['id_supplier'] > 0) {
                            $row['product_supplier_reference'] = ProductSupplier::getProductSupplierReference($row['parent_id'], $row['id_product_attribute'], $row['id_supplier']);
                        }
                    }

                    if (Configuration::get('CHANNABLE_FEEDMODE_ALTERNATIVE') != 1) {
                        $row['supplier'] = (int) $row['id_supplier'] > 0 ? Supplier::getNameById($row['id_supplier']) : '';
                        unset($row['id_supplier']);
                        if (isset($row['combination'])) {
                            $cmbs = explode(self::$combination_separator, $row['combination']);
                            foreach ($cmbs as $cmb) {
                                $cmbData = explode(self::$combination_desc_separator, $cmb);
                                $row[strtolower($cmbData[0])] = $cmbData[1];
                            }
                        } else {
                            if ((int) $row['id_product_attribute'] > 0) {
                                $product = new Product($row['parent_id']);
                                $combination_array = $product->getAttributeCombinationsById((int) $row['id_product_attribute'], (int) Context::getContext()->language->id);
                                foreach ($combination_array as $combination) {
                                    $row[strtolower($combination['group_name'])] = $combination['attribute_name'];
                                }
                            }
                        }
                        if (isset($row['specifications'])) {
                            $specs = explode(self::$combination_separator, $row['specifications']);
                            foreach ($specs as $spec) {
                                $specData = explode(self::$combination_desc_separator, $spec);
                                $key = strtolower($specData[0]);
                                if (isset($row[$key])) {
                                    $key = $key . '-2';
                                }
                                $row[$key] = $specData[1];
                            }
                        }
                        $row = $this->buildFeatures($row);
                        unset($row['combination']);
                        unset($row['specifications']);
                    } else {
                        $product = new Product($row['parent_id']);
                        $row['supplier'] = $product->supplier_name;
                        if ($product->supplier_name == '') {
                            $row['supplier'] = $product->id_supplier > 0 ? Supplier::getNameById($product->id_supplier) : '';
                        }
                        unset($row['id_supplier']);
                        if ((int) $row['id_product_attribute'] > 0) {
                            $combination_array = $product->getAttributeCombinationsById((int) $row['id_product_attribute'], (int) Context::getContext()->language->id);
                            foreach ($combination_array as $combination) {
                                $row[strtolower($combination['group_name'])] = $combination['attribute_name'];
                            }
                        }
                        $row = $this->buildFeatures($row);
                    }

                    $attributes_combinations = Product::getAttributesInformationsByProduct($row['parent_id']);
                    if (is_array($attributes_combinations) && count($attributes_combinations)) {
                    } else {
                        $row['id_product_attribute'] = null;
                    }

                    if ($currency) {
                        $row['currency'] = $currency;
                    }
                    $row['description'] = strip_tags(str_replace(['<br />', '<br>'], "\n", $row['description_html']));
                    $row['short_description'] = strip_tags(str_replace(['<br />', '<br>'], "\n", $row['short_description_html']));
                    $row['link'] = $this->context->link->getProductLink($row['parent_id']);

                    $setSSL = false;
                    if (strpos($row['link'], 'https://') !== false) {
                        $setSSL = true;
                    }

                    $delivery_period = $row['stock'] > 0 ? $shipping_time_available : $shipping_time_outofstock;

                    if (Configuration::get('CHANNABLE_FEEDMODE_SKIP_SHIPPING') == '1') {
                        $delivery_price = false;
                    } else {
                        if ($id_carrier == -1) {
                            $cart = Context::getContext()->cart;
                            if (isset($channable_default_address_id)) {
                                $cart->id_customer = $channable_customer_id;
                                $cart->id_address_delivery = (int) $channable_default_address_id;
                                $cart->id_address_invoice = (int) $channable_default_address_id;
                                $cart->id_guest = 0;
                            }
                            $cart->add();
                            $cart->updateQty(1, $row['parent_id'], $row['id_product_attribute'] > 0 ? $row['id_product_attribute'] : null);
                            $delivery_option = $cart->getDeliveryOption($default_country);
                            if (is_array($delivery_option) && sizeof($delivery_option) > 0) {
                                $delivery_option = explode(',', reset($delivery_option));
                                $carrier = new Carrier((int) $delivery_option[0]);
                                $shipping_method = $carrier->getShippingMethod();
                                if ($shipping_method == Carrier::SHIPPING_METHOD_WEIGHT) {
                                    $delivery_price = $carrier->getDeliveryPriceByWeight($row['package_weight'], $id_zone);
                                } else {
                                    $delivery_price = $carrier->getDeliveryPriceByPrice($row['price'], $id_zone);
                                }
                                if ($row['stock'] > 0) {
                                    if (isset($carrier->delay[(int) Context::getContext()->language->id])) {
                                        $delivery_period = $carrier->delay[(int) Context::getContext()->language->id];
                                    }
                                }
                            }
                        } else {
                            $shipping_method = $default_carrier->getShippingMethod();
                            if ($shipping_method == Carrier::SHIPPING_METHOD_WEIGHT) {
                                $delivery_price = $default_carrier->getDeliveryPriceByWeight($row['package_weight'], $id_zone);
                            } else {
                                $delivery_price = $default_carrier->getDeliveryPriceByPrice($row['price'], $id_zone);
                            }
                        }
                    }

                    if (isset($delivery_price) && $delivery_price !== false) {
                        $row['shipping'] = ['country' => $default_country->iso_code, 'price' => $delivery_price];
                    }

                    $row['price'] += $row['combination_price'];
                    $row['price_raw'] += $row['combination_price'];

                    $row['price'] = Product::getPriceStatic(
                        (int) $row['parent_id'],
                        false,
                        $row['id_product_attribute'],
                        6,
                        null,
                        false,
                        false,
                        1,
                        true,
                        null,
                        null,
                        null,
                        $specific_prices_obj
                    );
                    $row['price_raw'] = $row['price'];

                    $specific_prices_obj = null;
                    $row['sale_price'] = Product::getPriceStatic(
                        (int) $row['parent_id'],
                        false,
                        $row['id_product_attribute'],
                        6,
                        null,
                        false,
                        true,
                        1,
                        true,
                        null,
                        null,
                        null,
                        $specific_prices_obj
                    );
                    $row['sale_price_incl_vat'] = Product::getPriceStatic(
                        (int) $row['parent_id'],
                        true,
                        $row['id_product_attribute'],
                        6,
                        null,
                        false,
                        true,
                        1,
                        true,
                        null,
                        null,
                        null,
                        $specific_prices_obj
                    );

                    if (!isset($row['tax_rate']) || is_null($row['tax_rate'])) {
                        if (isset($channable_default_address_id)) {
                            Context::getContext()->cart->id_customer = $channable_customer_id;
                            Context::getContext()->cart->id_address_delivery = (int) $channable_default_address_id;
                            Context::getContext()->cart->id_address_invoice = (int) $channable_default_address_id;
                            Context::getContext()->cart->id_guest = 0;
                        }

                        if (!isset($product)) {
                            $product = new Product($row['parent_id']);
                        }
                        if (Context::getContext()->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')} != null) {
                            $address = Context::getContext()->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')};
                            $product->tax_rate = $product->getTaxesRate(new Address($address));
                        } else {
                            try {
                                $product->tax_rate = $product->getTaxesRate();
                            } catch (Exception $e) {
                            }
                        }
                        $row['tax_rate'] = $product->tax_rate;
                    }

                    $row['price_incl_vat'] = (float) (($row['price_raw'] * (($row['tax_rate'] / 100) + 1)) + ($row['ecotax'] * (($row['tax_rate'] / 100) + 1)));

                    unset($row['price_raw']);
                    unset($row['combination_price']);

                    if (!isset($row['brand'])) {
                        if (!isset($product)) {
                            $product = new Product($row['parent_id']);
                        }
                        $row['brand'] = $product->getWsManufacturerName();
                    }

                    $have_combination_images = false;
                    if ($row['id_product_attribute'] != '' && $row['id_product_attribute'] > 0) {
                        $row['link'] = $this->context->link->getProductLink($row['parent_id'], null, null, null, null, null, $row['id_product_attribute'], false, false, true);

                        $product = new Product($row['parent_id']);
                        $stock = Product::getQuantity($row['parent_id'], $row['id_product_attribute']);
                        if ($stock !== false) {
                            $row['stock'] = $stock;
                        }
                        $combination_images = $this->getAttributesImages((int) $row['id_product_attribute']);
                        if ($combination_images && is_array($combination_images) && sizeof($combination_images) > 0) {
                            $row['additional_images_raw'] = [];
                            $image_counter = 0;
                            foreach ($combination_images as $img_key => $ci) {
                                $pfad = $this->context->link->getImageLink($product->link_rewrite[(int) Context::getContext()->language->id], $ci['id']);
                                if ($image_counter == 0) {
                                    $row['image_link'] = $pfad;
                                } else {
                                    $row['additional_images_raw'][] = $pfad;
                                }
                                ++$image_counter;
                            }
                            if (isset($row['additional_images_raw']) && is_array($row['additional_images_raw'])) {
                                $have_combination_images = true;
                                $row['additional_images_raw'] = join(self::$imgurl_separator, $row['additional_images_raw']);
                            }
                        }
                    }

                    if ($have_combination_images && isset($row['additional_images_raw'])) {
                        // all good, nothing to do
                    } else {
                        $id_image_cover = Product::getCover((int) $row['parent_id']);
                        if (isset($id_image_cover['id_image']) && (int) $id_image_cover['id_image'] > 0) {
                            $product = new Product($row['parent_id']);
                            $pfad = $this->context->link->getImageLink($product->link_rewrite[(int) Context::getContext()->language->id], $id_image_cover['id_image']);
                            $row['image_link'] = $pfad;
                            if (!isset($row['id_image'])) {
                                $row['id_image'] = $id_image_cover['id_image'];
                            }
                        }

                        if (!$have_combination_images) {
                            $product = new Product($row['parent_id']);
                            $images = $product->getImages((int) Context::getContext()->language->id);
                            $image_counter = 0;
                            foreach ($images as $img_key => $imagedata) {
                                $pfad = $this->context->link->getImageLink($product->link_rewrite[(int) Context::getContext()->language->id], $imagedata['id_image']);
                                if ($image_counter == 0 && (!isset($row['image_link']) || $row['image_link'] == '')) {
                                    $row['image_link'] = $pfad;
                                } else {
                                    if (!isset($row['additional_images_raw'])) {
                                        $row['additional_images_raw'] = [];
                                    }
                                    $row['additional_images_raw'][] = $pfad;
                                }
                                ++$image_counter;
                            }
                            if (isset($row['additional_images_raw']) && is_array($row['additional_images_raw'])) {
                                $row['additional_images_raw'] = join(self::$imgurl_separator, $row['additional_images_raw']);
                            }
                        }
                    }

                    if (isset($row['image_link'])) {
                        $row['image_link'] = $setSSL ? str_replace('http://', 'https://', $row['image_link']) : $row['image_link'];
                    }

                    if (isset($row['additional_images_raw'])) {
                        $additional_images = [];
                        $ais = explode(self::$imgurl_separator, $row['additional_images_raw']);
                        foreach ($ais as $ai) {
                            if ($ai != $row['image_link'] && str_replace('http://', 'https://', $ai) != $row['image_link']) {
                                $additional_images[] = $setSSL ? str_replace('http://', 'https://', $ai) : $ai;
                            }
                        }
                        $row['additional_images'] = $additional_images;
                    }
                    unset($row['additional_images_raw']);
                    unset($row['id_image']);
                    unset($row['id_product_attribute']);

                    $row['delivery_period'] = $delivery_period;

                    if ($this->feedHasSpecificPricesQry()) {
                        $row['specific_price'] = $this->fetchMappedSpecificPrices($row['parent_id']);
                    }

                    $row['attachments'] = '';
                    $attachments = Product::getAttachmentsStatic((int) Context::getContext()->language->id, $row['parent_id']);
                    if (sizeof($attachments) > 0) {
                        foreach ($attachments as $atm) {
                            if (!is_array($row['attachments'])) {
                                $row['attachments'] = [];
                            }
                            $row['attachments'][] = [
                                'name' => $atm['name'],
                                'description' => $atm['description'],
                                'link' => $this->context->link->getPageLink('attachment', true, null, 'id_attachment=' . $atm['id_attachment']),
                            ];
                        }
                    }

                    try {
                        if (Module::isInstalled('pm_advancedpack')) {
                            if (Module::isEnabled('pm_advancedpack')) {
                                if (($row['ean13'] == '' || $row['reference'] == '') && $row['id'] != $row['parent_id']) {
                                    if (!isset($product)) {
                                        $product = new Product($row['parent_id']);
                                    }
                                    if ($product->ean13 != '' && $row['ean13'] == '') {
                                        $row['ean13'] = $product->ean13;
                                    }
                                    if ($product->reference != '' && $row['reference'] == '') {
                                        $row['reference'] = $product->reference;
                                    }
                                }
                            }
                        }
                    } catch (Exception $e) {
                    }

                    try {
                        if (Module::isInstalled('wkproductcustomfield')) {
                            if (Module::isEnabled('wkproductcustomfield') && class_exists('WkPcFieldValue') && class_exists('WkPcFieldOptions')) {
                                $this->objPcFieldValue = new WkPcFieldValue();
                                $this->objPcFieldOptions = new WkPcFieldOptions();
                                $methodArgumentsCheckClass = new ReflectionMethod('WkPcFieldValue', 'getCustomFieldInputValue');
                                if ($methodArgumentsCheckClass->getNumberOfRequiredParameters() == 2) {
                                    $WkProductCustomFields = $this->objPcFieldValue->getCustomFieldInputValue(
                                        (int) Context::getContext()->language->id,
                                        $row['parent_id']
                                    );
                                } else {
                                    $WkProductCustomFields = $this->objPcFieldValue->getCustomFieldInputValue(
                                        (int) Context::getContext()->language->id,
                                        $row['parent_id'],
                                        (int) Context::getContext()->shop->id
                                    );
                                }
                                if (is_array($WkProductCustomFields)) {
                                    foreach ($WkProductCustomFields as $fieldValue) {
                                        if ($fieldValue['input_type'] == 1 || $fieldValue['input_type'] == 2) {
                                            $value = $fieldValue['field_value'];
                                            if ($fieldValue['multi_lang']) {
                                                $values = $this->objPcFieldValue->getTextFieldValues($fieldValue['id_wk_pc_field_value']);
                                                if ($values) {
                                                    $value = $values['text_field_value'];
                                                }
                                            }
                                        } elseif ($fieldValue['input_type'] == 3) {
                                            $dropdownFieldValue = json_decode($fieldValue['field_value']);
                                            $dropdownchoosenvalues = [];
                                            if ($dropdownFieldValue) {
                                                foreach ($dropdownFieldValue as $dropdownvalue) {
                                                    $ddFieldValue = $this->objPcFieldOptions->getOptionsFieldValues(
                                                        (int) Context::getContext()->language->id,
                                                        $dropdownvalue
                                                    );
                                                    if ($ddFieldValue) {
                                                        $dropdownchoosenvalues[] = $ddFieldValue;
                                                    }
                                                }
                                            }
                                            $value = join(', ', $dropdownchoosenvalues);
                                        } elseif ($fieldValue['input_type'] == 4) {
                                            $checkboxFieldValue = json_decode($fieldValue['field_value']);
                                            $checkboxchoosenvalue = [];
                                            if ($checkboxFieldValue) {
                                                foreach ($checkboxFieldValue as $checkboxvalue) {
                                                    $cboxFieldValue = $this->objPcFieldOptions->getOptionsFieldValues(
                                                        (int) Context::getContext()->language->id,
                                                        $checkboxvalue
                                                    );
                                                    if ($cboxFieldValue) {
                                                        $checkboxchoosenvalue[] = $cboxFieldValue;
                                                    }
                                                }
                                            }
                                            $value = join(', ', $checkboxchoosenvalue);
                                        } elseif ($fieldValue['input_type'] == 5) {
                                            // nothing
                                        } elseif ($fieldValue['input_type'] == 6) {
                                            $radiobuttonFieldValue = $fieldValue['field_value'];
                                            $radiobuttonchoosenvalue = $this->objPcFieldOptions->getOptionsFieldValues(
                                                (int) Context::getContext()->language->id,
                                                $radiobuttonFieldValue
                                            );
                                            $value = $radiobuttonchoosenvalue;
                                        } elseif ($fieldValue['input_type'] == 7) {
                                            $value = $fieldValue['field_value'];
                                            if ($fieldValue['multi_lang']) {
                                                $values = $this->objPcFieldValue->getTextFieldValues($fieldValue['id_wk_pc_field_value']);
                                                if ($values) {
                                                    $value = $values['text_field_value'];
                                                }
                                            }
                                        }
                                        if (isset($value) && $value != '') {
                                            $row[$fieldValue['label_name']] = $value;
                                        }
                                    }
                                }
                            }
                        }

                        Hook::exec(
                            'channableFeed',
                            [
                                'item' => &$row,
                            ]
                        );
                    } catch (Exception $e) {
                    }

                    $productJsonCache->cache_value = json_encode($row);
                    $productJsonCache->save();

                    ChannableProductsQueue::deleteById($row['parent_id']);

                    $add_product_to_feed = true;

                    Hook::exec(
                        'channableAddProductToFeedCheck',
                        [
                            'add_product_to_feed' => &$add_product_to_feed,
                            'item' => &$row,
                        ]
                    );

                    if ($add_product_to_feed) {
                        if ($this->track_cached) {
                            $tracked_cache_new[] = (int) $row['parent_id'];
                        }

                        $items[] = $row;
                    }
                    unset($product);

                    if (isset($cart)) {
                        try {
                            $cart->delete();
                        } catch (Exception $e) {
                        }
                    }
                }
            }
        } else {
        }
        if ($this->track_cached) {
            header('CachedIDs: ' . join(',', $tracked_cache));
            header('NonCachedIDs: ' . join(',', $tracked_cache_new));
        }
        echo json_encode(
            $items
        );
        if (json_last_error() == 5 && defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            echo json_encode(
                $items,
                JSON_INVALID_UTF8_SUBSTITUTE
            );
        }
        exit;
    }

    /**
     * @return string
     */
    protected function getPossibleCombinationQuery()
    {
        if (Configuration::get('CHANNABLE_FEEDMODE_ALTERNATIVE') != 1) {
            return 'GROUP_CONCAT(DISTINCT (CONCAT(pgl.name, \'' . self::$combination_desc_separator . '\' , pal.name))
                    SEPARATOR \'' . self::$combination_separator . '\') as combination,';
        }

        return '';
    }

    /**
     * @return string
     */
    protected function getPossibleSpecificationsQuery()
    {
        if (Configuration::get('CHANNABLE_FEEDMODE_ALTERNATIVE') != 1) {
            return 'GROUP_CONCAT(DISTINCT (CONCAT(fl.name, \'' . self::$combination_desc_separator . '\' , fvl.value))
                    SEPARATOR \'' . self::$combination_separator . '\') as specifications,';
        }

        return '';
    }

    /**
     * @return string
     */
    protected function getPossibleCategoriesQuery()
    {
        if (Configuration::get('CHANNABLE_FEEDMODE_ALTERNATIVE') != 1) {
            return 'GROUP_CONCAT(DISTINCT cpl2.id_category SEPARATOR \',\') as categories_ids,
                    cpl.name as product_category, ';
        }

        return '';
    }

    /**
     * @return string
     */
    protected function getPossibleCombinationGroupLangJoin()
    {
        if (Configuration::get('CHANNABLE_FEEDMODE_ALTERNATIVE') != 1) {
            return 'LEFT JOIN
                ' . _DB_PREFIX_ . 'attribute_group_lang pgl ON (pgl.id_attribute_group = pattrib.id_attribute_group)';
        }

        return '';
    }

    /**
     * @return string
     */
    protected function getPossibleFeaturesJoins()
    {
        if (Configuration::get('CHANNABLE_FEEDMODE_ALTERNATIVE') != 1) {
            return 'LEFT JOIN
                ' . _DB_PREFIX_ . 'feature_product fp ON (fp.id_product = p.id_product)
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'feature_lang fl ON (fl.id_feature = fp.id_feature)
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'feature_value_lang fvl ON (fp.id_feature_value = fvl.id_feature_value)';
        }

        return '';
    }

    /**
     * @return string
     */
    protected function getPossibleCategoriesJoins()
    {
        if (Configuration::get('CHANNABLE_FEEDMODE_ALTERNATIVE') != 1) {
            return 'LEFT JOIN
                ' . _DB_PREFIX_ . 'category_lang cpl ON (cpl.id_category = p.id_category_default)
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'category_product cp ON (cp.id_product = p.id_product)
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'category_lang cpl2 ON (cp.id_category = cpl2.id_category) ';
        }

        return '';
    }

    /**
     * @return string
     */
    protected function getPossibleCombinationWhere()
    {
        if (Configuration::get('CHANNABLE_FEEDMODE_ALTERNATIVE') != 1) {
            return 'AND
                (pgl.id_lang = pl.id_lang OR pgl.id_lang IS NULL)';
        }

        return '';
    }

    /**
     * @return string
     */
    protected function getPossibleFeaturesWhere()
    {
        if (Configuration::get('CHANNABLE_FEEDMODE_ALTERNATIVE') != 1) {
            return 'AND
                (fl.id_lang = pl.id_lang OR fl.id_lang IS NULL)
                    AND
                (fvl.id_lang = pl.id_lang OR fvl.id_lang IS NULL)';
        }

        return '';
    }

    /**
     * @return string
     */
    protected function getPossibleCategoriesWhere()
    {
        if (Configuration::get('CHANNABLE_FEEDMODE_ALTERNATIVE') != 1) {
            return 'AND
                (cpl.id_lang = pl.id_lang OR cpl.id_lang IS NULL)
                    AND
                (cpl2.id_lang = pl.id_lang OR cpl2.id_lang IS NULL) ';
        }

        return '';
    }

    /**
     * @param $id_product
     *
     * @return array|bool|false|mysqli_result|PDOStatement|resource|null
     *
     * @throws PrestaShopDatabaseException
     */
    protected function getFeatures($id_product)
    {
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
				SELECT fl.`name`, fvl.`value`
				FROM `' . _DB_PREFIX_ . 'feature_product` fp
				LEFT JOIN `' . _DB_PREFIX_ . 'feature_value` fv ON (fp.id_feature_value = fv.id_feature_value)
                LEFT JOIN `' . _DB_PREFIX_ . 'feature_lang` fl ON (fl.id_feature = fp.id_feature)
                LEFT JOIN `' . _DB_PREFIX_ . 'feature_value_lang` fvl ON (fp.id_feature_value = fvl.id_feature_value)
				WHERE fp.`id_product` = ' . (int) $id_product . '
                  AND (fl.id_lang = ' . (int) Context::getContext()->language->id . ')
                  AND (fvl.id_lang = ' . (int) Context::getContext()->language->id . ')');

        return $result;
    }

    /**
     * @param $id_product
     *
     * @return string
     */
    protected function fetchCategories($id_product)
    {
        $cache = ChannableCache::getByKey('PRODUCTS_CAT_' . (int) $id_product, self::$cache_lifetime_categories, true, (int) Context::getContext()->language->id);
        if ($cache->id > 0) {
            return $cache->cache_value;
        } else {
            $categories = ProductCore::getProductCategories((int) $id_product);
            $cache->cache_value = join(',', $categories);
            $cache->save();

            return $cache->cache_value;
        }
    }

    /**
     * @param $id_product_attribute
     *
     * @return array|bool|false|mysqli_result|PDOStatement|resource|null
     *
     * @throws PrestaShopDatabaseException
     */
    protected function getAttributesImages($id_product_attribute)
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
			SELECT a.`id_image` as id
			FROM `' . _DB_PREFIX_ . 'product_attribute_image` a
			' . ShopCore::addSqlAssociation('product_attribute', 'a') . '
            JOIN `' . _DB_PREFIX_ . 'image` i ON (i.id_image = a.id_image)
			WHERE a.`id_product_attribute` = ' . (int) $id_product_attribute . '
            ORDER BY i.position
		');
    }

    /**
     * @return string
     */
    protected function getManualAssignedFeedFields()
    {
        $feedfields = ChannableFeedfield::getAllFeedfields();
        $ret = '';
        if (is_array($feedfields)) {
            foreach ($feedfields as $ff) {
                if ($ff['tablename'] != 'specific_price') {
                    $ret .= '`' . $this->getTableShort($ff['tablename']) . '`.`' . pSQL($ff['field_in_db']) . '` AS `' . pSQL($ff['field_in_feed']) . '`, ';
                }
            }
        }

        return $ret;
    }

    protected function fetchMappedSpecificPrices($id_product)
    {
        $return = [];
        $tmpSpecificPriceFields = [];
        $feedfields = ChannableFeedfield::getAllFeedfields();
        if (is_array($feedfields)) {
            foreach ($feedfields as $ff) {
                if ($ff['tablename'] == 'specific_price') {
                    $tmpSpecificPriceFields[] = '`' . $this->getTableShort($ff['tablename']) . '`.`' . pSQL($ff['field_in_db']) . '` AS `' . pSQL($ff['field_in_feed']) . '`';
                }
            }
            if (sizeof($tmpSpecificPriceFields) > 0) {
                $qry = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
                    'SELECT ' . join(', ', $tmpSpecificPriceFields) . '
                    FROM `' . _DB_PREFIX_ . 'specific_price` AS ' . $this->getTableShort('specific_price') . '
                    WHERE `id_product` = ' . (int) $id_product
                );
                if ($qry) {
                    foreach ($qry as $qryKey => $row) {
                        $return[] = $row;
                    }
                }
            }
        }

        return $return;
    }

    /**
     * @return bool
     */
    protected function feedHasSpecificPricesQry()
    {
        if (is_null($this->feedHasSpecificPrices)) {
            $this->feedHasSpecificPrices = false;
            $feedfields = ChannableFeedfield::getAllFeedfields();
            foreach ($feedfields as $ff) {
                if ($ff['tablename'] == 'specific_price') {
                    $this->feedHasSpecificPrices = true;
                }
            }
        }

        return $this->feedHasSpecificPrices;
    }

    /**
     * @param $row
     *
     * @return mixed
     */
    protected function buildFeatures($row)
    {
        $features = $this->getFeatures($row['parent_id']);
        if (is_array($features)) {
            foreach ($features as $feature) {
                $key = strtolower($feature['name']);
                if (isset($row[$key])) {
                    if (substr($row[$key], 0, 5) != 'list:') {
                        $row[$key] = 'list: ' . $row[$key];
                    }
                    $row[$key] = $row[$key] . '|' . $feature['value'];
                } else {
                    $row[$key] = $feature['value'];
                }
            }
        }

        return $row;
    }

    /**
     * @param $id_category
     * @param $allCategories
     *
     * @return array
     */
    protected function getParentsCategories($id_category, $allCategories)
    {
        $cache = ChannableCache::getByKey('PARENTS_CAT_' . (int) $id_category, self::$cache_lifetime_categories, true, (int) Context::getContext()->language->id);
        if ((int) $cache->id > 0) {
            return json_decode($cache->cache_value, true);
        } else {
            if (!isset($allCategories[$id_category])) {
                return [];
            }
            $tree = [];
            $tree[] = $allCategories[$id_category];
            $count = 0;
            $id_parent = $allCategories[$id_category]['id_parent'];
            while ($id_parent > 1 && $count < 100) {
                $tree[] = $allCategories[$id_parent];
                $id_parent = $allCategories[$id_parent]['id_parent'];
                ++$count;
            }
            $tree = array_reverse($tree);
            $cache->cache_value = json_encode($tree);
            $cache->save();

            return $tree;
        }
    }

    /**
     * @return array|mixed
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getAllCategories()
    {
        if (!self::$all_categories_cache) {
            $allCategoriesCache = ChannableCache::getByKey('ALL_CATEGORIES_INFOS_' . (int) Context::getContext()->language->id, self::$cache_lifetime_categories, true, (int) Context::getContext()->language->id);
            if ((int) $allCategoriesCache->id > 0) {
                $allCategories = json_decode($allCategoriesCache->cache_value, true);
            } else {
                $allCategories = [];
                $allCategoriesTmp = Channable::getSimpleCategoriesWithParentInfos((int) Context::getContext()->language->id);
                foreach ($allCategoriesTmp as $allCategoryTmp) {
                    $allCategories[$allCategoryTmp['id_category']] = $allCategoryTmp;
                }
                $allCategoriesCache->cache_value = json_encode($allCategories);
                $allCategoriesCache->save();
            }
            self::$all_categories_cache = $allCategories;
        }

        return self::$all_categories_cache;
    }

    /**
     * @param $table
     *
     * @return string
     */
    protected function getTableShort($table)
    {
        switch ($table) {
            case 'product':
                return 'p';
            case 'product_shop':
                return 'ps';
            case 'product_lang':
                return 'pl';
            case 'product_attribute':
                return 'pa';
            case 'product_attribute_shop':
                return 'pas';
            case 'manufacturer':
                return 'm';
            case 'specific_price':
                return 'sp';
            default:
                return $table;
        }
    }

    /**
     * @param $mixed
     *
     * @return array|false|mixed|string|string[]|null
     */
    protected function utf8ize($mixed)
    {
        if (is_array($mixed)) {
            foreach ($mixed as $key => $value) {
                $mixed[$key] = $this->utf8ize($value);
            }
        } elseif (is_string($mixed)) {
            return mb_convert_encoding($mixed, 'UTF-8', 'UTF-8');
        }

        return $mixed;
    }

    protected function getProductDetails($id)
    {
        if ($this->multiquerymode) {
            $sql = $this->getMultiqueryMode(true, ["'" . $id . "'"], '');
        } else {
            $default_country = new Country(Configuration::get('PS_COUNTRY_DEFAULT'), Configuration::get('PS_LANG_DEFAULT'));
            $sql = $this->getDefaultMode(true, ["'" . $id . "'"], '', $default_country);
        }
        $result = Db::getInstance()->executeS($sql);
        if (isset($result[0])) {
            return $result[0];
        }
    }

    /**
     * @param $product_ids_in
     * @param $limit_string
     * @param $default_country
     *
     * @return string
     */
    protected function getDefaultMode($full, $product_ids_in, $limit_string, $default_country)
    {
        if ($full) {
            return 'SELECT
                if(pa.id_product_attribute IS NULL,
                   p.id_product,
                   concat(p.id_product, \'_\', pa.id_product_attribute)
                ) as id,
                p.id_product as parent_id,
                pa.id_product_attribute,
                p.condition,
                p.visibility,
                ps.active as visible,
                ps.active as active,
                ifnull(pa.ean13, p.ean13) as ean13,
                ifnull(pa.reference, p.reference) as reference,
                ifnull(pa.ean13, ifnull(p.ean13, ifnull(pa.reference, p.reference))) as gtin,
                p.upc,
                p.id_supplier,
                pl.name as title,
                m.name as brand,
                pl.description as description_html,
                pl.description_short as short_description_html,
                pl.meta_title,
                pl.meta_description,
                @id_image := ifnull(pai.id_image, pid.id_image) as id_image,
                concat(\'http://\',
                        ifnull(shop_domain.value, \'domain\'),
                        psu.physical_uri,
                        \'img/p/\',
                        if(CHAR_LENGTH(@id_image) >= 9,
                            concat(SUBSTRING(@id_image from - 9 FOR 1), \'/\'),
                            \'\'),
                        if(CHAR_LENGTH(@id_image) >= 8,
                            concat(SUBSTRING(@id_image from - 8 FOR 1), \'/\'),
                            \'\'),
                        if(CHAR_LENGTH(@id_image) >= 7,
                            concat(SUBSTRING(@id_image from - 7 FOR 1), \'/\'),
                            \'\'),
                        if(CHAR_LENGTH(@id_image) >= 6,
                            concat(SUBSTRING(@id_image from - 6 FOR 1), \'/\'),
                            \'\'),
                        if(CHAR_LENGTH(@id_image) >= 5,
                            concat(SUBSTRING(@id_image from - 5 FOR 1), \'/\'),
                            \'\'),
                        if(CHAR_LENGTH(@id_image) >= 4,
                            concat(SUBSTRING(@id_image from - 4 FOR 1), \'/\'),
                            \'\'),
                        if(CHAR_LENGTH(@id_image) >= 3,
                            concat(SUBSTRING(@id_image from - 3 FOR 1), \'/\'),
                            \'\'),
                        if(CHAR_LENGTH(@id_image) >= 2,
                            concat(SUBSTRING(@id_image from - 2 FOR 1), \'/\'),
                            \'\'),
                        if(CHAR_LENGTH(@id_image) >= 1,
                            concat(SUBSTRING(@id_image from - 1 FOR 1), \'/\'),
                            \'\'),
                        @id_image,
                        \'.jpg\') as image_link,
                ' . $this->getPossibleCombinationQuery() . '
                ROUND(ps.price, 2) as price,
                ps.price as price_raw,
                ROUND(ps.price, 2) as sale_price,
                ROUND(pa.price, 2) as combination_price,
                p.ecotax,
                t.rate as tax_rate,
                p.weight as package_weight,
                p.height as package_height,
                p.width as package_width,
                p.depth as package_depth,
                ' . $this->getPossibleSpecificationsQuery() . '
                ' . $this->getPossibleCategoriesQuery() . '
                ' . $this->getManualAssignedFeedFields() . '
                ' . (Configuration::get('PS_STOCK_MANAGEMENT') ? 'sav.quantity as stock, ' : 'pq.quantity as stock, ') . '
                p.id_category_default
            FROM
                ' . _DB_PREFIX_ . 'product p
                	JOIN
                ' . _DB_PREFIX_ . 'product_shop ps ON (p.id_product = ps.id_product AND ps.id_shop = \'' . (int) Context::getContext()->shop->id . '\' ' . (Configuration::get('CHANNABLE_DISABLE_INACTIVE') == '1' ? ' AND ps.active = 1' : '') . ')
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'product_attribute pa ON (p.id_product = pa.id_product ' . $this->getAttributesDisabled('pa') . ')
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'product_attribute_shop pas ON (pa.id_product_attribute = pas.id_product_attribute OR pa.id_product_attribute IS NULL)
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'stock_available pq ON (p.id_product = pq.id_product AND (pa.id_product_attribute = pq.id_product_attribute OR pa.id_product_attribute IS NULL))
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'manufacturer m ON (p.id_manufacturer = m.id_manufacturer)
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'product_lang pl ON (p.id_product = pl.id_product)
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'product_attribute_combination pac ON (pa.id_product_attribute = pac.id_product_attribute)
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'attribute_lang pal ON (pac.id_attribute = pal.id_attribute)
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'attribute pattrib ON (pattrib.id_attribute = pac.id_attribute)
                ' . $this->getPossibleCombinationGroupLangJoin() . '
                ' . $this->getPossibleFeaturesJoins() . '
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'product_attribute_image pai ON (pa.id_product_attribute = pai.id_product_attribute)
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'image pi ON p.id_product = pi.id_product
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'image pid ON (p.id_product = pid.id_product AND pid.cover = 1)
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'configuration shop_domain ON shop_domain.name = \'PS_SHOP_DOMAIN\'
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'shop_url psu ON shop_domain.value = psu.domain
                ' . $this->getPossibleCategoriesJoins() . '
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'tax_rule tr ON (tr.id_tax_rules_group = p.id_tax_rules_group)
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'tax t ON (t.id_tax = tr.id_tax)
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'stock_available sav ON (sav.`id_product` = p.`id_product` AND sav.`id_product_attribute` =
                ( if(pa.id_product_attribute IS NULL, 0, pa.id_product_attribute) )
                ' . StockAvailable::addSqlShopRestriction(null, null, 'sav') . ')
            WHERE
            	' . (isset($_GET['manual_product_id']) ? ' p.id_product IN (\'' . pSQL($_GET['manual_product_id']) . '\') AND ' : '') . '
                ' . (($this->sql_optimization_mode == 'id_product_and_attribute') ? ((isset($product_ids_in) && sizeof($product_ids_in) > 0) ? ' if(pa.id_product_attribute IS NULL, p.id_product, concat(p.id_product, \'_\', pa.id_product_attribute)) IN (' . join(', ', $product_ids_in) . ') AND ' : '') : '') . '
                ' . (($this->sql_optimization_mode == 'id_product') ? ((isset($product_ids_in) && sizeof($product_ids_in) > 0) ? ' p.id_product IN (' . join(', ', $product_ids_in) . ') AND ' : '') : '') . '
                pl.id_lang = \'' . (int) Context::getContext()->language->id . '\'
                    AND
                pl.id_shop = \'' . (int) Context::getContext()->shop->id . '\'
                    AND
                (tr.id_country = \'' . (int) $default_country->id . '\' OR tr.id_country IS NULL)
                    AND
                (pas.id_shop = \'' . (int) Context::getContext()->shop->id . '\' OR pas.id_shop IS NULL)
                    AND
                (pal.id_lang = pl.id_lang OR pal.id_lang IS NULL)
                ' . $this->getPossibleCombinationWhere() . '
                ' . $this->getPossibleCategoriesWhere() . '
                ' . $this->getPossibleFeaturesWhere() . '
                ' . (Configuration::get('CHANNABLE_DISABLE_INACTIVE') == '1' ? ' AND (ps.active = 1) ' : '') . '
                ' . (Configuration::get('CHANNABLE_DISABLE_OUT_OF_STOCK') == '1' ? ' AND (pq.quantity > 0) ' : '') . '
            GROUP BY CONCAT(COALESCE(pa.id_product_attribute, \'\'), \'--\', p.id_product)
            ORDER BY p.id_product, pac.id_attribute ' . ((isset($product_ids_in) && sizeof($product_ids_in) > 0) ? '' : $limit_string);
        } else {
            return 'SELECT
                if(pa.id_product_attribute IS NULL,
                   p.id_product,
                   concat(p.id_product, \'_\', pa.id_product_attribute)
                ) as id,
                p.id_product as parent_id,
                pa.id_product_attribute
            FROM
                ' . _DB_PREFIX_ . 'product p
                	JOIN
                ' . _DB_PREFIX_ . 'product_shop ps ON (p.id_product = ps.id_product AND ps.id_shop = \'' . (int) Context::getContext()->shop->id . '\' ' . (Configuration::get('CHANNABLE_DISABLE_INACTIVE') == '1' ? ' AND ps.active = 1' : '') . ')
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'product_attribute pa ON (p.id_product = pa.id_product ' . $this->getAttributesDisabled('pa') . ')
            WHERE
            	' . (isset($_GET['manual_product_id']) ? ' p.id_product IN (\'' . pSQL($_GET['manual_product_id']) . '\') AND ' : '') . '
                ' . (($this->sql_optimization_mode == 'id_product_and_attribute') ? ((isset($product_ids_in) && sizeof($product_ids_in) > 0) ? ' if(pa.id_product_attribute IS NULL, p.id_product, concat(p.id_product, \'_\', pa.id_product_attribute)) IN (' . join(', ', $product_ids_in) . ') AND ' : '') : '') . '
                ' . (($this->sql_optimization_mode == 'id_product') ? ((isset($product_ids_in) && sizeof($product_ids_in) > 0) ? ' p.id_product IN (' . join(', ', $product_ids_in) . ') AND ' : '') : '') . '
                1
            GROUP BY CONCAT(COALESCE(pa.id_product_attribute, \'\'), \'--\', p.id_product)
            ORDER BY p.id_product ' . ((isset($product_ids_in) && sizeof($product_ids_in) > 0) ? '' : $limit_string);
        }
    }

    /**
     * @param $product_ids_in
     * @param $limit_string
     *
     * @return string
     */
    protected function getMultiqueryMode($full, $product_ids_in, $limit_string)
    {
        if ($full) {
            return 'SELECT
                if(pa.id_product_attribute IS NULL,
                   p.id_product,
                   concat(p.id_product, \'_\', pa.id_product_attribute)
                ) as id,
                p.id_product as parent_id,
                pa.id_product_attribute,
                p.condition,
                p.visibility,
                ps.active as visible,
                ps.active as active,
                ifnull(pa.ean13, p.ean13) as ean13,
                ifnull(pa.reference, p.reference) as reference,
                ifnull(pa.ean13, ifnull(p.ean13, ifnull(pa.reference, p.reference))) as gtin,
                p.upc,
                p.id_supplier,
                pl.name as title,
                pl.description as description_html,
                pl.description_short as short_description_html,
                pl.meta_title,
                pl.meta_description,
                ROUND(ps.price, 2) as price,
                ps.price as price_raw,
                ROUND(ps.price, 2) as sale_price,
                ROUND(pa.price, 2) as combination_price,
                p.ecotax,
                p.weight as package_weight,
                p.height as package_height,
                p.width as package_width,
                p.depth as package_depth,
                ' . $this->getManualAssignedFeedFields() . '
                ' . (Configuration::get('PS_STOCK_MANAGEMENT') ? 'sav.quantity as stock, ' : 'pq.quantity as stock, ') . '
                p.id_category_default
            FROM
                ' . _DB_PREFIX_ . 'product p
                	JOIN
                ' . _DB_PREFIX_ . 'product_shop ps ON (p.id_product = ps.id_product AND ps.id_shop = \'' . (int) Context::getContext()->shop->id . '\' ' . (Configuration::get('CHANNABLE_DISABLE_INACTIVE') == '1' ? ' AND ps.active = 1' : '') . ')
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'product_attribute pa ON (p.id_product = pa.id_product ' . $this->getAttributesDisabled('pa') . ')
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'product_attribute_shop pas ON (pa.id_product_attribute = pas.id_product_attribute OR pa.id_product_attribute IS NULL AND (pas.id_shop = \'' . (int) Context::getContext()->shop->id . '\' OR pas.id_shop IS NULL))
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'stock_available pq ON (p.id_product = pq.id_product AND (pa.id_product_attribute = pq.id_product_attribute OR pa.id_product_attribute IS NULL))
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'manufacturer m ON (p.id_manufacturer = m.id_manufacturer)
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'product_lang pl ON (p.id_product = pl.id_product)
                ' . (Configuration::get('PS_STOCK_MANAGEMENT') ?
                    'LEFT JOIN
                	' . _DB_PREFIX_ . 'stock_available sav ON (sav.`id_product` = p.`id_product` AND sav.`id_product_attribute` =
                	 ( if(pa.id_product_attribute IS NULL, 0, pa.id_product_attribute) )
                	 ' . StockAvailable::addSqlShopRestriction(null, null, 'sav') . ')' : ''
            ) . '
            WHERE
            	' . (isset($_GET['manual_product_id']) ? ' p.id_product IN (\'' . pSQL($_GET['manual_product_id']) . '\') AND ' : '') . '
                ' . (($this->sql_optimization_mode == 'id_product_and_attribute') ? ((isset($product_ids_in) && sizeof($product_ids_in) > 0) ? ' if(pa.id_product_attribute IS NULL, p.id_product, concat(p.id_product, \'_\', pa.id_product_attribute)) IN (' . join(', ', $product_ids_in) . ') AND ' : '') : '') . '
                ' . (($this->sql_optimization_mode == 'id_product') ? ((isset($product_ids_in) && sizeof($product_ids_in) > 0) ? ' p.id_product IN (' . join(', ', $product_ids_in) . ') AND ' : '') : '') . '
                pl.id_lang = \'' . (int) Context::getContext()->language->id . '\'
                    AND
                pl.id_shop = \'' . (int) Context::getContext()->shop->id . '\'
                ' . (Configuration::get('CHANNABLE_DISABLE_INACTIVE') == '1' ? ' AND (ps.active = 1) ' : '') . '
                ' . (Configuration::get('CHANNABLE_DISABLE_OUT_OF_STOCK') == '1' ? ' AND (' . (Configuration::get('PS_STOCK_MANAGEMENT') ? 'sav.quantity' : 'pq.quantity') . ' > 0) ' : '') . '
            GROUP BY CONCAT(COALESCE(pa.id_product_attribute, \'\'), \'--\', p.id_product)
            ORDER BY p.id_product ' . ((isset($product_ids_in) && sizeof($product_ids_in) > 0) ? '' : $limit_string);
        } else {
            return 'SELECT
                if(pa.id_product_attribute IS NULL,
                   p.id_product,
                   concat(p.id_product, \'_\', pa.id_product_attribute)
                ) as id,
                p.id_product as parent_id,
                pa.id_product_attribute
            FROM
                ' . _DB_PREFIX_ . 'product p
                	JOIN
                ' . _DB_PREFIX_ . 'product_shop ps ON (p.id_product = ps.id_product AND ps.id_shop = \'' . (int) Context::getContext()->shop->id . '\' ' . (Configuration::get('CHANNABLE_DISABLE_INACTIVE') == '1' ? ' AND ps.active = 1' : '') . ')
                    LEFT JOIN
                ' . _DB_PREFIX_ . 'product_attribute pa ON (p.id_product = pa.id_product ' . $this->getAttributesDisabled('pa') . ')
            WHERE
            	' . (isset($_GET['manual_product_id']) ? ' p.id_product IN (\'' . pSQL($_GET['manual_product_id']) . '\') AND ' : '') . '
                ' . (($this->sql_optimization_mode == 'id_product_and_attribute') ? ((isset($product_ids_in) && sizeof($product_ids_in) > 0) ? ' if(pa.id_product_attribute IS NULL, p.id_product, concat(p.id_product, \'_\', pa.id_product_attribute)) IN (' . join(', ', $product_ids_in) . ') AND ' : '') : '') . '
                ' . (($this->sql_optimization_mode == 'id_product') ? ((isset($product_ids_in) && sizeof($product_ids_in) > 0) ? ' p.id_product IN (' . join(', ', $product_ids_in) . ') AND ' : '') : '') . '
                1
            GROUP BY CONCAT(COALESCE(pa.id_product_attribute, \'\'), \'--\', p.id_product)
            ORDER BY p.id_product ' . ((isset($product_ids_in) && sizeof($product_ids_in) > 0) ? '' : $limit_string);
        }
    }

    protected function getAttributesDisabled($tableName)
    {
        if (Configuration::get('CHANNABLE_DISABLE_VARIANTS') == '1') {
            return ' AND pa.id_product_attribute < -1 ';
        }

        return;
    }

    protected function sqlHook($location)
    {
        return Hook::exec(
            'channableSql',
            [
                'location' => $location,
            ]
        );
    }
}
