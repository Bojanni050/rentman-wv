# Changelog

## [1.4] - 2026-11-03

### Added
- Fluent Forms integration: realtime availability check on the date field (flatpickr datepicker or native date input) and server-side blocking via `fluentform/validation_errors`
- Settings section "Fluent Forms" (enable, form ID, date field name; empty = auto-detect date fields)

### Changed
- Shared logic moved to `RAC_Form_Availability` (AJAX endpoint, messages, assets); integrations only decide which form/field to watch
- Gravity Forms and Fluent Forms integrations are only loaded when that plugin is active; the plugin no longer depends on Gravity Forms
- Front-end assets renamed to `js/form-availability.js` and `css/form-availability.css` (work for both form plugins); the old `js/gravity-forms.js` and `css/rentman-availability-calendar-gravity-forms.css` are no longer used
- Settings page: shared messages/format/position in one section, separate sections per form plugin. Existing `gf_*` options are kept, so no reconfiguration is needed
- `RAC_VERSION` now matches the plugin header (1.4)

### Fixed
- Gravity Forms: "Block unavailable dates" now really blocks the submission (uses `gform_validation` and marks the date field invalid); before it only added a message to the error summary of an already failing form
- Tooltip messages now get their styling (the tooltip is appended to `<body>`, outside the form wrapper)

## [1.3] - unreleased

### Changed
- Scoped Gravity Forms availability CSS to `.gform_wrapper` selectors for higher specificity, so no extra site CSS is needed
- Increased availability block font-size from 0.75rem to a fixed 13px (12px on mobile) for better readability on sites with a small base font-size

All notable changes to the Rentman Availability Calendar plugin will be documented in this file.

## [1.0.0] - 2024-09-04

### Added
- Initial release of Rentman Availability Calendar plugin
- Color-coded calendar display (Green/Orange/Red)
- Gravity Forms integration for realtime availability checks
- Elementor widget support
- Shortcode support: `[rentman_calendar]`
- Configurable cache duration
- Debug logging functionality
- Comprehensive settings page

### Features
- Visual availability indicator based on Rentman API data
- Realtime date validation in Gravity Forms
- Responsive design for all devices
- Multilingual support with translation functions

## [Unreleased]

### Added
- Security improvements: nonce verification, input sanitization, SQL injection prevention
- Performance optimizations: rate limiting, cache invalidation, improved pagination
- Bug fixes: date parsing, timezone handling, empty project handling
- Code quality improvements: type hints, DocBlocks, constants
- Enhanced documentation: README.md, CHANGELOG.md, CONTRIBUTING.md

### Changed
- Improved repository structure
- Enhanced error handling
- Better code organization and maintainability

### Fixed
- SQL injection vulnerabilities in cache clearing
- Date parsing edge cases
- Timezone-related issues
- Empty project handling in API responses

---

**Note:** This changelog follows the [Keep a Changelog](https://keepachangelog.com/) format.
