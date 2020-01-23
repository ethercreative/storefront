# Storefront
![Easily integrate Shopify with Craft CMS!](./resources/banner.jpg)

### TODO
- [ ] Create endpoints for the following webhooks:
  - [ ] Collection Create / Update / Delete
  - [x] Product Create / Update / Delete
  - [ ] Inventory item Update?
  - [ ] Inventory level Update?
  - [x] Add utility to automatically add the webhooks (will need more API access)
- [x] Add manual sync utilities

#### MVP
- [x] Dynamically create / remove entries based on Shopify products (entries disabled by default)
- [ ] Dynamically create / remove categories based on Shopify collections (categories disabled by default)
- [x] Show a snippet of the product in a custom field in the entry (use this custom field to get shopify product data)
- [ ] Add twig tags / endpoints to show / update the Shopify "checkouts" (carts)
- [ ] Tie craft users to Shopify customers (SSO?)

#### Nice to have
- [ ] Allow basic editing of Shopify products in craft (i.e. title)
- [ ] Widgets showing various sales metrics
- [ ] Track orders in Craft to allow for stuff like "frequently bought together" without needing shopify add-ons

## Usage

- Create Shopify private app
- Enable Storefront API
- "Products, variants and collections" Read / Write access