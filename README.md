# Just Log

A simple, lightweight and efficient logging solution for WordPress with SQLite storage and real-time search capabilities.

## Description

Just Log makes debugging WordPress applications easier by providing a clean interface to view, search, and manage logs. Instead of digging through server logs, Just Log stores everything in a SQLite database for fast access and efficient storage.

### Key Features

- **Lightweight Storage**: Uses SQLite for efficient, file-based database storage
- **Real-time Search**: Quickly find logs using the built-in search functionality
- **Clean Interface**: Modern UI that integrates with WordPress admin
- **Contextual Logging**: Automatically captures file, line number, and function details
- **JSON Formatting**: Pretty prints JSON data for better readability
- **Easy Cleanup**: Clear logs with a single click when you're done debugging

## Installation

1. Upload the `just-log` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Access the log viewer from the 'Just Log' menu in your WordPress admin area

## Usage

### Basic Logging

```php
// Log any data type
just_log('Simple text message');
just_log($array, $object, $anything);

// Multiple arguments are logged separately
just_log('User details:', $user, 'Process completed');
```

### Viewing Logs

1. Navigate to the "Just Log" menu in your WordPress admin
2. Use the search box to filter logs by content
3. Click on JSON data to expand/collapse objects
4. Use the scroll buttons to navigate through large log sets

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- SQLite extension for PHP

## Development

Want to contribute? Great! Please feel free to submit a pull request on GitHub.

## License

GPL v2 or later

## Credits

Created by Masiur Rahman Siddiki (www.MasiurSiddiki.com)