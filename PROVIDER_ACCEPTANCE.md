# Controlled provider acceptance

Automated tests do not contact Semaphore or Stability AI. Run these checks only with owner approval and valid private `.env` credentials.

## Semaphore OTP and messages

1. Confirm `SEMAPHORE_SENDER_NAME=FINGERLINGS`, the API key, and `SMS_ENABLED=true` in the private `.env`.
2. Register one approved test customer and verify the received OTP. Do not record the OTP in screenshots or logs.
3. Queue one custom order message from staff order management.
4. Preview pending messages without delivery:
   `C:\xampp\php\php.exe C:\xampp\htdocs\AI-CAKE\cli\dispatch_notifications.php --dry-run`
5. When ready, run the same command without `--dry-run` once and confirm the provider ID/status appears in `notification_outbox`.

## Stability AI (one paid request)

1. Log in as the approved test customer and open `/AI-CAKE/ai_cake.php`.
2. Use a short, defense-safe cake description and click Generate exactly once.
3. Confirm a preview is returned. Save it, then verify that the saved design belongs only to that customer.
4. Do not repeat the request unless the owner explicitly approves another paid generation.

## Safety

- Never paste API keys, OTP values, or raw provider errors into a defense slide.
- Re-disable `SMS_ENABLED` after testing if the local machine should not send messages.
- GCash/provider payment acceptance is outside this phase.
