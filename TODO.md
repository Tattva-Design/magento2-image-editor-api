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