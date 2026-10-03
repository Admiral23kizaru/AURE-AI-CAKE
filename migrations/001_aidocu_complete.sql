CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(100) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('customer','staff','admin') NOT NULL DEFAULT 'customer',
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  phone_verified_at DATETIME DEFAULT NULL,
  last_login_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email), UNIQUE KEY uq_users_phone (phone), KEY ix_users_role_active (role,is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE admins ADD COLUMN IF NOT EXISTS user_id BIGINT UNSIGNED NULL;
ALTER TABLE admins ADD UNIQUE KEY IF NOT EXISTS uq_admins_user (user_id);

CREATE TABLE IF NOT EXISTS otp_challenges (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED DEFAULT NULL,
  purpose ENUM('registration','phone_change','legacy_claim') NOT NULL,
  destination VARCHAR(30) NOT NULL,
  email_key VARCHAR(190) DEFAULT NULL,
  code_hash VARCHAR(255) NOT NULL,
  attempts_left TINYINT UNSIGNED NOT NULL DEFAULT 5,
  expires_at DATETIME NOT NULL,
  resend_after DATETIME NOT NULL,
  provider_message_id VARCHAR(100) DEFAULT NULL,
  consumed_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_otp_rate (destination,created_at), KEY ix_otp_user (user_id,purpose,consumed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_headers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(100) NOT NULL,
  user_id BIGINT UNSIGNED DEFAULT NULL,
  normal_order_id INT DEFAULT NULL,
  ai_order_id INT UNSIGNED DEFAULT NULL,
  order_type ENUM('normal','ai') NOT NULL,
  customer_name VARCHAR(255) NOT NULL,
  customer_email VARCHAR(190) DEFAULT NULL,
  customer_phone VARCHAR(32) DEFAULT NULL,
  status ENUM('Pending','Confirmed','Preparing','Ready for Pickup','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  pickup_date DATE DEFAULT NULL,
  pickup_time TIME DEFAULT NULL,
  store_id INT UNSIGNED DEFAULT NULL,
  store_name VARCHAR(255) DEFAULT NULL,
  payment_method ENUM('cash','gcash') NOT NULL DEFAULT 'cash',
  payment_status ENUM('unpaid','pending','paid','failed','refunded') NOT NULL DEFAULT 'unpaid',
  payment_reference VARCHAR(255) DEFAULT NULL,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  original_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  customer_note TEXT DEFAULT NULL,
  cancellation_reason VARCHAR(500) DEFAULT NULL,
  cancelled_by BIGINT UNSIGNED DEFAULT NULL,
  cancelled_at DATETIME DEFAULT NULL,
  refund_state ENUM('none','refund_required','refunded') NOT NULL DEFAULT 'none',
  claim_code_hash VARCHAR(255) DEFAULT NULL,
  claim_code_cipher TEXT DEFAULT NULL,
  claimed_at DATETIME DEFAULT NULL,
  completed_at DATETIME DEFAULT NULL,
  reservations_restored_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_header_number (order_number), UNIQUE KEY uq_header_normal (normal_order_id), UNIQUE KEY uq_header_ai (ai_order_id),
  KEY ix_header_user (user_id,created_at), KEY ix_header_pickup (pickup_date,pickup_time,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_header_id BIGINT UNSIGNED NOT NULL,
  from_status VARCHAR(40) DEFAULT NULL, to_status VARCHAR(40) NOT NULL, actor_user_id BIGINT UNSIGNED DEFAULT NULL,
  note VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_history_order (order_header_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pickup_capacities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, pickup_date DATE NOT NULL, pickup_time TIME NOT NULL,
  capacity INT UNSIGNED NOT NULL, reserved INT UNSIGNED NOT NULL DEFAULT 0, active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pickup_slot (pickup_date,pickup_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_capacities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, pickup_date DATE NOT NULL, size ENUM('Small','Medium','Large') NOT NULL,
  capacity INT UNSIGNED NOT NULL, reserved INT UNSIGNED NOT NULL DEFAULT 0, active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ai_capacity (pickup_date,size)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_reservations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_header_id BIGINT UNSIGNED NOT NULL,
  resource_type ENUM('cake','addon','option','pickup_slot','ai_capacity') NOT NULL, resource_id BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL, restored_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_order_resource (order_header_id,resource_type,resource_id), KEY ix_restore (order_header_id,restored_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cake_options (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, option_type ENUM('Flavor','Size','Design','Add-on') NOT NULL,
  name VARCHAR(150) NOT NULL, price_adjustment DECIMAL(10,2) NOT NULL DEFAULT 0, stock INT DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_option (option_type,name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cake_option_compatibility (
  cake_id INT NOT NULL, option_id BIGINT UNSIGNED NOT NULL, is_required TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (cake_id,option_id), KEY ix_compat_option (option_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_item_options (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_item_id INT NOT NULL, option_id BIGINT UNSIGNED DEFAULT NULL,
  option_type VARCHAR(30) NOT NULL, option_name VARCHAR(150) NOT NULL, price_snapshot DECIMAL(10,2) NOT NULL DEFAULT 0,
  quantity INT UNSIGNED NOT NULL DEFAULT 1, KEY ix_item_options (order_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_discounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_header_id BIGINT UNSIGNED NOT NULL,
  discount_type ENUM('fixed','percentage') NOT NULL, discount_value DECIMAL(12,2) NOT NULL,
  original_total DECIMAL(12,2) NOT NULL, discount_amount DECIMAL(12,2) NOT NULL, final_total DECIMAL(12,2) NOT NULL,
  reason VARCHAR(500) NOT NULL, applied_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_discount_order (order_header_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, order_header_id BIGINT UNSIGNED NOT NULL,
  order_item_id INT NOT NULL, cake_id INT NOT NULL, rating TINYINT UNSIGNED NOT NULL, comment TEXT DEFAULT NULL,
  is_verified TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_review_item (order_item_id), KEY ix_review_cake (cake_id,is_verified,rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recommendation_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, cake_id INT NOT NULL, action ENUM('qualified','removed') NOT NULL,
  verified_review_count INT UNSIGNED NOT NULL, average_rating DECIMAL(4,2) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_rec_log (cake_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS favorites (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, favorite_type ENUM('cake','ai_design') NOT NULL,
  cake_id INT DEFAULT NULL, ai_image VARCHAR(255) DEFAULT NULL, ai_prompt TEXT DEFAULT NULL, ai_size VARCHAR(30) DEFAULT NULL,
  description TEXT DEFAULT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_favorite_cake (user_id,favorite_type,cake_id), KEY ix_favorites_user (user_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_outbox (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, event_key VARCHAR(190) NOT NULL, channel ENUM('sms') NOT NULL DEFAULT 'sms',
  recipient VARCHAR(30) NOT NULL, template_name VARCHAR(80) NOT NULL, template_data LONGTEXT NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0, next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  provider_message_id VARCHAR(100) DEFAULT NULL, provider_status VARCHAR(50) DEFAULT NULL, last_error VARCHAR(255) DEFAULT NULL,
  final_state ENUM('pending','sent','failed','disabled') NOT NULL DEFAULT 'pending', sent_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_event_key (event_key), KEY ix_outbox_dispatch (final_state,next_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE stores ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1;

INSERT IGNORE INTO order_headers (order_number,normal_order_id,order_type,customer_name,customer_email,customer_phone,status,pickup_date,pickup_time,store_name,payment_method,payment_status,subtotal,original_total,total,created_at)
SELECT order_number,id,'normal',COALESCE(customer_name,''),customer_email,customer_phone,
 CASE LOWER(status) WHEN 'confirmed' THEN 'Confirmed' WHEN 'processing' THEN 'Preparing' WHEN 'ready' THEN 'Ready for Pickup' WHEN 'completed' THEN 'Completed' WHEN 'cancelled' THEN 'Cancelled' ELSE 'Pending' END,
 pickup_date,pickup_time,store_name,IF(LOWER(payment)='gcash','gcash','cash'),payment_status,total,total,total,created_at FROM orders WHERE order_number IS NOT NULL;

INSERT IGNORE INTO order_headers (order_number,ai_order_id,order_type,customer_name,customer_email,customer_phone,status,pickup_date,pickup_time,store_name,payment_method,payment_status,payment_reference,subtotal,original_total,total,refund_state,created_at)
SELECT order_number,id,'ai',customer_name,customer_email,customer_number,
 CASE LOWER(status) WHEN 'confirmed' THEN 'Confirmed' WHEN 'processing' THEN 'Preparing' WHEN 'ready' THEN 'Ready for Pickup' WHEN 'completed' THEN 'Completed' WHEN 'cancelled' THEN 'Cancelled' ELSE 'Pending' END,
 pickup_date,CAST(pickup_time AS TIME),store_name,IF(LOWER(payment)='gcash','gcash','cash'),IF(payment_status='pending','pending',payment_status),payment_reference,total,total,total,IF(refund_required=1,'refund_required','none'),created_at FROM ai_cake_orders;

INSERT IGNORE INTO order_status_history (order_header_id,to_status,note,created_at)
SELECT id,status,'Imported from legacy order',created_at FROM order_headers;

CREATE OR REPLACE VIEW recommended_cakes AS
SELECT c.*, COUNT(r.id) AS verified_review_count, ROUND(AVG(r.rating),2) AS average_rating
FROM cakes c JOIN reviews r ON r.cake_id=c.id AND r.is_verified=1
GROUP BY c.id HAVING COUNT(r.id)>=50 AND AVG(r.rating)>8.0;
