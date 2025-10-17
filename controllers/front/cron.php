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

class ChannableCronModuleFrontController extends ModuleFrontController
{
    private static $cache_lifetime = 259200; // 3 days in seconds
    private static $maxCategories = 5;
    private static $maxProducts = 50;
    private static $maxAssocs = 5;

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

        if (Tools::getValue('buildCategories') == '1') {
            if ((int) Tools::getValue('maxCategories') > 0) {
                self::$maxCategories = (int) Tools::getValue('maxCategories');
            }
            $this->buildCategories();
        } elseif (Tools::getValue('buildProductsAssocs') == '1') {
            if ((int) Tools::getValue('maxAssocs') > 0) {
                self::$maxAssocs = (int) Tools::getValue('maxAssocs');
            }
            $this->buildProductAssocs();
        } elseif (Tools::getValue('buildProductsJson') == '1') {
            if ((int) Tools::getValue('maxProducts') > 0) {
                self::$maxProducts = (int) Tools::getValue('maxProducts');
            }
            $this->buildProductsJson();
        } elseif (Tools::getValue('resetRunningQueue') == '1') {
            $this->resetRunningQueue();
        } else {
            $this->module->sendProductUpdate();
        }
        exit;
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function buildProductAssocs()
    {
        ChannableCache::fetchCaches('CAT_TREE_', self::$cache_lifetime, (int) Context::getContext()->language->id);
        echo date('Y-m-d H:i:s') . ' Fetching categories<br>';
        $allCategories = [];
        $allCategoriesTmp = Channable::getSimpleCategoriesWithParentInfos((int) Context::getContext()->language->id);
        foreach ($allCategoriesTmp as $allCategoryTmp) {
            if (!isset(ChannableCache::$cachedObjectsExist[self::$cache_lifetime]['CAT_TREE_' . $allCategoryTmp['id_category']])) {
                $allCategories[$allCategoryTmp['id_category']] = $allCategoryTmp;
            }
        }
        ChannableCache::fetchCaches('PROD_CATS_', self::$cache_lifetime);
        echo date('Y-m-d H:i:s') . ' Fetching products<br>';
        $allProducts = [];
        $allProductsTmp = Product::getSimpleProducts((int) $this->context->language->id, $this->context);
        foreach ($allProductsTmp as $allProductTmp) {
            if (!isset(ChannableCache::$cachedObjectsExist[self::$cache_lifetime]['PROD_CATS_' . $allProductTmp['id_product']])) {
                $allProducts[$allProductTmp['id_product']] = $allProductTmp;
            }
        }
        $count = 0;
        foreach ($allProducts as $id_product => $productData) {
            if ($count > self::$maxAssocs) {
                break;
            }
            $productCategoriesCache = ChannableCache::getByKey('PROD_CATS_' . $id_product, self::$cache_lifetime, true, (int) Context::getContext()->language->id);
            if ((int) $productCategoriesCache->id == 0) {
                $categories_ids = $this->fetchCategories($id_product);
                $product_categories_raw_titles = [];
                $product_categories_raw = [];
                foreach (explode(',', $categories_ids) as $category_id) {
                    $treeCache = ChannableCache::getByKey('CAT_TREE_' . (int) $category_id, self::$cache_lifetime, true, (int) Context::getContext()->language->id);
                    if ((int) $treeCache->id > 0) {
                        $product_categories_raw_title = $treeCache->cache_value;
                    } else {
                        $defaultTree = $this->getParentsCategories((int) $category_id, $allCategories);
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

                $categories = [];
                if (sizeof($product_categories_raw) > 0) {
                    foreach ($product_categories_raw as $product_category_raw) {
                        $categories[] = $product_category_raw['tree'];
                    }
                }
                $productCategoriesCache->cache_value = json_encode($categories);
                $productCategoriesCache->save();
                echo date('Y-m-d H:i:s') . ' [' . $count . '] Saving ' . $id_product . '<br>';
                ++$count;
            }
        }
        echo date('Y-m-d H:i:s') . ' finished';
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function buildCategories()
    {
        ChannableCache::fetchCaches('CAT_TREE_', self::$cache_lifetime, (int) Context::getContext()->language->id);
        echo date('Y-m-d H:i:s') . ' Fetching categories<br>';
        $allCategories = [];
        $allCategoriesTmp = Channable::getSimpleCategoriesWithParentInfos((int) Context::getContext()->language->id);
        foreach ($allCategoriesTmp as $allCategoryTmp) {
            if (!isset(ChannableCache::$cachedObjectsExist[self::$cache_lifetime]['CAT_TREE_' . $allCategoryTmp['id_category']])) {
                $allCategories[$allCategoryTmp['id_category']] = $allCategoryTmp;
            }
        }
        $count = 0;
        foreach ($allCategories as $id_category => $categoryData) {
            if ($count > self::$maxCategories) {
                break;
            }
            $treeCache = ChannableCache::getByKey('CAT_TREE_' . $id_category, self::$cache_lifetime, true, (int) Context::getContext()->language->id);
            if ((int) $treeCache->id == 0) {
                $tree = $this->getParentsCategories($id_category, $allCategories);
                $tmp = [];
                foreach ($tree as $cnt => $d) {
                    $tmp[] = $d['name'];
                }
                $treeCache->cache_value = join(' > ', $tmp);
                $treeCache->save();
                echo date('Y-m-d H:i:s') . ' [' . $count . '] Saving ' . $id_category . '<br>';
                ++$count;
            }
        }
        echo date('Y-m-d H:i:s') . ' finished';
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function buildProductsJson()
    {
        $webservice = new WebserviceKey((int) Configuration::get('CHANNABLE_API_ID'));
        $products = ChannableProductsQueue::getNonRunningQueue(self::$maxProducts);
        $count = 0;
        foreach ($products as $prod) {
            ChannableProductsQueue::updateRunningStatus(
                $prod['id_channable_products_queue']
            );
            $feed_url = $this->context->link->getModuleLink(
                'channable',
                'feed',
                [
                    'key' => $webservice->key,
                    'rebuild_cache' => 1,
                    'manual_product_id' => $prod['id_product'],
                    'limit' => '0,100',
                ]
            );
            $data = $this->get_content(
                $feed_url
            );
            $jsondata = json_decode($data, true);
            if ($jsondata === null
                && json_last_error() !== JSON_ERROR_NONE) {
                echo date('Y-m-d H:i:s') . ' [' . $count . '] JSON Data not valid: ' . $prod['id_product'] . '<br>';
            } else {
                if (sizeof($jsondata) == 0) {
                    echo date('Y-m-d H:i:s') . ' [' . $count . '] No data, product not valid for feed: ' . $prod['id_product'] . '<br>';
                } else {
                    foreach ($jsondata as $json) {
                        if (isset($json['id'])) {
                            $cacheObject = ChannableCache::getByKey(
                                'PRODUCT_JSON_' . $json['id'],
                                false,
                                true,
                                (int) Context::getContext()->language->id
                            );
                            $cacheObject->cache_value = json_encode(
                                $json
                            );
                            $cacheObject->save();
                            echo date('Y-m-d H:i:s') . ' [' . $count . '] Cached ' . $json['id'] . '<br>';
                            ++$count;
                        } else {
                            echo date('Y-m-d H:i:s') . ' [' . $count . '] No data, product not valid for feed: ' . $prod['id_product'] . '<br>';
                        }
                    }
                }
            }
            ChannableProductsQueue::deleteById($prod['id_channable_products_queue']);
        }
        echo date('Y-m-d H:i:s') . ' finished';
    }

    /**
     * resetting all currently running from 1 to 0 (once a night via cron)
     */
    public function resetRunningQueue()
    {
        ChannableProductsQueue::resetRunningStatus();
        echo date('Y-m-d H:i:s') . ' finished';
    }

    /**
     * @param $id_category
     * @param $allCategories
     *
     * @return array
     */
    protected function getParentsCategories($id_category, $allCategories)
    {
        $cache = ChannableCache::getByKey('PARENTS_CAT_' . (int) $id_category, self::$cache_lifetime, true, (int) Context::getContext()->language->id);
        if ((int) $cache->id > 0) {
            return json_decode($cache->cache_value, true);
        } else {
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
     * @param $id_product
     *
     * @return string
     */
    protected function fetchCategories($id_product)
    {
        $cache = ChannableCache::getByKey('PRODUCTS_CAT_' . (int) $id_product, self::$cache_lifetime, true, (int) Context::getContext()->language->id);
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
     * @param $URL
     *
     * @return bool|string
     */
    protected function get_content($URL)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $URL);
        $data = curl_exec($ch);
        curl_close($ch);

        return $data;
    }
}
