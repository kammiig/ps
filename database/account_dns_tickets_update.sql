SET NAMES utf8mb4;

DELIMITER $$

DROP PROCEDURE IF EXISTS planetic_account_dns_tickets_update $$
CREATE PROCEDURE planetic_account_dns_tickets_update()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_provisioning_items' AND COLUMN_NAME = 'customer_order_id'
    ) THEN
        ALTER TABLE customer_provisioning_items MODIFY customer_order_id INT UNSIGNED NULL;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'website_projects' AND COLUMN_NAME = 'customer_order_id'
    ) THEN
        ALTER TABLE website_projects MODIFY customer_order_id INT UNSIGNED NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'website_projects' AND COLUMN_NAME = 'whmcs_service_id'
    ) THEN
        ALTER TABLE website_projects ADD COLUMN whmcs_service_id INT UNSIGNED NULL AFTER whmcs_invoice_id;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'website_projects' AND COLUMN_NAME = 'whmcs_product_id'
    ) THEN
        ALTER TABLE website_projects ADD COLUMN whmcs_product_id INT UNSIGNED NULL AFTER whmcs_service_id;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_provisioning_items' AND INDEX_NAME = 'customer_provisioning_client_item_unique'
    ) THEN
        ALTER TABLE customer_provisioning_items ADD UNIQUE KEY customer_provisioning_client_item_unique (whmcs_client_id, item_type, item_key);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'website_projects' AND INDEX_NAME = 'website_projects_service_unique'
    ) THEN
        ALTER TABLE website_projects ADD UNIQUE KEY website_projects_service_unique (whmcs_client_id, whmcs_service_id);
    END IF;
END $$

DELIMITER ;

CALL planetic_account_dns_tickets_update();
DROP PROCEDURE IF EXISTS planetic_account_dns_tickets_update;

CREATE TABLE IF NOT EXISTS support_tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_ref VARCHAR(32) NOT NULL UNIQUE,
    customer_user_id INT UNSIGNED NOT NULL,
    whmcs_client_id INT UNSIGNED NULL,
    subject VARCHAR(190) NOT NULL,
    department ENUM('Domain Support', 'Hosting Support', 'Website Development', 'Billing', 'General Support') NOT NULL DEFAULT 'General Support',
    priority ENUM('Low', 'Medium', 'High', 'Urgent') NOT NULL DEFAULT 'Medium',
    status ENUM('Open', 'Answered', 'Customer Reply', 'In Progress', 'On Hold', 'Closed') NOT NULL DEFAULT 'Open',
    related_type VARCHAR(40) NULL,
    related_label VARCHAR(190) NULL,
    related_reference VARCHAR(190) NULL,
    last_customer_reply_at DATETIME NULL,
    last_admin_reply_at DATETIME NULL,
    closed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX support_tickets_customer_index (customer_user_id),
    INDEX support_tickets_status_index (status),
    INDEX support_tickets_updated_index (updated_at),
    CONSTRAINT support_tickets_customer_fk FOREIGN KEY (customer_user_id) REFERENCES customer_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_ticket_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    author_type ENUM('customer', 'admin') NOT NULL,
    author_user_id INT UNSIGNED NULL,
    author_name VARCHAR(160) NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX support_ticket_messages_ticket_index (ticket_id),
    CONSTRAINT support_ticket_messages_ticket_fk FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_ticket_attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    message_id INT UNSIGNED NOT NULL,
    uploaded_by_type ENUM('customer', 'admin') NOT NULL,
    original_name VARCHAR(190) NOT NULL,
    stored_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    INDEX support_ticket_attachments_ticket_index (ticket_id),
    INDEX support_ticket_attachments_message_index (message_id),
    CONSTRAINT support_ticket_attachments_ticket_fk FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
    CONSTRAINT support_ticket_attachments_message_fk FOREIGN KEY (message_id) REFERENCES support_ticket_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
