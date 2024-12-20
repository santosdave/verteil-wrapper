# Changelog

All notable changes to `verteil-wrapper` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.2] - 2024-12-20

### Added

- Enhanced handling of OfferItemIDs in FlightPrice requests
- Improved metadata structure handling with support for @type and java.util.type
- Added automatic OfferItemID generation based on parent OfferId

### Changed

- Updated FlightPrice offer structure to better handle refs and OfferItemIDs
- Improved metadata formatting in FlightPrice requests
- Enhanced documentation with detailed FlightPrice request examples

### Fixed

- Fixed OfferItemIDs structure in FlightPrice requests
- Corrected metadata type handling in API requests

## [2.0.1] - 2024-12-16

### Fixed

- Fixed "Too few arguments" error in VerteilService makeRequest method by adding RequestHelper class to properly transform parameters for different request types
- Added support for complex request parameter transformations while maintaining backward compatibility with simple requests

### Added

- New RequestHelper class for transforming raw API parameters into typed request constructor arguments
- Added support for FlightPrice request parameter transformation
- Added extensible parameter transformation system for future endpoint additions

### Changed

- Modified VerteilService makeRequest method to use RequestHelper for parameter transformation
- Updated request instantiation to use spread operator with transformed parameters

### [2.0.0] - 2024-12-13

### Added

- Complete rewrite of FlightPrice request builder with improved validation
- Enhanced corporate booking support in FlightPrice
- Comprehensive seat selection functionality
- Support for frequent flyer program integration
- Multiple currency handling capabilities
- Payment card validation and processing
- New helper methods in VerteilRequestBuilder
- Enhanced error handling with detailed messages
- Support for ThirdpartyId and OfficeId in configurations

### Changed

- Refactored FlightPriceRequest for better validation
- Updated request structure to match latest Verteil API specifications
- Improved handling of optional parameters
- Enhanced type safety throughout the codebase
- Better organization of complex data structures
- Updated documentation with comprehensive examples

### Fixed

- Fixed validation issues in FlightPrice requests
- Corrected handling of nested offer structures
- Fixed currency conversion edge cases
- Improved error handling for invalid corporate codes
- Fixed seat selection validation issues

## [1.2.2] - 2024-12-11

### Fixed

- Fixed media reference resolution in price class descriptions to properly handle MediaLinks and MediaRef structures
- Fixed media links organization to preserve size-specific URLs and types
- Fixed media description handling to properly extract and associate text content

### Added

- Added formatMediaLinks() method to organize media links by size (Small, Medium, Large, Unknown)
- Enhanced mediaReferences structure to store both links and descriptions
- Added support for web page links in media references

### Changed

- Updated buildMediaReferences() to use ListKey as reference key
- Improved formatMedia() to handle both direct media content and references
- Modified media data structure to organize links by size for easier access
- Enhanced price class formatting to properly resolve and include media references

## [1.2.1] - 2024-12-11

### Fixed

- Fixed datetime formatting in timeLimit expirationDateTime to include full datetime with timezone instead of just date
- Fixed currency decimal precision for monetary amounts based on currency metadata
- Fixed media reference resolution in price class descriptions

### Added

- Added $currencyDecimals property to store decimal places configuration for different currencies
- Added $mediaReferences property to store media lookup table
- Added initializeCurrencyDecimals() method to initialize currency decimals from metadata
- Added buildMediaReferences() method to build media reference lookup table
- Added formatAmount() method for currency-specific decimal formatting

### Changed

- Enhanced formatDateTime() method to use Carbon for proper datetime parsing and timezone handling
- Updated formatTimeLimit() to include complete datetime information
- Updated formatPrice() and formatTaxes() to respect currency-specific decimal places
- Improved formatMedia() to properly resolve and merge media references
- Updated formatPriceClass() to handle media references correctly

## [1.2.0] - 2024-12-11

### Added

- Enhanced AirShoppingResponse with comprehensive data extraction:
  - Added detailed flight segment information parsing
  - Added support for baggage allowance extraction (checked and carry-on)
  - Added price class information formatting
  - Added fare details extraction
  - Added support for corporate fare information
  - Added flight duration parsing
  - Added time limit information handling
  - Added commission information formatting
  - Added flight stops information
  - Added operating carrier details
  - Added currency metadata extraction
  - Added response statistics generation
  - Added detailed error information handling
  - Added comprehensive warning handling
  - Added extensive price breakdown formatting
  - Added support for piece and weight allowance formatting
  - Added trip duration calculation

### Changed

- Refactored AirShoppingResponse for better data organization:
  - Improved response data structure
  - Enhanced error and warning handling
  - Optimized data extraction methods
  - Improved price formatting
  - Enhanced datetime handling
  - Improved array access safety
  - Updated response validation logic

### Fixed

- Fixed nested data extraction in AirShoppingResponse
- Improved handling of missing or null values in response data
- Fixed duration parsing edge cases
- Corrected price calculation inconsistencies

## [1.1.0] - 2024-12-04

### Added

- Implemented Monolog for advanced logging capabilities
- Added depth control for nested log structures
- Added rotating file handler for log management
- Added JSON formatting for log entries
- Added new logging processors for additional context
- Added trace normalization for error logging
- Added object and resource handling in logs
- Added configurable max depth setting for logs

### Changed

- Refactored VerteilLogger class to use Monolog
- Improved log data sanitization
- Enhanced context normalization
- Updated log level handling
- Improved error context formatting

### Fixed

- Fixed "Over 9 levels deep, aborting normalization" error in logging
- Fixed issues with deep nested structures in logs
- Fixed memory issues with large response logging

## [1.0.8] - 2024-12-03

### Added

- Universal response logging support for all endpoints
- Smart flattening of deeply nested API responses
- Special handling for common Verteil data patterns
- Improved logging metadata with process and memory information
- Intelligent request structure logging

### Changed

- Modified logger to handle unlimited nesting depths
- Improved sanitization of sensitive data
- Enhanced log readability for complex responses
- Updated request logging to better handle multi-stage processing
- Optimized memory usage in logging deep structures

### Fixed

- Fixed "Over 9 levels deep, aborting normalization" error in logging
- Resolved response truncation issues
- Fixed nested object handling in request logging
- Corrected monetary value logging format
- Fixed date/time combination logging

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
