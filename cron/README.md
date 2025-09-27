# Payment Status Check Cron Job Setup

This directory contains the payment status monitoring system for tenant subscriptions.

## Files

- `check_payment_status.php` - Main cron job script that checks and updates tenant payment statuses

## Setup Instructions

### 1. Database Setup
Run the following SQL commands to add payment status tracking to the tenants table:

```sql
ALTER TABLE tenants
ADD COLUMN payment_status enum('current','warning','overdue','suspended') NOT NULL DEFAULT 'current' AFTER chat_default_auto_download,
ADD COLUMN payment_due_date date DEFAULT NULL AFTER payment_status,
ADD COLUMN last_payment_date date DEFAULT NULL AFTER payment_due_date,
ADD COLUMN payment_warning_sent tinyint(1) NOT NULL DEFAULT 0 AFTER last_payment_date;
```

### 2. Create Subscription Payments Table
Run the SQL from `../create_subscription_payments_table.sql` to create the subscription_payments table.

### 3. Set Up Cron Job

#### On Linux/Unix Systems:
Add the following line to your crontab (run `crontab -e`):

```
# Run payment status check daily at 2 AM
0 2 * * * /usr/bin/php /path/to/your/project/cron/check_payment_status.php >> /path/to/your/project/logs/payment_status.log 2>&1
```

#### On Windows (Task Scheduler):
1. Open Task Scheduler
2. Create a new task
3. Set trigger to "Daily" at your preferred time (e.g., 2:00 AM)
4. Set action to "Start a program"
5. Program/script: `C:\xampp\php\php.exe`
6. Add arguments: `C:\xampp\htdocs\almoqadas\cron\check_payment_status.php`
7. Start in: `C:\xampp\htdocs\almoqadas`

### 4. Log File Setup
Create a logs directory and ensure the web server can write to it:

```bash
mkdir logs
chmod 755 logs
```

## How It Works

### Payment Status Logic:
- **Current**: Payment is up to date (more than 3 days until due date)
- **Warning**: Payment due within 3 days - sends notification
- **Overdue**: Payment past due date but less than 5 days
- **Suspended**: Payment more than 5 days overdue - blocks tenant access

### Notification System:
- Sends daily notifications to tenant admins when payment is due within 3 days
- Continues sending daily notifications for overdue payments (up to 5 days past due)
- Tracks last notification time to ensure notifications are sent at most once per day
- Stops notifications when payment becomes current or tenant is suspended

### Access Control:
- Tenants with "suspended" status are automatically blocked from accessing the system
- Users are redirected to `payment_required.php` page
- Access is restored when payment is recorded by super admin

## Testing

To test the cron job manually:

```bash
cd /path/to/your/project
php cron/check_payment_status.php
```

Check the output for any errors and verify that tenant statuses are updated correctly.

## Monitoring

Monitor the log file for any issues:

```bash
tail -f logs/payment_status.log
```

## Troubleshooting

### Common Issues:

1. **Database Connection Errors**: Ensure database credentials are correct in `config.php`
2. **Permission Errors**: Ensure the web server can write to the logs directory
3. **PHP Path Issues**: Verify the PHP executable path in cron setup
4. **Timezone Issues**: Ensure server timezone is set correctly for date calculations

### Manual Status Updates:

If needed, you can manually update tenant payment status:

```sql
UPDATE tenants SET payment_status = 'current' WHERE id = [tenant_id];