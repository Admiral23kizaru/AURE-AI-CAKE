ALTER TABLE reviews DROP CONSTRAINT chk_reviews_rating;
ALTER TABLE ai_cake_orders DROP INDEX ix_ai_user_placed;

-- The expanded payment_status enum is intentionally retained on rollback.
-- Narrowing it could destroy valid cash-order states already stored as unpaid,
-- failed, or refunded.
