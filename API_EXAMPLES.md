# API Examples - Product Update Endpoint

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
