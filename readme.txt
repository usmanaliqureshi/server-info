=== Server Info - System Health & Diagnostics Suite ===
Contributors: usmanaliqureshi
Tags: server info, server status, php info, system health, diagnostics
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 7.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The ultimate free Server Info plugin for WordPress. View Memory Limits, PHP/MySQL versions, Datacenter Location, and an Always-On Admin HUD.

== Description ==

**Server Info - System Health & Diagnostics Suite** provides a stunning, comprehensive dashboard to track your server’s health, debug fatal errors, and monitor database limits, all for free.

Why pay for a PRO server monitoring plugin when you can get all the premium features out-of-the-box? This plugin gives you a detailed look into your web hosting environment, helping you easily identify bottlenecks, memory limits, and configuration errors that could be slowing down your site or crashing WooCommerce.

### 🌟 Premium Features included for FREE:
* **Always-On Admin Bar HUD:** A sleek HUD in your top WordPress admin bar showing your Environment (Local/Staging/Production), PHP version, and live RAM usage percentage.
* **Smart Environment Badges:** Auto-detects if you are on a Local or Staging site, displaying a color-coded badge so you never accidentally break a live production site!
* **Advanced Database Limits:** View your `Max Connections` and `Max Allowed Packet` to prevent your database from crashing during high-traffic events.

### 📊 Comprehensive System Diagnostics:
* **PHP Information:** PHP Version, Memory Limit, Upload Max Size, Post Max Size, Max Execution Time, Active Extensions.
* **Database Information:** MySQL Version, Total Database Size, Database Charset & Collation, Top 5 Largest Tables with Sizes, Max Allowed Packet, and Max Connections.
* **WordPress Configuration:** WP Memory Limit, Debug Mode Status, Multisite Detection, Permalink Structure, Current Theme, and Active/Inactive Plugins list.
* **Server Details:** Server IP, Server Hostname, Web Server Software (Nginx/Apache/LiteSpeed), OS Architecture, and Live CPU/Memory/Uptime Metrics.
* **Caching & Optimization:** Detects OPcache, Memcached, Redis Cache, and Output Buffering status to ensure your server is primed for speed.
* **Diagnostics & Security:** Detects file permissions for `wp-config.php`, `wp-content`, and `uploads` directories, plus checks if WP Cron is running properly or disabled.

Please rate the Plugin if you find it useful, thanks!

== Installation ==

1. In your WordPress admin, go to Plugins -> Add New.
2. Enter "Server Info" in the text box and click Search Plugins.
3. In the list of Plugins, click Install Now next to the Server Info Plugin.
4. Once installed, click to activate.
5. Go to your WordPress Settings -> Server Info to view the detailed dashboard!

== Frequently Asked Questions ==

= Does this plugin work with all major PHP versions? =

Yes! It is fully compatible and tested with PHP 7.3, 7.4, 8.0, 8.1, 8.2, and 8.3.

= Does it slow down my website? =

Absolutely not. The Server metrics are only loaded when an Administrator is actively viewing the backend dashboard. It has absolutely zero impact on your frontend site speed or database load.

= Is Server Info Plugin GDPR compliant? =

Yes, absolutely. It only queries local server environments and does not track your website visitors in any way. No data is sent to third-party servers.

= Who can view the Server Info dashboard? =

For strict security reasons, only users with the `manage_options` capability (which is restricted to Administrators) are permitted to view the dashboard or see the top admin bar HUD.

= Does it show caching status? =

Yes! The plugin automatically checks if object caching modules like Redis, Memcached, or PHP OPcache are loaded and enabled on your server.
== Screenshots ==

1. Overview Dashboard - Real-time server health and configuration summary.
2. Database Information - Detailed MySQL statistics and connection details.
3. WordPress Core - Important WordPress configurations and debug status.
4. PHP Information - Complete and beautifully styled phpinfo() output.
5. Diagnostics & Logs - Evaluate server health with detailed score impacts.
6. More Plugins - Additional tools to enhance your server experience.

== Changelog ==

= 1.0.0 =
* Initial Release. Completely restructured core.
* Added Admin Bar HUD and Footer replacements.
* Added Smart Environment Badges.