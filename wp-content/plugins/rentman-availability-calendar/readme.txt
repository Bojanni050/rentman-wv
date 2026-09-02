=== Rentman Availability Calendar ===
Contributors: yourname
Tags: rentman, calendar, availability, appointments, scheduling
Requires at least: 5.5
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Displays a color-coded availability calendar using appointment data from the Rentman API. Days are colored green (0 appointments), orange (1-2 appointments), or red (3+ appointments).

== Description ==

This plugin connects to the Rentman API (https://api.rentman.net) to retrieve appointments and displays them on a monthly calendar with color-coded availability:

* **Green** — 0 appointments (Available)
* **Orange** — 1-2 appointments (Busy)
* **Red** — 3+ appointments (Full)

Visitors can navigate between months, and hovering over a day shows a tooltip with the appointments scheduled for that day.

= Features =

* Shortcode `[rentman_calendar]` to display the calendar on any page or post
* Month navigation (previous/next)
* Hover tooltips showing appointment details per day
* Configurable API token and cache duration
* Admin settings page with connection test and cache clearing
* Responsive design for mobile and desktop
* Caching of API responses to reduce load

== Installation ==

1. Upload the `rentman-availability-calendar` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Rentman Calendar** in the admin sidebar
4. Enter your Rentman API token (found in Rentman under Settings > Configuration > Account > Integrations > API)
5. Optionally adjust the cache duration
6. Click **Test Connection** to verify your token works
7. Add the shortcode `[rentman_calendar]` to any page or post

== Frequently Asked Questions ==

= Where do I get my API token? =

In Rentman, go to Settings > Configuration > Account > Integrations, click "Connect" in the API field, then click "Show token".

= Can I show a specific month by default? =

Yes, use the shortcode with attributes: `[rentman_calendar year="2026" month="9"]`

= How often is the data refreshed? =

Data is cached for the duration you set in the settings (default 15 minutes). You can clear the cache manually from the admin page.

== Changelog ==

= 1.0.0 =
* Initial release. Fetches appointments from the Rentman API and displays a color-coded availability calendar.
