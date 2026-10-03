# Windows Task Scheduler

Create these two tasks after deployment. Run them every 15 minutes under the account that can read the private `.env` file:

```text
C:\xampp\php\php.exe C:\xampp\htdocs\AI-CAKE\cli\dispatch_notifications.php
C:\xampp\php\php.exe C:\xampp\htdocs\AI-CAKE\cli\queue_pickup_reminders.php
```

Before enabling delivery, inspect the queue without contacting Semaphore:

```text
C:\xampp\php\php.exe C:\xampp\htdocs\AI-CAKE\cli\dispatch_notifications.php --dry-run
```

The reminder script is idempotent through the unique event key. The dispatcher retries provider failures at bounded intervals and stops after five attempts.
Both commands use a non-blocking process lock, so a delayed run cannot overlap the next scheduled run. In Task Scheduler, also select "Do not start a new instance" and set the working directory to `C:\xampp\htdocs\AI-CAKE`.
