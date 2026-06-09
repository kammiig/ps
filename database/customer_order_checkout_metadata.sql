SET NAMES utf8mb4;

DELIMITER $$

DROP PROCEDURE IF EXISTS add_customer_order_checkout_metadata $$
CREATE PROCEDURE add_customer_order_checkout_metadata()
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'customer_orders'
          AND COLUMN_NAME = 'order_type'
    ) THEN
        ALTER TABLE customer_orders ADD COLUMN order_type VARCHAR(32) NULL AFTER currency;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'customer_orders'
          AND COLUMN_NAME = 'selected_domain'
    ) THEN
        ALTER TABLE customer_orders ADD COLUMN selected_domain VARCHAR(255) NULL AFTER order_type;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'customer_orders'
          AND COLUMN_NAME = 'hosting_plan_slug'
    ) THEN
        ALTER TABLE customer_orders ADD COLUMN hosting_plan_slug VARCHAR(120) NULL AFTER selected_domain;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'customer_orders'
          AND COLUMN_NAME = 'package_label'
    ) THEN
        ALTER TABLE customer_orders ADD COLUMN package_label VARCHAR(190) NULL AFTER hosting_plan_slug;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'customer_orders'
          AND COLUMN_NAME = 'billing_cycle'
    ) THEN
        ALTER TABLE customer_orders ADD COLUMN billing_cycle VARCHAR(32) NULL AFTER package_label;
    END IF;
END $$

DELIMITER ;

CALL add_customer_order_checkout_metadata();
DROP PROCEDURE IF EXISTS add_customer_order_checkout_metadata;
