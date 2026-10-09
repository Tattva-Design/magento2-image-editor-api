- Need check the auto update the tag when PR is merged, and update the tag when PR is merged.

### Add to cart TODO

- Need to check in database projectUuid is coming or not in magento
  - Data tables which is affected in magento add to cart to cash on delivery flows
  - `magento.quote_item, magento.tattva_image_editor_project, magento.sales_order_item, magento.sales_order, magento.quote`
- enable the edit button in cart page so user can change the frame type and paper type only
- Need to check in admin that how the `customisable_product` product is manage
- Need to verify that after adding the project in cart, we have to make sure that product doesn't visible in UI for that user and any other users too
- While adding the project into the cart from image-editor to framevala, we have to add that project as configurable_option not as a simple product
- Need to check pricing, tax, discount, weight for our `customisable_product`
  - currently it is static 500rs price, 0.5kg weight and no tax and no discount right now
- Need to check the same project is added to cart but different configurable option, then in cart it will have two separate entries for the product

---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

## 🗺️ Roadmap: Cart API Project ID & Variants Integration

### **Group 1: Database & Data Persistence Setup**
- [ ] **Task 1.1: Declare DB Schema Columns**
  - Verify and ensure `project_uuid` / `project_id` column exists in `quote_item` in `etc/db_schema.xml`.
  - Verify and ensure `project_uuid` / `project_id` column exists in `sales_order_item` in `etc/db_schema.xml`.
- [ ] **Task 1.2: Generate Whitelist / Apply Migration**
  - Run `bin/magento setup:upgrade` and `bin/magento setup:db-declaration:generate-whitelist` if schema updates are made.

### **Group 2: Add-to-Cart Request & Quote Item Isolation**
- [ ] **Task 2.1: Intercept Add to Cart Request (`info_buyRequest`)**
  - Verify/extend `GraphQlBuyRequestBuilderPlugin` and `BuyRequestBuilderPlugin` to map `project_uuid` and `super_attribute` options (Size `138`, Frame Type `137`, Paper Type `139`) into `info_buyRequest`.
- [ ] **Task 2.2: Prevent Cart Item Merging (`aroundRepresentProduct`)**
  - Verify `QuoteItemPlugin` (`aroundRepresentProduct`) ensures items with different `project_uuid` values (or different variant combinations) stay as separate line items in cart.

### **Group 3: Quote to Order Conversion**
- [ ] **Task 3.1: Configure Fieldset Copy & Plugin**
  - Verify `etc/fieldset.xml` mapping under `sales_convert_quote_item` to copy `project_uuid` from `quote_item` to `order_item`.
  - Ensure `Plugin/QuoteToOrderItem.php` copies custom options and project data to order items upon placing order.

### **Group 4: Expose `project_id` in Cart APIs (GraphQL & REST)**
- [ ] **Task 4.1: Extension Attributes Configuration**
  - Declare `project_uuid` under `Magento\Quote\Api\Data\CartItemInterface` and `Magento\Sales\Api\Data\OrderItemInterface` in `etc/extension_attributes.xml`.
- [ ] **Task 4.2: GraphQL Schema Extension**
  - Extend `CartItemInterface`, `SimpleCartItem`, and `ConfigurableCartItem` in `etc/schema.graphqls` with `project_uuid` and `project_thumbnail_url`.
  - Ensure `CartItemInput` supports `project_uuid`.
- [ ] **Task 4.3: GraphQL & REST Output Resolvers / Plugins**
  - Verify `GetItemsDataPlugin` / `CartItemProjectThumbnail` resolver populates `project_uuid`, product title, SKU, and thumbnail in GraphQL cart query.
  - Populate extension attributes on REST Cart item endpoints (`GET /V1/carts/mine`).

### **Group 5: Frontend Integration & End-to-End Testing**
- [ ] **Task 5.1: Update Frontend Add-to-Cart Payload**
  - Send `project_uuid` and selected variant options in GraphQL `addProductsToCart` mutation.
- [ ] **Task 5.2: Update Frontend Cart Query**
  - Include `project_uuid` and `project_thumbnail_url` in cart fetching queries.
- [ ] **Task 5.3: Verification Tests**
  - Test adding two different frame customizations with the same base SKU $\rightarrow$ verify 2 distinct cart lines with correct `project_uuid`.
  - Test order placement through checkout $\rightarrow$ verify `sales_order_item` stores `project_uuid` in database and admin.

### **Group 6: Code Cleanup, Redundancy Audit & File Optimization**
- [ ] **Task 6.1: Audit & Consolidate BuyRequest Plugins**
  - Compare `Plugin/BuyRequestBuilderPlugin.php` and `Plugin/GraphQlBuyRequestBuilderPlugin.php`.
  - Extract duplicate super attribute mapping logic into a unified helper/service (`Model/Service/ProjectSuperAttributeMapper.php` or similar) to eliminate duplicate code.
- [ ] **Task 6.2: Review & Clean Up Observer / Plugin Overlap**
  - Review `Observer/QuoteProductAddAfterObserver.php` vs `Plugin/AddProductsToCartPlugin.php` / `Model/Registry/CartProjectRegistry.php`.
  - Remove redundant quote/observer handlers if project data is already cleanly passed and processed via GraphQL `cartItemData` / `BuyRequest`.
- [ ] **Task 6.3: Audit Quote-to-Order Transfer Methods**
  - Check `Plugin/QuoteToOrderItem.php` vs `etc/fieldset.xml`.
  - Keep only the necessary mechanism to avoid duplicate assignments and clean up unused files.
- [ ] **Task 6.4: Remove Unused DI Configurations & Events**
  - Audit `etc/di.xml` and `etc/events.xml` to delete any plugin or observer declarations associated with removed or consolidated files.
- [ ] **Task 6.5: Clean Up Hardcoded Values & Magic Numbers**
  - Refactor static option IDs (`137`, `138`, `139`, `4`, `5`, `6`, etc.) in `BuyRequestBuilderPlugin` / `GraphQlBuyRequestBuilderPlugin` into class constants or configuration parameters for better maintainability.

---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------