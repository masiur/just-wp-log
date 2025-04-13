=== Just Log ===
Contributors: masiur
Tags: logging, debug, developer, mysql, tool
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A simple, lightweight and efficient logging solution for WordPress with built-in log viewer and real-time search capabilities.

== Description ==

Just Log makes debugging WordPress applications easier by providing a clean interface to view, search, and manage logs. It uses your existing WordPress database for storage, ensuring maximum compatibility with all WordPress installations.

= Key Features =

* **WordPress Database Integration**: Uses your existing MySQL database for reliable storage
* **Real-time Search**: Quickly find logs using the built-in search functionality
* **Clean Interface**: Modern UI that integrates with WordPress admin
* **Contextual Logging**: Automatically captures file, line number, and function details
* **JSON Formatting**: Pretty prints JSON data for better readability
* **Easy Cleanup**: Clear logs with a single click when you're done debugging
* **Universal Compatibility**: Works with any WordPress installation without additional extensions

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

Just Log is designed to be lightweight and only consumes resources when actively logging. It creates a separate table in your WordPress database to store logs efficiently.

= Can I use this on a production site? =

Yes, but we recommend using it primarily for development and debugging purposes. You can activate it temporarily on production to troubleshoot specific issues.

= Where are the logs stored? =

The logs are stored in a dedicated table in your WordPress database. This makes the plugin compatible with all WordPress installations.

= Are there any special requirements for this plugin? =

No! Just Log works with any standard WordPress installation without requiring any special PHP extensions.

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