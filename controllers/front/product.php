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

class ChannableProductModuleFrontController extends ModuleFrontController
{
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

        if (Tools::getValue('id_product')) {
            $get_id_product = Tools::getValue('id_product');
            $selected_attribute = false;
            $selected_attribute_id = false;
            $id_product = false;
            if (is_numeric($get_id_product)) {
                $id_product = Db::getInstance()->getValue(
                    'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product` WHERE `id_product` = \'' . pSQL($get_id_product) . '\''
                );
            } else {
                if (strpos($get_id_product, '_') > 0) {
                    $get_id_product_params = explode('_', $get_id_product, 2);
                    if (sizeof($get_id_product_params) == 2) {
                        if (is_numeric($get_id_product_params[0]) && is_numeric($get_id_product_params[1])) {
                            $id_product = Db::getInstance()->getValue(
                                'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product_attribute` WHERE `id_product` = \'' . pSQL($get_id_product_params[0]) . '\' AND id_product_attribute = \'' . pSQL($get_id_product_params[1]) . '\''
                            );
                            $selected_attribute_id = $get_id_product_params[1];
                        }
                    }
                }
            }
            if (!$id_product) {
                $id_product = Db::getInstance()->getValue(
                    'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product` WHERE `reference` = \'' . pSQL($get_id_product) . '\''
                );
                if (!$id_product) {
                    $id_product = Db::getInstance()->getValue(
                        'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product_attribute` WHERE `reference` = \'' . pSQL($get_id_product) . '\''
                    );
                    if ($id_product) {
                        $selected_attribute = $get_id_product;
                    }
                }
            }
            if (!$id_product) {
                $jsonData = [
                    'status' => 'error',
                    'message' => 'Product not found',
                ];
            } else {
                $url = 'products/' . $id_product . '?ws_key=' . Tools::getValue('key');

                $input_xml = null;
                $class_name = WebserviceKey::getClassFromKey(Tools::getValue('key'));
                $bad_class_name = false;
                if (!class_exists($class_name)) {
                    $bad_class_name = $class_name;
                    $class_name = 'WebserviceRequest';
                }

                WebserviceRequest::$ws_current_classname = $class_name;
                $request = call_user_func([$class_name, 'getInstance']);

                $result = $request->fetch(
                    Tools::getValue('key'),
                    'GET',
                    $url,
                    [
                        'ws_key' => Tools::getValue('key'),
                        'output_format' => 'JSON',
                    ],
                    $bad_class_name,
                    $input_xml
                );
                $json = json_decode($result['content'], true);

                if (isset($json['product']['associations'])) {
                    unset($json['product']['associations']);
                }

                $product = new ChannableProduct((int) $id_product, true);
                $attributes = $product->getAttributesForZusammenfassungUse(
                    Context::getContext()->language->id
                );
                $json['product']['combinations'] = $attributes;

                $override_data = false;
                if ($selected_attribute) {
                    foreach ($attributes as $att) {
                        if ($att['reference'] == $selected_attribute) {
                            $override_data = $att;
                        }
                    }
                }
                if ($selected_attribute_id) {
                    foreach ($attributes as $id_product_attribute => $att) {
                        if ($id_product_attribute == $selected_attribute_id) {
                            $override_data = $att;
                        }
                    }
                }
                $json['product'] = ChannableProduct::prepareFieldsForFeed($json['product']);

                $json['product']['tax_rate'] = $product->tax_rate;
                if (isset($json['product']['id_category_default'])) {
                    $category = new Category((int) $json['product']['id_category_default']);
                    $json['product']['category_default'] = $category->name[Context::getContext()->language->id];
                }

                if ($override_data) {
                    foreach ($override_data as $key => $values) {
                        $json['product'][$key] = $values;
                    }
                    unset($json['product']['combinations']);
                }
                $json['product']['features'] = $product->getApiFeatures();

                $json = json_encode($json);

                header('Content-Type: application/json');

                echo $json;

                exit;
            }
        }
        if (isset($jsonData)) {
            header('Content-Type: application/json');
            echo json_encode($jsonData);
        }
        exit;
    }
}
