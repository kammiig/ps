SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS customer_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    whmcs_client_id INT UNSIGNED NULL UNIQUE,
    first_name VARCHAR(120) NOT NULL,
    last_name VARCHAR(120) NOT NULL,
    company_name VARCHAR(160) NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(80) NULL,
    address VARCHAR(255) NULL,
    city VARCHAR(120) NULL,
    state VARCHAR(120) NULL,
    postcode VARCHAR(40) NULL,
    country CHAR(2) NOT NULL DEFAULT 'GB',
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX customer_users_email_index (email),
    INDEX customer_users_whmcs_index (whmcs_client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    INDEX customer_password_resets_user_index (customer_user_id),
    CONSTRAINT customer_password_resets_user_fk FOREIGN KEY (customer_user_id) REFERENCES customer_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_rate_limits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(80) NOT NULL,
    identifier VARCHAR(190) NOT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY customer_rate_limits_action_identifier_unique (action, identifier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_token CHAR(64) NOT NULL UNIQUE,
    customer_user_id INT UNSIGNED NULL,
    whmcs_client_id INT UNSIGNED NOT NULL,
    whmcs_order_id INT UNSIGNED NULL,
    whmcs_invoice_id INT UNSIGNED NOT NULL UNIQUE,
    invoice_amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'GBP',
    order_type VARCHAR(32) NULL,
    selected_domain VARCHAR(255) NULL,
    hosting_plan_slug VARCHAR(120) NULL,
    package_label VARCHAR(190) NULL,
    billing_cycle VARCHAR(32) NULL,
    payment_status ENUM('pending', 'processing', 'paid', 'failed') NOT NULL DEFAULT 'pending',
    stripe_payment_intent_id VARCHAR(120) NULL UNIQUE,
    stripe_payment_reference VARCHAR(160) NULL,
    last_error TEXT NULL,
    paid_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX customer_orders_customer_index (customer_user_id),
    INDEX customer_orders_whmcs_client_index (whmcs_client_id),
    INDEX customer_orders_status_index (payment_status),
    CONSTRAINT customer_orders_user_fk FOREIGN KEY (customer_user_id) REFERENCES customer_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stripe_webhook_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stripe_event_id VARCHAR(160) NOT NULL UNIQUE,
    event_type VARCHAR(120) NOT NULL,
    payment_intent_id VARCHAR(120) NULL,
    customer_order_id INT UNSIGNED NULL,
    processing_status ENUM('received', 'processed', 'failed') NOT NULL DEFAULT 'received',
    created_at DATETIME NOT NULL,
    processed_at DATETIME NULL,
    INDEX stripe_webhook_events_payment_intent_index (payment_intent_id),
    CONSTRAINT stripe_webhook_events_order_fk FOREIGN KEY (customer_order_id) REFERENCES customer_orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value, updated_at)
VALUES ('whmcs_payment_gateway_name', '', NOW())
ON DUPLICATE KEY UPDATE setting_value = setting_value, updated_at = updated_at;
