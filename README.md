## Girofeeds Integration Flow (PrestaShop ↔ Girofeeds)

### 1) Module configuration in PrestaShop
![Step 1 - PrestaShop module config](01-prestashop-config.jpg)

### 2) API Key and endpoint setup
In the PrestaShop module configuration, copy the API key and endpoint URLs (Feed-URL, Webhook-URL & Order-API-URL, Product-Info-URL) and configure them in Girofeeds.

![Step 2 - API key and endpoints](03-prestashop-api-key.jpg)
![Step 2b - Girofeeds provider config (API key field)](05-provider-config-apikey-final.jpg)

### 3) Product synchronization from Girofeeds
Run synchronization from Girofeeds Catalog to send updated data to PrestaShop.

![Step 3 - Girofeeds catalog sync](02-girofeeds-catalog-sync.jpg)

### 4) Product edition in Girofeeds (Optimizations)
Open an optimization, edit product fields (title/description/images), and show before/after values in the optimization preview.

![Step 4 - Optimizations list](04-optimizations-list.png)
![Step 4 - Before and after modal](06-optimization-before-after-modal.png)

### 5) Sync back to PrestaShop (Catalog)
From **Catálogo**, click **Sincronizar con Ecommerce**.

![Step 5 - Sync to PrestaShop](07-catalog-sync-to-prestashop.png)

### 6) Validation in PrestaShop
Open the product edit page in PrestaShop and verify the updated fields (title, description, images, etc.).
