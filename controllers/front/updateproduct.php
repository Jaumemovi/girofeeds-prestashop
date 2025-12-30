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

class ChannableUpdateproductModuleFrontController extends ModuleFrontController
{
    protected static $fieldTableMapping = [
        'product' => [
            'condition', 'visibility', 'active', 'ean13', 'reference', 'upc',
            'id_supplier', 'price', 'ecotax', 'weight', 'height', 'width',
            'depth', 'id_category_default', 'id_manufacturer', 'id_tax_rules_group',
            'on_sale', 'online_only', 'minimal_quantity', 'available_for_order',
            'show_price', 'indexed', 'cache_is_pack', 'cache_has_attachments',
            'is_virtual', 'out_of_stock', 'quantity_discount', 'customizable',
            'uploadable_files', 'text_fields', 'redirect_type', 'id_type_redirected',
            'available_date', 'show_condition', 'mpn', 'isbn', 'low_stock_threshold',
            'low_stock_alert', 'additional_delivery_times', 'state', 'pack_stock_type',
            'wholesale_price', 'unity', 'unit_price_ratio', 'additional_shipping_cost',
            'location'
        ],
        'product_shop' => [
            'visibility', 'active', 'price', 'ecotax', 'id_category_default',
            'id_tax_rules_group', 'on_sale', 'online_only', 'minimal_quantity',
            'available_for_order', 'show_price', 'indexed', 'redirect_type',
            'id_type_redirected', 'available_date', 'show_condition',
            'additional_delivery_times', 'pack_stock_type', 'wholesale_price',
            'unity', 'unit_price_ratio', 'additional_shipping_cost', 'uploadable_files',
            'text_fields', 'customizable'
        ],
        'product_lang' => [
            'name', 'description', 'description_short', 'meta_title', 'meta_description',
            'meta_keywords', 'link_rewrite', 'available_now', 'available_later',
            'delivery_in_stock', 'delivery_out_stock'
        ],
        'product_attribute' => [
            'ean13', 'reference', 'price', 'ecotax', 'weight', 'default_on',
            'minimal_quantity', 'available_date', 'upc', 'mpn', 'isbn',
            'wholesale_price', 'unit_price_impact', 'low_stock_threshold',
            'low_stock_alert', 'quantity'
        ],
        'product_attribute_shop' => [
            'price', 'ecotax', 'weight', 'default_on', 'minimal_quantity',
            'available_date', 'wholesale_price', 'unit_price_impact'
        ]
    ];

    protected static $numericFields = [
        'price', 'ecotax', 'weight', 'height', 'width', 'depth', 'wholesale_price',
        'unit_price_ratio', 'additional_shipping_cost', 'unit_price_impact',
        'id_supplier', 'id_manufacturer', 'id_tax_rules_group', 'id_category_default',
        'minimal_quantity', 'uploadable_files', 'text_fields', 'id_type_redirected',
        'low_stock_threshold', 'out_of_stock', 'quantity_discount', 'customizable',
        'pack_stock_type', 'quantity'
    ];

    protected static $booleanFields = [
        'active', 'on_sale', 'online_only', 'available_for_order', 'show_price',
        'indexed', 'cache_is_pack', 'cache_has_attachments', 'is_virtual',
        'show_condition', 'low_stock_alert', 'default_on'
    ];

    protected static $dateFields = [
        'available_date'
    ];

    protected static $htmlFields = [
        'description', 'description_short'
    ];

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

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('HTTP/1.1 405 Method Not Allowed');
            header('Allow: POST');
            exit('Method not allowed');
        }

        $postData = Channable::fetchPhpInput();
        $jsonData = json_decode($postData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            header('HTTP/1.1 400 Bad Request');
            exit('Invalid JSON data');
        }

        if (!isset($jsonData['id_product']) || empty($jsonData['id_product'])) {
            header('HTTP/1.1 400 Bad Request');
            exit('Product ID is required');
        }

        $id_product = (int) $jsonData['id_product'];

        if (!Validate::isLoadedObject(new Product($id_product))) {
            header('HTTP/1.1 404 Not Found');
            exit('Product not found');
        }

        try {
            $result = $this->updateProduct($id_product, $jsonData);

            header('Content-Type: application/json');
            echo json_encode($result);
        } catch (Exception $e) {
            ChannableLogger::getInstance()->addLog(
                'Error updating product: ' . $e->getMessage(),
                1,
                $e,
                $jsonData
            );

            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode([
                'status' => 'error',
                'message' => 'Internal server error: ' . $e->getMessage()
            ]);
        }

        exit;
    }

    private function updateProduct($id_product, $data)
    {
        $product = new Product($id_product);
        $updated_fields = [];
        $errors = [];

        $feedFieldsConfig = $this->getFeedFieldsMapping();

        $id_product_attribute = isset($data['id_product_attribute']) ? (int) $data['id_product_attribute'] : 0;

        foreach ($data as $field_name => $value) {
            if ($field_name === 'id_product' || $field_name === 'id_product_attribute') {
                continue;
            }

            if ($field_name === 'category') {
                $category_result = $this->handleCategoryUpdate($product, $value);
                if ($category_result['success']) {
                    $updated_fields = array_merge($updated_fields, $category_result['updated_fields']);
                } else {
                    $errors = array_merge($errors, $category_result['errors']);
                }
                continue;
            }

            if ($field_name === 'specific_price' && is_array($value)) {
                $specific_price_result = $this->handleSpecificPriceUpdate($product, $value);
                if ($specific_price_result['success']) {
                    $updated_fields = array_merge($updated_fields, $specific_price_result['updated_fields']);
                } else {
                    $errors = array_merge($errors, $specific_price_result['errors']);
                }
                continue;
            }

            $fieldInfo = $this->getFieldInfo($field_name, $feedFieldsConfig);

            if ($fieldInfo === false) {
                continue;
            }

            $result = $this->updateField($product, $fieldInfo, $value, $id_product_attribute);
            if ($result['success']) {
                $updated_fields = array_merge($updated_fields, $result['updated_fields']);
            } else {
                $errors = array_merge($errors, $result['errors']);
            }
        }

        if (!empty($errors) && empty($updated_fields)) {
            return [
                'status' => 'error',
                'message' => 'Validation errors',
                'errors' => $errors,
                'updated_fields' => $updated_fields
            ];
        }

        if (empty($updated_fields)) {
            return [
                'status' => 'warning',
                'message' => 'No valid fields to update',
                'product_id' => $id_product
            ];
        }

        $save_result = $product->save();

        if ($save_result) {
            ChannableLogger::getInstance()->addLog(
                'Product updated successfully via API: ' . $id_product,
                2,
                false,
                ['updated_fields' => $updated_fields, 'product_id' => $id_product]
            );

            if (Channable::useCache()) {
                ChannableProductsQueue::addToQueueIfNotExists($id_product);
            }

            return [
                'status' => 'success',
                'message' => 'Product updated successfully',
                'product_id' => $id_product,
                'updated_fields' => $updated_fields,
                'errors' => $errors
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Failed to save product',
                'product_id' => $id_product,
                'updated_fields' => $updated_fields,
                'errors' => $errors
            ];
        }
    }

    private function getFeedFieldsMapping()
    {
        $mapping = [];

        $feedfields = ChannableFeedfield::getAllFeedfields();
        if (is_array($feedfields)) {
            foreach ($feedfields as $ff) {
                $mapping[$ff['field_in_feed']] = [
                    'tablename' => $ff['tablename'],
                    'field_in_db' => $ff['field_in_db'],
                    'field_in_feed' => $ff['field_in_feed']
                ];
            }
        }

        return $mapping;
    }

    private function getFieldInfo($field_name, $feedFieldsConfig)
    {
        if (isset($feedFieldsConfig[$field_name])) {
            return $feedFieldsConfig[$field_name];
        }

        $defaultFields = [
            'name' => ['tablename' => 'product_lang', 'field_in_db' => 'name'],
            'description' => ['tablename' => 'product_lang', 'field_in_db' => 'description'],
            'description_short' => ['tablename' => 'product_lang', 'field_in_db' => 'description_short'],
            'meta_title' => ['tablename' => 'product_lang', 'field_in_db' => 'meta_title'],
            'meta_description' => ['tablename' => 'product_lang', 'field_in_db' => 'meta_description'],
            'meta_keywords' => ['tablename' => 'product_lang', 'field_in_db' => 'meta_keywords'],
            'link_rewrite' => ['tablename' => 'product_lang', 'field_in_db' => 'link_rewrite'],
            'available_now' => ['tablename' => 'product_lang', 'field_in_db' => 'available_now'],
            'available_later' => ['tablename' => 'product_lang', 'field_in_db' => 'available_later'],
            'delivery_in_stock' => ['tablename' => 'product_lang', 'field_in_db' => 'delivery_in_stock'],
            'delivery_out_stock' => ['tablename' => 'product_lang', 'field_in_db' => 'delivery_out_stock'],
            'price' => ['tablename' => 'product', 'field_in_db' => 'price'],
            'wholesale_price' => ['tablename' => 'product', 'field_in_db' => 'wholesale_price'],
            'ecotax' => ['tablename' => 'product', 'field_in_db' => 'ecotax'],
            'weight' => ['tablename' => 'product', 'field_in_db' => 'weight'],
            'height' => ['tablename' => 'product', 'field_in_db' => 'height'],
            'width' => ['tablename' => 'product', 'field_in_db' => 'width'],
            'depth' => ['tablename' => 'product', 'field_in_db' => 'depth'],
            'reference' => ['tablename' => 'product', 'field_in_db' => 'reference'],
            'ean13' => ['tablename' => 'product', 'field_in_db' => 'ean13'],
            'upc' => ['tablename' => 'product', 'field_in_db' => 'upc'],
            'mpn' => ['tablename' => 'product', 'field_in_db' => 'mpn'],
            'isbn' => ['tablename' => 'product', 'field_in_db' => 'isbn'],
            'active' => ['tablename' => 'product', 'field_in_db' => 'active'],
            'visibility' => ['tablename' => 'product', 'field_in_db' => 'visibility'],
            'condition' => ['tablename' => 'product', 'field_in_db' => 'condition'],
            'on_sale' => ['tablename' => 'product', 'field_in_db' => 'on_sale'],
            'online_only' => ['tablename' => 'product', 'field_in_db' => 'online_only'],
            'minimal_quantity' => ['tablename' => 'product', 'field_in_db' => 'minimal_quantity'],
            'available_for_order' => ['tablename' => 'product', 'field_in_db' => 'available_for_order'],
            'show_price' => ['tablename' => 'product', 'field_in_db' => 'show_price'],
            'id_supplier' => ['tablename' => 'product', 'field_in_db' => 'id_supplier'],
            'id_manufacturer' => ['tablename' => 'product', 'field_in_db' => 'id_manufacturer'],
            'id_tax_rules_group' => ['tablename' => 'product', 'field_in_db' => 'id_tax_rules_group'],
            'unity' => ['tablename' => 'product', 'field_in_db' => 'unity'],
            'unit_price_ratio' => ['tablename' => 'product', 'field_in_db' => 'unit_price_ratio'],
            'additional_shipping_cost' => ['tablename' => 'product', 'field_in_db' => 'additional_shipping_cost'],
            'location' => ['tablename' => 'product', 'field_in_db' => 'location'],
            'low_stock_threshold' => ['tablename' => 'product', 'field_in_db' => 'low_stock_threshold'],
            'low_stock_alert' => ['tablename' => 'product', 'field_in_db' => 'low_stock_alert'],
            'available_date' => ['tablename' => 'product', 'field_in_db' => 'available_date'],
            'show_condition' => ['tablename' => 'product', 'field_in_db' => 'show_condition'],
            'state' => ['tablename' => 'product', 'field_in_db' => 'state'],
        ];

        if (isset($defaultFields[$field_name])) {
            return $defaultFields[$field_name];
        }

        return false;
    }

    private function updateField($product, $fieldInfo, $value, $id_product_attribute = 0)
    {
        $tablename = $fieldInfo['tablename'];
        $field_in_db = $fieldInfo['field_in_db'];
        $updated_fields = [];
        $errors = [];

        try {
            switch ($tablename) {
                case 'product':
                    $result = $this->updateProductField($product, $field_in_db, $value);
                    break;

                case 'product_shop':
                    $result = $this->updateProductShopField($product, $field_in_db, $value);
                    break;

                case 'product_lang':
                    $result = $this->updateProductLangField($product, $field_in_db, $value);
                    break;

                case 'product_attribute':
                    if ($id_product_attribute > 0) {
                        $result = $this->updateProductAttributeField($product->id, $id_product_attribute, $field_in_db, $value);
                    } else {
                        $result = $this->updateAllProductAttributeFields($product->id, $field_in_db, $value);
                    }
                    break;

                case 'product_attribute_shop':
                    if ($id_product_attribute > 0) {
                        $result = $this->updateProductAttributeShopField($product->id, $id_product_attribute, $field_in_db, $value);
                    } else {
                        $result = $this->updateAllProductAttributeShopFields($product->id, $field_in_db, $value);
                    }
                    break;

                default:
                    return [
                        'success' => false,
                        'updated_fields' => [],
                        'errors' => ["Unknown table: {$tablename}"]
                    ];
            }

            return $result;

        } catch (Exception $e) {
            ChannableLogger::getInstance()->addLog(
                'Error updating field: ' . $e->getMessage(),
                1,
                $e,
                ['field' => $field_in_db, 'table' => $tablename, 'value' => $value]
            );

            return [
                'success' => false,
                'updated_fields' => [],
                'errors' => ["Error updating {$field_in_db}: " . $e->getMessage()]
            ];
        }
    }

    private function updateProductField($product, $field, $value)
    {
        $validated_value = $this->validateAndSanitizeValue($field, $value);

        if ($validated_value === false) {
            return [
                'success' => false,
                'updated_fields' => [],
                'errors' => ["Invalid value for field: {$field}"]
            ];
        }

        if (property_exists($product, $field)) {
            $product->{$field} = $validated_value;
            return [
                'success' => true,
                'updated_fields' => [$field],
                'errors' => []
            ];
        }

        return [
            'success' => false,
            'updated_fields' => [],
            'errors' => ["Field {$field} does not exist in product"]
        ];
    }

    private function updateProductShopField($product, $field, $value)
    {
        $validated_value = $this->validateAndSanitizeValue($field, $value);

        if ($validated_value === false) {
            return [
                'success' => false,
                'updated_fields' => [],
                'errors' => ["Invalid value for field: {$field}"]
            ];
        }

        $id_shop = (int) Context::getContext()->shop->id;

        $sql = 'UPDATE ' . _DB_PREFIX_ . 'product_shop
                SET `' . pSQL($field) . '` = \'' . pSQL($validated_value) . '\'
                WHERE id_product = ' . (int) $product->id . '
                AND id_shop = ' . (int) $id_shop;

        if (Db::getInstance()->execute($sql)) {
            if (property_exists($product, $field)) {
                $product->{$field} = $validated_value;
            }
            return [
                'success' => true,
                'updated_fields' => ["product_shop.{$field}"],
                'errors' => []
            ];
        }

        return [
            'success' => false,
            'updated_fields' => [],
            'errors' => ["Failed to update product_shop.{$field}"]
        ];
    }

    private function updateProductLangField($product, $field, $value)
    {
        $updated_fields = [];
        $errors = [];

        if (is_array($value)) {
            foreach ($value as $id_lang => $lang_value) {
                $validated_value = $this->validateAndSanitizeLangValue($field, $lang_value);
                if ($validated_value !== false) {
                    if (property_exists($product, $field) && is_array($product->{$field})) {
                        $product->{$field}[$id_lang] = $validated_value;
                        $updated_fields[] = "{$field}[{$id_lang}]";
                    } else {
                        $result = $this->directUpdateProductLang($product->id, $id_lang, $field, $validated_value);
                        if ($result) {
                            $updated_fields[] = "{$field}[{$id_lang}]";
                        } else {
                            $errors[] = "Failed to update {$field} for language {$id_lang}";
                        }
                    }
                } else {
                    $errors[] = "Invalid value for {$field} in language {$id_lang}";
                }
            }
        } else {
            $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
            $validated_value = $this->validateAndSanitizeLangValue($field, $value);

            if ($validated_value !== false) {
                if (property_exists($product, $field) && is_array($product->{$field})) {
                    $product->{$field}[$id_lang] = $validated_value;
                    $updated_fields[] = "{$field}[{$id_lang}]";
                } else {
                    $result = $this->directUpdateProductLang($product->id, $id_lang, $field, $validated_value);
                    if ($result) {
                        $updated_fields[] = "{$field}[{$id_lang}]";
                    } else {
                        $errors[] = "Failed to update {$field}";
                    }
                }
            } else {
                $errors[] = "Invalid value for {$field}";
            }
        }

        return [
            'success' => !empty($updated_fields),
            'updated_fields' => $updated_fields,
            'errors' => $errors
        ];
    }

    private function directUpdateProductLang($id_product, $id_lang, $field, $value)
    {
        $id_shop = (int) Context::getContext()->shop->id;

        $sql = 'UPDATE ' . _DB_PREFIX_ . 'product_lang
                SET `' . pSQL($field) . '` = \'' . pSQL($value, true) . '\'
                WHERE id_product = ' . (int) $id_product . '
                AND id_lang = ' . (int) $id_lang . '
                AND id_shop = ' . (int) $id_shop;

        return Db::getInstance()->execute($sql);
    }

    private function updateProductAttributeField($id_product, $id_product_attribute, $field, $value)
    {
        $validated_value = $this->validateAndSanitizeValue($field, $value);

        if ($validated_value === false) {
            return [
                'success' => false,
                'updated_fields' => [],
                'errors' => ["Invalid value for field: {$field}"]
            ];
        }

        if ($field === 'quantity') {
            return $this->updateProductAttributeQuantity($id_product, $id_product_attribute, $validated_value);
        }

        $sql = 'UPDATE ' . _DB_PREFIX_ . 'product_attribute
                SET `' . pSQL($field) . '` = \'' . pSQL($validated_value) . '\'
                WHERE id_product = ' . (int) $id_product . '
                AND id_product_attribute = ' . (int) $id_product_attribute;

        if (Db::getInstance()->execute($sql)) {
            return [
                'success' => true,
                'updated_fields' => ["product_attribute[{$id_product_attribute}].{$field}"],
                'errors' => []
            ];
        }

        return [
            'success' => false,
            'updated_fields' => [],
            'errors' => ["Failed to update product_attribute.{$field}"]
        ];
    }

    private function updateAllProductAttributeFields($id_product, $field, $value)
    {
        if (is_array($value)) {
            $updated_fields = [];
            $errors = [];

            foreach ($value as $id_product_attribute => $attr_value) {
                $result = $this->updateProductAttributeField($id_product, (int) $id_product_attribute, $field, $attr_value);
                if ($result['success']) {
                    $updated_fields = array_merge($updated_fields, $result['updated_fields']);
                } else {
                    $errors = array_merge($errors, $result['errors']);
                }
            }

            return [
                'success' => !empty($updated_fields),
                'updated_fields' => $updated_fields,
                'errors' => $errors
            ];
        }

        $validated_value = $this->validateAndSanitizeValue($field, $value);

        if ($validated_value === false) {
            return [
                'success' => false,
                'updated_fields' => [],
                'errors' => ["Invalid value for field: {$field}"]
            ];
        }

        if ($field === 'quantity') {
            return $this->updateAllProductAttributeQuantities($id_product, $validated_value);
        }

        $sql = 'UPDATE ' . _DB_PREFIX_ . 'product_attribute
                SET `' . pSQL($field) . '` = \'' . pSQL($validated_value) . '\'
                WHERE id_product = ' . (int) $id_product;

        if (Db::getInstance()->execute($sql)) {
            return [
                'success' => true,
                'updated_fields' => ["product_attribute.{$field} (all)"],
                'errors' => []
            ];
        }

        return [
            'success' => false,
            'updated_fields' => [],
            'errors' => ["Failed to update product_attribute.{$field}"]
        ];
    }

    private function updateProductAttributeShopField($id_product, $id_product_attribute, $field, $value)
    {
        $validated_value = $this->validateAndSanitizeValue($field, $value);

        if ($validated_value === false) {
            return [
                'success' => false,
                'updated_fields' => [],
                'errors' => ["Invalid value for field: {$field}"]
            ];
        }

        $id_shop = (int) Context::getContext()->shop->id;

        $sql = 'UPDATE ' . _DB_PREFIX_ . 'product_attribute_shop
                SET `' . pSQL($field) . '` = \'' . pSQL($validated_value) . '\'
                WHERE id_product_attribute = ' . (int) $id_product_attribute . '
                AND id_shop = ' . (int) $id_shop;

        if (Db::getInstance()->execute($sql)) {
            return [
                'success' => true,
                'updated_fields' => ["product_attribute_shop[{$id_product_attribute}].{$field}"],
                'errors' => []
            ];
        }

        return [
            'success' => false,
            'updated_fields' => [],
            'errors' => ["Failed to update product_attribute_shop.{$field}"]
        ];
    }

    private function updateAllProductAttributeShopFields($id_product, $field, $value)
    {
        if (is_array($value)) {
            $updated_fields = [];
            $errors = [];

            foreach ($value as $id_product_attribute => $attr_value) {
                $result = $this->updateProductAttributeShopField($id_product, (int) $id_product_attribute, $field, $attr_value);
                if ($result['success']) {
                    $updated_fields = array_merge($updated_fields, $result['updated_fields']);
                } else {
                    $errors = array_merge($errors, $result['errors']);
                }
            }

            return [
                'success' => !empty($updated_fields),
                'updated_fields' => $updated_fields,
                'errors' => $errors
            ];
        }

        $validated_value = $this->validateAndSanitizeValue($field, $value);

        if ($validated_value === false) {
            return [
                'success' => false,
                'updated_fields' => [],
                'errors' => ["Invalid value for field: {$field}"]
            ];
        }

        $id_shop = (int) Context::getContext()->shop->id;

        $sql = 'SELECT pa.id_product_attribute
                FROM ' . _DB_PREFIX_ . 'product_attribute pa
                JOIN ' . _DB_PREFIX_ . 'product_attribute_shop pas
                    ON pa.id_product_attribute = pas.id_product_attribute
                WHERE pa.id_product = ' . (int) $id_product . '
                AND pas.id_shop = ' . (int) $id_shop;

        $attributes = Db::getInstance()->executeS($sql);

        if ($attributes) {
            $ids = array_column($attributes, 'id_product_attribute');
            $sql = 'UPDATE ' . _DB_PREFIX_ . 'product_attribute_shop
                    SET `' . pSQL($field) . '` = \'' . pSQL($validated_value) . '\'
                    WHERE id_product_attribute IN (' . implode(',', array_map('intval', $ids)) . ')
                    AND id_shop = ' . (int) $id_shop;

            if (Db::getInstance()->execute($sql)) {
                return [
                    'success' => true,
                    'updated_fields' => ["product_attribute_shop.{$field} (all)"],
                    'errors' => []
                ];
            }
        }

        return [
            'success' => false,
            'updated_fields' => [],
            'errors' => ["Failed to update product_attribute_shop.{$field}"]
        ];
    }

    private function updateProductAttributeQuantity($id_product, $id_product_attribute, $quantity)
    {
        try {
            StockAvailable::setQuantity($id_product, $id_product_attribute, (int) $quantity);
            return [
                'success' => true,
                'updated_fields' => ["stock[{$id_product_attribute}]"],
                'errors' => []
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'updated_fields' => [],
                'errors' => ["Failed to update stock: " . $e->getMessage()]
            ];
        }
    }

    private function updateAllProductAttributeQuantities($id_product, $quantity)
    {
        try {
            $attributes = Product::getProductAttributesIds($id_product);
            $updated = 0;

            foreach ($attributes as $attr) {
                StockAvailable::setQuantity($id_product, $attr['id_product_attribute'], (int) $quantity);
                $updated++;
            }

            return [
                'success' => true,
                'updated_fields' => ["stock (all {$updated} attributes)"],
                'errors' => []
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'updated_fields' => [],
                'errors' => ["Failed to update stock: " . $e->getMessage()]
            ];
        }
    }

    private function validateAndSanitizeValue($field, $value)
    {
        if (in_array($field, self::$numericFields)) {
            if (!is_numeric($value)) {
                return false;
            }
            return (float) $value;
        }

        if (in_array($field, self::$booleanFields)) {
            return $value ? 1 : 0;
        }

        if (in_array($field, self::$dateFields)) {
            if ($value === '' || $value === null || $value === '0000-00-00') {
                return '0000-00-00';
            }
            if (!Validate::isDate($value)) {
                return false;
            }
            return pSQL($value);
        }

        if ($field === 'visibility') {
            $allowed = ['both', 'catalog', 'search', 'none'];
            if (!in_array($value, $allowed)) {
                return false;
            }
            return $value;
        }

        if ($field === 'condition') {
            $allowed = ['new', 'used', 'refurbished'];
            if (!in_array($value, $allowed)) {
                return false;
            }
            return $value;
        }

        if ($field === 'redirect_type') {
            $allowed = ['', '404', '410', '301-product', '302-product', '301-category', '302-category'];
            if (!in_array($value, $allowed)) {
                return false;
            }
            return $value;
        }

        if ($field === 'reference' || $field === 'supplier_reference') {
            if (!Validate::isReference($value)) {
                return false;
            }
            return pSQL($value);
        }

        if ($field === 'ean13') {
            if ($value !== '' && !Validate::isEan13($value)) {
                return false;
            }
            return pSQL($value);
        }

        if ($field === 'upc') {
            if ($value !== '' && !Validate::isUpc($value)) {
                return false;
            }
            return pSQL($value);
        }

        if ($field === 'isbn') {
            if ($value !== '' && !Validate::isIsbn($value)) {
                return false;
            }
            return pSQL($value);
        }

        return pSQL($value);
    }

    private function validateAndSanitizeLangValue($field, $value)
    {
        if (in_array($field, self::$htmlFields)) {
            if (!Validate::isCleanHtml($value)) {
                return false;
            }
            return $value;
        }

        if ($field === 'name') {
            if (!Validate::isGenericName($value)) {
                return false;
            }
            return pSQL($value);
        }

        if ($field === 'link_rewrite') {
            if (!Validate::isLinkRewrite($value)) {
                $value = Tools::link_rewrite($value);
            }
            return pSQL($value);
        }

        if ($field === 'meta_title') {
            if (!Validate::isGenericName($value)) {
                return false;
            }
            return pSQL($value);
        }

        if ($field === 'meta_description') {
            if (!Validate::isGenericName($value)) {
                return false;
            }
            return pSQL($value);
        }

        if ($field === 'meta_keywords') {
            if (!Validate::isGenericName($value)) {
                return false;
            }
            return pSQL($value);
        }

        return pSQL($value);
    }

    private function handleCategoryUpdate($product, $category_data)
    {
        $updated_fields = [];
        $errors = [];

        try {
            if (is_numeric($category_data)) {
                $id_category = (int) $category_data;
                if ($this->categoryExists($id_category)) {
                    $product->id_category_default = $id_category;
                    $updated_fields[] = "id_category_default";
                } else {
                    $errors[] = "Category with ID {$id_category} does not exist";
                }
            } elseif (is_string($category_data)) {
                if (strpos($category_data, '>') !== false) {
                    $category_path = array_map('trim', explode('>', $category_data));
                    $id_category = $this->findOrCreateCategoryPath($category_path);
                    if ($id_category) {
                        $product->id_category_default = $id_category;
                        $updated_fields[] = "id_category_default";
                    } else {
                        $errors[] = "Failed to create category path: {$category_data}";
                    }
                } else {
                    $category_name = trim($category_data);
                    if (!empty($category_name)) {
                        $id_category = $this->findOrCreateCategory($category_name);
                        if ($id_category) {
                            $product->id_category_default = $id_category;
                            $updated_fields[] = "id_category_default";
                        } else {
                            $errors[] = "Failed to create or find category: {$category_name}";
                        }
                    } else {
                        $errors[] = "Category name cannot be empty";
                    }
                }
            } elseif (is_array($category_data)) {
                $id_category = $this->findOrCreateCategoryPath($category_data);
                if ($id_category) {
                    $product->id_category_default = $id_category;
                    $updated_fields[] = "id_category_default";
                } else {
                    $errors[] = "Failed to create category path: " . implode(' > ', $category_data);
                }
            } else {
                $errors[] = "Invalid category format";
            }

            return [
                'success' => empty($errors),
                'updated_fields' => $updated_fields,
                'errors' => $errors
            ];

        } catch (Exception $e) {
            ChannableLogger::getInstance()->addLog(
                'Error handling category update: ' . $e->getMessage(),
                1,
                $e,
                ['category_data' => $category_data]
            );

            return [
                'success' => false,
                'updated_fields' => [],
                'errors' => ['Category update failed: ' . $e->getMessage()]
            ];
        }
    }

    private function categoryExists($id_category)
    {
        return Validate::isLoadedObject(new Category($id_category));
    }

    private function findOrCreateCategory($category_name, $id_parent = 2)
    {
        $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        $sql = 'SELECT c.id_category
                FROM ' . _DB_PREFIX_ . 'category c
                LEFT JOIN ' . _DB_PREFIX_ . 'category_lang cl ON (c.id_category = cl.id_category)
                WHERE cl.name = "' . pSQL($category_name) . '"
                AND cl.id_lang = ' . (int) $id_lang . '
                AND c.id_parent = ' . (int) $id_parent . '
                AND c.active = 1';

        $existing_id = Db::getInstance()->getValue($sql);

        if ($existing_id) {
            return (int) $existing_id;
        }

        return $this->createCategory($category_name, $id_parent);
    }

    private function createCategory($category_name, $id_parent = 2)
    {
        try {
            $category = new Category();
            $category->id_parent = (int) $id_parent;
            $category->active = 1;
            $category->is_root_category = 0;

            $languages = Language::getLanguages(false);
            foreach ($languages as $language) {
                $category->name[$language['id_lang']] = $category_name;
                $category->link_rewrite[$language['id_lang']] = Tools::link_rewrite($category_name);
                $category->description[$language['id_lang']] = '';
                $category->meta_title[$language['id_lang']] = $category_name;
                $category->meta_description[$language['id_lang']] = '';
                $category->meta_keywords[$language['id_lang']] = '';
            }

            $category->position = Category::getLastPosition($id_parent, $category->id);

            if ($category->save()) {
                ChannableLogger::getInstance()->addLog(
                    'Created new category: ' . $category_name . ' (ID: ' . $category->id . ')',
                    2,
                    false,
                    ['category_name' => $category_name, 'id_parent' => $id_parent]
                );

                return (int) $category->id;
            } else {
                ChannableLogger::getInstance()->addLog(
                    'Failed to save new category: ' . $category_name,
                    1,
                    false,
                    ['category_name' => $category_name, 'id_parent' => $id_parent]
                );
                return false;
            }

        } catch (Exception $e) {
            ChannableLogger::getInstance()->addLog(
                'Exception creating category: ' . $e->getMessage(),
                1,
                $e,
                ['category_name' => $category_name, 'id_parent' => $id_parent]
            );
            return false;
        }
    }

    private function findOrCreateCategoryPath($category_path)
    {
        if (!is_array($category_path) || empty($category_path)) {
            return false;
        }

        $current_parent = 2;
        $final_category_id = false;

        foreach ($category_path as $category_name) {
            $category_name = trim($category_name);
            if (empty($category_name)) {
                continue;
            }

            $category_id = $this->findOrCreateCategory($category_name, $current_parent);
            if (!$category_id) {
                return false;
            }

            $current_parent = $category_id;
            $final_category_id = $category_id;
        }

        return $final_category_id;
    }

    private function handleSpecificPriceUpdate($product, $specific_prices_data)
    {
        $updated_fields = [];
        $errors = [];

        try {
            $this->deleteExistingSpecificPrices($product->id);

            if (empty($specific_prices_data)) {
                $updated_fields[] = "specific_price (cleared)";

                ChannableLogger::getInstance()->addLog(
                    'Cleared all specific prices for product: ' . $product->id,
                    2,
                    false,
                    ['product_id' => $product->id]
                );

                return [
                    'success' => true,
                    'updated_fields' => $updated_fields,
                    'errors' => []
                ];
            }

            $created_count = 0;
            foreach ($specific_prices_data as $index => $sp_data) {
                if (!is_array($sp_data)) {
                    $errors[] = "Invalid specific price format at index {$index}";
                    continue;
                }

                $result = $this->createSpecificPrice($product, $sp_data);
                if ($result['success']) {
                    $created_count++;
                } else {
                    $errors = array_merge($errors, $result['errors']);
                }
            }

            if ($created_count > 0) {
                $updated_fields[] = "specific_price (created {$created_count})";

                ChannableLogger::getInstance()->addLog(
                    'Updated specific prices for product: ' . $product->id,
                    2,
                    false,
                    [
                        'product_id' => $product->id,
                        'created_count' => $created_count,
                        'data' => $specific_prices_data
                    ]
                );
            }

            return [
                'success' => empty($errors) || $created_count > 0,
                'updated_fields' => $updated_fields,
                'errors' => $errors
            ];

        } catch (Exception $e) {
            ChannableLogger::getInstance()->addLog(
                'Error handling specific price update: ' . $e->getMessage(),
                1,
                $e,
                ['product_id' => $product->id, 'data' => $specific_prices_data]
            );

            return [
                'success' => false,
                'updated_fields' => [],
                'errors' => ['Specific price update failed: ' . $e->getMessage()]
            ];
        }
    }

    private function deleteExistingSpecificPrices($id_product)
    {
        try {
            $sql = 'DELETE FROM ' . _DB_PREFIX_ . 'specific_price
                    WHERE id_product = ' . (int) $id_product;

            return Db::getInstance()->execute($sql);
        } catch (Exception $e) {
            ChannableLogger::getInstance()->addLog(
                'Error deleting specific prices: ' . $e->getMessage(),
                1,
                $e,
                ['product_id' => $id_product]
            );
            return false;
        }
    }

    private function createSpecificPrice($product, $sp_data)
    {
        $errors = [];

        try {
            $specificPrice = new SpecificPrice();

            $specificPrice->id_product = (int) $product->id;
            $specificPrice->id_shop = (int) Context::getContext()->shop->id;
            $specificPrice->id_currency = 0;
            $specificPrice->id_country = 0;
            $specificPrice->id_group = 0;
            $specificPrice->id_customer = 0;
            $specificPrice->id_product_attribute = 0;
            $specificPrice->price = -1;
            $specificPrice->from_quantity = 1;
            $specificPrice->from = '0000-00-00 00:00:00';
            $specificPrice->to = '0000-00-00 00:00:00';

            if (isset($sp_data['reduction'])) {
                $specificPrice->reduction = (float) $sp_data['reduction'];
            } else {
                $specificPrice->reduction = 0;
            }

            if (isset($sp_data['reduction_type']) && in_array($sp_data['reduction_type'], ['percentage', 'amount'])) {
                $specificPrice->reduction_type = $sp_data['reduction_type'];
            } else {
                $specificPrice->reduction_type = 'percentage';
            }

            if (isset($sp_data['reduction_tax'])) {
                $specificPrice->reduction_tax = (int) $sp_data['reduction_tax'];
            } else {
                $specificPrice->reduction_tax = 1;
            }

            if (isset($sp_data['price']) && is_numeric($sp_data['price'])) {
                $specificPrice->price = (float) $sp_data['price'];
            }

            if (isset($sp_data['id_product_attribute']) && is_numeric($sp_data['id_product_attribute'])) {
                $specificPrice->id_product_attribute = (int) $sp_data['id_product_attribute'];
            }

            if (isset($sp_data['from_quantity']) && is_numeric($sp_data['from_quantity'])) {
                $specificPrice->from_quantity = (int) $sp_data['from_quantity'];
            }

            if (isset($sp_data['id_group']) && is_numeric($sp_data['id_group'])) {
                $specificPrice->id_group = (int) $sp_data['id_group'];
            }

            if (isset($sp_data['id_customer']) && is_numeric($sp_data['id_customer'])) {
                $specificPrice->id_customer = (int) $sp_data['id_customer'];
            }

            if (isset($sp_data['id_country']) && is_numeric($sp_data['id_country'])) {
                $specificPrice->id_country = (int) $sp_data['id_country'];
            }

            if (isset($sp_data['id_currency']) && is_numeric($sp_data['id_currency'])) {
                $specificPrice->id_currency = (int) $sp_data['id_currency'];
            }

            if (isset($sp_data['id_shop']) && is_numeric($sp_data['id_shop'])) {
                $specificPrice->id_shop = (int) $sp_data['id_shop'];
            }

            if (isset($sp_data['from']) && !empty($sp_data['from'])) {
                $specificPrice->from = pSQL($sp_data['from']);
            }
            if (isset($sp_data['to']) && !empty($sp_data['to'])) {
                $specificPrice->to = pSQL($sp_data['to']);
            }

            if ($specificPrice->add()) {
                return [
                    'success' => true,
                    'errors' => []
                ];
            } else {
                $errors[] = "Failed to save specific price";
                return [
                    'success' => false,
                    'errors' => $errors
                ];
            }

        } catch (Exception $e) {
            ChannableLogger::getInstance()->addLog(
                'Exception creating specific price: ' . $e->getMessage(),
                1,
                $e,
                ['product_id' => $product->id, 'sp_data' => $sp_data]
            );

            return [
                'success' => false,
                'errors' => ['Exception: ' . $e->getMessage()]
            ];
        }
    }
}
