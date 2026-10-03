ALTER TABLE otp_challenges
  DROP INDEX IF EXISTS ix_otp_success_rate,
  DROP COLUMN IF EXISTS sent_at,
  DROP COLUMN IF EXISTS send_succeeded,
  DROP COLUMN IF EXISTS last_error,
  DROP COLUMN IF EXISTS provider_status;
