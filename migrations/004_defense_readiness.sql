ALTER TABLE order_headers
  ADD COLUMN IF NOT EXISTS created_by_user_id INT NULL AFTER user_id,
  ADD COLUMN IF NOT EXISTS order_source ENUM('customer','staff_assisted') NOT NULL DEFAULT 'customer' AFTER order_type,
  ADD INDEX IF NOT EXISTS ix_order_creator (created_by_user_id,created_at);

ALTER TABLE notification_outbox
  ADD COLUMN IF NOT EXISTS actor_user_id INT NULL AFTER recipient,
  ADD COLUMN IF NOT EXISTS reason VARCHAR(255) NULL AFTER template_name,
  ADD INDEX IF NOT EXISTS ix_notification_actor (actor_user_id,created_at);

ALTER TABLE addons
  ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER name;

CREATE TABLE IF NOT EXISTS addon_inventory_adjustments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  addon_id INT UNSIGNED NOT NULL,
  quantity_change INT NOT NULL,
  resulting_stock INT NOT NULL,
  reason VARCHAR(255) NOT NULL,
  actor_user_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX ix_addon_adjustment (addon_id,created_at),
  CONSTRAINT fk_addon_adjustment_addon FOREIGN KEY (addon_id) REFERENCES addons(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO addon_inventory_adjustments(addon_id,quantity_change,resulting_stock,reason,actor_user_id,created_at)
SELECT a.id,COALESCE(i.quantity,a.quantity),COALESCE(i.quantity,a.quantity),'Opening balance imported by migration',NULL,NOW()
FROM addons a
LEFT JOIN inventory_addons i ON i.addon_id=a.id
WHERE NOT EXISTS (SELECT 1 FROM addon_inventory_adjustments x WHERE x.addon_id=a.id);

ALTER TABLE inventory_adjustments CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE inventory_adjustments
  ADD COLUMN IF NOT EXISTS adjustment_type ENUM('opening','purchase','sale','cancellation','correction') NOT NULL DEFAULT 'correction' AFTER qty_change,
  ADD COLUMN IF NOT EXISTS resulting_stock INT NULL AFTER adjustment_type,
  ADD COLUMN IF NOT EXISTS actor_user_id INT NULL AFTER note,
  ADD INDEX IF NOT EXISTS ix_inventory_adjustment (cake_id,created_at);

SET @inventory_fk = (
  SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='inventory_adjustments' AND REFERENCED_TABLE_NAME='cakes'
  LIMIT 1
);
SET @drop_inventory_fk = IF(@inventory_fk IS NULL,'SELECT 1',CONCAT('ALTER TABLE inventory_adjustments DROP FOREIGN KEY `',@inventory_fk,'`'));
PREPARE stmt FROM @drop_inventory_fk; EXECUTE stmt; DEALLOCATE PREPARE stmt;
ALTER TABLE inventory_adjustments
  ADD CONSTRAINT fk_inventory_adjustment_cake FOREIGN KEY (cake_id) REFERENCES cakes(id) ON DELETE RESTRICT;

CREATE TABLE IF NOT EXISTS system_settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT NULL,
  updated_by_user_id INT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings(setting_key,setting_value) VALUES
('business_prices_confirmed','0'),('task_reminder_installed','0'),('task_dispatcher_installed','0')
ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);
