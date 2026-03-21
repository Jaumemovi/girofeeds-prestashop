

## Girofeeds Integration Flow (PrestaShop ↔ Girofeeds)

### 1) Module configuration in PrestaShop
![Step 1 - PrestaShop module config](01-prestashop-config.jpg)

### 2) Product synchronization from Girofeeds
![Step 2 - Girofeeds catalog sync](02-girofeeds-catalog-sync.jpg)

### 3) API Key and endpoint setup
In the PrestaShop module config, copy the API key and endpoint URLs (Feed-URL, Webhook-URL & Order-API-URL, Product-Info-URL) and use them in Girofeeds provider setup.

![Step 3 - API key and endpoints](03-prestashop-api-key.jpg)

### 4) Update product data in Girofeeds and sync back to PrestaShop
- Edit title, description, images in Girofeeds
- Run **Sincronizar con Ecommerce**
- Validate updated data in PrestaShop product edit page
