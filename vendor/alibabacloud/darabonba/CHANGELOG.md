# CHANGELOG

## [1.0.4] - 2025-12-15

### Changed
- Improved PHP version compatibility for multi-version support (PHP 5.6 - 8.x)
- Refactored FileFormStream implementation with separate classes for different PHP versions
- Updated test environment to use alibabacloud.com domains
- Improved test assertions to verify HTTP status codes
- Updated all code comments to English

### Fixed
- Fixed PHP 7.0 compatibility issues with nullable types and void return types
- Fixed trait and interface return type conflicts
- Fixed test assertions for random values in RandomBackoffPolicy
- Fixed StreamUtil JSON parsing for HTML content

### Added
- Added FileFormStreamTrait for shared stream implementation
- Added FileFormStreamTyped for PHP 7.1+ with type declarations
- Added comprehensive test coverage improvements

## [1.0.3] - 2025-11-27

### Fixed
- Fixed psr/http-message dependency version constraint
- Locked psr/http-message to ^1.0 for PHP 5.5+ compatibility

### Changed
- Updated test domains from jsonplaceholder.typicode.com to alibabacloud.com
- Updated API endpoints to use api.alibabacloud.com
- Improved test validation to focus on HTTP status codes

## [1.0.2] - 2025-10-23

### Fixed
- Fixed StreamUtil methods for better stream handling
- Fixed stream reading and parsing functionality

### Added
- Added StreamUtilTest with comprehensive test coverage

### Changed
- Updated DaraRetryException.php (2025-07-30)

## [1.0.1] - 2025-06-12

### Changed
- Removed monolog dependency to reduce package size and simplify dependencies
- Refactored Console class to remove monolog dependency
- Improved console output handling with custom stream support

### Added
- Added ConsoleTest for comprehensive console functionality testing

## [1.0.0] - 2025-01-15

### Added
- Initial release of Alibaba Cloud Darabonba SDK for PHP
- Core SDK functionality for Alibaba Cloud Tea
- Request/Response handling with PSR-7 compatibility
- Model validation and serialization
- Retry policy with multiple backoff strategies:
  - FixedBackoffPolicy
  - RandomBackoffPolicy
  - ExponentialBackoffPolicy
  - EqualJitterBackoffPolicy
  - FullJitterBackoffPolicy
- Exception handling:
  - DaraException
  - DaraRespException
  - DaraRetryException
  - DaraUnableRetryException
- Utility modules:
  - Date and time utilities
  - File operations support
  - Stream utilities
  - XML and form data handling
  - URL manipulation
  - String and byte utilities
  - Math utilities
- Comprehensive test suite with PHPUnit
- CI/CD integration with GitHub Actions
- Code style configuration with PHP CS Fixer
- Support for PHP 5.5+
- Guzzle HTTP client integration (^6.3|^7.0)
