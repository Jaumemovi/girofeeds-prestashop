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
    public function postProcess()
    {
        // Authentication check
        if (!Tools::getValue('key')) {
            header('HTTP/1.1 401 Unauthorized');
            exit('Not authenticated');
        }
        if (!WebserviceKey::keyExists(Tools::getValue('key')) || !WebserviceKey::isKeyActive(Tools::getValue('key'))) {
            header('HTTP/1.1 401 Unauthorized');
            exit('Not authenticated');
        }

        // Only allow POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('HTTP/1.1 405 Method Not Allowed');
            header('Allow: POST');
            exit('Method not allowed');
        }

        // Get JSON data from request body
        $postData = Channable::fetchPhpInput();
        $jsonData = json_decode($postData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            header('HTTP/1.1 400 Bad Request');
            exit('Invalid JSON data');
        }

        // Validate required fields
        if (!isset($jsonData['id_product']) || empty($jsonData['id_product'])) {
            header('HTTP/1.1 400 Bad Request');
            exit('Product ID is required');
        }

        $id_product = (int) $jsonData['id_product'];
        
        // Check if product exists
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

    /**
     * Update product information
     *
     * @param int $id_product
     * @param array $data
     * @return array
     * @throws PrestaShopException
     */
    private function updateProduct($id_product, $data)
    {
        $product = new Product($id_product);
        $updated_fields = [];
        $errors = [];

        // Update name (title)
        if (isset($data['name']) && !empty($data['name'])) {
            if (is_array($data['name'])) {
                // Multi-language support
                foreach ($data['name'] as $id_lang => $name) {
                    if (Validate::isGenericName($name)) {
                        $product->name[$id_lang] = pSQL($name);
                        $updated_fields[] = "name[{$id_lang}]";
                    } else {
                        $errors[] = "Invalid name for language {$id_lang}";
                    }
                }
            } else {
                // Single language (use default language)
                $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
                if (Validate::isGenericName($data['name'])) {
                    $product->name[$id_lang] = pSQL($data['name']);
                    $updated_fields[] = "name[{$id_lang}]";
                } else {
                    $errors[] = "Invalid name";
                }
            }
        }

        // Update description
        if (isset($data['description']) && !empty($data['description'])) {
            if (is_array($data['description'])) {
                // Multi-language support
                foreach ($data['description'] as $id_lang => $description) {
                    if (Validate::isCleanHtml($description)) {
                        $product->description[$id_lang] = $description;
                        $updated_fields[] = "description[{$id_lang}]";
                    } else {
                        $errors[] = "Invalid description for language {$id_lang}";
                    }
                }
            } else {
                // Single language (use default language)
                $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
                if (Validate::isCleanHtml($data['description'])) {
                    $product->description[$id_lang] = $data['description'];
                    $updated_fields[] = "description[{$id_lang}]";
                } else {
                    $errors[] = "Invalid description";
                }
            }
        }

        // Update short description
        if (isset($data['description_short']) && !empty($data['description_short'])) {
            if (is_array($data['description_short'])) {
                // Multi-language support
                foreach ($data['description_short'] as $id_lang => $description_short) {
                    if (Validate::isCleanHtml($description_short)) {
                        $product->description_short[$id_lang] = $description_short;
                        $updated_fields[] = "description_short[{$id_lang}]";
                    } else {
                        $errors[] = "Invalid short description for language {$id_lang}";
                    }
                }
            } else {
                // Single language (use default language)
                $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
                if (Validate::isCleanHtml($data['description_short'])) {
                    $product->description_short[$id_lang] = $data['description_short'];
                    $updated_fields[] = "description_short[{$id_lang}]";
                } else {
                    $errors[] = "Invalid short description";
                }
            }
        }

        // Update price
        if (isset($data['price']) && is_numeric($data['price'])) {
            $price = (float) $data['price'];
            if ($price >= 0 && Validate::isPrice($price)) {
                $product->price = $price;
                $updated_fields[] = "price";
            } else {
                $errors[] = "Invalid price";
            }
        }

        // Update reference
        if (isset($data['reference']) && !empty($data['reference'])) {
            if (Validate::isReference($data['reference'])) {
                $product->reference = pSQL($data['reference']);
                $updated_fields[] = "reference";
            } else {
                $errors[] = "Invalid reference";
            }
        }

        // Update EAN13
        if (isset($data['ean13']) && !empty($data['ean13'])) {
            if (Validate::isEan13($data['ean13'])) {
                $product->ean13 = pSQL($data['ean13']);
                $updated_fields[] = "ean13";
            } else {
                $errors[] = "Invalid EAN13";
            }
        }

        // Update weight
        if (isset($data['weight']) && is_numeric($data['weight'])) {
            $weight = (float) $data['weight'];
            if ($weight >= 0) {
                $product->weight = $weight;
                $updated_fields[] = "weight";
            } else {
                $errors[] = "Invalid weight";
            }
        }

        // Update active status
        if (isset($data['active'])) {
            $active = (bool) $data['active'];
            $product->active = $active ? 1 : 0;
            $updated_fields[] = "active";
        }

        // Update category
        if (isset($data['category']) && !empty($data['category'])) {
            $category_result = $this->handleCategoryUpdate($product, $data['category']);
            if ($category_result['success']) {
                $updated_fields = array_merge($updated_fields, $category_result['updated_fields']);
            } else {
                $errors = array_merge($errors, $category_result['errors']);
            }
        }

        // Update specific prices
        if (isset($data['specific_price']) && is_array($data['specific_price'])) {
            $specific_price_result = $this->handleSpecificPriceUpdate($product, $data['specific_price']);
            if ($specific_price_result['success']) {
                $updated_fields = array_merge($updated_fields, $specific_price_result['updated_fields']);
            } else {
                $errors = array_merge($errors, $specific_price_result['errors']);
            }
        }

        // If there are validation errors, return them
        if (!empty($errors)) {
            return [
                'status' => 'error',
                'message' => 'Validation errors',
                'errors' => $errors,
                'updated_fields' => $updated_fields
            ];
        }

        // Save the product
        if (empty($updated_fields)) {
            return [
                'status' => 'warning',
                'message' => 'No valid fields to update',
                'product_id' => $id_product
            ];
        }

        $save_result = $product->save();
        
        if ($save_result) {
            // Log the update
            ChannableLogger::getInstance()->addLog(
                'Product updated successfully via API: ' . $id_product,
                2,
                false,
                ['updated_fields' => $updated_fields, 'product_id' => $id_product]
            );

            // Add product to queue for cache rebuild if caching is enabled
            if (Channable::useCache()) {
                ChannableProductsQueue::addToQueueIfNotExists($id_product);
            }

            return [
                'status' => 'success',
                'message' => 'Product updated successfully',
                'product_id' => $id_product,
                'updated_fields' => $updated_fields
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Failed to save product',
                'product_id' => $id_product,
                'updated_fields' => $updated_fields
            ];
        }
    }

    /**
     * Handle category update - create category if it doesn't exist
     *
     * @param Product $product
     * @param mixed $category_data
     * @return array
     * @throws PrestaShopException
     */
    private function handleCategoryUpdate($product, $category_data)
    {
        $updated_fields = [];
        $errors = [];

        try {
            // Handle different category input formats
            if (is_numeric($category_data)) {
                // Category ID provided
                $id_category = (int) $category_data;
                if ($this->categoryExists($id_category)) {
                    $product->id_category_default = $id_category;
                    $updated_fields[] = "id_category_default";
                } else {
                    $errors[] = "Category with ID {$id_category} does not exist";
                }
            } elseif (is_string($category_data)) {
                // Category name provided - find or create
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
            } elseif (is_array($category_data)) {
                // Category path provided (e.g., ["Electronics", "Smartphones", "iPhone"])
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

    /**
     * Check if category exists
     *
     * @param int $id_category
     * @return bool
     */
    private function categoryExists($id_category)
    {
        return Validate::isLoadedObject(new Category($id_category));
    }

    /**
     * Find existing category by name or create new one
     *
     * @param string $category_name
     * @param int $id_parent
     * @return int|false Category ID or false on failure
     * @throws PrestaShopException
     */
    private function findOrCreateCategory($category_name, $id_parent = 2)
    {
        $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        
        // First, try to find existing category
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

        // Category doesn't exist, create new one
        return $this->createCategory($category_name, $id_parent);
    }

    /**
     * Create a new category
     *
     * @param string $category_name
     * @param int $id_parent
     * @return int|false Category ID or false on failure
     * @throws PrestaShopException
     */
    private function createCategory($category_name, $id_parent = 2)
    {
        try {
            $category = new Category();
            $category->id_parent = (int) $id_parent;
            $category->active = 1;
            $category->is_root_category = 0;
            
            // Set name for all languages
            $languages = Language::getLanguages(false);
            foreach ($languages as $language) {
                $category->name[$language['id_lang']] = $category_name;
                $category->link_rewrite[$language['id_lang']] = Tools::link_rewrite($category_name);
                $category->description[$language['id_lang']] = '';
                $category->meta_title[$language['id_lang']] = $category_name;
                $category->meta_description[$language['id_lang']] = '';
                $category->meta_keywords[$language['id_lang']] = '';
            }

            // Set position
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

    /**
     * Find or create category path (nested categories)
     *
     * @param array $category_path
     * @return int|false Final category ID or false on failure
     * @throws PrestaShopException
     */
    private function findOrCreateCategoryPath($category_path)
    {
        if (!is_array($category_path) || empty($category_path)) {
            return false;
        }

        $current_parent = 2; // Start from Home category
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

    /**
     * Handle specific price update - replace existing specific prices
     *
     * @param Product $product
     * @param array $specific_prices_data
     * @return array
     */
    private function handleSpecificPriceUpdate($product, $specific_prices_data)
    {
        $updated_fields = [];
        $errors = [];

        try {
            // Delete all existing specific prices for this product
            $this->deleteExistingSpecificPrices($product->id);

            // Create new specific prices from the provided data
            if (empty($specific_prices_data)) {
                // If empty array provided, just delete existing ones
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

            // Create each specific price
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

    /**
     * Delete all existing specific prices for a product
     *
     * @param int $id_product
     * @return bool
     */
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

    /**
     * Create a specific price
     *
     * @param Product $product
     * @param array $sp_data
     * @return array
     */
    private function createSpecificPrice($product, $sp_data)
    {
        $errors = [];

        try {
            $specificPrice = new SpecificPrice();

            // Required fields
            $specificPrice->id_product = (int) $product->id;
            $specificPrice->id_shop = (int) Context::getContext()->shop->id;
            $specificPrice->id_currency = 0;
            $specificPrice->id_country = 0;
            $specificPrice->id_group = 0;
            $specificPrice->id_customer = 0;
            $specificPrice->id_product_attribute = 0;
            $specificPrice->price = -1; // Use product price
            $specificPrice->from_quantity = 1;
            $specificPrice->from = '0000-00-00 00:00:00';
            $specificPrice->to = '0000-00-00 00:00:00';

            // Set reduction (discount)
            if (isset($sp_data['reduction'])) {
                $reduction = (float) $sp_data['reduction'];
                if ($reduction >= 0 && $reduction <= 1) {
                    $specificPrice->reduction = $reduction;
                    $specificPrice->reduction_type = 'percentage';
                } elseif ($reduction > 1) {
                    // Assume it's an absolute amount
                    $specificPrice->reduction = $reduction;
                    $specificPrice->reduction_type = 'amount';
                } else {
                    $specificPrice->reduction = 0;
                    $specificPrice->reduction_type = 'percentage';
                }
            } else {
                $specificPrice->reduction = 0;
                $specificPrice->reduction_type = 'percentage';
            }

            // Optional: reduction_tax (whether tax is applied to reduction)
            if (isset($sp_data['reduction_tax'])) {
                $specificPrice->reduction_tax = (int) $sp_data['reduction_tax'];
            } else {
                $specificPrice->reduction_tax = 1;
            }

            // Optional: fixed price
            if (isset($sp_data['price']) && is_numeric($sp_data['price'])) {
                $specificPrice->price = (float) $sp_data['price'];
            }

            // Optional: id_product_attribute (for combinations)
            if (isset($sp_data['id_product_attribute']) && is_numeric($sp_data['id_product_attribute'])) {
                $specificPrice->id_product_attribute = (int) $sp_data['id_product_attribute'];
            }

            // Optional: from_quantity
            if (isset($sp_data['from_quantity']) && is_numeric($sp_data['from_quantity'])) {
                $specificPrice->from_quantity = (int) $sp_data['from_quantity'];
            }

            // Optional: id_group
            if (isset($sp_data['id_group']) && is_numeric($sp_data['id_group'])) {
                $specificPrice->id_group = (int) $sp_data['id_group'];
            }

            // Optional: id_customer
            if (isset($sp_data['id_customer']) && is_numeric($sp_data['id_customer'])) {
                $specificPrice->id_customer = (int) $sp_data['id_customer'];
            }

            // Optional: id_country
            if (isset($sp_data['id_country']) && is_numeric($sp_data['id_country'])) {
                $specificPrice->id_country = (int) $sp_data['id_country'];
            }

            // Optional: id_currency
            if (isset($sp_data['id_currency']) && is_numeric($sp_data['id_currency'])) {
                $specificPrice->id_currency = (int) $sp_data['id_currency'];
            }

            // Optional: date range
            if (isset($sp_data['from']) && !empty($sp_data['from'])) {
                $specificPrice->from = pSQL($sp_data['from']);
            }
            if (isset($sp_data['to']) && !empty($sp_data['to'])) {
                $specificPrice->to = pSQL($sp_data['to']);
            }

            // Save the specific price
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