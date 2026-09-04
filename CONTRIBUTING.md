# Contributing to Rentman Availability Calendar

Thank you for considering contributing to Rentman Availability Calendar! We welcome contributions from everyone.

## Ways to Contribute

- 🐛 **Report bugs** – Open an issue with detailed steps to reproduce
- 💡 **Suggest features** – Share your ideas for improvements
- 📝 **Improve documentation** – Help make our docs clearer
- 🔧 **Fix bugs** – Submit pull requests to fix issues
- ✨ **Add features** – Implement new functionality

## Getting Started

### Prerequisites

- PHP 7.4 or higher
- WordPress 5.0 or higher
- Composer (for dependency management)
- Git
- A Rentman API token (for testing)

### Setup

1. **Fork the repository**
   ```bash
   git clone https://github.com/Bojanni050/rentman-wv.git
   cd rentman-wv
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Set up WordPress**
   - Install WordPress locally (e.g., using Local by Flywheel, Docker, or MAMP)
   - Copy the plugin folder to `wp-content/plugins/`
   - Activate the plugin in WordPress admin

4. **Configure for development**
   - Add your Rentman API token in plugin settings
   - Enable debug logging for development

## Development Guidelines

### Code Style

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- Use PHPDoc for all public methods
- Use type hints (PHP 7.4+)
- Keep lines under 120 characters
- Use meaningful variable and function names

### PHP

```php
/**
 * Example function with proper documentation
 *
 * @param string $param Description of parameter
 * @return string Description of return value
 */
public function example_function(string $param): string {
    // Code here
    return $result;
}
```

### JavaScript

- Use jQuery (included with WordPress)
- Follow jQuery best practices
- Use `use strict` mode
- Add comments for complex logic

```javascript
(function ($) {
    'use strict';
    
    $(document).ready(function () {
        // Your code here
    });
})(jQuery);
```

### Security

- Always sanitize user input
- Always escape output
- Use nonces for AJAX requests
- Use prepared statements for SQL queries
- Never trust user input

```php
// Good
$safe_input = sanitize_text_field($_POST['input']);
$escaped_output = esc_html($output);

// Bad
$unsafe = $_POST['input'];
echo $output;
```

### Internationalization

- Wrap all user-facing text in translation functions
- Use text domains consistently

```php
// Good
__('Hello World', 'rentman-availability-calendar');
esc_html_e('Hello World', 'rentman-availability-calendar');

// Bad
echo 'Hello World';
```

## Testing

### Running Tests

```bash
composer test
```

### Writing Tests

- Write tests for new features
- Write tests for bug fixes
- Keep tests focused and isolated
- Test edge cases

```php
class MyClass_Test extends WP_UnitTestCase {
    public function test_something() {
        $result = my_function();
        $this->assertEquals('expected', $result);
    }
}
```

## Pull Requests

### Before Submitting

1. Run `composer test` to ensure all tests pass
2. Check your code follows the coding standards
3. Update documentation if needed
4. Update the CHANGELOG.md with your changes

### Pull Request Template

```markdown
## Description

Brief description of the changes

## Related Issues

- Closes #123

## Changes Made

- Added feature X
- Fixed bug Y
- Improved performance of Z

## Testing

- Tested on WordPress 6.0
- Tested with PHP 8.0
- All existing tests pass
```

## Code Review Process

1. Submit your pull request
2. Wait for maintainer review
3. Address any feedback
4. Once approved, your changes will be merged

## Reporting Bugs

When reporting bugs, please include:

- WordPress version
- PHP version
- Plugin version
- Steps to reproduce
- Expected behavior
- Actual behavior
- Screenshots (if applicable)
- Error logs (if applicable)

## Feature Requests

When requesting features, please include:

- Description of the feature
- Use case or problem it solves
- Any relevant examples or mockups

## License

By contributing, you agree that your contributions will be licensed under the same [GPL-2.0-or-later](LICENSE) license as the project.

---

Thank you for contributing to Rentman Availability Calendar! 🎉
