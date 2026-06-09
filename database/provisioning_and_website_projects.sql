SET NAMES utf8mb4;

DELIMITER $$

DROP PROCEDURE IF EXISTS add_customer_order_provisioning_columns $$
CREATE PROCEDURE add_customer_order_provisioning_columns()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_orders' AND COLUMN_NAME = 'provisioning_status'
    ) THEN
        ALTER TABLE customer_orders ADD COLUMN provisioning_status VARCHAR(40) NOT NULL DEFAULT 'pending_payment' AFTER billing_cycle;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_orders' AND COLUMN_NAME = 'domain_registration_status'
    ) THEN
        ALTER TABLE customer_orders ADD COLUMN domain_registration_status VARCHAR(80) NULL AFTER provisioning_status;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_orders' AND COLUMN_NAME = 'hosting_setup_status'
    ) THEN
        ALTER TABLE customer_orders ADD COLUMN hosting_setup_status VARCHAR(80) NULL AFTER domain_registration_status;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_orders' AND COLUMN_NAME = 'website_project_status'
    ) THEN
        ALTER TABLE customer_orders ADD COLUMN website_project_status VARCHAR(80) NULL AFTER hosting_setup_status;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_orders' AND COLUMN_NAME = 'whm_package'
    ) THEN
        ALTER TABLE customer_orders ADD COLUMN whm_package VARCHAR(120) NULL AFTER website_project_status;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_orders' AND COLUMN_NAME = 'provisioning_last_error'
    ) THEN
        ALTER TABLE customer_orders ADD COLUMN provisioning_last_error TEXT NULL AFTER whm_package;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_orders' AND COLUMN_NAME = 'provisioned_at'
    ) THEN
        ALTER TABLE customer_orders ADD COLUMN provisioned_at DATETIME NULL AFTER paid_at;
    END IF;
END $$

DELIMITER ;

CALL add_customer_order_provisioning_columns();
DROP PROCEDURE IF EXISTS add_customer_order_provisioning_columns;

CREATE TABLE IF NOT EXISTS customer_provisioning_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_order_id INT UNSIGNED NOT NULL,
    customer_user_id INT UNSIGNED NULL,
    whmcs_client_id INT UNSIGNED NOT NULL,
    whmcs_order_id INT UNSIGNED NULL,
    whmcs_invoice_id INT UNSIGNED NOT NULL,
    item_type ENUM('domain', 'hosting') NOT NULL,
    item_key VARCHAR(255) NOT NULL,
    display_name VARCHAR(190) NOT NULL,
    domain_name VARCHAR(255) NULL,
    hosting_plan_slug VARCHAR(120) NULL,
    whm_package VARCHAR(120) NULL,
    billing_cycle VARCHAR(32) NULL,
    payment_status ENUM('pending', 'processing', 'paid', 'failed') NOT NULL DEFAULT 'pending',
    provisioning_status ENUM('pending_payment', 'processing', 'active', 'action_required', 'cancelled') NOT NULL DEFAULT 'pending_payment',
    domain_registration_status VARCHAR(80) NULL,
    hosting_setup_status VARCHAR(80) NULL,
    whmcs_domain_id INT UNSIGNED NULL,
    whmcs_service_id INT UNSIGNED NULL,
    registration_date DATE NULL,
    expiry_date DATE NULL,
    renewal_date DATE NULL,
    start_date DATE NULL,
    next_due_date DATE NULL,
    renewal_amount DECIMAL(12,2) NULL,
    nameservers_json TEXT NULL,
    setup_issue_public VARCHAR(255) NULL,
    setup_issue_internal TEXT NULL,
    stripe_reference VARCHAR(160) NULL,
    provision_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    last_attempt_at DATETIME NULL,
    provisioned_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY customer_provisioning_order_item_unique (customer_order_id, item_type, item_key),
    INDEX customer_provisioning_customer_index (customer_user_id),
    INDEX customer_provisioning_whmcs_client_index (whmcs_client_id),
    INDEX customer_provisioning_invoice_index (whmcs_invoice_id),
    INDEX customer_provisioning_status_index (provisioning_status),
    CONSTRAINT customer_provisioning_order_fk FOREIGN KEY (customer_order_id) REFERENCES customer_orders(id) ON DELETE CASCADE,
    CONSTRAINT customer_provisioning_user_fk FOREIGN KEY (customer_user_id) REFERENCES customer_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS website_projects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_order_id INT UNSIGNED NOT NULL UNIQUE,
    customer_user_id INT UNSIGNED NULL,
    whmcs_client_id INT UNSIGNED NOT NULL,
    whmcs_order_id INT UNSIGNED NULL,
    whmcs_invoice_id INT UNSIGNED NOT NULL,
    package_name VARCHAR(190) NOT NULL,
    domain_name VARCHAR(255) NULL,
    hosting_plan_slug VARCHAR(120) NULL,
    payment_status VARCHAR(40) NOT NULL DEFAULT 'Payment Pending',
    project_status VARCHAR(80) NOT NULL DEFAULT 'Payment Pending',
    onboarding_status VARCHAR(120) NOT NULL DEFAULT 'Payment Pending',
    customer_note TEXT NULL,
    internal_notes TEXT NULL,
    estimated_next_step VARCHAR(255) NULL,
    purchase_date DATE NULL,
    completed_at DATETIME NULL,
    delivered_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX website_projects_customer_index (customer_user_id),
    INDEX website_projects_whmcs_client_index (whmcs_client_id),
    INDEX website_projects_invoice_index (whmcs_invoice_id),
    INDEX website_projects_status_index (project_status),
    CONSTRAINT website_projects_order_fk FOREIGN KEY (customer_order_id) REFERENCES customer_orders(id) ON DELETE CASCADE,
    CONSTRAINT website_projects_user_fk FOREIGN KEY (customer_user_id) REFERENCES customer_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
