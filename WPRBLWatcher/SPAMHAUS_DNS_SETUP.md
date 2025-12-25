# Setting Up Local DNS Resolver to Avoid Spamhaus Errors

## Problem

When performing RBL (DNSBL) lookups, you may encounter the error code `127.255.255.254` from Spamhaus. This error occurs when:

1. DNS queries are made through public/open DNS resolvers (e.g., Google DNS at 8.8.8.8, Cloudflare at 1.1.1.1)
2. Your server's IP has generic/unattributable reverse DNS (rDNS)

Spamhaus enforces this to track usage and prevent abuse of their free public mirrors. **No allowlisting of your server is required**—it's about changing how queries are made.

## Solution: Set Up a Local DNS Resolver

The recommended solution is to set up a local DNS resolver on your server (like Unbound or dnsmasq) and configure the plugin to use it. This ensures queries come directly from your server's IP, making them attributable to Spamhaus.

## Step 1: Install a Local DNS Resolver

### Option A: Unbound (Recommended)

Unbound is a lightweight, secure, validating DNS resolver.

**On Ubuntu/Debian:**
```bash
sudo apt update
sudo apt install unbound
```

**On CentOS/RHEL:**
```bash
sudo yum install unbound
# or for newer versions:
sudo dnf install unbound
```

### Option B: dnsmasq

dnsmasq is a lightweight DNS forwarder.

**On Ubuntu/Debian:**
```bash
sudo apt update
sudo apt install dnsmasq
```

**On CentOS/RHEL:**
```bash
sudo yum install dnsmasq
# or for newer versions:
sudo dnf install dnsmasq
```

## Step 2: Configure the Resolver

### For Unbound:

1. Edit the configuration file:
   ```bash
   sudo nano /etc/unbound/unbound.conf
   ```

2. Add or modify these settings:
   ```yaml
   server:
       # Listen on localhost
       interface: 127.0.0.1
       access-control: 127.0.0.0/8 allow
       
       # IMPORTANT: Do NOT forward to public DNS servers (8.8.8.8, 1.1.1.1)
       # Unbound should do recursive queries directly to authoritative DNS servers
       # This ensures queries originate from your server's IP, not a public resolver
       # Remove or comment out any forward-zone sections that forward to public DNS
       
       # Optional: Use root hints for recursive resolution (default behavior)
       # Unbound will query root DNS servers and follow the DNS hierarchy
   ```
   
   **CRITICAL:** If you have `forward-zone` configured to forward to public DNS servers (8.8.8.8, 1.1.1.1, etc.), Spamhaus will still see queries as coming from those public resolvers and return `127.255.255.254`. You must remove or disable forwarding to public DNS servers.

3. Start and enable Unbound:
   ```bash
   sudo systemctl start unbound
   sudo systemctl enable unbound
   ```

4. Test the resolver:
   ```bash
   dig @127.0.0.1 google.com
   ```

### For dnsmasq:

1. Edit the configuration file:
   ```bash
   sudo nano /etc/dnsmasq.conf
   ```

2. Add or modify these settings:
   ```
   # Listen on localhost only
   listen-address=127.0.0.1
   
   # IMPORTANT: Do NOT use public DNS servers (8.8.8.8, 1.1.1.1)
   # dnsmasq should forward to your ISP's DNS or do recursive queries
   # For Spamhaus compatibility, queries must originate from your server's IP
   # Comment out or remove any server= lines pointing to public DNS
   
   # Option 1: Use your ISP's DNS (if available)
   # server=YOUR_ISP_DNS_IP
   
   # Option 2: Use system's default DNS resolver
   # (dnsmasq will use /etc/resolv.conf upstream servers)
   ```
   
   **CRITICAL:** If dnsmasq is configured to forward to public DNS servers (8.8.8.8, 1.1.1.1, etc.), Spamhaus will still see queries as coming from those public resolvers and return `127.255.255.254`. You must configure dnsmasq to use your ISP's DNS or the system's default resolver, NOT public DNS servers.

3. Start and enable dnsmasq:
   ```bash
   sudo systemctl start dnsmasq
   sudo systemctl enable dnsmasq
   ```

4. Test the resolver:
   ```bash
   dig @127.0.0.1 google.com
   ```

## Step 3: Configure Reverse DNS (rDNS)

**IMPORTANT:** Ensure your server's public IP has proper reverse DNS (PTR record) set. Contact your hosting provider to configure it.

1. **Check current rDNS:**
   ```bash
   dig -x YOUR_SERVER_IP
   ```

2. **Request rDNS update from your hosting provider:**
   - AWS: Use Route 53 or EC2 Elastic IP settings
   - DigitalOcean: Use Networking → Domains → Reverse DNS
   - Linode: Use Networking → IPs → Reverse DNS
   - Other providers: Contact support

3. **Set rDNS to something meaningful:**
   - Good: `server.yourdomain.com`
   - Bad: `ec2-x-x-x-x.compute.amazonaws.com` (generic)

4. **Set forward DNS (A record) to match:**
   - Create an A record: `server.yourdomain.com` → `YOUR_SERVER_IP`

## Step 4: Configure the Plugin

### For WordPress Plugin:

Edit `wp-config.php` or the plugin's main file (`wprbl-watcher.php`) and set:

```php
define('WPRBL_DNS_SERVER', '127.0.0.1');
```

### For Standalone Version:

Edit `config.php` and set:

```php
define('DNS_SERVER', '127.0.0.1');
```

## Step 5: Verify the Setup

1. **Test DNS resolution:**
   ```bash
   dig @127.0.0.1 2.0.0.127.zen.spamhaus.org
   ```

2. **Check your server's public IP:**
   ```bash
   curl ifconfig.me
   # or
   curl ipinfo.io/ip
   ```

3. **Verify rDNS:**
   ```bash
   dig -x YOUR_SERVER_IP
   ```

4. **Run a test RBL check** through the plugin and verify it no longer returns `127.255.255.254` errors.

## Troubleshooting

### Resolver not responding:
- Check if the service is running: `sudo systemctl status unbound` (or `dnsmasq`)
- Check firewall rules: Ensure port 53 is open on localhost
- Check logs: `sudo journalctl -u unbound -f` (or `dnsmasq`)

### Still getting 127.255.255.254:
- **MOST COMMON ISSUE:** Your local resolver is forwarding queries to public DNS servers (8.8.8.8, 1.1.1.1, etc.)
  - Check Unbound config: Remove any `forward-zone` sections that forward to public DNS
  - Check dnsmasq config: Remove any `server=8.8.8.8` or `server=1.1.1.1` lines
  - The resolver must do recursive queries directly to authoritative DNS servers
- Verify the plugin is using the local resolver (check configuration)
- Ensure rDNS is properly configured (not generic)
- Check if queries are actually going through the local resolver (use tcpdump: `sudo tcpdump -i lo port 53`)
- Verify queries originate from your server's IP, not a public resolver IP

### DNS queries timing out:
- Check upstream DNS servers are reachable
- Verify network connectivity
- Check resolver logs for errors

## Alternative: System-Wide DNS Configuration

If you prefer to configure DNS system-wide instead of per-application:

### On systemd systems (Ubuntu 18.04+, Debian 9+, CentOS 7+):

Edit `/etc/systemd/resolved.conf`:
```ini
[Resolv]
DNS=127.0.0.1
Domains=~.
```

Then restart:
```bash
sudo systemctl restart systemd-resolved
```

### On traditional systems:

Edit `/etc/resolv.conf`:
```
nameserver 127.0.0.1
```

**Note:** On systemd systems, `/etc/resolv.conf` may be managed automatically. Use `systemd-resolved` configuration instead.

## Fair Use Policy

Spamhaus's free public mirrors are intended for:
- Small-scale use (typically under 100,000 queries/day)
- Non-profits
- Small businesses

If your query volume is high, consider:
- Using Spamhaus's paid query service
- Implementing caching to reduce query volume
- Contacting Spamhaus for commercial licensing

## Additional Resources

- [Unbound Documentation](https://www.nlnetlabs.nl/documentation/unbound/)
- [dnsmasq Documentation](http://www.thekelleys.org.uk/dnsmasq/doc.html)
- [Spamhaus Query Policy](https://www.spamhaus.org/faq/section/Spamhaus%20DNSBL%20Usage)

## Summary

1. ✅ Install Unbound or dnsmasq
2. ✅ Configure to listen on 127.0.0.1
3. ✅ Set up proper rDNS for your server IP
4. ✅ Configure plugin to use `127.0.0.1` as DNS server
5. ✅ Test and verify

This setup ensures all DNS queries (including RBL lookups) originate from your server's IP address with proper attribution, resolving the Spamhaus `127.255.255.254` error.

