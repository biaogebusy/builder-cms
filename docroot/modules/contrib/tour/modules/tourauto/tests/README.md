# Tourauto Module Tests

This directory contains tests for the tourauto submodule functionality.

## Test Structure

### Unit Tests (`src/Unit/`)
- **TourautoManagerTest.php**: Tests the core TourautoManager class functionality
  - User preference management
  - Tour state management
  - Service instantiation
  - Translation functionality

### Kernel Tests (`src/Kernel/`)
- **TourautoServiceTest.php**: Tests the tourauto service registration and basic functionality
  - Service availability
  - User account management
  - Default preferences
  - State management

### Functional Tests (`src/Functional/`)
- **TourautoFunctionalTest.php**: Tests the user interface integration
  - User form elements
  - Preference saving
  - Tour state clearing
  - JavaScript loading

### FunctionalJavascript Tests (`src/FunctionalJavascript/`)
- **TourautoJavascriptTest.php**: Tests the JavaScript behavior
  - JavaScript loading and initialization
  - DrupalSettings integration
  - Debug information
  - Tour module compatibility

### Test Module (`modules/tourauto_test/`)
- **tourauto_test**: A test module that provides tours for testing
  - Test tour configuration
  - Test controller and routes
  - Test page with tour elements

## Running Tests

To run the tourauto tests:

```bash
# Run all tourauto tests
ddev phpunit --group=tourauto

# Run specific test types
ddev phpunit modules/tourauto/tests/src/Unit/
ddev phpunit modules/tourauto/tests/src/Kernel/
ddev phpunit modules/tourauto/tests/src/Functional/
ddev phpunit modules/tourauto/tests/src/FunctionalJavascript/
```

## Test Coverage

The tests cover the following functionality:

1. **TourautoManager Class**:
   - User preference management (enable/disable tourauto)
   - Tour state tracking (seen/unseen tours)
   - State clearing functionality
   - Account-specific manager creation

2. **Service Integration**:
   - Service registration and availability
   - Dependency injection
   - User data storage

3. **User Interface**:
   - User form integration
   - Form element presence and functionality
   - Preference saving and loading

4. **JavaScript Behavior**:
   - JavaScript library loading
   - DrupalSettings integration
   - Debug information for administrators
   - Tour module compatibility

5. **Integration**:
   - Tour detection and management
   - Page bottom integration
   - Cache handling

## Test Dependencies

The tests require the following modules:
- `tour`: Core tour functionality
- `tourauto`: The module under test
- `user`: User management
- `tourauto_test`: Test module providing tours

## Notes

- Tests follow Drupal coding standards
- All tests are properly namespaced and grouped
- Tests use appropriate base classes (UnitTestCase, KernelTestBase, BrowserTestBase, WebDriverTestBase)
- Mock objects are used where appropriate for unit testing
- Functional tests use real browser interactions
- JavaScript tests verify client-side behavior 