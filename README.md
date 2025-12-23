# RBL Monitor

A comprehensive Real-time Blackhole List (RBL) monitoring system that allows you to monitor up to 250 IP addresses per account against multiple RBLs.

## Features

- **IP Management**: Add up to 250 IP addresses per account with optional labels
- **RBL Monitoring**: Checks against 29+ RBLs (based on rblmon.com's monitored list)
- **Rate Limiting**: Built-in rate limiting to respect RBL query policies
- **Smart Filtering**: Automatically excludes paid RBLs
- **Reports**: 
  - Daily or weekly email reports
  - HTML and CSV report formats
  - Only shows blacklisted IPs
  - Sorted by number of listings (most listed first)
- **Real-time Checking**: Manual or scheduled checks
- **User Management**: Registration and authentication system

## Installation

1. **Database Setup**:
   ```bash
   mysql -u root -p < database.sql
   ```

2. **Configuration**:
   Edit `config.php` and update:
   - Database credentials
   - Email settings (SMTP)
   - Application settings

3. **Web Server**:
   - Point your web server document root to this directory
   - Ensure PHP has DNS functions enabled
   - Recommended: PHP 7.4+ with PDO MySQL extension

4. **Cron Jobs** (Optional but recommended):
   ```bash
   # Check IPs daily at 2 AM
   0 2 * * * /usr/bin/php /path/to/WPRBLWatcher/check_ips.php
   
   # Send reports daily at 8 AM
   0 8 * * * /usr/bin/php /path/to/WPRBLWatcher/send_reports.php
   ```

## Usage

1. **Register/Login**: Create an account or login at `login.php`
2. **Add IPs**: Add IP addresses to monitor (up to 250 per account)
3. **Run Checks**: Click "Run RBL Check Now" or wait for scheduled checks
4. **View Reports**: View blacklisted IPs in the dashboard or download CSV reports
5. **Configure Preferences**: Set report frequency (daily/weekly) and email notifications

## RBLs Monitored

The system monitors reliable RBLs from rblmon.com's list, excluding those known for false positives:
- Barracuda
- SpamCop
- SORBS (multiple lists)
- SpamRats
- Spamhaus (XBL, ZEN)
- DroneBL
- And many more...

**Note:** UCEPROTECT and other false-positive prone RBLs have been excluded for accuracy. Paid RBLs are automatically excluded. Rate limiting is applied per RBL to respect query policies.

## File Structure

- `config.php` - Configuration and RBL list
- `db.php` - Database connection class
- `auth.php` - Authentication and session management
- `rbl_checker.php` - RBL checking logic
- `reports.php` - Report generation
- `index.php` - Main dashboard
- `login.php` - Login/registration page
- `report.php` - Detailed report view
- `check_ips.php` - Cron script for checking IPs
- `send_reports.php` - Cron script for sending reports
- `database.sql` - Database schema

## Security Notes

- Passwords are hashed using PHP's `password_hash()`
- SQL injection protection via PDO prepared statements
- Session management with timeout
- Input validation for IP addresses

## Requirements

- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB 10.2+
- PHP extensions: PDO, PDO_MySQL, DNS functions
- Web server (Apache/Nginx)

## License

This project is provided as-is for monitoring purposes.

