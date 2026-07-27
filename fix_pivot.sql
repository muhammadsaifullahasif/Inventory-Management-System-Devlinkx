ALTER TABLE sales_channel_product ADD COLUMN id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY FIRST;
ALTER TABLE sales_channel_product ADD UNIQUE KEY product_sales_channel_unique (product_id, sales_channel_id);
