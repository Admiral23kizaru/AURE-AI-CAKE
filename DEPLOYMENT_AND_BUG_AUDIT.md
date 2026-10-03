# AI-CAKE Deployment and Functional Bug Audit

**Audit date:** 2026-10-03  
**Scope:** Read-only review of the active `AI-CAKE` codebase and local GET-only smoke checks. No orders, SMS, Stability generations, Xendit invoices, or database data were created or changed.

## Result

The project is suitable for a controlled localhost capstone demonstration. It is **not yet ready for a public Hostinger release** until the critical deployment and GCash issues below are fixed and tested on the live domain.

## Confirmed functional bugs

### 1. GCash checkout does not clear the customer cart

`checkout.php` clears `cart_user_{user_id}` after a successful **cash** order, but the GCash branch redirects to `gcash_payment.php` / Xendit before removing that browser-local cart entry.

**Customer effect:** after a successful GCash payment and return to the system, the same cake items remain visible in the cart.

**Important:** this is a browser cart issue, not proof that the paid order or product record was deleted. Product stock is reserved when the order is created.

**Required fix:** after the server successfully creates the GCash order and returns the Xendit redirect URL, disable the button and clear only the current customer cart immediately before navigating to Xendit. Do not clear it when invoice creation fails.

### 2. Repeated GCash clicks can create duplicate pending orders

The GCash branch does not disable the Place Order button before its POST request. Each repeat request can create another pending order and reserve stock, add-ons, pickup capacity, and options.

**Required fix:** add immediate button locking and server-side idempotency for online-payment order creation.

### 3. Failed Xendit invoice creation leaves reservations held

The normal and AI GCash flows create and commit the order before the invoice is created. If Xendit rejects or fails, the order remains pending with reservations held. The expiry script only releases old unpaid orders after 24 hours; if the scheduler is disabled, this cleanup does not run.

**Required fix:** handle invoice-creation failure deterministically, restore reservations once, and make payment creation idempotent. Enable the expiry cron job in production.

### 4. Checkout wording is outdated for the Xendit flow

The active checkout still displays **Cash on hand** and **Reference number optional**. These labels do not match the current Xendit GCash invoice flow.

**Required fix:** use clear labels for Cash at Pickup and GCash via Xendit; remove manual-reference wording from the GCash path.

## Critical Hostinger deployment blockers

1. **Application base path:** active code contains `/AI-CAKE/...` paths. Deploy under `public_html/AI-CAKE/`, or make the base path configurable before deploying at the bare domain root.
2. **Live URL:** set `APP_URL` to the final HTTPS Hostinger URL. Otherwise Xendit success/failure redirects point to localhost.
3. **Xendit webhook:** configure a business-owned production secret key, callback token, public HTTPS webhook URL, and test an actual paid callback before accepting real payments.
4. **Public backup directory:** do not upload `frontend-backup-20261001-144852/`. It contains executable old PHP files and is not explicitly denied by the root `.htaccess`.
5. **Secrets:** keep `.env` private. Prefer placing it above `public_html` when Hostinger permits it; otherwise confirm HTTP access to `.env` returns 403 after deployment. Never upload real API keys in ZIP files, repositories, or screenshots.
6. **Cron jobs:** Hostinger Cron Jobs must replace Windows Task Scheduler for notification dispatch, pickup reminders, and expired GCash cleanup. Use Hostinger's PHP binary and absolute project paths every 15 minutes.
7. **Server requirements:** verify PHP 8+, PDO MySQL, cURL, mbstring, fileinfo, OpenSSL, HTTPS, and PHP write access to `uploads/` (without using `777`).
8. **Database release:** take a backup, import the intended database, then run the version-tracked migrations once. Do not run the fixture-producing `cli/system_self_test.php` against business data.

## Checks completed

- 181 active PHP files passed `php -l` syntax validation.
- Local GET-only smoke checks returned 200 for public pages and expected 302 redirects to login for protected customer, staff, and admin pages.
- A valid cake customization URL redirected to its expected next customization step, not the landing page.
- The active root `.htaccess` blocks `.env`, migrations, CLI tools, logs, SQL files, and several retired folders. Preserve this file when uploading to Hostinger.
- Static review found no confirmed customer/staff/admin role bypass in the central authorization helpers.

## Release order

1. Fix GCash cart clearing, duplicate submission, and invoice-failure reservation handling.
2. Remove or block backup/legacy files from the public hosting package.
3. Set Hostinger base URL, HTTPS, business-owned credentials, webhook token, and public callback URL.
4. Configure and test Hostinger Cron Jobs.
5. Test one normal cash order, one GCash test payment, one AI cash order, cancellation/restoration, staff completion, and receipt flow on the staging/live domain.
6. Only then enable real customer ordering and production payment credentials.
## Administrator and staff route audit

A read-only HTTP header check covered every root `admin/` and `staff/` PHP page.

- No administrator or staff route returned a 500 server error.
- Protected operational pages correctly redirect an anonymous visitor to the unified login page.
- Administrator shortcut pages that intentionally delegate to staff operations correctly route to the shared Unified Order Management, AI order, pickup schedule, chat, and report pages. Administrators are accepted by the shared `staff-or-admin` authorization check.
- No current administrator/staff button was found redirecting to the public landing page.

### Minor hardening observation

`admin/capacity_range_fields.php` is a form-field include partial and returns 200 when opened directly. It contains no mutation handler or business data, and it is not linked as a standalone page, so this is not an operational workflow break. Move it under `inc/` or explicitly deny direct web access before public deployment.

`admin/logout.php` returns HTTP 405 to a HEAD request because it is intentionally a POST-only logout action; this is expected and not a broken logout button.