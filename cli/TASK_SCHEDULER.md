# Windows Task Scheduler

Create three tasks that run every 15 minutes with the project directory as the **Start in** folder:

- `C:\xampp\php\php.exe cli\dispatch_notifications.php`
- `C:\xampp\php\php.exe cli\queue_pickup_reminders.php`
- `C:\xampp\php\php.exe cli\expire_gcash_orders.php`

Use a Windows account that can read this project and connect to MySQL. Leave `SMS_ENABLED=false` until an approved test number and explicit live-test approval are available.
