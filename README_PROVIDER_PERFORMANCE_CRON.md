# Automated Provider Performance Updates

This document explains how to set up automated provider performance updates using cron jobs.

## Overview

The provider performance tracking system automatically calculates and updates performance metrics for all active service providers. This ensures that performance data remains current and accurate for analytics and decision-making.

## Files

- `cron_update_provider_performance.php` - Main cron job script
- `includes/provider_performance.php` - Performance calculation logic
- `admin/analytics.php` - Analytics dashboard (displays performance data)

## Setup Instructions

### 1. Server Requirements

- PHP 8.2 or higher
- MySQL database
- Cron daemon (available on Linux/Unix systems)

### 2. Configure Cron Job

Add the following line to your crontab (run `crontab -e`):

```bash
# Update provider performance metrics daily at 2 AM
0 2 * * * /usr/bin/php /path/to/your/project/cron_update_provider_performance.php >> /path/to/logs/provider_performance.log 2>&1
```

**Windows Task Scheduler Alternative:**

1. Open Task Scheduler
2. Create a new task
3. Set trigger to "Daily" at 2:00 AM
4. Set action to "Start a program"
5. Program: `C:\xampp\php\php.exe`
6. Arguments: `C:\xampp\htdocs\Bii_localFinder\cron_update_provider_performance.php`
7. Start in: `C:\xampp\htdocs\Bii_localFinder`

### 3. Log File Setup

Create a logs directory and ensure write permissions:

```bash
mkdir -p /path/to/your/project/logs
chmod 755 /path/to/your/project/logs
```

### 4. Test the Cron Job

Run the script manually to test:

```bash
cd /path/to/your/project
php cron_update_provider_performance.php
```

## What the Script Does

1. **Connects to Database** - Establishes database connection
2. **Fetches Active Providers** - Gets all active service providers
3. **Calculates Performance** - For each provider:
   - Rating and review metrics
   - Booking completion statistics
   - Response time analysis
   - Cancellation rates
   - On-time completion rates
   - Client satisfaction scores
   - Availability scores
   - Overall performance score and grade
4. **Updates Database** - Saves calculated metrics to `provider_performance` table
5. **Cleanup** - Removes performance records older than 90 days

## Performance Metrics Calculated

- **Overall Performance Score** (0-100)
- **Performance Grade** (Excellent/Good/Average/Needs Improvement)
- **Average Rating** from client reviews
- **Response Time** in hours
- **Cancellation Rate** percentage
- **On-Time Completion Rate** percentage
- **Client Satisfaction Score**
- **Availability Score** based on working hours

## Monitoring

### Check Cron Job Status

```bash
# Check if cron is running
crontab -l

# Check recent executions in log
tail -f /path/to/logs/provider_performance.log
```

### Manual Updates

You can also update performance manually via the API:

```bash
curl -X POST http://your-domain.com/api/provider_performance.php \
  -d "action=update_provider_performance&provider_id=123"
```

## Troubleshooting

### Common Issues

1. **Permission Errors**
   - Ensure PHP has write access to log files
   - Check database user permissions

2. **Memory Issues**
   - For large provider databases, increase PHP memory limit
   - Process providers in batches if needed

3. **Database Connection**
   - Verify database credentials in `config/database.php`
   - Check database server status

### Error Logs

Check the log file for detailed error messages:

```bash
tail -50 /path/to/logs/provider_performance.log
```

## Performance Optimization

- **Batch Processing**: Script processes all providers in a single run
- **Database Indexing**: Ensure proper indexes on performance tables
- **Cleanup**: Automatic removal of old records prevents table bloat

## Security Notes

- Cron script runs with web server permissions
- Database credentials are protected
- No user input processing (fully automated)