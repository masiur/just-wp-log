=== Just Log ===
Contributors: masiur
Tags: logging, debug, developer, sqlite, tool
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A simple, lightweight and efficient logging solution for WordPress with SQLite storage and real-time search capabilities.

== Description ==

Just Log makes debugging WordPress applications easier by providing a clean interface to view, search, and manage logs. Instead of digging through server logs, Just Log stores everything in a SQLite database for fast access and efficient storage.

= Key Features =

* **Lightweight Storage**: Uses SQLite for efficient, file-based database storage
* **Real-time Search**: Quickly find logs using the built-in search functionality
* **Clean Interface**: Modern UI that integrates with WordPress admin
* **Contextual Logging**: Automatically captures file, line number, and function details
* **JSON Formatting**: Pretty prints JSON data for better readability
* **Easy Cleanup**: Clear logs with a single click when you're done debugging

= Use Cases =

* Debug complex WordPress applications
* Trace user actions and system behaviors
* Monitor plugin and theme activity
* Track down hard-to-find issues
* Log API and integration responses
* Record variable states throughout execution

= Developer Friendly =

The `just_log()` function works with any data type you throw at it. It can handle strings, arrays, objects, and automatically captures the context of where it was called from.

== Installation ==

1. Upload the `just-log` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Access the log viewer from the 'Just Log' menu in your WordPress admin area

== Frequently Asked Questions ==

= Does this plugin affect site performance? =

Just Log is designed to be lightweight and only consumes resources when actively logging. The SQLite database provides efficient storage without the overhead of using your main WordPress database tables.

= Can I use this on a production site? =

Yes, but we recommend using it primarily for development and debugging purposes. You can activate it temporarily on production to troubleshoot specific issues.

= Where are the logs stored? =

The logs are stored in a SQLite database file within the plugin directory. This keeps logging data separate from your WordPress database.

= Is SQLite required on my server? =

Yes, your PHP installation needs to have the SQLite extension enabled. Most modern hosting environments have this enabled by default.

= Can I export the logs? =

Currently, you can view logs in the admin interface. Export functionality may be added in future releases.

== Screenshots ==

1. Just Log admin interface
2. Log entry detail view
3. Search functionality in action
4. Settings page

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release of Just Log.