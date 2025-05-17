=== Just Log ===
Contributors: MasiurSiddiki
Tags: logging, debug, development, developer, logger
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A simple yet powerful log viewer for WordPress with MySQL storage and real-time search capabilities.

== Description ==

Just Log provides WordPress developers with an easy way to log and view debug information. With a sleek UI and powerful search capabilities, it's the perfect debugging companion for your WordPress development workflow.

### Key Features

* **Simple Logging Function** - Use `just_log($variable)` anywhere in your code
* **Beautiful UI** - Modern admin interface with card-based log display
* **Real-time Search** - Quickly find logs using the search functionality  
* **Smart Pagination** - Navigate through logs with intelligent page navigation
* **Timezone Support** - View logs in server time and your local time
* **Caller Information** - See exactly which file, function, and line generated each log
* **JSON Formatting** - Automatic pretty printing for JSON data
* **Log Clearing** - Reset logs with a single click when needed

### Developer Friendly

Just Log is designed with developers in mind. The simple `just_log()` function accepts any variable type and automatically captures the context information.

```php
// Log any variable type
just_log('Simple text message');
just_log($complex_object);
just_log($array, $another_variable, $third_item);
```

### Privacy Notice

This plugin doesn't collect any user data. All logs are stored locally in your WordPress database.

== Installation ==

1. Upload the `just-log` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Use the `just_log()` function in your code to log data
4. Access logs from the "Just Log" menu in your WordPress admin

== Frequently Asked Questions ==

= How do I log data? =

Simply use the `just_log()` function anywhere in your PHP code:

```php
just_log('This is my log message');
just_log($my_variable);
```

= Does this work with any data type? =

Yes! Just Log can handle strings, arrays, objects, and any other PHP data type.

= Will logging slow down my site? =

Just Log is designed to be lightweight. However, excessive logging in high-traffic areas could impact performance. We recommend using it primarily in development environments.

= How do I clear all logs? =

You can clear all logs by clicking the "Reset Logs" button in the Just Log admin interface.

= Does this work with multisite? =

Yes, Just Log works with WordPress multisite installations. Each site has its own separate logs.

== Screenshots ==

1. Just Log main interface with search functionality
2. Detailed log entries with file and function information
3. JSON data displayed with syntax highlighting

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release of Just Log for WordPress.