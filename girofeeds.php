<?php
/**
 * Girofeeds - Feed management module for PrestaShop
 * Based on the Channable addon by patworx multimedia GmbH (2007-2025, patworx.de)
 *
 *  @author    Moviendote <hello@girofeeds.com>
 *  @copyright 2025-2026 Moviendote
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Girofeeds extends Module
{
    protected $config_form = false;
    protected $this_file = __FILE__;
    protected static $sent_update_ids = [];
    protected static $hasWebhooks = 0;

    /**
     * Girofeeds constructor.
     */
    public function __construct()
    {
        $this->name = 'girofeeds';
        $this->tab = 'market_place';
        $this->version = '3.3.21';
        $this->author = 'Moviendote';
        $this->need_instance = 1;
        $this->module_key = 'c083cf4a313f7b7fdf8bc505dc60c3b6';

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Girofeeds');
        $this->description = $this->l('Girofeeds feed management module for PrestaShop');

        $this->confirmUninstall = $this->l('Are you sure to uninstall this module?');

        $this->ps_versions_compliancy = ['min' => '1.5', 'max' => _PS_VERSION_];

        require_once dirname(__FILE__) . '/classes/GirofeedsCache.php';
        require_once dirname(__FILE__) . '/classes/GirofeedsLogger.php';
        require_once dirname(__FILE__) . '/classes/GirofeedsProductsQueue.php';
        require_once dirname(__FILE__) . '/classes/GirofeedsWebhook.php';
        require_once dirname(__FILE__) . '/classes/GirofeedsFeedfield.php';
        require_once dirname(__FILE__) . '/classes/GirofeedsProduct.php';
        require_once dirname(__FILE__) . '/classes/GirofeedsStockUpdate.php';
    }

    /**
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function install()
    {
        $this->enableApi();
        Configuration::updateValue('GIROFEEDS_SQL_OPTIMIZATION_MODE', 1);
        Configuration::updateValue('GIROFEEDS_MULTIQUERY_MODE', 1);
        Configuration::updateValue('GIROFEEDS_DEFAULT_PAGE_SIZE', 100);
        Configuration::updateValue('GIROFEEDS_LOGLEVEL', 0);
        Configuration::updateValue('GIROFEEDS_DO_CRON_FROM_BACKEND', 1);
        Configuration::updateValue('GIROFEEDS_CRON_BACKEND_TIMEDIFF_MIN', 5);
        Configuration::updateValue('GIROFEEDS_USE_FEED_CACHE', 0);
        Configuration::updateValue('GIROFEEDS_DISABLE_VARIANTS', 0);
        Configuration::updateValue('GIROFEEDS_SHOP_STOCK_SYNC', 0);
        Configuration::updateValue('GIROFEEDS_USE_PHONE_FOR_MOBILE', 0);
        Configuration::updateValue('GIROFEEDS_ENABLE_ORDERS_COUNT', 0);

        Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'girofeeds_webhooks` (
    `id_girofeeds_webhook` int(11) NOT NULL AUTO_INCREMENT,
	`active` int(11) NOT NULL,
	`action` VARCHAR(255) NOT NULL,
	`address` VARCHAR(255) NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_girofeeds_webhook`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;');

        Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'girofeeds_feedfields` (
    `id_girofeeds_feedfields` int(11) NOT NULL AUTO_INCREMENT,
    `tablename` VARCHAR(255) NOT NULL,
	`field_in_db` VARCHAR(255) NOT NULL,
	`field_in_feed` VARCHAR(255) NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_girofeeds_feedfields`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;');

        Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'girofeeds_stock_update` (
    `id_girofeeds_stock_update` int(11) NOT NULL AUTO_INCREMENT,
    `id_product` int(11) NOT NULL,
    `id_product_attribute` int(11) NOT NULL,
    `working` int(11) NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_girofeeds_stock_update`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;');

        Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'girofeeds_cache` (
    `id_girofeeds_cache` int(11) NOT NULL AUTO_INCREMENT,
    `cache_key` VARCHAR(255) NOT NULL,
    `cache_value` MEDIUMTEXT NOT NULL,
    `id_lang` INT(11) NOT NULL,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_girofeeds_cache`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;');

        Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'girofeeds_cache` ADD INDEX (`cache_key`)');
        Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'girofeeds_cache` ADD INDEX (`id_lang`)');

        Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'girofeeds_products_queue` (
    `id_girofeeds_products_queue` int(11) NOT NULL AUTO_INCREMENT,
    `id_product` int(11) NOT NULL,
    `running` int(2) DEFAULT 0,
    `date_add` DATETIME,
    PRIMARY KEY  (`id_girofeeds_products_queue`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;');

        Db::getInstance()->execute('INSERT INTO  `' . _DB_PREFIX_ . 'girofeeds_products_queue`
        (id_product, running, date_add)
        SELECT id_product, 0, NOW() FROM `' . _DB_PREFIX_ . 'product`
    ');

        return parent::install()
            && $this->registerHook('actionUpdateQuantity')
            && $this->registerHook('actionProductUpdate')
            && $this->registerHook('actionProductAdd')
            && $this->registerHook('actionProductAttributeUpdate')
            && $this->registerHook('displayBackOfficeHeader');
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        Configuration::deleteByName('GIROFEEDS_FEEDMODE_ALTERNATIVE');
        Configuration::deleteByName('GIROFEEDS_SQL_OPTIMIZATION_MODE');
        Configuration::deleteByName('GIROFEEDS_FEEDMODE_SKIP_SHIPPING');
        Configuration::deleteByName('GIROFEEDS_MULTIQUERY_MODE');
        Configuration::deleteByName('GIROFEEDS_DEFAULT_PAGE_SIZE');
        Configuration::deleteByName('GIROFEEDS_LOGLEVEL');
        Configuration::deleteByName('GIROFEEDS_DO_CRON_FROM_BACKEND');
        Configuration::deleteByName('GIROFEEDS_CRON_BACKEND_TIMEDIFF_MIN');
        Configuration::deleteByName('GIROFEEDS_USE_FEED_CACHE');
        Configuration::deleteByName('GIROFEEDS_DISABLE_VARIANTS');
        Configuration::deleteByName('GIROFEEDS_SHOP_STOCK_SYNC');
        Configuration::deleteByName('GIROFEEDS_USE_PHONE_FOR_MOBILE');
        Configuration::deleteByName('GIROFEEDS_ENABLE_ORDERS_COUNT');

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
        if (((bool) Tools::isSubmit('submitGirofeedsModule')) == true) {
            $this->postProcess();
        }
        $this->context->smarty->assign('module_dir', $this->_path);

        if (Tools::getValue('submitGirofeedsStockSettingsModule') == '1') {
            if (Tools::getValue('send_product_stock_interval') != '') {
                Configuration::updateValue('GIROFEEDS_CRON_BACKEND_TIMEDIFF_MIN', (int) Tools::getValue('send_product_stock_interval'));
            }
            if (Tools::getValue('enable_shop_stock_sync') != '') {
                Configuration::updateValue('GIROFEEDS_SHOP_STOCK_SYNC', (int) Tools::getValue('enable_shop_stock_sync'));
            }

            $this->context->smarty->assign('success_message', $this->l('Settings updated'));
        }
        if (Tools::getValue('submitGirofeedsAssignmentModule') == '1') {
            GirofeedsFeedfield::removeAllFeedfields();
            if (Tools::getValue('assigned_fields')) {
                foreach (Tools::getValue('assigned_fields') as $afKey => $data) {
                    $assignedField = new GirofeedsFeedfield();
                    $assignedField->tablename = $data['tablename'];
                    $assignedField->field_in_db = $data['field_in_db'];
                    $assignedField->field_in_feed = $data['field_in_feed'];
                    $assignedField->save();
                }
            }
            $this->context->smarty->assign('success_message', $this->l('Assigned fields in feed updated'));
        }

        $webservice = new WebserviceKey((int) Configuration::get('GIROFEEDS_API_ID'));

        $this->context->smarty->assign('feed_url', $this->context->link->getModuleLink('girofeeds', 'feed', ['key' => $webservice->key, 'limit' => '0,100']));
        $this->context->smarty->assign('auto_connect_feed_url', $this->context->link->getModuleLink('girofeeds', 'feed'));
        $this->context->smarty->assign('webhook_url', $this->context->link->getModuleLink('girofeeds', 'webhooks'));
        $this->context->smarty->assign('product_api_url', $this->context->link->getModuleLink('girofeeds', 'product', ['key' => $webservice->key, 'id_product' => 'XX_PRODUCT_ID_XX']));
        $this->context->smarty->assign('product_cache_cron_url', $this->context->link->getModuleLink('girofeeds', 'cron', ['buildProductsJson' => '1']));
        $this->context->smarty->assign('girofeeds_key', $webservice->key);
        $this->context->smarty->assign('lang_id', $this->context->language->id);
        $this->context->smarty->assign('form_url', $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'));
        $this->context->smarty->assign('feedfields_available', GirofeedsFeedfield::getAvailableFieldsFiltered());
        $this->context->smarty->assign('feedfields_assigned', GirofeedsFeedfield::getAllFeedfields());

        $date_last_modification = filemtime($this->local_path . 'logo.png');
        $key_theorical = Tools::substr('GIROFEEDS' . md5((string) $date_last_modification), 0, 32);
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
        $helper->submit_action = 'submitGirofeedsModule';
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
                        'name' => 'GIROFEEDS_MULTIQUERY_MODE',
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
                        'name' => 'GIROFEEDS_FEEDMODE_ALTERNATIVE',
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
                        'name' => 'GIROFEEDS_SQL_OPTIMIZATION_MODE',
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
                        'name' => 'GIROFEEDS_DISABLE_OUT_OF_STOCK',
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
                        'name' => 'GIROFEEDS_DISABLE_INACTIVE',
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
                        'name' => 'GIROFEEDS_FEEDMODE_SKIP_SHIPPING',
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
                        'name' => 'GIROFEEDS_DISABLE_VARIANTS',
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
                        'name' => 'GIROFEEDS_CUSTOMER_ID',
                        'label' => $this->l('Default Customer-ID'),
                        'col' => 3,
                    ],
                    [
                        'type' => 'text',
                        'name' => 'GIROFEEDS_DEFAULT_PAGE_SIZE',
                        'label' => $this->l('Default page size'),
                        'col' => 3,
                    ],
                    [
                        'type' => 'switch',
                        'desc' => $this->l('If inactive, all feed data will be created on the fly.'),
                        'name' => 'GIROFEEDS_USE_FEED_CACHE',
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
                    [
                        'type' => 'switch',
                        'desc' => $this->l('Include order count fields (orders_1d, orders_7d, orders_30d, orders_90d, orders_365d) in the product feed.'),
                        'name' => 'GIROFEEDS_ENABLE_ORDERS_COUNT',
                        'label' => $this->l('Enable order count in feed'),
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'orders_count_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'orders_count_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'select',
                        'desc' => $this->l('Select one or more order statuses to count orders per product in feed export (orders_1d, orders_7d, orders_30d, orders_90d, orders_365d).'),
                        'name' => 'GIROFEEDS_ORDERS_COUNT_STATUS[]',
                        'label' => $this->l('Order status for counting orders'),
                        'multiple' => true,
                        'class' => 'chosen',
                        'options' => [
                            'query' => $this->getOrderStatusesForSelect(),
                            'id' => 'id_order_state',
                            'name' => 'name',
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
            'GIROFEEDS_FEEDMODE_ALTERNATIVE' => Tools::getValue('GIROFEEDS_FEEDMODE_ALTERNATIVE', Configuration::get('GIROFEEDS_FEEDMODE_ALTERNATIVE') == '1' ? 1 : 0),
            'GIROFEEDS_MULTIQUERY_MODE' => Tools::getValue('GIROFEEDS_MULTIQUERY_MODE', Configuration::get('GIROFEEDS_MULTIQUERY_MODE') == '1' ? 1 : 0),
            'GIROFEEDS_SQL_OPTIMIZATION_MODE' => Tools::getValue('GIROFEEDS_SQL_OPTIMIZATION_MODE', Configuration::get('GIROFEEDS_SQL_OPTIMIZATION_MODE') == '1' ? 1 : 0),
            'GIROFEEDS_DISABLE_OUT_OF_STOCK' => Tools::getValue('GIROFEEDS_DISABLE_OUT_OF_STOCK', Configuration::get('GIROFEEDS_DISABLE_OUT_OF_STOCK') == '1' ? 1 : 0),
            'GIROFEEDS_DISABLE_INACTIVE' => Tools::getValue('GIROFEEDS_DISABLE_INACTIVE', Configuration::get('GIROFEEDS_DISABLE_INACTIVE') == '1' ? 1 : 0),
            'GIROFEEDS_FEEDMODE_SKIP_SHIPPING' => Tools::getValue('GIROFEEDS_FEEDMODE_SKIP_SHIPPING', Configuration::get('GIROFEEDS_FEEDMODE_SKIP_SHIPPING') == '1' ? 1 : 0),
            'GIROFEEDS_DISABLE_VARIANTS' => Tools::getValue('GIROFEEDS_DISABLE_VARIANTS', Configuration::get('GIROFEEDS_DISABLE_VARIANTS') == '1' ? 1 : 0),
            'GIROFEEDS_CUSTOMER_ID' => Tools::getValue('GIROFEEDS_CUSTOMER_ID', Configuration::get('GIROFEEDS_CUSTOMER_ID')),
            'GIROFEEDS_DEFAULT_PAGE_SIZE' => Tools::getValue('GIROFEEDS_DEFAULT_PAGE_SIZE', Configuration::get('GIROFEEDS_DEFAULT_PAGE_SIZE')),
            'GIROFEEDS_USE_FEED_CACHE' => Tools::getValue('GIROFEEDS_USE_FEED_CACHE', Configuration::get('GIROFEEDS_USE_FEED_CACHE') == '1' ? 1 : 0),
            'GIROFEEDS_ENABLE_ORDERS_COUNT' => Tools::getValue('GIROFEEDS_ENABLE_ORDERS_COUNT', Configuration::get('GIROFEEDS_ENABLE_ORDERS_COUNT') == '1' ? 1 : 0),
            'GIROFEEDS_ORDERS_COUNT_STATUS[]' => $this->getOrdersCountStatusFormValue(),
        ];
    }

    protected function getOrderStatusesForSelect()
    {
        return OrderState::getOrderStates((int) Configuration::get('PS_LANG_DEFAULT'));
    }

    /**
     * Returns the selected order statuses as an array of integer IDs,
     * normalizing both submitted form values (array) and stored config (CSV string).
     *
     * @return array<int>
     */
    protected function getOrdersCountStatusFormValue()
    {
        $submitted = Tools::getValue('GIROFEEDS_ORDERS_COUNT_STATUS');
        if ($submitted !== false && $submitted !== null && $submitted !== '') {
            if (!is_array($submitted)) {
                $submitted = explode(',', (string) $submitted);
            }
            return array_values(array_filter(array_map('intval', $submitted)));
        }

        $stored = Configuration::get('GIROFEEDS_ORDERS_COUNT_STATUS');
        if ($stored === false || $stored === null || $stored === '') {
            return [];
        }
        return array_values(array_filter(array_map('intval', explode(',', (string) $stored))));
    }

    /**
     * update config routine
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            if ($key === 'GIROFEEDS_ORDERS_COUNT_STATUS[]') {
                $selected = $this->getOrdersCountStatusFormValue();
                Configuration::updateValue('GIROFEEDS_ORDERS_COUNT_STATUS', implode(',', $selected));
                continue;
            }
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
     * Backoffice header hook
     */
    public function hookDisplaybackOfficeHeader()
    {
        $doCron = false;
        if (Configuration::get('GIROFEEDS_DO_CRON_FROM_BACKEND') == '1') {
            $cronRun = Configuration::get('GIROFEEDS_LAST_CRONRUN');
            if ($cronRun == '') {
                $doCron = true;
            } else {
                $current_date = new DateTime('now');
                $cron_date = new DateTime($cronRun);
                $diff = $current_date->diff($cron_date);
                if ($diff->format('%i') >= (int) Configuration::get('GIROFEEDS_CRON_BACKEND_TIMEDIFF_MIN')) {
                    $doCron = true;
                }
            }
        }
        if ($doCron) {
            $this->sendProductUpdate();
            Configuration::updateValue('GIROFEEDS_LAST_CRONRUN', date('Y-m-d H:i:s'));
        }
        if (Tools::getValue('module_name') == $this->name
            || Tools::getValue('configure') == $this->name) {
            if (version_compare(_PS_VERSION_, '9.0.0', '<')) {
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
            GirofeedsProductsQueue::addToQueueIfNotExists((int) $params['id_product']);
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
            GirofeedsProductsQueue::addToQueueIfNotExists((int) $params['id_product']);
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
            GirofeedsProductsQueue::addToQueueIfNotExists((int) $params['id_product']);
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
            GirofeedsProductsQueue::addToQueueIfNotExists((int) $params['id_product']);
        }
        if (self::$hasWebhooks == 1) {
            $this->storeProductUpdate($params);
        } else {
            if (self::$hasWebhooks == 0) {
                $webHookData = GirofeedsWebhook::getAllWebhooks();
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
            $check = GirofeedsStockUpdate::existsByIdProduct((int) $combination->id_product, (int) $params['id_product_attribute']);
            $id_product = (int) $combination->id_product;
        } else {
            $check = GirofeedsStockUpdate::existsByIdProduct((int) $params['id_product']);
            $id_product = (int) $params['id_product'];
        }
        if (!$check) {
            $stockUpdate = new GirofeedsStockUpdate();
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
        $webHookData = GirofeedsWebhook::getAllWebhooks();
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
            $stockUpdates = GirofeedsStockUpdate::getQualifiedUpdates();
            if (sizeof($stockUpdates) > 0) {
                foreach ($stockUpdates as $stockUpdate) {
                    $stockUpdateObject = new GirofeedsStockUpdate($stockUpdate['id_girofeeds_stock_update']);
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
                    GirofeedsLogger::getInstance()->addLog(
                        'Sending product update',
                        3,
                        null,
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
                    $jsonData['title'] = $product->name[$this->context->language->id];
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
        $webserviceKey->key = Tools::substr('GIROFEEDS' . md5(_COOKIE_KEY_ . time()), 0, 32);
        $webserviceKey->description = $this->l('Webservice API Key for girofeeds created by plugin');
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
        Configuration::updateValue('GIROFEEDS_API_ID', (int) $webserviceKey->id);

        return true;
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
    public static function useCache()
    {
        return Configuration::get('GIROFEEDS_USE_FEED_CACHE') == '1';
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
            $idCategoryRoot = (new Shop((int) Configuration::get('PS_SHOP_DEFAULT')))->id_category;
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
