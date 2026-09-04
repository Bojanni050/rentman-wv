# Rentman Availability Calendar

[![License: GPL-2.0-or-later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/WordPress-5.0+-21759B?logo=wordpress)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?logo=php)](https://www.php.net/)

**Rentman Availability Calendar** is a WordPress plugin that displays a color-coded availability calendar based on appointment counts from the [Rentman API](https://www.rentman.net/). The calendar uses a simple color scheme to indicate availability:

- 🟢 **Green**: Available (0 appointments)
- 🟠 **Orange**: Busy (1-2 appointments)
- 🔴 **Red**: Full (3+ appointments)

## Features

✅ **Color-coded Calendar** – Visual availability indicator based on appointment counts
✅ **Gravity Forms Integration** – Realtime availability checks when users select dates
✅ **Elementor Widget** – Drag-and-drop widget for Elementor page builder
✅ **Shortcode Support** – Easy embedding with `[rentman_calendar]` shortcode
✅ **Caching** – Configurable cache duration to reduce API calls
✅ **Debug Logging** – Comprehensive logging for troubleshooting
✅ **Responsive Design** – Works on all devices
✅ **Multilingual** – Full translation support

## Quick Start

### Installation

1. **Download the plugin**
   - Clone this repository or download the ZIP file
   - Upload the `rentman-availability-calendar` folder to `/wp-content/plugins/`

2. **Activate the plugin**
   - Go to **WordPress Admin > Plugins**
   - Find "Rentman Availability Calendar" and click **Activate**

3. **Configure API Token**
   - Go to **Settings > Rentman Calendar**
   - Enter your Rentman API token
   - Click **Save Changes**

4. **Test Connection**
   - Click **Test Connection** to verify your API token works

### Getting Your Rentman API Token

1. Log in to your [Rentman account](https://www.rentman.net/)
2. Navigate to **Settings > Configuration > Account > Integrations > API**
3. Generate a new API token
4. Copy the token and paste it into the plugin settings

## Usage

### Shortcode

Add the calendar to any page or post using the shortcode:

```html
[rentman_calendar]
```

**Optional parameters:**

```html
[rentman_calendar year="2024" month="9"]
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `year` | int | Current year | The year to display |
| `month` | int | Current month | The month to display (1-12) |

### Elementor Widget

1. Edit a page with Elementor
2. Search for "Rentman Calendar" in the widget panel
3. Drag the widget to your page
4. Configure the widget settings (year, month, styling)

### Gravity Forms Integration

To enable realtime availability checks in Gravity Forms:

1. Go to **Settings > Rentman Calendar > Gravity Forms Integration**
2. Enable the integration
3. Set the **Form ID** (use 0 for all forms)
4. Set the **Date Field ID** (use 0 to auto-detect)
5. Configure the messages for available, limited, and unavailable dates
6. Choose whether to **block form submission** when a date is unavailable

**Available options:**

- **Message Position**: Below field, above field, or tooltip
- **Message Style**: Full (dot + text), dot only, or text only
- **Date Format**: Configure the expected date format from users

## Configuration

### Main Settings

| Setting | Default | Description |
|---------|---------|-------------|
| API Token | - | Your Rentman API token |
| Cache Duration | 15 minutes | How long to cache API responses |

### Gravity Forms Settings

| Setting | Default | Description |
|---------|---------|-------------|
| Enable Integration | No | Enable Gravity Forms integration |
| Form ID | 0 | Target form ID (0 = all forms) |
| Date Field ID | 0 | Target date field ID (0 = auto-detect) |
| Block Unavailable | Yes | Block form submission for unavailable dates |
| Available Message | "This date is available." | Message shown when date is available |
| Limited Message | "Limited availability for this date." | Message shown when date has 1-2 appointments |
| Unavailable Message | "Sorry, this date is not available." | Message shown when date has 3+ appointments |
| Date Format | d/m/Y | Expected date format from users |
| Message Position | Below | Where to display the availability message |
| Message Style | Full | How to display the message (dot + text, dot only, text only) |

### Debug Settings

| Setting | Default | Description |
|---------|---------|-------------|
| Enable Debug Logging | No | Log API requests, cache hits/misses, and availability checks |

**Log location:** `wp-content/uploads/rac-logs/debug.log`

## Actions & Filters

### Actions

- `rac_before_calendar_render` – Fires before the calendar is rendered
- `rac_after_calendar_render` – Fires after the calendar is rendered
- `rac_cache_cleared` – Fires when the cache is cleared

### Filters

- `rac_calendar_levels` – Modify the availability levels (green, orange, red thresholds)
- `rac_relevant_project_types` – Modify which project types are considered relevant
- `rac_relevant_project_statuses` – Modify which project statuses are considered relevant
- `rac_cache_ttl` – Modify the cache duration in seconds

## Troubleshooting

### No Data Showing

1. **Check API Token** – Ensure your token is correct and hasn't expired
2. **Test Connection** – Click "Test Connection" in plugin settings
3. **Check Cache** – Try clearing the cache
4. **Enable Debug Logging** – Check the debug log for errors

### Cache Issues

- **Clear Cache**: Go to Settings > Rentman Calendar and click "Clear Cache"
- **Adjust Cache Duration**: Increase or decrease the cache minutes in settings

### Date Format Issues

If dates are not being recognized correctly:
1. Check the **Date Format** setting in Gravity Forms integration
2. Ensure it matches the format your users are entering
3. Try setting it to "auto" to let the plugin detect the format

### Connection Errors

- **401/403 Errors**: Your API token is invalid or expired
- **429 Errors**: You're hitting rate limits (consider increasing cache duration)
- **Timeout Errors**: The API is slow to respond (increase timeout in code)

## Security

- ✅ All AJAX endpoints use nonce verification
- ✅ Input is sanitized and output is escaped
- ✅ API tokens are stored securely
- ✅ SQL queries use prepared statements
- ✅ Rate limiting prevents API abuse

## Performance

- **Caching**: API responses are cached to reduce requests
- **Pagination**: Large datasets are fetched in pages
- **Lazy Loading**: Data is loaded on-demand when users navigate months

## Development

### Requirements

- PHP 7.4+
- WordPress 5.0+
- Composer (for development)

### Setup

```bash
# Clone the repository
git clone https://github.com/Bojanni050/rentman-wv.git
cd rentman-wv

# Install dependencies
composer install

# Run tests
composer test
```

### Project Structure

```
rentman-availability-calendar/
├── rentman-availability-calendar.php  # Main plugin file
├── README.md                          # This file
├── CHANGELOG.md                       # Version history
├── CONTRIBUTING.md                    # Contribution guidelines
├── includes/
│   ├── class-rac-api-client.php      # API client for Rentman
│   ├── class-rac-calendar.php         # Calendar rendering and logic
│   ├── class-rac-logger.php           # Debug logging
│   ├── class-rac-settings.php         # Plugin settings
│   ├── class-rac-elementor-widget.php # Elementor widget
│   └── integrations/
│       └── class-rac-gravity-forms.php # Gravity Forms integration
├── js/
│   ├── calendar.js                    # Frontend calendar JavaScript
│   └── gravity-forms.js               # Gravity Forms integration JS
└── css/
    ├── calendar.css                   # Calendar styles
    └── rentman-availability-calendar-gravity-forms.css              # Gravity Forms integration styles
```

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for contribution guidelines.

## License

This plugin is licensed under the **GPL-2.0-or-later** license. See [LICENSE](LICENSE) for details.

## Support

For support, issues, or feature requests:

- **GitHub Issues**: [https://github.com/Bojanni050/rentman-wv/issues](https://github.com/Bojanni050/rentman-wv/issues)
- **White Vision**: [https://whitevision.nl](https://whitevision.nl)

---

**Developed by:** [Bojan Davidović / White Vision](https://whitevision.nl)  
**Version:** 1.0.0  
**Last Updated:** September 2024
