# RBL Watcher

A comprehensive Real-time Blackhole List (RBL) monitoring system that allows you to monitor up to 1000 IP addresses per account against multiple reliable RBLs.

## Features

- **IP Management**: Add up to 1000 IP addresses per account with optional labels
- **RBL Monitoring**: Checks against 24 reliable RBLs
- **Rate Limiting**: Built-in rate limiting to respect RBL query policies
- **Smart Filtering**: Automatically excludes paid RBLs and unreliable/slow RBLs
- **Custom DNS Resolver**: Configurable DNS server support (Cloudflare, Google, local resolver, or system default)
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

2. **Initialize RBLs**:
   ```bash
   php setup.php
   ```
   This script populates the database with the RBL list from `config.php`.

3. **Configuration**:
   Edit `config.php` and update:
   - Database credentials (DB_HOST, DB_NAME, DB_USER, DB_PASS)
   - Email settings (SMTP) for reports
   - DNS resolver settings (optional, see DNS Resolver Configuration below)
   - Application settings (rate limiting, timeouts)

4. **Web Server**:
   - Point your web server document root to this directory
   - Ensure PHP has DNS functions enabled
   - Recommended: PHP 7.4+ with PDO MySQL extension

5. **Cron Jobs** (Optional but recommended):
   ```bash
   # Check IPs daily at 2 AM
   0 2 * * * /usr/bin/php /path/to/WPRBLWatcher/check_ips.php
   
   # Send reports daily at 8 AM
   0 8 * * * /usr/bin/php /path/to/WPRBLWatcher/send_reports.php
   ```

## Usage

1. **Register/Login**: Create an account or login at `login.php`
2. **Add IPs**: Add IP addresses to monitor (up to 1000 per account)
3. **Run Checks**: Click "Run RBL Check Now" or wait for scheduled checks
4. **View Reports**: View blacklisted IPs in the dashboard or download CSV reports
5. **Configure Preferences**: Set report frequency (daily/weekly) and email notifications

## RBLs Monitored

The system monitors 24 reliable, actively maintained RBLs. We only include RBLs that:
- Respond reliably and quickly
- Are actively maintained
- Don't have excessive false positives
- Are free to query

**Included RBLs:**
- Barracuda
- SpamCop
- SORBS (multiple specialized lists: DNSBL, Spam, SMTP, SOCKS, Web, Zombie)
- PSBL
- SpamLab
- SureSupport
- Kundenserver Relays
- Nether Relays
- MSRBL (Spam, Virus)
- SpamGuard
- IMP (Spam, Worm)
- Unsubscribe Score
- DroneBL
- Tornevall
- S5H
- HostKarma
- Anonmails

**Excluded RBLs:**
- **Spamhaus** - Requires specific DNS configuration (local resolver with proper rDNS) that many users cannot easily set up
- **SpamRats** - Unreliable DNS responses
- **NJABL** - Extremely slow response times (4+ seconds)
- **INPS** - Not responding reliably
- **Blocklist.de** - Not working reliably with the plugin's DNS lookup implementation
- **UCEPROTECT** and other false-positive prone RBLs
- **Paid RBLs** - Automatically excluded

**Note:** This project was inspired by rblmon.com's RBL monitoring approach. Rate limiting is applied per RBL to respect query policies. The RBL list is regularly updated to maintain reliability.

## File Structure

- `config.php` - Configuration and RBL list
- `db.php` - Database connection class
- `auth.php` - Authentication and session management
- `rbl_checker.php` - RBL checking logic
- `dns_lookup.php` - Custom DNS lookup class (standalone version)
- `reports.php` - Report generation
- `index.php` - Main dashboard
- `login.php` - Login/registration page
- `report.php` - Detailed report view
- `check_ips.php` - Cron script for checking IPs
- `send_reports.php` - Cron script for sending reports
- `setup.php` - RBL initialization script (run after database setup)
- `database.sql` - Database schema
- `includes/class-wprbl-dns.php` - Custom DNS lookup class (WordPress plugin version)

## Security Notes

- Passwords are hashed using PHP's `password_hash()`
- SQL injection protection via PDO prepared statements
- Session management with timeout
- Input validation for IP addresses

## DNS Resolver Configuration

The system includes a custom DNS resolver implementation that allows you to query a specific DNS server instead of using the system default resolver. This is useful for:
- **Testing different DNS providers** (Cloudflare 1.1.1.1, Google 8.8.8.8, etc.)
- **Advanced DNS configurations** (local resolver with custom upstream servers)
- **Network-specific requirements** (bypassing system DNS settings)

### Available DNS Server Options:

**Public DNS Servers:**
- **Cloudflare DNS**: `1.1.1.1` (default, recommended for reliability)
- **Google DNS**: `8.8.8.8`
- **Quad9**: `9.9.9.9`
- **OpenDNS**: `208.67.222.222`

**Local Resolver:**
- **Local resolver**: `127.0.0.1` or `localhost` (requires Unbound/dnsmasq setup - see SPAMHAUS_DNS_SETUP.md)

**System Default:**
- **System default**: `null` or empty string (uses server's default DNS resolver)

### Quick Setup:

**For Standalone Version:**
```php
// In config.php
define('DNS_SERVER', '1.1.1.1'); // Cloudflare DNS (default)
// define('DNS_SERVER', '8.8.8.8'); // Google DNS
// define('DNS_SERVER', '127.0.0.1'); // Local resolver
// define('DNS_SERVER', null); // System default
```

**For WordPress Plugin:**
```php
// In wp-config.php or wprbl-watcher.php
define('WPRBL_DNS_SERVER', '1.1.1.1'); // Cloudflare DNS (default)
// define('WPRBL_DNS_SERVER', '8.8.8.8'); // Google DNS
// define('WPRBL_DNS_SERVER', '127.0.0.1'); // Local resolver
// define('WPRBL_DNS_SERVER', null); // System default
```

**Note:** The system defaults to Cloudflare DNS (1.1.1.1) for optimal reliability. You can change this to any DNS server that works best for your environment. For Spamhaus compatibility, you may need to use a local resolver (see SPAMHAUS_DNS_SETUP.md).

## Debug Logging

To enable detailed debug logging for troubleshooting RBL lookups, add this line to your `wp-config.php` file:

```php
define('WPRBL_DEBUG', true);
```

Debug logs will be written to `wp-content/wprbl-debug.log` and include:
- DNS lookup queries being performed
- Raw DNS response details
- Validation steps for Spamhaus and other RBLs
- Final listing status and any errors

This is particularly useful for:
- Diagnosing issues with specific RBLs
- Understanding why an IP is showing as listed/not listed
- Troubleshooting DNS lookup problems
- Verifying RBL response validation

**Note:** Remember to disable debug logging in production by removing the constant or setting it to `false`, as it can generate large log files.

## Requirements

### Required:
- **PHP 7.4 or higher**
- **MySQL 5.7+ or MariaDB 10.2+**
- **PHP extensions:**
  - PDO
  - PDO_MySQL
  - DNS functions (`dns_get_record()`)
  - Socket functions (for custom DNS resolver)
- **Web server** (Apache/Nginx)
- **Command-line PHP access** (for setup script and cron jobs)

### Recommended:
- **Cron job access** for automated checks and reports
- **Local DNS resolver** (Unbound or dnsmasq) - Optional, only if you need custom DNS configuration

### Optional:
- **SMTP server** or mail service for email reports
- **HTTPS/SSL certificate** for secure access in production

## License

This project is provided as-is for monitoring purposes.

