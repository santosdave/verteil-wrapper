# Changelog

All notable changes to `verteil-wrapper` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.7] - 2024-12-03

### Added

- Support for ThirdpartyId and OfficeId in configurations
- Added new environment variables VERTEIL_THIRD_PARTY_ID and VERTEIL_OFFICE_ID
- Improved AirShopping request structure with better response parameters
- Added comprehensive sort order options for flight results
- Added support for different shop result preferences (OPTIMIZED, FULL, BEST)

### Changed

- Updated AirShoppingRequest to use configuration values for ThirdpartyId and OfficeId
- Improved request validation for AirShopping endpoint
- Enhanced documentation with detailed AirShopping examples

### Fixed

- Fixed AirShoppingRequest constructor parameter handling
- Corrected response parameter structure in AirShopping requests

## [1.0.6] - 2024-12-03

### Added

- Enhanced logging configuration options
- Configurable log file location
- Separate channel for Verteil logs
- Log file size rotation
- Log retention settings
- Configurable event logging

### Changed

- Updated logging implementation to use dedicated channel
- Improved log format with timestamps and better context
- Added environment variable support for log configuration

## [1.0.5] - 2024-12-03

### Changed

- Modified RateLimiter to use Laravel's Cache instead of Redis
- Simplified rate limiting implementation
- Added rate limit clearing functionality

### Fixed

- Removed Redis dependency requirement
- Fixed "Class Redis not found" error in rate limiter

## [1.0.4] - 2024-12-03

### Changed

- Modified cache implementation to support all Laravel cache drivers
- Removed cache tagging dependency
- Improved cache key management system

### Fixed

- Fixed "This cache store does not support tagging" error

## [1.0.3] - 2024-12-03

### Fixed

- Fixed "This cache store does not support tagging" error

## [1.0.2] - 2024-12-03

### Added

- Comprehensive configuration file with sections for:
  - API credentials
  - Base configuration
  - Retry settings
  - Cache configuration
  - Rate limiting rules
  - Logging settings
  - Monitoring options
  - Notification preferences

### Fixed

- Added missing configuration file in correct package location at src/config/verteil.php
- Fixed configuration file path reference in VerteilServiceProvider

## [1.0.1] - 2024-12-03

### Fixed

- Fixed incorrect service provider reference in composer.json from VerteilProvider to VerteilServiceProvider

## [1.0.0] - 2024-12-03

### Added

- Initial release with core Verteil NDC API integration
- Support for all major Verteil API endpoints:
  - Air Shopping
  - Flight Price
  - Order Creation
  - Order Retrieval
  - Order Cancellation
  - Seat Availability
  - Service List
  - Order Change
  - Order Reshop
  - Itinerary Reshop
  - Order Change Notifications
- Type-safe request builders for all endpoints
- Comprehensive response handling with dedicated response classes
- Automatic token management and refresh functionality
- Request rate limiting with configurable thresholds
- Response caching system with endpoint-specific TTLs
- Health monitoring and metrics collection
- Input sanitization and security features
- Advanced error handling and retry logic
- Robust logging system
- Artisan commands:
  - `verteil:health` - Check API health status
  - `verteil:cache:flush` - Manage API response cache
- Event system for monitoring API interactions
- Laravel integration:
  - Service Provider
  - Facade
  - Configuration publishing
  - Laravel cache integration
- Comprehensive test suite
- Full documentation

### Security

- Secure token storage with encryption
- Input sanitization and validation
- XSS protection
- SQL injection protection
- Rate limiting protection

[1.0.0]: https://github.com/santosdave/verteil-wrapper/releases/tag/v1.0.0
