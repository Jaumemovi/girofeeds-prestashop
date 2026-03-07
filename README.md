# Girofeeds - PrestaShop Feed Management Module

Feed management module for PrestaShop. Generate product feeds, manage orders, and synchronize stock with external marketplaces and channels.

## Attribution

This module is based on the **Channable PrestaShop addon** developed by [patworx multimedia GmbH](https://www.patworx.de/).

- **Original work:** 2007-2025 patworx multimedia GmbH
- **Modifications:** 2025-2026 [Moviendote](https://girofeeds.com/) (hello@girofeeds.com)

Licensed under the [Academic Free License (AFL 3.0)](http://opensource.org/licenses/afl-3.0.php).

## Requirements

- PrestaShop 1.5 or later (tested up to PS 9.x)
- PHP 7.0 or later

## Installation

1. Download the latest release ZIP
2. In PrestaShop Back Office, go to **Modules > Module Manager**
3. Click **Upload a module** and select the ZIP file
4. Configure the module under **Modules > Girofeeds**

## Configuration

After installation, configure the module in your PrestaShop back office:

- **API Key**: Generated automatically; used for feed access and order/product API endpoints
- **Feed Settings**: Select products, categories, languages, and currencies to include in your feed
- **Expert Fields**: Map additional database fields to your feed output
- **Order Settings**: Configure marketplace order import (carrier mapping, status mapping, customer groups)
- **Stock Sync**: Enable stock update webhooks for real-time inventory sync
- **Logging**: Set log level (Error / Info / Debug) for troubleshooting

## Build

To build a distributable ZIP package:

```bash
npm install
npm run zip
```

This creates `girofeeds-v{version}.zip` ready for PrestaShop upload.

## Documentation

- [CHANGELOG.md](CHANGELOG.md) - Version history
- [API_EXAMPLES.md](API_EXAMPLES.md) - Product update API examples
