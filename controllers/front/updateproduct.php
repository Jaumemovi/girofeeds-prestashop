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
}