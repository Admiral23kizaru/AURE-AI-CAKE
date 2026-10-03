ALTER TABLE otp_challenges
  ADD COLUMN IF NOT EXISTS provider_status VARCHAR(50) NULL AFTER provider_message_id,
  ADD COLUMN IF NOT EXISTS last_error VARCHAR(100) NULL AFTER provider_status,
  ADD COLUMN IF NOT EXISTS send_succeeded TINYINT(1) NOT NULL DEFAULT 0 AFTER last_error,
  ADD COLUMN IF NOT EXISTS sent_at DATETIME NULL AFTER send_succeeded,
  ADD INDEX IF NOT EXISTS ix_otp_success_rate (destination,send_succeeded,created_at);

UPDATE otp_challenges
SET send_succeeded=1,
    sent_at=COALESCE(sent_at,created_at)
WHERE provider_message_id IS NOT NULL AND provider_message_id<>'';
