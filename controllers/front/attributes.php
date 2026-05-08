<?php
/**
 * Girofeeds - Feed management module for PrestaShop
 * Attribute definitions endpoint - returns comprehensive metadata about all product fields
 *
 *  @author    Moviendote <hello@girofeeds.com>
 *  @copyright 2025-2026 Moviendote
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class GirofeedsAttributesModuleFrontController extends ModuleFrontController
{
    protected static $numericFields = [
        'price', 'ecotax', 'weight', 'height', 'width', 'depth',
        'wholesale_price', 'unit_price_ratio', 'additional_shipping_cost',
        'unit_price_impact', 'id_supplier', 'id_manufacturer',
        'id_tax_rules_group', 'id_category_default', 'minimal_quantity',
        'uploadable_files', 'text_fields', 'id_type_redirected',
        'low_stock_threshold', 'out_of_stock', 'quantity_discount',
        'customizable', 'pack_stock_type', 'quantity', 'stock'
    ];

    protected static $booleanFields = [
        'active', 'on_sale', 'online_only', 'available_for_order',
        'show_price', 'indexed', 'cache_is_pack', 'cache_has_attachments',
        'is_virtual', 'show_condition', 'low_stock_alert', 'default_on'
    ];

    protected static $dateFields = ['available_date'];

    protected static $htmlFields = ['description', 'description_short'];

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

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            header('HTTP/1.1 405 Method Not Allowed');
            header('Allow: GET');
            exit('Method not allowed');
        }

        try {
            $result = $this->getAttributeDefinitions();
            header('Content-Type: application/json');
            echo json_encode($result);
        } catch (Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }

    private function getAttributeDefinitions()
    {
        $id_lang = (int) $this->context->language->id;
        $id_shop = (int) $this->context->shop->id;

        return [
            'status' => 'success',
            'fetchedAt' => date('c'),
            'storeInfo' => [
                'defaultLanguageId' => $id_lang,
                'shopId' => $id_shop,
                'prestashopVersion' => _PS_VERSION_
            ],
            'standardFields' => $this->getStandardFields(),
            'features' => $this->getFeatures($id_lang),
            'attributeGroups' => $this->getAttributeGroups($id_lang),
            'manufacturers' => $this->getManufacturers(),
            'suppliers' => $this->getSuppliers(),
            'categories' => $this->getCategoryTree($id_lang, $id_shop),
            'taxRulesGroups' => $this->getTaxRulesGroups()
        ];
    }

    private function getFieldType($fieldName)
    {
        if (in_array($fieldName, self::$numericFields)) {
            return 'number';
        }
        if (in_array($fieldName, self::$booleanFields)) {
            return 'boolean';
        }
        if (in_array($fieldName, self::$dateFields)) {
            return 'date';
        }
        if (in_array($fieldName, self::$htmlFields)) {
            return 'html';
        }
        return 'string';
    }

    private function buildField($key, $overrides = [])
    {
        $defaults = [
            'key' => $key,
            'type' => $this->getFieldType($key),
            'table' => 'product',
            'isMultilingual' => false,
            'isRelation' => false,
            'relatedEntity' => null,
            'referenceTo' => null,
            'isArray' => false,
            'possibleValues' => null,
            'isRequired' => false,
            'isReadOnly' => false,
            'category' => 'basic',
            'description' => ''
        ];
        return array_merge($defaults, $overrides);
    }

    private function getStandardFields()
    {
        $fields = [];

        // --- Feed-generated fields (computed by feed, not direct DB columns) ---
        $fields[] = $this->buildField('id', [
            'type' => 'string',
            'category' => 'identifiers',
            'isReadOnly' => true,
            'description' => 'Product ID (or id_product_attribute compound) as returned by the feed'
        ]);
        $fields[] = $this->buildField('parent_id', [
            'type' => 'number',
            'category' => 'identifiers',
            'isReadOnly' => true,
            'description' => 'Parent product ID (id_product)'
        ]);
        $fields[] = $this->buildField('gtin', [
            'category' => 'identifiers',
            'isReadOnly' => true,
            'description' => 'GTIN code (computed from EAN13 or reference)'
        ]);
        $fields[] = $this->buildField('title', [
            'table' => 'product_lang',
            'isMultilingual' => true,
            'category' => 'content',
            'description' => 'Product title (alias of name from feed)'
        ]);
        $fields[] = $this->buildField('short_description', [
            'table' => 'virtual',
            'category' => 'content',
            'isReadOnly' => true,
            'description' => 'Short product description (plain text, stripped from HTML)'
        ]);
        $fields[] = $this->buildField('link', [
            'table' => 'virtual',
            'category' => 'content',
            'isReadOnly' => true,
            'description' => 'Product URL in the store'
        ]);
        $fields[] = $this->buildField('product_supplier_reference', [
            'table' => 'virtual',
            'category' => 'identifiers',
            'isReadOnly' => true,
            'description' => 'Supplier reference for this product'
        ]);
        $fields[] = $this->buildField('image_link', [
            'table' => 'virtual',
            'category' => 'media',
            'isReadOnly' => true,
            'description' => 'Main product image URL'
        ]);
        $fields[] = $this->buildField('additional_images', [
            'type' => 'array',
            'table' => 'virtual',
            'isArray' => true,
            'category' => 'media',
            'isReadOnly' => true,
            'description' => 'Additional product image URLs'
        ]);
        $fields[] = $this->buildField('price_incl_vat', [
            'type' => 'number',
            'table' => 'virtual',
            'category' => 'pricing',
            'isReadOnly' => true,
            'description' => 'Product price including VAT'
        ]);
        $fields[] = $this->buildField('sale_price', [
            'type' => 'number',
            'table' => 'virtual',
            'category' => 'pricing',
            'isReadOnly' => true,
            'description' => 'Sale price (tax excluded)'
        ]);
        $fields[] = $this->buildField('sale_price_incl_vat', [
            'type' => 'number',
            'table' => 'virtual',
            'category' => 'pricing',
            'isReadOnly' => true,
            'description' => 'Sale price including VAT'
        ]);
        $fields[] = $this->buildField('tax_rate', [
            'type' => 'number',
            'table' => 'virtual',
            'category' => 'pricing',
            'isReadOnly' => true,
            'description' => 'Tax rate percentage'
        ]);
        $fields[] = $this->buildField('currency', [
            'table' => 'virtual',
            'category' => 'pricing',
            'isReadOnly' => true,
            'description' => 'Product currency code'
        ]);
        $fields[] = $this->buildField('visible', [
            'type' => 'boolean',
            'table' => 'virtual',
            'category' => 'basic',
            'isReadOnly' => true,
            'description' => 'Whether the product is visible (alias of active from feed)'
        ]);
        $fields[] = $this->buildField('package_weight', [
            'type' => 'number',
            'table' => 'virtual',
            'category' => 'shipping',
            'isReadOnly' => true,
            'description' => 'Product weight (alias of weight from feed)'
        ]);
        $fields[] = $this->buildField('package_height', [
            'type' => 'number',
            'table' => 'virtual',
            'category' => 'shipping',
            'isReadOnly' => true,
            'description' => 'Product height (alias of height from feed)'
        ]);
        $fields[] = $this->buildField('package_width', [
            'type' => 'number',
            'table' => 'virtual',
            'category' => 'shipping',
            'isReadOnly' => true,
            'description' => 'Product width (alias of width from feed)'
        ]);
        $fields[] = $this->buildField('package_depth', [
            'type' => 'number',
            'table' => 'virtual',
            'category' => 'shipping',
            'isReadOnly' => true,
            'description' => 'Product depth (alias of depth from feed)'
        ]);
        $fields[] = $this->buildField('shipping', [
            'type' => 'object',
            'table' => 'virtual',
            'category' => 'shipping',
            'isReadOnly' => true,
            'description' => 'Shipping info (country and delivery price)'
        ]);
        $fields[] = $this->buildField('delivery_period', [
            'table' => 'virtual',
            'category' => 'shipping',
            'isReadOnly' => true,
            'description' => 'Estimated delivery time text'
        ]);
        $fields[] = $this->buildField('orders_1d', [
            'type' => 'number',
            'table' => 'virtual',
            'category' => 'analytics',
            'isReadOnly' => true,
            'description' => 'Number of orders in the last 1 day'
        ]);
        $fields[] = $this->buildField('orders_7d', [
            'type' => 'number',
            'table' => 'virtual',
            'category' => 'analytics',
            'isReadOnly' => true,
            'description' => 'Number of orders in the last 7 days'
        ]);
        $fields[] = $this->buildField('orders_30d', [
            'type' => 'number',
            'table' => 'virtual',
            'category' => 'analytics',
            'isReadOnly' => true,
            'description' => 'Number of orders in the last 30 days'
        ]);
        $fields[] = $this->buildField('orders_90d', [
            'type' => 'number',
            'table' => 'virtual',
            'category' => 'analytics',
            'isReadOnly' => true,
            'description' => 'Number of orders in the last 90 days'
        ]);
        $fields[] = $this->buildField('orders_365d', [
            'type' => 'number',
            'table' => 'virtual',
            'category' => 'analytics',
            'isReadOnly' => true,
            'description' => 'Number of orders in the last 365 days'
        ]);
        $fields[] = $this->buildField('attachments', [
            'type' => 'array',
            'table' => 'virtual',
            'isArray' => true,
            'category' => 'media',
            'isReadOnly' => true,
            'description' => 'Product attachments (name, description, link)'
        ]);

        // --- Basic fields ---
        $fields[] = $this->buildField('condition', [
            'category' => 'basic',
            'description' => 'Product condition',
            'possibleValues' => [
                ['value' => 'new'],
                ['value' => 'used'],
                ['value' => 'refurbished']
            ]
        ]);
        $fields[] = $this->buildField('visibility', [
            'category' => 'basic',
            'description' => 'Product visibility in catalog and search',
            'possibleValues' => [
                ['value' => 'both'],
                ['value' => 'catalog'],
                ['value' => 'search'],
                ['value' => 'none']
            ]
        ]);
        $fields[] = $this->buildField('active', [
            'type' => 'boolean',
            'category' => 'basic',
            'description' => 'Whether the product is active/enabled'
        ]);

        // --- Identifiers ---
        $fields[] = $this->buildField('reference', [
            'category' => 'identifiers',
            'description' => 'Product reference (SKU)'
        ]);
        $fields[] = $this->buildField('ean13', [
            'category' => 'identifiers',
            'description' => 'EAN-13 barcode'
        ]);
        $fields[] = $this->buildField('upc', [
            'category' => 'identifiers',
            'description' => 'UPC barcode'
        ]);
        $fields[] = $this->buildField('mpn', [
            'category' => 'identifiers',
            'description' => 'Manufacturer Part Number'
        ]);
        $fields[] = $this->buildField('isbn', [
            'category' => 'identifiers',
            'description' => 'International Standard Book Number'
        ]);

        // --- Pricing ---
        $fields[] = $this->buildField('price', [
            'type' => 'number',
            'category' => 'pricing',
            'description' => 'Product price (tax excluded)'
        ]);
        $fields[] = $this->buildField('wholesale_price', [
            'type' => 'number',
            'category' => 'pricing',
            'description' => 'Wholesale/cost price'
        ]);
        $fields[] = $this->buildField('ecotax', [
            'type' => 'number',
            'category' => 'pricing',
            'description' => 'Eco-tax amount'
        ]);
        $fields[] = $this->buildField('on_sale', [
            'type' => 'boolean',
            'category' => 'pricing',
            'description' => 'Whether the product is on sale'
        ]);
        $fields[] = $this->buildField('unit_price_ratio', [
            'type' => 'number',
            'category' => 'pricing',
            'description' => 'Unit price ratio for price per unit display'
        ]);
        $fields[] = $this->buildField('unity', [
            'category' => 'pricing',
            'description' => 'Unit of measure for price per unit (e.g., kg, L)'
        ]);

        // --- Shipping ---
        $fields[] = $this->buildField('weight', [
            'type' => 'number',
            'category' => 'shipping',
            'description' => 'Product weight'
        ]);
        $fields[] = $this->buildField('height', [
            'type' => 'number',
            'category' => 'shipping',
            'description' => 'Product height'
        ]);
        $fields[] = $this->buildField('width', [
            'type' => 'number',
            'category' => 'shipping',
            'description' => 'Product width'
        ]);
        $fields[] = $this->buildField('depth', [
            'type' => 'number',
            'category' => 'shipping',
            'description' => 'Product depth'
        ]);
        $fields[] = $this->buildField('additional_shipping_cost', [
            'type' => 'number',
            'category' => 'shipping',
            'description' => 'Additional shipping cost for this product'
        ]);
        $fields[] = $this->buildField('location', [
            'category' => 'shipping',
            'description' => 'Product warehouse location'
        ]);

        // --- Inventory ---
        $fields[] = $this->buildField('stock', [
            'type' => 'number',
            'category' => 'inventory',
            'description' => 'Available stock quantity'
        ]);
        $fields[] = $this->buildField('minimal_quantity', [
            'type' => 'number',
            'category' => 'inventory',
            'description' => 'Minimum purchase quantity'
        ]);
        $fields[] = $this->buildField('available_for_order', [
            'type' => 'boolean',
            'category' => 'inventory',
            'description' => 'Whether the product can be ordered'
        ]);
        $fields[] = $this->buildField('show_price', [
            'type' => 'boolean',
            'category' => 'inventory',
            'description' => 'Whether the price is displayed'
        ]);
        $fields[] = $this->buildField('out_of_stock', [
            'type' => 'number',
            'category' => 'inventory',
            'description' => 'Out of stock behavior',
            'possibleValues' => [
                ['value' => '0', 'label' => 'Deny orders'],
                ['value' => '1', 'label' => 'Allow orders'],
                ['value' => '2', 'label' => 'Use global setting']
            ]
        ]);
        $fields[] = $this->buildField('low_stock_threshold', [
            'type' => 'number',
            'category' => 'inventory',
            'description' => 'Low stock alert threshold'
        ]);
        $fields[] = $this->buildField('low_stock_alert', [
            'type' => 'boolean',
            'category' => 'inventory',
            'description' => 'Whether to receive low stock alerts'
        ]);
        $fields[] = $this->buildField('state', [
            'type' => 'number',
            'category' => 'inventory',
            'description' => 'Product state (0=temporary, 1=saved)'
        ]);
        $fields[] = $this->buildField('pack_stock_type', [
            'type' => 'number',
            'category' => 'inventory',
            'description' => 'Pack stock management type',
            'possibleValues' => [
                ['value' => '0', 'label' => 'Decrement pack only'],
                ['value' => '1', 'label' => 'Decrement products only'],
                ['value' => '2', 'label' => 'Decrement both'],
                ['value' => '3', 'label' => 'Use global setting']
            ]
        ]);

        // --- Content (multilingual) ---
        $fields[] = $this->buildField('name', [
            'table' => 'product_lang',
            'isMultilingual' => true,
            'isRequired' => true,
            'category' => 'content',
            'description' => 'Product name'
        ]);
        $fields[] = $this->buildField('description', [
            'type' => 'html',
            'table' => 'product_lang',
            'isMultilingual' => true,
            'category' => 'content',
            'description' => 'Full product description (HTML allowed)'
        ]);
        $fields[] = $this->buildField('description_short', [
            'type' => 'html',
            'table' => 'product_lang',
            'isMultilingual' => true,
            'category' => 'content',
            'description' => 'Short product description / summary (HTML allowed)'
        ]);
        $fields[] = $this->buildField('link_rewrite', [
            'table' => 'product_lang',
            'isMultilingual' => true,
            'category' => 'content',
            'description' => 'URL-friendly product slug'
        ]);
        $fields[] = $this->buildField('available_now', [
            'table' => 'product_lang',
            'isMultilingual' => true,
            'category' => 'content',
            'description' => 'Label displayed when product is in stock'
        ]);
        $fields[] = $this->buildField('available_later', [
            'table' => 'product_lang',
            'isMultilingual' => true,
            'category' => 'content',
            'description' => 'Label displayed when product is out of stock but orderable'
        ]);
        $fields[] = $this->buildField('delivery_in_stock', [
            'table' => 'product_lang',
            'isMultilingual' => true,
            'category' => 'content',
            'description' => 'Delivery time text when in stock'
        ]);
        $fields[] = $this->buildField('delivery_out_stock', [
            'table' => 'product_lang',
            'isMultilingual' => true,
            'category' => 'content',
            'description' => 'Delivery time text when out of stock'
        ]);

        // --- SEO (multilingual) ---
        $fields[] = $this->buildField('meta_title', [
            'table' => 'product_lang',
            'isMultilingual' => true,
            'category' => 'seo',
            'description' => 'SEO meta title'
        ]);
        $fields[] = $this->buildField('meta_description', [
            'table' => 'product_lang',
            'isMultilingual' => true,
            'category' => 'seo',
            'description' => 'SEO meta description'
        ]);
        $fields[] = $this->buildField('meta_keywords', [
            'table' => 'product_lang',
            'isMultilingual' => true,
            'category' => 'seo',
            'description' => 'SEO meta keywords (comma-separated)'
        ]);

        // --- Relations ---
        $fields[] = $this->buildField('id_manufacturer', [
            'type' => 'number',
            'isRelation' => true,
            'relatedEntity' => 'manufacturer',
            'category' => 'relations',
            'description' => 'Manufacturer/brand ID'
        ]);
        $fields[] = $this->buildField('id_supplier', [
            'type' => 'number',
            'isRelation' => true,
            'relatedEntity' => 'supplier',
            'category' => 'relations',
            'description' => 'Default supplier ID'
        ]);
        $fields[] = $this->buildField('id_category_default', [
            'type' => 'number',
            'isRelation' => true,
            'relatedEntity' => 'category',
            'category' => 'relations',
            'description' => 'Default category ID'
        ]);
        $fields[] = $this->buildField('id_tax_rules_group', [
            'type' => 'number',
            'isRelation' => true,
            'relatedEntity' => 'tax_rules_group',
            'category' => 'relations',
            'description' => 'Tax rules group ID'
        ]);

        // --- Virtual/special fields (updatable via updateproduct endpoint) ---
        $fields[] = $this->buildField('category', [
            'type' => 'reference',
            'table' => 'virtual',
            'isRelation' => true,
            'relatedEntity' => 'category',
            'referenceTo' => 'categories',
            'category' => 'relations',
            'description' => 'Default category path (e.g. "Home > Clothes > Men"). Reference to categories (by id or path).'
        ]);
        $fields[] = $this->buildField('categories', [
            'table' => 'virtual',
            'type' => 'array',
            'isArray' => true,
            'isRelation' => true,
            'relatedEntity' => 'category',
            'category' => 'relations',
            'description' => 'Array of category names. Replaces all category associations. Auto-creates if not found.'
        ]);
        $fields[] = $this->buildField('brand', [
            'type' => 'reference',
            'table' => 'virtual',
            'isRelation' => true,
            'relatedEntity' => 'manufacturer',
            'referenceTo' => 'manufacturers',
            'category' => 'relations',
            'description' => 'Manufacturer/brand name or ID. Reference to manufacturers. Auto-creates if not found.'
        ]);
        $fields[] = $this->buildField('supplier', [
            'table' => 'virtual',
            'isRelation' => true,
            'relatedEntity' => 'supplier',
            'category' => 'relations',
            'description' => 'Supplier name or ID. Auto-creates if not found.'
        ]);
        $fields[] = $this->buildField('specifications', [
            'table' => 'virtual',
            'type' => 'object',
            'isArray' => false,
            'category' => 'features',
            'description' => 'Product features/characteristics as key-value object: {"Feature Name": "Value"}. Creates new features if not found.'
        ]);
        $fields[] = $this->buildField('features', [
            'table' => 'virtual',
            'type' => 'object',
            'isArray' => false,
            'category' => 'features',
            'description' => 'Alias for specifications. Product features as key-value object.'
        ]);
        $fields[] = $this->buildField('image', [
            'table' => 'virtual',
            'category' => 'media',
            'description' => 'Product image URL. Downloads and sets as main image.'
        ]);
        $fields[] = $this->buildField('image_url', [
            'table' => 'virtual',
            'category' => 'media',
            'description' => 'Alias for image. Product image URL.'
        ]);
        $fields[] = $this->buildField('specific_price', [
            'table' => 'virtual',
            'type' => 'object',
            'category' => 'pricing',
            'description' => 'Specific price rule (discounts, quantity pricing). Replaces all existing specific prices.'
        ]);

        // --- Advanced ---
        $fields[] = $this->buildField('online_only', [
            'type' => 'boolean',
            'category' => 'advanced',
            'description' => 'Whether the product is only available online'
        ]);
        $fields[] = $this->buildField('is_virtual', [
            'type' => 'boolean',
            'category' => 'advanced',
            'description' => 'Whether the product is virtual (no shipping)'
        ]);
        $fields[] = $this->buildField('indexed', [
            'type' => 'boolean',
            'category' => 'advanced',
            'description' => 'Whether the product is indexed for search'
        ]);
        $fields[] = $this->buildField('show_condition', [
            'type' => 'boolean',
            'category' => 'advanced',
            'description' => 'Whether to display the product condition'
        ]);
        $fields[] = $this->buildField('redirect_type', [
            'category' => 'advanced',
            'description' => 'Redirect type when product is inactive',
            'possibleValues' => [
                ['value' => '404', 'label' => 'No redirect (404)'],
                ['value' => '410', 'label' => 'Gone (410)'],
                ['value' => '301-product', 'label' => '301 redirect to product'],
                ['value' => '302-product', 'label' => '302 redirect to product'],
                ['value' => '301-category', 'label' => '301 redirect to category'],
                ['value' => '302-category', 'label' => '302 redirect to category']
            ]
        ]);
        $fields[] = $this->buildField('id_type_redirected', [
            'type' => 'number',
            'category' => 'advanced',
            'description' => 'Target product/category ID for redirect'
        ]);
        $fields[] = $this->buildField('available_date', [
            'type' => 'date',
            'category' => 'advanced',
            'description' => 'Date when the product becomes available'
        ]);
        $fields[] = $this->buildField('quantity_discount', [
            'type' => 'number',
            'category' => 'advanced',
            'description' => 'Whether quantity discounts apply'
        ]);
        $fields[] = $this->buildField('customizable', [
            'type' => 'number',
            'category' => 'advanced',
            'description' => 'Customization level (0=none, 1=file upload, 2=text field)'
        ]);
        $fields[] = $this->buildField('uploadable_files', [
            'type' => 'number',
            'category' => 'advanced',
            'description' => 'Number of uploadable files for customization'
        ]);
        $fields[] = $this->buildField('text_fields', [
            'type' => 'number',
            'category' => 'advanced',
            'description' => 'Number of text fields for customization'
        ]);

        // --- Variant/combination fields ---
        $variantFields = [
            ['key' => 'variant_ean13', 'source' => 'ean13', 'description' => 'Variant EAN-13 barcode'],
            ['key' => 'variant_reference', 'source' => 'reference', 'description' => 'Variant reference (SKU)'],
            ['key' => 'variant_upc', 'source' => 'upc', 'description' => 'Variant UPC barcode'],
            ['key' => 'variant_mpn', 'source' => 'mpn', 'description' => 'Variant Manufacturer Part Number'],
            ['key' => 'variant_isbn', 'source' => 'isbn', 'description' => 'Variant ISBN'],
            ['key' => 'variant_price', 'source' => 'price', 'description' => 'Variant price impact (added to base price)'],
            ['key' => 'variant_ecotax', 'source' => 'ecotax', 'description' => 'Variant eco-tax'],
            ['key' => 'variant_weight', 'source' => 'weight', 'description' => 'Variant weight impact'],
            ['key' => 'variant_minimal_quantity', 'source' => 'minimal_quantity', 'description' => 'Variant minimum purchase quantity'],
            ['key' => 'variant_available_date', 'source' => 'available_date', 'description' => 'Variant availability date'],
            ['key' => 'variant_wholesale_price', 'source' => 'wholesale_price', 'description' => 'Variant wholesale price'],
            ['key' => 'variant_unit_price_impact', 'source' => 'unit_price_impact', 'description' => 'Variant unit price impact'],
            ['key' => 'variant_low_stock_threshold', 'source' => 'low_stock_threshold', 'description' => 'Variant low stock threshold'],
            ['key' => 'variant_low_stock_alert', 'source' => 'low_stock_alert', 'description' => 'Variant low stock alert'],
            ['key' => 'variant_quantity', 'source' => 'quantity', 'description' => 'Variant stock quantity'],
            ['key' => 'variant_default_on', 'source' => 'default_on', 'description' => 'Whether this is the default variant'],
        ];

        foreach ($variantFields as $vf) {
            $fields[] = $this->buildField($vf['key'], [
                'type' => $this->getFieldType($vf['source']),
                'table' => 'product_attribute',
                'category' => 'variants',
                'description' => $vf['description']
            ]);
        }

        return $fields;
    }

    private function getFeatures($id_lang)
    {
        $sql = 'SELECT f.id_feature, f.position, fl.name
                FROM `' . _DB_PREFIX_ . 'feature` f
                LEFT JOIN `' . _DB_PREFIX_ . 'feature_lang` fl
                  ON (f.id_feature = fl.id_feature AND fl.id_lang = ' . (int) $id_lang . ')
                ORDER BY f.position ASC';
        $features = Db::getInstance()->executeS($sql);

        if (!$features) {
            return [];
        }

        $result = [];
        foreach ($features as $feature) {
            $valuesSql = 'SELECT fv.id_feature_value, fvl.value
                          FROM `' . _DB_PREFIX_ . 'feature_value` fv
                          LEFT JOIN `' . _DB_PREFIX_ . 'feature_value_lang` fvl
                            ON (fv.id_feature_value = fvl.id_feature_value
                                AND fvl.id_lang = ' . (int) $id_lang . ')
                          WHERE fv.id_feature = ' . (int) $feature['id_feature'] . '
                            AND fv.custom = 0
                          ORDER BY fvl.value ASC';
            $values = Db::getInstance()->executeS($valuesSql);

            $result[] = [
                'id' => (int) $feature['id_feature'],
                'key' => 'feature_' . (int) $feature['id_feature'],
                'name' => $feature['name'],
                'position' => (int) $feature['position'],
                'table' => 'feature',
                'type' => 'string',
                'isMultilingual' => true,
                'isRelation' => false,
                'isArray' => false,
                'isRequired' => false,
                'isReadOnly' => false,
                'category' => 'features',
                'description' => 'Product feature: ' . $feature['name'],
                'possibleValues' => array_map(function ($v) {
                    return [
                        'id' => (int) $v['id_feature_value'],
                        'value' => $v['value']
                    ];
                }, $values ?: [])
            ];
        }
        return $result;
    }

    private function getAttributeGroups($id_lang)
    {
        $sql = 'SELECT ag.id_attribute_group, agl.name, agl.public_name,
                       ag.group_type, ag.is_color_group, ag.position
                FROM `' . _DB_PREFIX_ . 'attribute_group` ag
                LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group_lang` agl
                  ON (ag.id_attribute_group = agl.id_attribute_group
                      AND agl.id_lang = ' . (int) $id_lang . ')
                ORDER BY ag.position ASC';
        $groups = Db::getInstance()->executeS($sql);

        if (!$groups) {
            return [];
        }

        $result = [];
        foreach ($groups as $group) {
            $valuesSql = 'SELECT a.id_attribute, al.name, a.color, a.position
                          FROM `' . _DB_PREFIX_ . 'attribute` a
                          LEFT JOIN `' . _DB_PREFIX_ . 'attribute_lang` al
                            ON (a.id_attribute = al.id_attribute
                                AND al.id_lang = ' . (int) $id_lang . ')
                          WHERE a.id_attribute_group = ' . (int) $group['id_attribute_group'] . '
                          ORDER BY a.position ASC';
            $values = Db::getInstance()->executeS($valuesSql);

            $result[] = [
                'id' => (int) $group['id_attribute_group'],
                'key' => 'attribute_group_' . (int) $group['id_attribute_group'],
                'name' => $group['name'],
                'publicName' => $group['public_name'],
                'groupType' => $group['group_type'],
                'isColorGroup' => (bool) $group['is_color_group'],
                'position' => (int) $group['position'],
                'table' => 'attribute_group',
                'type' => 'string',
                'isMultilingual' => true,
                'category' => 'variants',
                'description' => 'Variant attribute group: ' . $group['public_name'],
                'possibleValues' => array_map(function ($v) {
                    $item = [
                        'id' => (int) $v['id_attribute'],
                        'value' => $v['name'],
                        'position' => (int) $v['position']
                    ];
                    if (!empty($v['color'])) {
                        $item['color'] = $v['color'];
                    }
                    return $item;
                }, $values ?: [])
            ];
        }
        return $result;
    }

    private function getManufacturers()
    {
        $sql = 'SELECT m.id_manufacturer, m.name, m.active
                FROM `' . _DB_PREFIX_ . 'manufacturer` m
                WHERE m.active = 1
                ORDER BY m.name ASC';
        $manufacturers = Db::getInstance()->executeS($sql);

        return array_map(function ($m) {
            return [
                'id' => (int) $m['id_manufacturer'],
                'name' => $m['name'],
                'active' => (bool) $m['active']
            ];
        }, $manufacturers ?: []);
    }

    private function getSuppliers()
    {
        $sql = 'SELECT s.id_supplier, s.name, s.active
                FROM `' . _DB_PREFIX_ . 'supplier` s
                WHERE s.active = 1
                ORDER BY s.name ASC';
        $suppliers = Db::getInstance()->executeS($sql);

        return array_map(function ($s) {
            return [
                'id' => (int) $s['id_supplier'],
                'name' => $s['name'],
                'active' => (bool) $s['active']
            ];
        }, $suppliers ?: []);
    }

    private function getCategoryTree($id_lang, $id_shop)
    {
        $sql = 'SELECT c.id_category, c.id_parent, c.level_depth,
                       c.active, c.position, cl.name, cl.link_rewrite
                FROM `' . _DB_PREFIX_ . 'category` c
                LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl
                  ON (c.id_category = cl.id_category
                      AND cl.id_lang = ' . (int) $id_lang . '
                      AND cl.id_shop = ' . (int) $id_shop . ')
                WHERE c.active = 1
                ORDER BY c.level_depth ASC, c.position ASC';
        $categories = Db::getInstance()->executeS($sql) ?: [];

        $byId = [];
        foreach ($categories as $c) {
            $byId[(int) $c['id_category']] = $c;
        }

        $buildPath = function ($id) use (&$byId, &$buildPath) {
            $segments = [];
            $currentId = (int) $id;
            $guard = 0;
            while ($currentId > 1 && isset($byId[$currentId]) && $guard < 50) {
                $node = $byId[$currentId];
                array_unshift($segments, $node['name']);
                $parentId = (int) $node['id_parent'];
                if ($parentId === $currentId) {
                    break;
                }
                $currentId = $parentId;
                $guard++;
            }
            return implode(' > ', $segments);
        };

        $result = [];
        foreach ($categories as $c) {
            $result[] = [
                'id' => (int) $c['id_category'],
                'parentId' => (int) $c['id_parent'],
                'depth' => (int) $c['level_depth'],
                'active' => (bool) $c['active'],
                'position' => (int) $c['position'],
                'name' => $c['name'],
                'path' => $buildPath((int) $c['id_category']),
                'slug' => $c['link_rewrite']
            ];
        }
        return $result;
    }

    private function getTaxRulesGroups()
    {
        $sql = 'SELECT trg.id_tax_rules_group, trg.name, trg.active
                FROM `' . _DB_PREFIX_ . 'tax_rules_group` trg
                WHERE trg.active = 1 AND trg.deleted = 0
                ORDER BY trg.name ASC';
        $groups = Db::getInstance()->executeS($sql);

        return array_map(function ($g) {
            return [
                'id' => (int) $g['id_tax_rules_group'],
                'name' => $g['name'],
                'active' => (bool) $g['active']
            ];
        }, $groups ?: []);
    }
}
