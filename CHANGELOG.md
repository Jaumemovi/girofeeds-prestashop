# Changelog

### 3.3.20
- **Multi-select order statuses for orders count**: the `GIROFEEDS_ORDERS_COUNT_STATUS` setting now accepts multiple order states. Order-count fields in the feed (`orders_1d`, `orders_7d`, `orders_30d`, `orders_90d`, `orders_365d`) aggregate orders across all selected statuses instead of a single one.
- Configuration form field upgraded to a multi-select (`chosen` class) and placeholder entry removed.
- Selection is stored as a CSV of integer IDs — backward-compatible with previous single-ID values (a single ID is still a valid CSV of one element).
- Feed SQL in `controllers/front/feed.php` (`fetchProductOrdersCounts`, `fetchBatchProductOrdersCounts`) now uses `AND o.current_state IN (...)` instead of an equality match. New helper `getConfiguredOrderStatusIds()` parses and sanitizes the stored value to `array<int>`.

### 3.3.18
- **Fixed product feature updates via `/updateproduct`**: features exposed in the feed as top-level fields (lowercased feature name, e.g. `ads_label_0`) were silently ignored by the update endpoint, returning `No valid fields to update`
- Added fallback in `updateproduct.php` that matches unknown field names case-insensitively against existing PrestaShop features and routes them to the feature-assignment logic (reuses `findOrCreateFeatureValue` + `feature_product` insert)
- Fallback does **not** auto-create new features — only assigns/creates values for features that already exist, to avoid generating garbage features from misrouted fields

### 3.3.17
- **Added all feed-generated fields to `/attributes` endpoint**: `id`, `parent_id`, `gtin`, `title`, `short_description`, `link`, `product_category`, `product_supplier_reference`, `image_link`, `additional_images`, `price_incl_vat`, `sale_price`, `sale_price_incl_vat`, `tax_rate`, `currency`, `visible`, `package_weight/height/width/depth`, `shipping`, `delivery_period`, `orders_1d/7d/30d/90d/365d`, `attachments`
- These fields are computed by the feed (not direct DB columns) and were previously missing from attribute definitions, making them unavailable for filters, actions, and ecommerce sync in Girofeeds SaaS

### 3.3.16
- **NEW: `/attributes` endpoint** for product field definitions — returns metadata about all product fields, dynamic features with possible values, variant attribute groups, manufacturers, suppliers, categories, and tax rules
- **Improved order count fields in feed**: renamed intervals to `orders_1d`, `orders_7d`, `orders_30d`, `orders_90d`, `orders_365d` with efficient batch SQL query (single query per page)
- **Removed legacy order import functionality** inherited from Channable — removed order controller, admin hooks/grid extensions, carrier/customer group/marketplace/tax assignment panels, and 18 order-specific configuration keys
- **Added Spanish locale descriptor** (`config_es.xml`)

### 3.3.15
- **Skip duplicate image upload**: compare MD5 hash of new image with current cover to avoid re-uploading identical images
- **Fixed image upload duplicate cover error**: reset all existing covers via direct SQL before `Image::add()` to prevent `id_product_cover` unique constraint violation
- **Improved image upload error diagnostics**: added detailed validation, lang, and DB error info when `Image::add()` fails in updateproduct endpoint
- **PrestaShop Marketplace validation fixes**
- Fixed copyright headers: single @author/@copyright tag across all PHP, JS, and TPL files
- Replaced `Context::getContext()` with `$this->context` in all controllers and module class
- Replaced deprecated `Module::isInstalled()`/`Module::isEnabled()` with `Module::getInstanceByName()`
- Replaced deprecated `Tools::link_rewrite()` with `Tools::str2url()`
- Added `version_compare()` guard for `Warehouse` class (removed in PS9)
- Fixed `_PS_VERSION_ < 9` comparison to use `version_compare()`
- Fixed type issues: `false` → `null` defaults in GirofeedsLogger, boolean property assignments, `(int)` casts on Configuration::get() calls
- Fixed `GirofeedsProduct::getAttributesForZusammenfassungUse()` to return `[]` instead of `false`
- Fixed `getConversationRate()` typo → `getConversionRate()` in order controller
- Fixed missing `mod='girofeeds'` in hookAdminOrder.tpl translation
- Removed empty `return;` statements and unreachable `break;` after `return`
- Removed unused methods: `findOrCreateCategoryPath()`, `findOrCreateCategoryPathWithDebug()`, `findOrCreateManufacturer()`
- Removed deprecated `meta_keywords` assignments (removed in PS9)
- Fixed PHPDoc parameter name in GirofeedsOrderReturn

### 4.0.0
- Complete rebranding from Channable to Girofeeds
- All class names, DB tables, config keys, hooks, and CSS classes renamed
- Copyright headers updated (Moviendote as modifications author)
- Original Channable addon by patworx multimedia GmbH properly attributed

### 3.3.14

- **Rebranding: Channable renamed to Girofeeds** in module configuration interface
- Removed auto-connect button from module configuration
- **NEW: Order counting fields for feed export**
- Added order status selector in Feed Settings to configure which order status to count
- New "orders" field group in Expert: Additional fields with:
  - `orders_last_7days` - Orders in the last 7 days
  - `orders_last_30days` - Orders in the last 30 days
  - `orders_last_365days` - Orders in the last 365 days
  - `orders_all_time` - All orders
- Order counts are based on product quantity in orders with the configured status

### 3.3.13

- **NEW: Multi-category support in product update API**
- Added support for `categories` field (array) to associate multiple categories to a product
- Modified `category` field to accept category name (string) or ID (numeric) for the main category
- Automatic category creation if category name doesn't exist in PrestaShop
- Categories are searched by name (case-insensitive) in existing PrestaShop categories
- New categories are created as children of root category (Home)
- All product categories are replaced when `categories` field is provided
- Improved debug information for category operations (created, existing, associated)
- Categories field accepts array of category names: `["Category 1", "Category 2", ...]`

### 3.3.12

- Enhanced product update API response with comprehensive debug information
- Updated fields now return as object with field name and actual modified value
- Added categories_debug section: input value, created/existing categories, final category details, previous category
- Added images_debug section: source URL, download status, image ID, PrestaShop path, cover status, demoted images
- Added brands_debug section: input value, created/existing manufacturer, final manufacturer details
- Improved error reporting and troubleshooting capabilities

### 3.3.11

- Added Firebase Storage image download support in product update endpoint
- Images from Firebase Storage (firebasestorage.googleapis.com, flender, girofieeds) are automatically downloaded and added to PrestaShop
- Downloaded images are set as main product image, existing images become additional images
- Support for image fields: image, image_url, image_link
- Automatic image format detection (jpg, png, gif, webp)
- All product image thumbnails are generated automatically

### 3.3.10

- Version consolidation release

### 3.3.9

- Added specific_price update functionality in product update endpoint
- Ability to replace all specific prices by sending specific_price array
- Category string with ">" separator now interpreted as category hierarchy (e.g., "Home > Category > Subcategory")
- Dynamic field updates based on "Expert: Additional fields in feed" configuration
- Support for all PrestaShop product tables: product, product_shop, product_lang, product_attribute, product_attribute_shop
- Multi-language field support with array format (e.g., {"1": "value_en", "2": "value_de"})
- Product attribute/combination field updates with id_product_attribute parameter
- Stock quantity updates via StockAvailable (stock or quantity field)
- Brand field support: find existing or create new manufacturer automatically
- Supplier field support: find existing or create new supplier automatically
- Specifications/features update: find existing or create new features and values
- Product supplier reference update support
- Field aliases for feed compatibility:
  - title -> name
  - gtin -> ean13
  - description_html -> description
  - short_description_html -> description_short
  - package_weight/height/width/depth -> weight/height/width/depth
  - visible -> active
  - quantity -> stock

### 3.3.8

- Default reduction_type is 'percentage' if not specified

### 3.3.7

- Newly submitted business_order field will be stored
- Country based shipping VAT calculation extended

### 3.3.6

- Newly submitted tax_id_number field will be stored

### 3.3.5

- Added "reduction" to be exported as specific price table value

### 3.3.4

- Order grid optimization
- New config option: Country based shipping VAT calculation

### 3.3.3

- Fix for older PHP (7.x) versions

### 3.3.2

- PrestaShop V9 Beta requirements

### 3.3.1

- Add id_address_delivery and id_address_invoice to cart-table at order creation

### 3.3.0

- New feature to import specific price table values

### 3.2.9

- New field "Return Tracking Code" at order management to submit to Girofeeds

### 3.2.8

- Added additional logging for debugging
- Compatibility update (PS 8.2.0)

### 3.2.7

- Added filter possibility for order grid

### 3.2.6

- Compatibility update for 3rd-party "wkproductcustomfield" plugin latest version
- New setting option to use phone number as mobile number 

### 3.2.5

- HTTP Status codes optimized

### 3.2.4

- Optimized grid view hook handling for older PS versions (<= 1.7.6.x)

### 3.2.3

- Adaption at feed generation for outdated PHP versions (7.0/7.1/7.2)

### 3.2.2

- Fix feed generation

### 3.2.1

- Fix vulnerability, thanks to TouchWeb.fr & 202 Ecommerce

### 3.2.0

- New feature "map different shipping status per marketplace"
- Workarround for miscalculated raw prices in specific scenarios

### 3.1.9

- Added additional hooks for order creation (girofeedsOrderCreation)
- Compatibility Update

### 3.1.8

- Added optional stock sync for multishop environments

### 3.1.7

- Added additional information shipping -> "pickup point name" for created orders
- Added additional information shipping -> "shipping center id" for created orders

### 3.1.6

- Compatibility PrestaShop 8.1.3
- New configuration option "Default string for orders with empty name fields"

### 3.1.5

- Reformat submitted phone numbers to not trigger PrestaShop internal validation on order creation

### 3.1.4

- Added support for new information "shipment_method" in Order submissions
- PS Compatibility update

### 3.1.3

- Change in customer temporary password generation for validation rule

### 3.1.2

- Fix for missing possible attribute image
- Compatibility jsonEncode & jsonDecode

### 3.1.1

- Warehouse config var fix for PrestaShop >= 1.7.8

### 3.1.0

- Compatibility PrestaShop 8.1
- Added additional hooks for feed generation (girofeedsSql, girofeedsAddProductToFeedCheck)

### 3.0.2

- Compatibility 8.0.2
- Implementation compatibility with pm_advancedpack plugin

### 3.0.1

- Zalando Information import feature
- Bugfix in feed SQL generation

### 3.0.0

- PrestaShop V8 Compatibility

### 2.9.3

- Fix for activated cache option when unexpected null values occur

### 2.9.2

- Added special chars replacement to fulfill PrestaShop internal string validation for customer names in order creation

### 2.9.1

- Added new option to deactivate variants in feed at all

### 2.9.0

- Fix for PS1.6 cache creation 

### 2.8.9

- Added "product_shop", "product_attribute_shop" as manual assignable fields

### 2.8.8

- Added support for multilanguage in cache
- Added support for "region_code" in Order submissions

### 2.8.7

- Added cronjob for precreating products JSON data

### 2.8.6

- Further improved caching, introducing new database table for specific cache scenarios and cronjob possibility to manually update cache

### 2.8.5

- Advanced caching mechanism for feed creation to avoid high load when checking category-trees with native PrestaShop methods

### 2.8.4

- Possibility to set an Employee (backend user) for order creation. This could prevent automatic stock update errors in some cases.

### 2.8.3

- Change in main class to avoid errors in PHP7.0 integrations (backward compatibility)

### 2.8.2

- Fix "order_paid_real" value for specific configurations

### 2.8.1

- Handling of "middle_name" dataset.
- Improved tax calculation

### 2.8.0

- Call of specific hook "girofeedsFeed" at the end of each processed feed item. Merchants now can implement own modifications of each item in individual modules. 

### 2.7.9

- Included ProductSupplier object / table to fetch supplier references for product feed

### 2.7.8

- Improvement handling manual carrier tax in order creation

### 2.7.7

- Order view extended for PS > 1.7.7.x, new option in backend configuration to view girofeeds order notes in PrestaShop order overview grid

### 2.7.6

- integration 3rd party plugin "wkproductcustomfield" in product feed

### 2.7.5

- Stock update config interface

### 2.7.4

- Improvement order creation

### 2.7.3

- Bugfix ecotax

### 2.7.2

- Import shipping rates: tax rate import now possible

### 2.7.1

- Cronjob update

### 2.7.0

- Extended logging class

### 2.6.9

- Order weight added

### 2.6.8

- implementation of optional validateOrder Hook
- improved group settings

### 2.6.7

- Customer group to be set via channel/marketplace

### 2.6.6

- Company in shipping address

### 2.6.5

- Fix admin config

### 2.6.4

- Compatibility PS 1.7.7.0

### 2.6.3

- Pricing workarround for multishop

### 2.6.2

- Workarround stock updates for older PS versions to fix PS internal bug

### 2.6.1

- Update for invoice creation
- Stock management improvement

### 2.6.0

- Fix payment transaction ID creation 
- added Currency detection
- added billing company

