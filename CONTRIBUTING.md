# Contributing to kantui

Thank you for considering contributing to kantui! This document outlines the process and guidelines for contributing.

## Development Setup

### Prerequisites

- PHP 8.3 or higher
- Composer

### Getting Started

1. Fork the repository
2. Clone your fork:
   ```bash
   git clone https://github.com/your-username/kantui.git
   cd kantui
   ```

3. Install dependencies:
   ```bash
   composer install
   ```

4. Run the application:
   ```bash
   ./bin/kantui
   ```

## Development Workflow

### Running Tests

```bash
# Run all tests
composer test

# Run tests with coverage
composer test:coverage

# Generate HTML coverage report
composer test:coverage-html
```

### Code Quality

Before submitting a pull request, ensure your code passes all quality checks:

```bash
# Run all checks (formatting, static analysis, tests)
composer check

# Or run individually:
composer format        # Format code with Laravel Pint
composer format:test   # Check code formatting
composer analyse       # Run PHPStan static analysis
composer test          # Run Pest tests
```

### Code Style

This project uses [Laravel Pint](https://laravel.com/docs/pint) for code formatting. The configuration is defined in `pint.json`.

- Run `composer format` to automatically format your code
- Run `composer format:test` to check formatting without making changes
- All code must pass Pint checks before being merged

### Static Analysis

We use PHPStan for static analysis with configuration in `phpstan.neon`:

```bash
composer analyse
```

Ensure your code passes all PHPStan checks at the configured level.

## Making Changes

### Branch Naming

Use descriptive branch names:
- `feature/add-new-keybinding`
- `fix/todo-deletion-bug`
- `docs/update-readme`
- `refactor/widget-structure`

### Commit Messages

Write clear, descriptive commit messages:
- Use the imperative mood ("Add feature" not "Added feature")
- Keep the first line under 72 characters
- Add detailed description if needed after a blank line

Examples:
```
Add support for custom color themes

- Add theme configuration option
- Implement color scheme switching
- Update documentation
```

### Pull Requests

1. Update your fork with the latest changes from main:
   ```bash
   git fetch upstream
   git rebase upstream/main
   ```

2. Push your changes:
   ```bash
   git push origin your-branch-name
   ```

3. Create a pull request with:
   - Clear title describing the change
   - Description of what changed and why
   - Reference to any related issues
   - Screenshots/demos for UI changes

4. Ensure all CI checks pass

## Testing

- Write tests for all new features and bug fixes
- Use Pest for testing (see `tests/` directory for examples)
- Aim for high test coverage
- Test both happy paths and edge cases

## Documentation

- Update the README.md if you add new features or change functionality
- Document new configuration options
- Add inline comments for complex logic
- Update PHPDoc blocks for new methods and classes

## Reporting Issues

### Bug Reports

Include:
- Clear, descriptive title
- Steps to reproduce the issue
- Expected behavior
- Actual behavior
- PHP version and OS
- Any relevant error messages or logs

### Feature Requests

Include:
- Clear description of the feature
- Use cases and benefits
- Potential implementation approach (optional)

## Code of Conduct

Please note that this project adheres to a Code of Conduct (see CODE_OF_CONDUCT.md). By participating, you are expected to uphold this code.

## Questions?

Feel free to open an issue for any questions about contributing!
