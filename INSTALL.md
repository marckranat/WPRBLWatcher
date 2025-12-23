# Installation Guide

## Quick Start

1. **Database Setup**:
   ```bash
   mysql -u root -p < database.sql
   ```

2. **Configure Settings**:
   Edit `config.php`:
   - Update database credentials (DB_HOST, DB_NAME, DB_USER, DB_PASS)
   - Configure email settings for reports
   - Adjust rate limiting if needed

3. **Initialize RBLs**:
   ```bash
   php setup.php
   ```

4. **Web Server Configuration**:
   - Point your web server to this directory
   - Ensure PHP has PDO and DNS functions enabled
   - Recommended: PHP 7.4+ with MySQL/MariaDB

5. **Set Up Cron Jobs** (Recommended):
   ```bash
   # Edit crontab
   crontab -e
   
   # Add these lines (adjust paths):
   # Check IPs daily at 2 AM
   0 2 * * * /usr/bin/php /path/to/WPRBLWatcher/check_ips.php >> /var/log/rbl_check.log 2>&1
   
   # Send reports daily at 8 AM
   0 8 * * * /usr/bin/php /path/to/WPRBLWatcher/send_reports.php >> /var/log/rbl_reports.log 2>&1
   ```

6. **Access the Application**:
   - Navigate to `http://your-domain/login.php`
   - Register a new account
   - Start adding IP addresses to monitor

## Features

- ✅ Monitor up to 250 IPs per account
- ✅ Check against 29+ RBLs (based on rblmon.com)
- ✅ Automatic rate limiting
- ✅ Daily or weekly email reports
- ✅ Reports show only blacklisted IPs
- ✅ IPs sorted by number of listings (most listed first)
- ✅ CSV export available
- ✅ Manual check option

## Troubleshooting

**DNS lookups not working?**
- Ensure PHP has DNS functions enabled
- Check firewall rules for DNS queries
- Verify network connectivity

**Email reports not sending?**
- Check SMTP settings in `config.php`
- Verify PHP mail() function or configure SMTP
- Check server logs for errors

**Rate limiting issues?**
- Adjust `rate_limit_delay_ms` in `config.php` or database
- Some RBLs may require slower queries
- Monitor for rate limit errors

**Database connection errors?**
- Verify database credentials in `config.php`
- Ensure database exists and user has permissions
- Check MySQL service is running

## Security Notes

- Change default database credentials
- Use strong passwords for user accounts
- Keep PHP and MySQL updated
- Consider using HTTPS in production
- Review `.htaccess` settings for your environment

