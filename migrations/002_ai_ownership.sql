ALTER TABLE ai_cake_orders ADD COLUMN IF NOT EXISTS user_id BIGINT UNSIGNED NULL;
ALTER TABLE ai_cake_orders ADD COLUMN IF NOT EXISTS cake_size ENUM('Small','Medium','Large') DEFAULT NULL;
ALTER TABLE ai_cake_orders ADD COLUMN IF NOT EXISTS customer_note TEXT DEFAULT NULL;
ALTER TABLE ai_cake_orders ADD COLUMN IF NOT EXISTS placed_at DATETIME DEFAULT NULL;
ALTER TABLE ai_cake_orders ADD KEY IF NOT EXISTS ix_ai_user (user_id,created_at);
