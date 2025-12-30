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

class Channable extends Module
{
    protected $config_form = false;
    protected $this_file = __FILE__;
    protected static $sent_update_ids = [];
    protected static $hasWebhooks = 0;

    /**
     * Channable constructor.
     */
    public function __construct()
    {
        $this->name = 'channable';
        $this->tab = 'market_place';
        $this->version = '3.3.10';
        $this->author = 'patworx multimedia GmbH';
        $this->need_instance = 1;
        $this->module_key = 'b69e52b06d7c14d37ac752c73823c0b3';

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Channable');
        $this->description = $this->l('channable Module to connect your shop to channable service');

        $this->confirmUninstall = $this->l('Are you sure to uninstall this module?');

        $this->ps_versions_compliancy = ['min' => '1.5', 'max' => _PS_VERSION_];

        require_once dirname(__FILE__) . '/classes/ChannableCache.php';
        require_once dirname(__FILE__) . '/classes/ChannableLogger.php';
        require_once dirname(__FILE__) . '/classes/ChannableProductsQueue.php';
        require_once dirname(__FILE__) . '/classes/ChannableWebhook.php';
        require_once dirname(__FILE__) . '/classes/ChannableFeedfield.php';
        require_once dirname(__FILE__) . '/classes/ChannableCarrier.php';
        require_once dirname(__FILE__) . '/classes/ChannableOrdersAdditionalData.php';
        require_once dirname(__FILE__) . '/classes/ChannableProduct.php';
        require_once dirname(__FILE__) . '/classes/ChannableStockUpdate.php';
        require_once dirname(__FILE__) . '/classes/ChannableOrderReturn.php';
    }

    /**
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function install()
    {
        $this->enableApi();
        Configuration::updateValue('CHANNABLE_SQL_OPTIMIZATION_MODE', 1);
        Configuration::updateValue('CHANNABLE_USE_GUEST_CHECKOUT', 1);
        Configuration::updateValue('CHANNABLE_MULTIQUERY_MODE', 1);
        Configuration::updateValue('CHANNABLE_DEFAULT_PAGE_SIZE', 100);
        Configuration::updateValue('CHANNABLE_COMMENT_AS_NOTE', 1);
        Configuration::updateValue('CHANNABLE_COMMENT_AS_CUSTOMER_THREAD', 1);
        Configuration::updateValue('CHANNABLE_LOGLEVEL', 0);
        Configuration::updateValue('CHANNABLE_DO_CRON_FROM_BACKEND', 1);
        Configuration::updateValue('CHANNABLE_CRON_BACKEND_TIMEDIFF_MIN', 5);
        Configuration::updateValue('CHANNABLE_EXTEND_ORDER_VIEW_GRID', 1);
        Configuration::updateValue('CHANNABLE_EMPLOYEE_ID', 0);
        Configuration::updateValue('CHANNABLE_USE_FEED_CACHE', 0);
        Configuration::updateValue('CHANNABLE_DISABLE_VARIANTS', 0);
        Configuration::updateValue('CHANNABLE_REPLACE_NAME_CHARACTERS', 0);
        Configuration::updateValue('CHANNABLE_SHOP_STOCK_SYNC', 0);
        Configuration::updateValue('CHANNABLE_USE_PHONE_FOR_MOBILE', 0);

        Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'channable_webhooks` (
    `id_channable_webhook` int(11) NOT NULL AUTO_INCREMENT,
	`active` int(11) NOT NULL,
	`action` VARCHAR(255) NOT NULL,
	`address` VARCHAR(255) NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_channable_webhook`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;');

        Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'channable_feedfields` (
    `id_channable_feedfields` int(11) NOT NULL AUTO_INCREMENT,
    `tablename` VARCHAR(255) NOT NULL,
	`field_in_db` VARCHAR(255) NOT NULL,
	`field_in_feed` VARCHAR(255) NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_channable_feedfields`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;');

        Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'channable_orders_additional_data` (
    `id_channable_orders_additional_data` int(11) NOT NULL AUTO_INCREMENT,
    `id_order` int(11) NOT NULL,
	`field_in_post` VARCHAR(255) NOT NULL,
	`value_in_post` VARCHAR(255) NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_channable_orders_additional_data`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;');

        Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'channable_stock_update` (
    `id_channable_stock_update` int(11) NOT NULL AUTO_INCREMENT,
    `id_product` int(11) NOT NULL,
    `id_product_attribute` int(11) NOT NULL,
    `working` int(11) NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_channable_stock_update`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;');

        Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'channable_carriers` (
    `id_channable_carriers` int(11) NOT NULL AUTO_INCREMENT,
    `entity_type` VARCHAR(15) NOT NULL,
    `id_entity` int(11) NOT NULL,
    `id_carrier` int(11) NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_channable_carriers`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;');

        Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'channable_cache` (
    `id_channable_cache` int(11) NOT NULL AUTO_INCREMENT,
    `cache_key` VARCHAR(255) NOT NULL,
    `cache_value` MEDIUMTEXT NOT NULL,
    `id_lang` INT(11) NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_channable_cache`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;');

        Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'channable_cache` ADD INDEX (`cache_key`)');
        Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'channable_cache` ADD INDEX (`id_lang`)');

        Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'channable_products_queue` (
    `id_channable_products_queue` int(11) NOT NULL AUTO_INCREMENT,
    `id_product` int(11) NOT NULL,
    `running` int(2) DEFAULT 0,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_channable_products_queue`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;');

        Db::getInstance()->execute('INSERT INTO  `' . _DB_PREFIX_ . 'channable_products_queue`
        (id_product, running, date_add)
        SELECT id_product, 0, NOW() FROM `' . _DB_PREFIX_ . 'product`
    ');

        Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'channable_order_return` (
    `id_channable_order_return` int(11) NOT NULL AUTO_INCREMENT,
    `id_order` int(11) NOT NULL,
    `return_code` VARCHAR(255),
    `date_add` DATETIME,
    PRIMARY KEY  (`id_channable_order_return`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;');

        return parent::install()
            && $this->registerHook('actionUpdateQuantity')
            && $this->registerHook('actionProductUpdate')
            && $this->registerHook('actionProductAdd')
            && $this->registerHook('actionProductAttributeUpdate')
            && $this->registerHook('actionOrderGridDataModifier')
            && $this->registerHook('actionOrderGridDefinitionModifier')
            && $this->registerHook('actionAdminOrdersListingResultsModifier')
            && $this->registerHook('actionAdminOrdersListingFieldsModifier')
            && $this->registerHook('actionOrderGridQueryBuilderModifier')
            && $this->registerHook('displayAdminOrder')
            && $this->registerHook('displayBackOfficeHeader');
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        Configuration::deleteByName('CHANNABLE_FEEDMODE_ALTERNATIVE');
        Configuration::deleteByName('CHANNABLE_SQL_OPTIMIZATION_MODE');
        Configuration::deleteByName('CHANNABLE_FEEDMODE_SKIP_SHIPPING');
        Configuration::deleteByName('CHANNABLE_ORDER_WAREHOUSE');
        Configuration::deleteByName('CHANNABLE_MULTIQUERY_MODE');
        Configuration::deleteByName('CHANNABLE_DEFAULT_PAGE_SIZE');
        Configuration::deleteByName('CHANNABLE_COMMENT_AS_NOTE');
        Configuration::deleteByName('CHANNABLE_COMMENT_AS_CUSTOMER_THREAD');
        Configuration::deleteByName('CHANNABLE_LOGLEVEL');
        Configuration::deleteByName('CHANNABLE_DO_CRON_FROM_BACKEND');
        Configuration::deleteByName('CHANNABLE_CRON_BACKEND_TIMEDIFF_MIN');
        Configuration::deleteByName('CHANNABLE_EXTEND_ORDER_VIEW_GRID');
        Configuration::deleteByName('CHANNABLE_EMPLOYEE_ID');
        Configuration::deleteByName('CHANNABLE_USE_FEED_CACHE');
        Configuration::deleteByName('CHANNABLE_DISABLE_VARIANTS');
        Configuration::deleteByName('CHANNABLE_REPLACE_NAME_CHARACTERS');
        Configuration::deleteByName('CHANNABLE_SHOP_STOCK_SYNC');
        Configuration::deleteByName('CHANNABLE_USE_PHONE_FOR_MOBILE');

        return parent::uninstall();
    }

    /**
     * @return bool|string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function getContent()
    {
        if (((bool) Tools::isSubmit('submitChannableModule')) == true) {
            $this->postProcess();
        }
        $this->context->smarty->assign('module_dir', $this->_path);

        if (Tools::getValue('submitChannableOrderSettingsModule') == '1') {
            if ($osData = Tools::getValue('os')) {
                if (isset($osData['shipped'])) {
                    Configuration::updateValue('CHANNABLE_ORDER_STATES_SHIPPED', join(',', $osData['shipped']));
                } else {
                    Configuration::updateValue('CHANNABLE_ORDER_STATES_SHIPPED', join(',', []));
                }
                if (isset($osData['cancelled'])) {
                    Configuration::updateValue('CHANNABLE_ORDER_STATES_CANCELLED', join(',', $osData['cancelled']));
                } else {
                    Configuration::updateValue('CHANNABLE_ORDER_STATES_CANCELLED', join(',', []));
                }
            }
            if (Tools::getValue('os_import') != '') {
                Configuration::updateValue('CHANNABLE_ORDER_STATE_IMPORT', (int) Tools::getValue('os_import'));
            }
            if (Tools::getValue('carrier_import') != '') {
                Configuration::updateValue('CHANNABLE_ORDER_CARRIER_ID_IMPORT', (int) Tools::getValue('carrier_import'));
            }
            if (Tools::getValue('carrier_import_tax') != '') {
                Configuration::updateValue('CHANNABLE_ORDER_CARRIER_TAX', (float) str_replace(',', '.', Tools::getValue('carrier_import_tax')));
            }
            if (Tools::getValue('order_warehouse') != '') {
                Configuration::updateValue('CHANNABLE_ORDER_WAREHOUSE', (int) Tools::getValue('order_warehouse'));
            }
            if (Tools::getValue('comment_as_note') != '') {
                Configuration::updateValue('CHANNABLE_COMMENT_AS_NOTE', (int) Tools::getValue('comment_as_note'));
            }
            if (Tools::getValue('comment_as_customer_thread') != '') {
                Configuration::updateValue('CHANNABLE_COMMENT_AS_CUSTOMER_THREAD', (int) Tools::getValue('comment_as_customer_thread'));
            }
            if (Tools::getValue('enable_new_order_hook') != '') {
                Configuration::updateValue('CHANNABLE_ENABLE_NEW_ORDER_HOOK', (int) Tools::getValue('enable_new_order_hook'));
            }
            if (Tools::getValue('order_view_grid') != '') {
                Configuration::updateValue('CHANNABLE_EXTEND_ORDER_VIEW_GRID', (int) Tools::getValue('order_view_grid'));
            }
            if (Tools::getValue('enable_char_replacement') != '') {
                Configuration::updateValue('CHANNABLE_REPLACE_NAME_CHARACTERS', (int) Tools::getValue('enable_char_replacement'));
            }
            if (Tools::getValue('send_product_stock_interval') != '') {
                Configuration::updateValue('CHANNABLE_CRON_BACKEND_TIMEDIFF_MIN', (int) Tools::getValue('send_product_stock_interval'));
            }
            if (Tools::getValue('employee_id') != '') {
                Configuration::updateValue('CHANNABLE_EMPLOYEE_ID', (int) Tools::getValue('employee_id'));
            }
            if (Tools::getValue('order_import_name_default') != '') {
                Configuration::updateValue('CHANNABLE_ORDER_IMPORT_NAME_DEFAULT', (string) Tools::getValue('order_import_name_default'));
            }
            if (Tools::getValue('enable_shop_stock_sync') != '') {
                Configuration::updateValue('CHANNABLE_SHOP_STOCK_SYNC', (int) Tools::getValue('enable_shop_stock_sync'));
            }
            if (Tools::getValue('enable_phone_as_mobile') != '') {
                Configuration::updateValue('CHANNABLE_USE_PHONE_FOR_MOBILE', (int) Tools::getValue('enable_phone_as_mobile'));
            }

            $this->context->smarty->assign('success_message', $this->l('Settings updated'));
        }
        if (Tools::getValue('submitChannableAssignmentModule') == '1') {
            ChannableFeedfield::removeAllFeedfields();
            if (Tools::getValue('assigned_fields')) {
                foreach (Tools::getValue('assigned_fields') as $afKey => $data) {
                    $assignedField = new ChannableFeedfield();
                    $assignedField->tablename = $data['tablename'];
                    $assignedField->field_in_db = $data['field_in_db'];
                    $assignedField->field_in_feed = $data['field_in_feed'];
                    $assignedField->save();
                }
            }
            $this->context->smarty->assign('success_message', $this->l('Assigned fields in feed updated'));
        }

        if (Tools::getValue('submitChannableCustomergroupAssignmentModule') == '1') {
            if (Tools::getValue('cga')) {
                if (is_array(Tools::getValue('cga'))) {
                    Configuration::updateValue('CHANNABLE_CUSTOMER_GROUP_ASSIGNMENTS', json_encode(Tools::getValue('cga')));
                }
            }
            $this->context->smarty->assign('success_message', $this->l('Assigned customergroups updated'));
        }

        if (Tools::getValue('submitChannableMarketplaceAssignmentModule') == '1') {
            if (Tools::getValue('msa')) {
                if (is_array(Tools::getValue('msa'))) {
                    Configuration::updateValue('CHANNABLE_MARKETPLACE_ASSIGNMENTS', json_encode(Tools::getValue('msa')));
                }
            }
            $this->context->smarty->assign('success_message', $this->l('Assigned shipping status to marketplace updated'));
        }

        if (Tools::getValue('submitChannableTaxRateModule') == '1') {
            if (Tools::getValue('coa')) {
                if (is_array(Tools::getValue('coa'))) {
                    Configuration::updateValue('CHANNABLE_TAXCOUNTRY_ASSIGNMENTS', json_encode(Tools::getValue('coa')));
                }
            }
            $this->context->smarty->assign('success_message', $this->l('Assigned taxrates to country updated'));
        }

        if (Tools::getValue('submitChannableCarrierAssignmentModule') == '1') {
            if (Tools::getValue('csa')) {
                if (is_array(Tools::getValue('csa'))) {
                    foreach (Tools::getValue('csa') as $csaKey => $carrierAssignment) {
                        if ($csaKey < 0) {
                            $csaObject = new ChannableCarrier();
                        } else {
                            $csaObject = new ChannableCarrier((int) $csaKey);
                        }
                        if ($carrierAssignment['id_entity'] == 0 || trim($carrierAssignment['id_entity']) == '') {
                            if ($csaObject->id) {
                                $csaObject->delete();
                            }
                        } else {
                            $csaObject->entity_type = $carrierAssignment['entity_type'];
                            $csaObject->id_entity = (int) $carrierAssignment['id_entity'];
                            $csaObject->id_carrier = (int) $carrierAssignment['id_carrier'];
                            $csaObject->save();
                        }
                    }
                }
            }
        }

        $webservice = new WebserviceKey((int) Configuration::get('CHANNABLE_API_ID'));

        $this->context->smarty->assign('feed_url', $this->context->link->getModuleLink('channable', 'feed', ['key' => $webservice->key, 'limit' => '0,100']));
        $this->context->smarty->assign('auto_connect_feed_url', $this->context->link->getModuleLink('channable', 'feed'));
        $this->context->smarty->assign('webhook_url', $this->context->link->getModuleLink('channable', 'webhooks'));
        $this->context->smarty->assign('order_api_url', $this->context->link->getModuleLink('channable', 'order'));
        $this->context->smarty->assign('order_api_fetch_url', $this->context->link->getModuleLink('channable', 'order', ['order' => 'XX_ORDER_ID_XX']));
        $this->context->smarty->assign('product_api_url', $this->context->link->getModuleLink('channable', 'product', ['key' => $webservice->key, 'id_product' => 'XX_PRODUCT_ID_XX']));
        $this->context->smarty->assign('product_cache_cron_url', $this->context->link->getModuleLink('channable', 'cron', ['buildProductsJson' => '1']));
        $this->context->smarty->assign('channable_key', $webservice->key);
        $this->context->smarty->assign('lang_id', Context::getContext()->language->id);
        $this->context->smarty->assign('form_url', $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'));
        $this->context->smarty->assign('order_states', OrderState::getOrderStates((int) Configuration::get('PS_LANG_DEFAULT')));
        $this->context->smarty->assign('order_states_shipped', $this->getOrderStates('shipped'));
        $this->context->smarty->assign('order_states_cancelled', $this->getOrderStates('cancelled'));
        $this->context->smarty->assign('order_state_import', Configuration::get('CHANNABLE_ORDER_STATE_IMPORT'));
        $this->context->smarty->assign('order_carrier_import', Configuration::get('CHANNABLE_ORDER_CARRIER_ID_IMPORT'));
        $this->context->smarty->assign('carrier_import_tax', (float) Configuration::get('CHANNABLE_ORDER_CARRIER_TAX'));
        $this->context->smarty->assign('order_warehouse', Configuration::get('CHANNABLE_ORDER_WAREHOUSE'));
        $this->context->smarty->assign('employee_id', Configuration::get('CHANNABLE_EMPLOYEE_ID'));
        $this->context->smarty->assign('order_import_name_default', Configuration::get('CHANNABLE_ORDER_IMPORT_NAME_DEFAULT'));
        $this->context->smarty->assign('enable_shop_stock_sync', Configuration::get('CHANNABLE_SHOP_STOCK_SYNC'));
        $this->context->smarty->assign('employees', Employee::getEmployees());
        $this->context->smarty->assign('feedfields_available', ChannableFeedfield::getAvailableFieldsFiltered());
        $this->context->smarty->assign('feedfields_assigned', ChannableFeedfield::getAllFeedfields());
        $this->context->smarty->assign('shop_countries', Country::getCountries(Configuration::get('PS_LANG_DEFAULT'), true));
        $this->context->smarty->assign('carriers', Carrier::getCarriers(Configuration::get('PS_LANG_DEFAULT'), false, false, false, null, Carrier::ALL_CARRIERS));
        $this->context->smarty->assign('customer_group_assignments', self::getCustomerGroupAssignments());
        $this->context->smarty->assign('marketplace_assignments', self::getMarketplaceAssignments());
        $this->context->smarty->assign('tax_country_assignments', self::getTaxCountryAssignments());
        $this->context->smarty->assign('carrier_assignments', self::getCarrierAssignments());
        $this->context->smarty->assign('customer_groups', Group::getGroups(Context::getContext()->language->id));
        try {
            $this->context->smarty->assign('warehouses', Warehouse::getWarehouses());
        } catch (Exception $e) {
            $this->context->smarty->assign('warehouses', []);
        }

        $date_last_modification = filemtime($this->local_path . 'logo.png');
        $key_theorical = Tools::substr('CHANNABLE' . md5($date_last_modification), 0, 32);
        if ($key_theorical == $webservice->key) {
            $this->context->smarty->assign('update_key_message', true);
        }

        $basicform = $this->renderForm();
        $this->context->smarty->assign('mainform', $basicform);

        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        return $output;
    }

    /**
     * @return string
     *
     * @throws PrestaShopException
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitChannableModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
        . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getConfigForm()]);
    }

    /**
     * @return array[]
     */
    protected function getConfigForm()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Feed Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'desc' => $this->l('Only change this if you have a high powered server.'),
                        'name' => 'CHANNABLE_MULTIQUERY_MODE',
                        'label' => $this->l('Timeout optimized mode'),
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_multiquery_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'active_multiquery_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'desc' => $this->l('Use this mode if you experience problems with the product feed.'),
                        'name' => 'CHANNABLE_FEEDMODE_ALTERNATIVE',
                        'label' => $this->l('Alternative Mode'),
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'desc' => $this->l('Use this mode if you experience problems with the SQL server.'),
                        'name' => 'CHANNABLE_SQL_OPTIMIZATION_MODE',
                        'label' => $this->l('SQL Optimization Mode'),
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'sql_active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'sql_active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'desc' => $this->l(''),
                        'name' => 'CHANNABLE_DISABLE_OUT_OF_STOCK',
                        'label' => $this->l('Disable out of stock products'),
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'dos_active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'dos_active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'desc' => $this->l(''),
                        'name' => 'CHANNABLE_DISABLE_INACTIVE',
                        'label' => $this->l('Disable inactive products'),
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'di_active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'di_active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'desc' => $this->l(''),
                        'name' => 'CHANNABLE_FEEDMODE_SKIP_SHIPPING',
                        'label' => $this->l('Skip shipping calculation'),
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'skip_active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'skip_active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'desc' => $this->l(''),
                        'name' => 'CHANNABLE_DISABLE_VARIANTS',
                        'label' => $this->l('Disable variants in feed'),
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'variants_active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'variants_active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'desc' => $this->l('Customer and corresponding default address used for shipping cost calculation in feed.'),
                        'name' => 'CHANNABLE_CUSTOMER_ID',
                        'label' => $this->l('Default Customer-ID'),
                        'col' => 3,
                    ],
                    [
                        'type' => 'text',
                        'name' => 'CHANNABLE_DEFAULT_PAGE_SIZE',
                        'label' => $this->l('Default page size'),
                        'col' => 3,
                    ],
                    [
                        'type' => 'switch',
                        'desc' => $this->l('If inactive, all incoming order will have created a "real" customer account.'),
                        'name' => 'CHANNABLE_USE_GUEST_CHECKOUT',
                        'label' => $this->l('Use Guest checkout'),
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'uguest_active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'uguest_active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'desc' => $this->l('If inactive, all feed data will be created on the fly.'),
                        'name' => 'CHANNABLE_USE_FEED_CACHE',
                        'label' => $this->l('Use Feed-Cache'),
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'ucachefeed_active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'ucachefeed_active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];
    }

    /**
     * @return array
     */
    protected function getConfigFormValues()
    {
        return [
            'CHANNABLE_FEEDMODE_ALTERNATIVE' => Tools::getValue('CHANNABLE_FEEDMODE_ALTERNATIVE', Configuration::get('CHANNABLE_FEEDMODE_ALTERNATIVE') == '1' ? 1 : 0),
            'CHANNABLE_MULTIQUERY_MODE' => Tools::getValue('CHANNABLE_MULTIQUERY_MODE', Configuration::get('CHANNABLE_MULTIQUERY_MODE') == '1' ? 1 : 0),
            'CHANNABLE_SQL_OPTIMIZATION_MODE' => Tools::getValue('CHANNABLE_SQL_OPTIMIZATION_MODE', Configuration::get('CHANNABLE_SQL_OPTIMIZATION_MODE') == '1' ? 1 : 0),
            'CHANNABLE_DISABLE_OUT_OF_STOCK' => Tools::getValue('CHANNABLE_DISABLE_OUT_OF_STOCK', Configuration::get('CHANNABLE_DISABLE_OUT_OF_STOCK') == '1' ? 1 : 0),
            'CHANNABLE_DISABLE_INACTIVE' => Tools::getValue('CHANNABLE_DISABLE_INACTIVE', Configuration::get('CHANNABLE_DISABLE_INACTIVE') == '1' ? 1 : 0),
            'CHANNABLE_FEEDMODE_SKIP_SHIPPING' => Tools::getValue('CHANNABLE_FEEDMODE_SKIP_SHIPPING', Configuration::get('CHANNABLE_FEEDMODE_SKIP_SHIPPING') == '1' ? 1 : 0),
            'CHANNABLE_DISABLE_VARIANTS' => Tools::getValue('CHANNABLE_DISABLE_VARIANTS', Configuration::get('CHANNABLE_DISABLE_VARIANTS') == '1' ? 1 : 0),
            'CHANNABLE_CUSTOMER_ID' => Tools::getValue('CHANNABLE_CUSTOMER_ID', Configuration::get('CHANNABLE_CUSTOMER_ID')),
            'CHANNABLE_DEFAULT_PAGE_SIZE' => Tools::getValue('CHANNABLE_DEFAULT_PAGE_SIZE', Configuration::get('CHANNABLE_DEFAULT_PAGE_SIZE')),
            'CHANNABLE_USE_GUEST_CHECKOUT' => Tools::getValue('CHANNABLE_USE_GUEST_CHECKOUT', Configuration::get('CHANNABLE_USE_GUEST_CHECKOUT') == '1' ? 1 : 0),
            'CHANNABLE_USE_FEED_CACHE' => Tools::getValue('CHANNABLE_USE_FEED_CACHE', Configuration::get('CHANNABLE_USE_FEED_CACHE') == '1' ? 1 : 0),
        ];
    }

    /**
     * update config routine
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
     * Backoffice header hook
     */
    public function hookDisplaybackOfficeHeader()
    {
        $doCron = false;
        if (Configuration::get('CHANNABLE_DO_CRON_FROM_BACKEND') == '1') {
            $cronRun = Configuration::get('CHANNABLE_LAST_CRONRUN');
            if ($cronRun == '') {
                $doCron = true;
            } else {
                $current_date = new DateTime('now');
                $cron_date = new DateTime($cronRun);
                $diff = $current_date->diff($cron_date);
                if ($diff->format('%i') >= (int) Configuration::get('CHANNABLE_CRON_BACKEND_TIMEDIFF_MIN')) {
                    $doCron = true;
                }
            }
        }
        if ($doCron) {
            $this->sendProductUpdate();
            Configuration::updateValue('CHANNABLE_LAST_CRONRUN', date('Y-m-d H:i:s'));
        }
        if (Tools::getValue('module_name') == $this->name
            || Tools::getValue('configure') == $this->name) {
            if (_PS_VERSION_ < 9) {
                $this->context->controller->addJquery();
            }
            $this->context->controller->addJS($this->_path . 'views/js/backend.js');
            $this->context->controller->addCSS($this->_path . 'views/css/back.css');
        }
    }

    /**
     * @param $params
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookActionUpdateQuantity($params)
    {
        if (isset($params['id_product'])) {
            ChannableProductsQueue::addToQueueIfNotExists((int) $params['id_product']);
        }

        $sql = 'SELECT product_attribute_shop.id_product_attribute
				FROM ' . _DB_PREFIX_ . 'product_attribute pa
				' . Shop::addSqlAssociation('product_attribute', 'pa') . '
				WHERE pa.id_product = ' . (int) $params['id_product'];
        $combinations = Db::getInstance()->executeS($sql);
        if ($combinations && is_array($combinations) && sizeof($combinations) > 0) {
            foreach ($combinations as $c) {
                $params['id_product_attribute'] = $c['id_product_attribute'];
                $this->storeProductUpdate($params);
            }
        } else {
            $this->storeProductUpdate($params);
        }
    }

    /**
     * @param $params
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookActionProductUpdate($params)
    {
        if (isset($params['id_product'])) {
            ChannableProductsQueue::addToQueueIfNotExists((int) $params['id_product']);
        }

        $sql = 'SELECT product_attribute_shop.id_product_attribute
				FROM ' . _DB_PREFIX_ . 'product_attribute pa
				' . Shop::addSqlAssociation('product_attribute', 'pa') . '
				WHERE pa.id_product = ' . (int) $params['id_product'];
        $combinations = Db::getInstance()->executeS($sql);
        if ($combinations && is_array($combinations) && sizeof($combinations) > 0) {
            foreach ($combinations as $c) {
                $params['id_product_attribute'] = $c['id_product_attribute'];
                $this->storeProductUpdate($params);
            }
        } else {
            $this->storeProductUpdate($params, true);
        }
    }

    /**
     * @param $params
     *
     * @throws PrestaShopException
     */
    public function hookActionProductAdd($params)
    {
        if (isset($params['id_product'])) {
            ChannableProductsQueue::addToQueueIfNotExists((int) $params['id_product']);
        }
    }

    /**
     * @param $params
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookActionProductAttributeUpdate($params)
    {
        if (isset($params['id_product'])) {
            ChannableProductsQueue::addToQueueIfNotExists((int) $params['id_product']);
        }
        if (self::$hasWebhooks == 1) {
            $this->storeProductUpdate($params);
        } else {
            if (self::$hasWebhooks == 0) {
                $webHookData = ChannableWebhook::getAllWebhooks();
                if (sizeof($webHookData) > 0) {
                    self::$hasWebhooks = 1;
                    $this->storeProductUpdate($params);
                } else {
                    self::$hasWebhooks = -1;
                }
            }
        }
    }

    /**
     * @param $params
     * @param false $override
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function storeProductUpdate($params, $override = false)
    {
        if (isset($params['id_product_attribute']) && $params['id_product_attribute'] > 0) {
            $combination = new Combination((int) $params['id_product_attribute']);
            $check = ChannableStockUpdate::existsByIdProduct((int) $combination->id_product, (int) $params['id_product_attribute']);
            $id_product = (int) $combination->id_product;
        } else {
            $check = ChannableStockUpdate::existsByIdProduct((int) $params['id_product']);
            $id_product = (int) $params['id_product'];
        }
        if (!$check) {
            $stockUpdate = new ChannableStockUpdate();
            $stockUpdate->id_product = (int) $id_product;
            if (isset($params['id_product_attribute']) && $params['id_product_attribute'] > 0) {
                $stockUpdate->id_product_attribute = (int) $params['id_product_attribute'];
            } else {
                $stockUpdate->id_product_attribute = 0;
            }
            $stockUpdate->working = 0;
            $stockUpdate->save();
        }
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function sendProductUpdate()
    {
        $webHookData = ChannableWebhook::getAllWebhooks();
        if (sizeof($webHookData) > 0) {
            $jsonData = [
                'id' => '',
                'created' => '',
                'modified' => date('Y-m-d H:i:s'),
                'gtin' => '',
                'price' => '',
                'stock' => '',
                'title' => '',
            ];
            $stockUpdates = ChannableStockUpdate::getQualifiedUpdates();
            if (sizeof($stockUpdates) > 0) {
                foreach ($stockUpdates as $stockUpdate) {
                    $stockUpdateObject = new ChannableStockUpdate($stockUpdate['id_channable_stock_update']);
                    $stockUpdateObject->working = 1;
                    $stockUpdateObject->save();
                    if ($stockUpdate['id_product_attribute'] > 0) {
                        $is_variant = true;
                        $combination = new Combination((int) $stockUpdate['id_product_attribute']);
                        $product = new Product((int) $combination->id_product);
                        $jsonData['id'] = $product->id . '_' . (int) $stockUpdate['id_product_attribute'];
                        if ($combination->reference != '') {
                            $jsonData['gtin'] = $combination->reference;
                        } elseif ($product->reference != '') {
                            $jsonData['gtin'] = $product->reference;
                        } else {
                            $jsonData['gtin'] = $product->ean13;
                        }
                        $jsonData['price'] = $product->price + $combination->price;
                        $stockResult = $jsonData['stock'] = StockAvailable::getQuantityAvailableByProduct($stockUpdate['id_product'], $stockUpdate['id_product_attribute']);
                    } else {
                        $is_variant = false;
                        $product = new Product((int) $stockUpdate['id_product']);
                        $jsonData['id'] = (int) $stockUpdate['id_product'];
                        if ($product->reference != '') {
                            $jsonData['gtin'] = $product->reference;
                        } else {
                            $jsonData['gtin'] = $product->ean13;
                        }
                        $jsonData['price'] = $product->price;
                        $stockResult = $jsonData['stock'] = StockAvailable::getQuantityAvailableByProduct($stockUpdate['id_product']);
                    }
                    ChannableLogger::getInstance()->addLog(
                        'Sending product update',
                        3,
                        false,
                        [
                            'params' => [
                                'id_product' => $stockUpdate['id_product'],
                                'id_product_attribute' => isset($stockUpdate['id_product_attribute']) ? $stockUpdate['id_product_attribute'] : false,
                                'quantity' => $jsonData['stock'],
                            ],
                            'jsonData' => $jsonData,
                            'stockResult' => $stockResult,
                        ]
                    );
                    $jsonData['created'] = $product->date_add;
                    $jsonData['title'] = $product->name[Context::getContext()->language->id];
                    if ($jsonData['stock'] !== null) {
                        $curlJson = json_encode($jsonData);
                        foreach ($webHookData as $webHook) {
                            if ($webHook['active'] == '1') {
                                $ch = curl_init($webHook['address']);
                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                                curl_setopt($ch, CURLOPT_POSTFIELDS, $curlJson);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . Tools::strlen($curlJson)]);
                                $result = curl_exec($ch);
                            }
                        }
                        self::$sent_update_ids[$jsonData['id'] . '_' . $jsonData['stock']] = true;
                    }
                    $stockUpdateObject->delete();
                    /*
                    error_log('fired event');
                    error_log(Tools::jsonEncode($jsonData));
                    header('Content-Type: application/json');
                    echo Tools::jsonEncode($jsonData);
                    exit();
                    */
                }
            }
        }
    }

    /**
     * @return bool
     *
     * @throws PrestaShopException
     */
    protected function enableApi()
    {
        Configuration::updateValue('PS_WEBSERVICE', 1);

        $webserviceKey = new WebserviceKey();
        $webserviceKey->active = true;
        $webserviceKey->key = Tools::substr('CHANNABLE' . md5(_COOKIE_KEY_ . time()), 0, 32);
        $webserviceKey->description = $this->l('Webservice API Key for channable created by plugin');
        $webserviceKey->save();

        $permissions_to_set = [];
        $ressources = WebserviceRequest::getResources();

        $methods = ['GET' => 'GET', 'HEAD' => 'HEAD'];
        foreach ($ressources as $resource_name => $data) {
            if (is_array($data) && isset($data['forbidden_method'])) {
                $permissions_to_set[$resource_name] = [];
                foreach ($methods as $method) {
                    if (!in_array($method, $data['forbidden_method'])) {
                        $permissions_to_set[$resource_name][$method] = $method;
                    }
                }
            } else {
                $permissions_to_set[$resource_name] = $methods;
            }
        }

        WebserviceKey::setPermissionForAccount($webserviceKey->id, $permissions_to_set);
        Configuration::updateValue('CHANNABLE_API_ID', (int) $webserviceKey->id);

        return true;
    }

    /**
     * @param $type
     *
     * @return false|string[]
     */
    public function getOrderStates($type)
    {
        $states = Configuration::get('CHANNABLE_ORDER_STATES_' . Tools::strtoupper($type));

        return explode(',', $states);
    }

    /**
     * @param $type
     *
     * @return false|string[]
     */
    public static function getChannableOrderStates($type)
    {
        switch ($type) {
            case 'SHIPPING_SHIPPED':
                $states = Configuration::get('CHANNABLE_ORDER_STATES_SHIPPED');

                return explode(',', $states);
                break;
            case 'SHIPPING_CANCELLED':
                $states = Configuration::get('CHANNABLE_ORDER_STATES_CANCELLED');

                return explode(',', $states);
                break;
        }

        return false;
    }

    /**
     * @return bool|string
     */
    public static function fetchPhpInput()
    {
        return Tools::file_get_contents('php://input');
    }

    /**
     * @return bool
     */
    public static function isPrestaShop177OrHigherStatic()
    {
        return version_compare(_PS_VERSION_, '1.7.7', '>=');
    }

    /**
     * @param $params
     *
     * @return bool|string|void
     *
     * @throws PrestaShopDatabaseException
     */
    public function hookDisplayAdminOrder($params)
    {
        if (!isset($params['id_order'])) {
            return;
        }
        $this->context->smarty->assign('isHigher176', self::isPrestaShop177OrHigherStatic());
        if (Tools::getValue('channable_return_code')) {
            ChannableOrderReturn::addOrUpdateToOrder(
                (int) $params['id_order'],
                Tools::getValue('channable_return_code')
            );
        }
        $additionalData = ChannableOrdersAdditionalData::getByOrderId($params['id_order']);
        if ($additionalData) {
            $order_return_code = ChannableOrderReturn::getByOrderId((int) $params['id_order']);
            $this->context->smarty->assign('orderReturnCode', $order_return_code ? $order_return_code->return_code : false);
            $this->context->smarty->assign('additionalData', $additionalData);

            return $this->display($this->this_file, 'views/templates/admin/hookAdminOrder.tpl');
        }
    }

    /**
     * Hook allows to modify Order grid data before 1.7.7.0
     *
     * @param array $params
     */
    public function hookActionAdminOrdersListingFieldsModifier(array $params)
    {
        if (Configuration::get('CHANNABLE_EXTEND_ORDER_VIEW_GRID') == 1) {
            if (isset($params['select'])) {
                $params['join'] .= ' LEFT JOIN `' . _DB_PREFIX_ . 'channable_orders_additional_data` AS cm ON (cm.`id_order` = a.`id_order` AND cm.`field_in_post` = \'marketplace_order_id\') ';
                $params['select'] .= ', cm.`value_in_post` as channable_comment ';
            }
            $params['fields'] += [
                'value_in_post' => [
                    'title' => 'Channable Info',
                    'search' => true,
                ],
            ];
        }
    }

    /**
     * Hook allows to modify Order grid data before 1.7.7.0
     *
     * @param array $params
     */
    public function hookActionAdminOrdersListingResultsModifier(array $params)
    {
        if (Configuration::get('CHANNABLE_EXTEND_ORDER_VIEW_GRID') == 1) {
            foreach ($params['list'] as $key => $fields) {
                $channable_comment = '';
                if (!isset($fields['value_in_post']) || empty($fields['value_in_post'])) {
                    $dbResults = Db::getInstance()->query(
                        'SELECT
                        cm.`value_in_post` as `message` FROM `' . _DB_PREFIX_ . 'channable_orders_additional_data` cm
                     WHERE cm.id_order = \'' . (int) $fields['id_order'] . '\'
                       AND cm.`field_in_post` = \'marketplace_order_id\'
                    '
                    );
                    if ($dbResults) {
                        foreach ($dbResults as $dbResult) {
                            if ($channable_comment != '') {
                                $channable_comment = $channable_comment . "\n" . $dbResult['message'];
                            } else {
                                $channable_comment = $dbResult['message'];
                            }
                        }
                    }
                    if ($channable_comment != '') {
                        $params['list'][$key]['value_in_post'] = $channable_comment;
                    }
                }
            }
        }
    }

    /**
     * @param array $params
     */
    public function hookActionOrderGridDefinitionModifier(array $params)
    {
        if (Configuration::get('CHANNABLE_EXTEND_ORDER_VIEW_GRID') == 1) {
            /** @var PrestaShop\PrestaShop\Core\Grid\Definition\GridDefinitionInterface $definition */
            $definition = $params['definition'];

            $translator = $this->getTranslator();

            $definition
                ->getColumns()
                ->addAfter(
                    'osname',
                    (new PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn('channable'))
                        ->setName($translator->trans('Channable Info', [], 'Modules.Channable'))
                        ->setOptions([
                            'field' => 'channable_comment',
                        ])
                )
            ;

            $definition->getFilters()->add(
                (new PrestaShop\PrestaShop\Core\Grid\Filter\Filter('channable', Symfony\Component\Form\Extension\Core\Type\TextType::class))
                    ->setAssociatedColumn('channable')
                    ->setTypeOptions([
                        'required' => false,
                    ])
            );
        }
    }

    /**
     * @param array $params
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     */
    public function hookActionOrderGridQueryBuilderModifier(array $params)
    {
        if (empty($params['search_query_builder']) || empty($params['search_criteria']) || Configuration::get('CHANNABLE_EXTEND_ORDER_VIEW_GRID') != 1) {
            return;
        }

        $searchQueryBuilder = $params['search_query_builder'];

        $searchCriteria = $params['search_criteria'];

        $searchQueryBuilder->addSelect('cm.`value_in_post` as `channable_comment`');
        $searchQueryBuilder->leftJoin(
            'o',
            '`' . _DB_PREFIX_ . 'channable_orders_additional_data`',
            'cm',
            'cm.`id_order` = o.`id_order` AND cm.`field_in_post` = \'marketplace_order_id\' '
        );

        if ('channable' === $searchCriteria->getOrderBy()) {
            $searchQueryBuilder->orderBy('cm.`value_in_post`', $searchCriteria->getOrderWay());
        }

        foreach ($searchCriteria->getFilters() as $filterName => $filterValue) {
            if ('channable' === $filterName) {
                $searchQueryBuilder->andWhere('cm.`value_in_post` LIKE :channable');
                $searchQueryBuilder->setParameter('channable', $filterValue);
            }
        }
    }

    /**
     * @param array $params
     *
     * @throws PrestaShopDatabaseException
     */
    public function hookActionOrderGridDataModifier(array $params)
    {
        if (Configuration::get('CHANNABLE_EXTEND_ORDER_VIEW_GRID') == 1) {
            /** @var PrestaShop\PrestaShop\Core\Grid\Data\GridData $data */
            $data = $params['data'];
            $records = $data->getRecords()->all();
            foreach ($records as &$record) {
                $dbResults = Db::getInstance()->query(
                    'SELECT
                        cm.`value_in_post` as `message` FROM `' . _DB_PREFIX_ . 'channable_orders_additional_data` cm
                     WHERE cm.id_order = \'' . (int) $record['id_order'] . '\'
                       AND cm.`field_in_post` = \'marketplace_order_id\'
                    '
                );
                if ($dbResults) {
                    foreach ($dbResults as $dbResult) {
                        if (isset($channable_comment) && $channable_comment != '') {
                            $channable_comment = $channable_comment . "\n" . $dbResult['message'];
                        } else {
                            $channable_comment = $dbResult['message'];
                        }
                    }
                }
                if (isset($channable_comment)) {
                    $record['channable_comment'] = $channable_comment;
                }
                unset($channable_comment);
            }
            $params['data'] = new PrestaShop\PrestaShop\Core\Grid\Data\GridData(
                new PrestaShop\PrestaShop\Core\Grid\Record\RecordCollection($records),
                $data->getRecordsTotal(),
                $data->getQuery()
            );
        }
    }

    /**
     * @return array[]|mixed
     */
    public static function getCustomerGroupAssignments()
    {
        $data = Configuration::get('CHANNABLE_CUSTOMER_GROUP_ASSIGNMENTS');
        $json = json_decode($data, true);
        $struct = [
            's' => '',
            'g' => 0,
        ];
        if ($json == null) {
            $json = [
                0 => $struct,
                1 => $struct,
                2 => $struct,
                3 => $struct,
                4 => $struct,
                5 => $struct,
            ];
        }

        return $json;
    }

    /**
     * @return array[]|mixed
     */
    public static function getMarketplaceAssignments()
    {
        $data = Configuration::get('CHANNABLE_MARKETPLACE_ASSIGNMENTS');
        $json = json_decode($data, true);
        $struct = [
            's' => '',
            'g' => 0,
        ];
        if ($json == null) {
            $json = [
                0 => $struct,
                1 => $struct,
                2 => $struct,
                3 => $struct,
                4 => $struct,
                5 => $struct,
            ];
        }

        return $json;
    }

    public static function getTaxCountryAssignments()
    {
        $data = Configuration::get('CHANNABLE_TAXCOUNTRY_ASSIGNMENTS');
        $json = json_decode($data, true);
        $struct = [
            'country_id' => '',
            'tax_rate' => '',
        ];
        if ($json == null) {
            for ($x = 0; $x < 30; ++$x) {
                $json[] = $struct;
            }
        } else {
            if (sizeof($json) < 30) {
                $neededFields = (30 - sizeof($json));
                for ($x = 0; $x < $neededFields; ++$x) {
                    $json[] = $struct;
                }
            }
        }

        return $json;
    }

    public static function getCarrierAssignments()
    {
        $carrierAssignments = ChannableCarrier::getAllAssignements();
        for ($x = 1; $x < 6; ++$x) {
            if (!is_array($carrierAssignments)) {
                $carrierAssignments = [];
            }
            $carrierAssignments[] = ['id' => ($x * -1),
                'entity_type' => '',
                'id_entity' => '',
                'id_carrier' => '',
            ];
        }
        return $carrierAssignments;
    }

    /**
     * @return bool
     */
    public static function useCache()
    {
        return Configuration::get('CHANNABLE_USE_FEED_CACHE') == '1';
    }

    /**
     * Get a simple list of categories with id_category, name and id_parent infos
     * It also takes into account the root category of the current shop.
     *
     * @param int $idLang Language ID
     *
     * @return array|false|mysqli_result|PDOStatement|resource|null
     */
    public static function getSimpleCategoriesWithParentInfos($idLang)
    {
        $context = Context::getContext();
        if (count(Category::getCategoriesWithoutParent()) > 1
            && Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE')
            && count(Shop::getShops(true, null, true)) !== 1) {
            $idCategoryRoot = (int) Configuration::get('PS_ROOT_CATEGORY');
        } elseif (!$context->shop->id) {
            $idCategoryRoot = (new Shop(Configuration::get('PS_SHOP_DEFAULT')))->id_category;
        } else {
            $idCategoryRoot = $context->shop->id_category;
        }

        $rootTreeInfo = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
            'SELECT c.`nleft`, c.`nright` FROM `' . _DB_PREFIX_ . 'category` c ' .
            'WHERE c.`id_category` = ' . (int) $idCategoryRoot
        );

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
		SELECT c.`id_category`, cl.`name`, c.id_parent
		FROM `' . _DB_PREFIX_ . 'category` c
		LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl
		ON (c.`id_category` = cl.`id_category`' . Shop::addSqlRestrictionOnLang('cl') . ')
		' . Shop::addSqlAssociation('category', 'c') . '
		WHERE cl.`id_lang` = ' . (int) $idLang . '
        AND c.`nleft` >= ' . (int) $rootTreeInfo['nleft'] . '
        AND c.`nright` <= ' . (int) $rootTreeInfo['nright'] . '
		GROUP BY c.id_category
		ORDER BY c.`id_category`, category_shop.`position`');
    }
}
