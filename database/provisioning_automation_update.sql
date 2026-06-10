SET NAMES utf8mb4;

DELIMITER $$

DROP PROCEDURE IF EXISTS add_provisioning_automation_columns $$
CREATE PROCEDURE add_provisioning_automation_columns()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_provisioning_items' AND COLUMN_NAME = 'cpanel_username'
    ) THEN
        ALTER TABLE customer_provisioning_items ADD COLUMN cpanel_username VARCHAR(16) NULL AFTER whmcs_service_id;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_provisioning_items' AND COLUMN_NAME = 'cpanel_password_encrypted'
    ) THEN
        ALTER TABLE customer_provisioning_items ADD COLUMN cpanel_password_encrypted TEXT NULL AFTER cpanel_username;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_provisioning_items' AND COLUMN_NAME = 'cpanel_password_sent_at'
    ) THEN
        ALTER TABLE customer_provisioning_items ADD COLUMN cpanel_password_sent_at DATETIME NULL AFTER cpanel_password_encrypted;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_provisioning_items' AND COLUMN_NAME = 'server_ip'
    ) THEN
        ALTER TABLE customer_provisioning_items ADD COLUMN server_ip VARCHAR(45) NULL AFTER cpanel_password_sent_at;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_provisioning_items' AND COLUMN_NAME = 'cloudflare_zone_id'
    ) THEN
        ALTER TABLE customer_provisioning_items ADD COLUMN cloudflare_zone_id VARCHAR(80) NULL AFTER nameservers_json;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_provisioning_items' AND COLUMN_NAME = 'cloudflare_status'
    ) THEN
        ALTER TABLE customer_provisioning_items ADD COLUMN cloudflare_status VARCHAR(80) NULL AFTER cloudflare_zone_id;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_provisioning_items' AND COLUMN_NAME = 'cloudflare_nameservers_json'
    ) THEN
        ALTER TABLE customer_provisioning_items ADD COLUMN cloudflare_nameservers_json TEXT NULL AFTER cloudflare_status;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_provisioning_items' AND COLUMN_NAME = 'cloudflare_last_error'
    ) THEN
        ALTER TABLE customer_provisioning_items ADD COLUMN cloudflare_last_error TEXT NULL AFTER cloudflare_nameservers_json;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_provisioning_items' AND COLUMN_NAME = 'dns_records_json'
    ) THEN
        ALTER TABLE customer_provisioning_items ADD COLUMN dns_records_json LONGTEXT NULL AFTER cloudflare_last_error;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_provisioning_items' AND COLUMN_NAME = 'dns_attempts'
    ) THEN
        ALTER TABLE customer_provisioning_items ADD COLUMN dns_attempts INT UNSIGNED NOT NULL DEFAULT 0 AFTER dns_records_json;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_provisioning_items' AND COLUMN_NAME = 'dns_last_attempt_at'
    ) THEN
        ALTER TABLE customer_provisioning_items ADD COLUMN dns_last_attempt_at DATETIME NULL AFTER dns_attempts;
    END IF;
END $$

DELIMITER ;

CALL add_provisioning_automation_columns();
DROP PROCEDURE IF EXISTS add_provisioning_automation_columns;

CREATE TABLE IF NOT EXISTS provisioning_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_order_id INT UNSIGNED NULL,
    provisioning_item_id INT UNSIGNED NULL,
    item_type ENUM('domain', 'hosting', 'payment', 'dns', 'whmcs') NOT NULL DEFAULT 'whmcs',
    event VARCHAR(120) NOT NULL,
    status VARCHAR(40) NOT NULL,
    message TEXT NULL,
    context_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    INDEX provisioning_logs_order_index (customer_order_id),
    INDEX provisioning_logs_item_index (provisioning_item_id),
    INDEX provisioning_logs_event_index (event),
    CONSTRAINT provisioning_logs_order_fk FOREIGN KEY (customer_order_id) REFERENCES customer_orders(id) ON DELETE SET NULL,
    CONSTRAINT provisioning_logs_item_fk FOREIGN KEY (provisioning_item_id) REFERENCES customer_provisioning_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value, updated_at) VALUES
('whm_hostname', '', NOW()),
('whm_username', '', NOW()),
('whm_api_token', '', NOW()),
('default_hosting_server_ip', '', NOW()),
('cpanel_login_url', '', NOW()),
('cloudflare_account_id', '', NOW()),
('cloudflare_ssl_mode', 'full', NOW()),
('default_mx_records', '', NOW()),
('default_spf_record', '', NOW()),
('default_dkim_records', '', NOW())
ON DUPLICATE KEY UPDATE setting_value = setting_value;
