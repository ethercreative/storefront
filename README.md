# Storefront
![Easily integrate Shopify with Craft CMS!](./resources/banner.jpg)

### TODO
- [x] Create endpoints for the following webhooks:
  - [x] Collection Create? / Update / Delete
  - [x] Product Create / Update / Delete
  - [x] Order Create
  - [x] Add utility to automatically add the webhooks (will need more API access)
- [x] Add manual sync utilities
- [x] DON'T use element type, use section

#### MVP
- [x] Dynamically create / remove entries based on Shopify products (entries disabled by default)
- [x] Dynamically create / remove categories based on Shopify collections (categories disabled by default)
- [x] Show a snippet of the product in a custom field in the entry (use this custom field to get shopify product data)
- [ ] Add twig tags / endpoints to show / update the Shopify "checkouts" (carts)
- [ ] Tie craft users to Shopify customers (SSO?)

#### Nice to have
- [ ] Allow basic editing of Shopify products in craft (i.e. title)
- [ ] Widgets showing various sales metrics
- [ ] Store some product info to allow for in-craft filtering
- [ ] In-craft orders section for viewing / filtering orders (link to shopify)
- [ ] Add "New Shopify Product" button to element index for selected product element type
- [ ] Map any value from a product to any field (within reason)
- [ ] Add product search to collection page
- [ ] Two-way sync (sync changes in Craft to Shopify for tracked fields)
- [ ] Add "Admin Link" to Shopify, linking directly from a product or collection to its Craft counterpart

## Usage

- Create Shopify private app
- Enable Storefront API
- "Products, variants and collections" Read / Write access
- "Orders, transactions and fulfillments" Read / Write access

## Caveats
- Using the bulk product importer will only include the first 10 collections on the product