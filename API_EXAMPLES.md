# API Examples - Girofeeds PrestaShop Module

All endpoints require the `key` parameter with a valid PrestaShop WebserviceKey.

Base URL: `https://{your-store-domain}/module/girofeeds/`

---

## Product Listing (Feed)

Retrieve products from the store as a JSON array.

### GET all products

```
GET /module/girofeeds/feed?key={API_KEY}
```

### Paginated request

```
GET /module/girofeeds/feed?key={API_KEY}&limit={offset},{count}
```

**Example:** Get the first 100 products:
```
GET /module/girofeeds/feed?key={API_KEY}&limit=0,100
```

**Example:** Get products 101-200:
```
GET /module/girofeeds/feed?key={API_KEY}&limit=100,100
```

**Response:** JSON array of product objects with all configured feed fields.

```json
[
  {
    "id": "123",
    "title": "Samsung Galaxy S23",
    "description": "...",
    "price": "899.99",
    "sale_price": "799.99",
    "stock": "50",
    "reference": "SGS23-BLK",
    "ean13": "1234567890123",
    "brand": "Samsung",
    "product_category": "Smartphones",
    "active": "1",
    "image_link": "https://store.com/img/p/123.jpg",
    "...": "..."
  }
]
```

---

## Attribute Definitions

Retrieve comprehensive metadata about all product fields, features, attribute groups, manufacturers, suppliers, and categories. Used to discover the store schema for attribute management.

### GET attribute definitions

```
GET /module/girofeeds/attributes?key={API_KEY}
```

**Response:**

```json
{
  "status": "success",
  "fetchedAt": "2026-03-20T12:00:00+01:00",
  "storeInfo": {
    "defaultLanguageId": 1,
    "shopId": 1,
    "prestashopVersion": "8.1.0"
  },
  "standardFields": [
    {
      "key": "name",
      "type": "string",
      "table": "product_lang",
      "isMultilingual": true,
      "isRelation": false,
      "relatedEntity": null,
      "isArray": false,
      "possibleValues": null,
      "isRequired": true,
      "isReadOnly": false,
      "category": "content",
      "description": "Product name"
    },
    {
      "key": "condition",
      "type": "string",
      "table": "product",
      "isMultilingual": false,
      "isRelation": false,
      "relatedEntity": null,
      "isArray": false,
      "possibleValues": [
        {"value": "new"},
        {"value": "used"},
        {"value": "refurbished"}
      ],
      "isRequired": false,
      "isReadOnly": false,
      "category": "basic",
      "description": "Product condition"
    }
  ],
  "features": [
    {
      "id": 1,
      "key": "feature_1",
      "name": "Color",
      "position": 0,
      "table": "feature",
      "type": "string",
      "isMultilingual": true,
      "isRelation": false,
      "isArray": false,
      "isRequired": false,
      "isReadOnly": false,
      "category": "features",
      "description": "Product feature: Color",
      "possibleValues": [
        {"id": 1, "value": "Red"},
        {"id": 2, "value": "Blue"},
        {"id": 3, "value": "Black"}
      ]
    }
  ],
  "attributeGroups": [
    {
      "id": 1,
      "key": "attribute_group_1",
      "name": "Size",
      "publicName": "Size",
      "groupType": "select",
      "isColorGroup": false,
      "position": 0,
      "table": "attribute_group",
      "type": "string",
      "isMultilingual": true,
      "category": "variants",
      "description": "Variant attribute group: Size",
      "possibleValues": [
        {"id": 1, "value": "S", "position": 0},
        {"id": 2, "value": "M", "position": 1},
        {"id": 3, "value": "L", "position": 2}
      ]
    }
  ],
  "manufacturers": [
    {"id": 1, "name": "Samsung", "active": true},
    {"id": 2, "name": "Apple", "active": true}
  ],
  "suppliers": [
    {"id": 1, "name": "Main Supplier", "active": true}
  ],
  "categories": [
    {"id": 1, "parentId": 0, "depth": 0, "active": true, "position": 0, "name": "Root", "slug": "root"},
    {"id": 2, "parentId": 1, "depth": 1, "active": true, "position": 0, "name": "Home", "slug": "home"},
    {"id": 3, "parentId": 2, "depth": 2, "active": true, "position": 0, "name": "Electronics", "slug": "electronics"}
  ],
  "taxRulesGroups": [
    {"id": 1, "name": "IVA 21%", "active": true}
  ]
}
```

### Standard Field Categories

| Category | Description | Fields |
|----------|-------------|--------|
| `basic` | Basic product info | condition, visibility, active |
| `identifiers` | Product codes | reference, ean13, upc, mpn, isbn |
| `pricing` | Prices and costs | price, wholesale_price, ecotax, on_sale, unit_price_ratio, unity |
| `shipping` | Physical dimensions | weight, height, width, depth, additional_shipping_cost, location |
| `inventory` | Stock management | stock, minimal_quantity, available_for_order, show_price, out_of_stock, etc. |
| `content` | Product text (multilingual) | name, description, description_short, link_rewrite, etc. |
| `seo` | SEO fields (multilingual) | meta_title, meta_description, meta_keywords |
| `relations` | Related entities | id_manufacturer, id_supplier, id_category_default, category, categories, brand, supplier |
| `media` | Images | image, image_url, image_link |
| `features` | Product characteristics | specifications, features |
| `variants` | Combination fields | variant_reference, variant_price, variant_quantity, etc. |
| `advanced` | Advanced settings | online_only, is_virtual, redirect_type, available_date, etc. |

---

## Product Update

Update product fields via POST request.

```
POST /module/girofeeds/updateproduct?key={API_KEY}
Content-Type: application/json
```

### Specifications / Features Update

Update product features/characteristics using the `specifications` (or `features`) field.

**Example:**
```json
{
  "id_product": 123,
  "specifications": {
    "Color": "Red",
    "Material": "Cotton",
    "Weight": "150g"
  }
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Product updated successfully",
  "product_id": 123,
  "updated_fields": {
    "feature_Color": "Red",
    "feature_Material": "Cotton",
    "feature_Weight": "150g"
  },
  "errors": []
}
```

**How it works:**
- Feature names are matched **case-insensitively** against existing features
- If a feature name doesn't exist, it is **automatically created** in all store languages
- If a feature value doesn't exist for that feature, it is **automatically created**
- Previous feature-product associations are removed and replaced
- Both `specifications` and `features` field names are accepted (they are aliases)

---

## Category Management

### Single Category (Main Category)

Set the main category for a product using the `category` field.

**Example 1: Using category name (string)**
```json
{
  "id_product": 123,
  "category": "Electronics"
}
```

**Example 2: Using category ID (numeric)**
```json
{
  "id_product": 123,
  "category": 5
}
```

**Response:**
```json
{
  "status": "success",
  "product_id": 123,
  "updated_fields": {
    "id_category_default": 5
  },
  "categories_debug": {
    "input": "Electronics",
    "created": false,
    "existing": true,
    "final_category_id": 5,
    "final_category_name": "Electronics",
    "previous_category_id": 2
  }
}
```

### Multiple Categories

Associate multiple categories to a product using the `categories` field (array).

**Example 1: Multiple category names**
```json
{
  "id_product": 123,
  "category": "Electronics",
  "categories": ["Smartphones", "Mobile Accessories", "Tech Deals"]
}
```

**Example 2: Mixed with existing and new categories**
```json
{
  "id_product": 456,
  "categories": ["New Arrivals", "Summer Sale", "Women's Fashion"]
}
```

**Response:**
```json
{
  "status": "success",
  "product_id": 123,
  "updated_fields": {
    "id_category_default": 5,
    "categories": "5, 12, 13, 14"
  },
  "categories_debug": {
    "input": ["Smartphones", "Mobile Accessories", "Tech Deals"],
    "created_categories": [
      {"id": 13, "name": "Mobile Accessories"},
      {"id": 14, "name": "Tech Deals"}
    ],
    "existing_categories": [
      {"id": 5, "name": "Electronics"},
      {"id": 12, "name": "Smartphones"}
    ],
    "associated_category_ids": [5, 12, 13, 14],
    "previous_categories": [2, 5],
    "skipped_categories": []
  }
}
```

## How it Works

### Category Field (`category`)
- Accepts a **string** (category name) or **numeric** (category ID)
- Sets the **main/default category** for the product (`id_category_default`)
- If the category name doesn't exist, it will be **automatically created** as a child of the root category (Home)
- Category search is **case-insensitive**

### Categories Field (`categories`)
- Accepts an **array of category names** (strings): `["Category 1", "Category 2", ...]`
- Associates **multiple categories** to the product
- **Replaces all existing category associations** (except the main category)
- Each category name is searched in PrestaShop:
  - If found: uses the existing category
  - If not found: creates a new category as a child of root (Home)
- The main category (from `category` field) is **always included** in the associations

### Category Creation Rules
- New categories are created as **children of the root category** (Home, id=2)
- Categories are created with:
  - Active status
  - Name in all store languages
  - Auto-generated friendly URL (link_rewrite)
- Category search uses **case-insensitive** matching on the category name

### Important Notes
1. **Always specify the main category** using the `category` field when using `categories`
2. Categories are **replaced, not merged** - all previous category associations are removed
3. Category names are **trimmed** and empty values are skipped
4. If a category fails to create, it's logged and skipped (doesn't stop the update)
5. The main category is **always included** in the product's category associations

## Complete Example

```json
{
  "id_product": 789,
  "name": "Samsung Galaxy S23",
  "category": "Smartphones",
  "categories": [
    "Electronics",
    "Mobile Phones",
    "5G Devices",
    "Android Phones",
    "Premium Smartphones"
  ],
  "price": 899.99,
  "active": 1
}
```

This will:
1. Set "Smartphones" as the main/default category
2. Associate the product with all listed categories
3. Create any categories that don't exist
4. Return detailed debug info about which categories were created vs. found
