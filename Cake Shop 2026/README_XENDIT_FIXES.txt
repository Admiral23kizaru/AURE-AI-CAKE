XENDIT LIVE GCASH FIX PACKAGE

What I changed:
1. Removed the wrong live-mode ₱100 minimum block. Xendit GCash supports PHP 1 minimum.
2. Added better error logging for Xendit API HTTP responses.
3. Updated normal and AI GCash invoice creation to save payment_invoice_id and keep payment_status=pending.
4. Added webhook handling for BOTH tables: orders and ai_cake_orders.
5. Added optional x-callback-token verification for safer live webhooks.
6. Added SQL migration for missing columns used by your PHP code.

Files to upload/replace:
- inc/xendit.php
- gcash_xendit.php
- ai_gcash_xendit.php
- xendit_webhook.php

SQL to run in phpMyAdmin:
- sql/001_xendit_required_columns.sql

Important setup:
1. In inc/xendit.php, replace PASTE_YOUR_XENDIT_LIVE_SECRET_KEY_HERE with your Xendit LIVE secret key.
2. Add your Xendit callback token in inc/xendit.php if available.
3. In Xendit Dashboard, set live webhook/callback URL to:
   https://auresanchez.shop/xendit_webhook.php
4. Test a real GCash payment using PHP 1.
5. After payment, check logs/xendit_webhook.log and the order payment_status.

Security warning:
Your uploaded ZIP contains real credentials in PHP config files. Rotate/change your DB password and API keys after testing.
