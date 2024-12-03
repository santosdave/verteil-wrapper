# Verteil NDC API Laravel Wrapper

A Laravel package for easy integration with the Verteil NDC API for airline bookings, searches, and management. This package provides a robust interface for interacting with Verteil's NDC API, including comprehensive error handling, rate limiting, caching, and monitoring capabilities.

## Features

- Easy-to-use interfaces for all major Verteil API operations
- Type-safe request building with helper classes
- Built-in validation and error handling
- Automatic token management and refresh
- Request rate limiting
- Response caching
- Comprehensive logging
- Retry handling for failed requests
- Health monitoring and metrics collection
- Security features including input sanitization and secure token storage
- Supports all Verteil NDC API endpoints:
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

## Installation

Install the package via Composer:

```bash
composer require santosdave/verteil-wrapper
```

### Laravel Configuration

1. Publish the configuration file:

```bash
php artisan vendor:publish --provider="Santosdave\VerteilWrapper\VerteilServiceProvider"
```

2. Add your Verteil credentials to your `.env` file:

```env
VERTEIL_USERNAME=your_username
VERTEIL_PASSWORD=your_password
VERTEIL_BASE_URL=https://api.stage.verteil.com
VERTEIL_TIMEOUT=30
VERTEIL_VERIFY_SSL=true
```

## Basic Usage

### Initialization

```php
use Santosdave\VerteilWrapper\Facades\Verteil;
// or
use Santosdave\VerteilWrapper\Services\VerteilService;
```

### Authentication

The package handles authentication automatically, but you can manually authenticate if needed:

```php
$verteil = Verteil::authenticate();
```

### Air Shopping Example

```php
use Santosdave\VerteilWrapper\DataTypes\AirShopping;

$request = AirShopping::create([
    'coreQuery' => [
        'originDestinations' => [
            [
                'departureAirport' => 'LHR',
                'arrivalAirport' => 'JFK',
                'departureDate' => '2024-12-01',
                'key' => 'OD1'
            ]
        ]
    ],
    'travelers' => [
        [
            'passengerType' => 'ADT',
            'frequentFlyer' => [
                'airlineCode' => 'BA',
                'accountNumber' => '12345678'
            ],
            'objectKey' => 'T1',
            'name' => [
                'given' => ['John'],
                'surname' => 'Doe',
                'title' => 'Mr'
            ]
        ]
    ],
    'preference' => [
        'cabin' => 'Y',
        'fareTypes' => ['PUBL', 'PVT']
    ]
]);

$response = Verteil::airShopping($request);
```

### Flight Price Example

```php
use Santosdave\VerteilWrapper\DataTypes\FlightPrice;
use Santosdave\VerteilWrapper\DataTypes\VerteilRequestBuilder as Builder;

// Create flight details using named parameters
$flight = Builder::createFlightType([
    'departureAirport' => 'LHR',
    'arrivalAirport' => 'JFK',
    'departureDate' => '2024-12-01',
    'departureTime' => '09:00',
    'airlineCode' => 'BA',
    'flightNumber' => '123',
    'arrivalDate' => '2024-12-01',
    'arrivalTime' => '12:00',
    'classOfService' => 'Y'
]);

$request = FlightPrice::create([
    'dataLists' => [
        'fares' => [
            [
                'listKey' => 'FARE1',
                'code' => 'Y1N2C3',
                'fareCode' => 'Y'
            ]
        ]
    ],
    'query' => [
        'originDestinations' => [
            [
                'flights' => [$flight]
            ]
        ],
        'offers' => [
            [
                'owner' => 'BA',
                'offerId' => 'OFFER123',
                'offerItems' => ['ITEM1', 'ITEM2']
            ]
        ]
    ],
    'shoppingResponseId' => [
        'owner' => 'BA',
        'responseId' => 'SHOP123'
    ]
]);

$response = Verteil::flightPrice($request);
```

### Order Creation Example

```php
use Santosdave\VerteilWrapper\DataTypes\OrderCreate;
use Santosdave\VerteilWrapper\DataTypes\VerteilRequestBuilder as Builder;

// Create passenger details using named parameters
$name = Builder::createNameType([
    'given' => 'John',
    'surname' => 'Doe',
    'title' => 'Mr'
]);

$contacts = Builder::createContactType([
    'phoneNumber' => '1234567890',
    'phoneCountryCode' => '44',
    'email' => 'john.doe@example.com',
    'street' => '123 Main St',
    'city' => 'London',
    'postalCode' => '12345',
    'countryCode' => 'GB'
]);

$document = Builder::createPassengerDocumentType([
    'documentNumber' => 'AB123456',
    'issuingCountry' => 'GB',
    'type' => 'PT',
    'expiryDate' => '2030-01-01'
]);

$payment = Builder::createPaymentCardType([
    'cardNumber' => '4111111111111111',
    'cvv' => '123',
    'expiryDate' => '1225',
    'holderName' => 'John Doe',
    'brand' => 'VI'
]);

$request = OrderCreate::create([
    'query' => [
        'owner' => 'BA',
        'responseId' => 'SHOP123',
        'passengers' => [
            [
                'objectKey' => 'T1',
                'gender' => 'Male',
                'passengerType' => 'ADT',
                'name' => $name,
                'contacts' => $contacts,
                'document' => $document
            ]
        ]
    ],
    'payments' => [$payment]
]);

$response = Verteil::createOrder($request);
```

### Order Retrieval and Cancellation

```php
use Santosdave\VerteilWrapper\DataTypes\OrderRetrieve;
use Santosdave\VerteilWrapper\DataTypes\OrderCancel;

// Retrieve order
$retrieveRequest = OrderRetrieve::create([
    'owner' => 'BA',
    'orderId' => 'ABC123',
    'channel' => 'NDC'
]);

$order = Verteil::retrieveOrder($retrieveRequest);

// Cancel order
$cancelRequest = OrderCancel::create([
    'orders' => [
        [
            'owner' => 'BA',
            'orderId' => 'ABC123',
            'channel' => 'NDC'
        ]
    ],
    'expectedRefundAmount' => [
        'amount' => 1200.00,
        'currency' => 'USD'
    ]
]);

$response = Verteil::cancelOrder($cancelRequest);
```

### Seat Availability and Service List

```php
use Santosdave\VerteilWrapper\DataTypes\SeatAvailability;
use Santosdave\VerteilWrapper\DataTypes\ServiceList;

// Create flight details for service list
$flight = Builder::createFlightType([
    'departureAirport' => 'LHR',
    'arrivalAirport' => 'JFK',
    'departureDate' => '2024-12-01',
    'departureTime' => '09:00',
    'airlineCode' => 'BA',
    'flightNumber' => '123'
]);

// Check seat availability
$seatRequest = SeatAvailability::create('pre', [
    'query' => [
        'originDestinations' => [
            [
                'segmentRefs' => ['SEG1', 'SEG2']
            ]
        ],
        'offers' => [
            [
                'owner' => 'BA',
                'offerId' => 'OFF123',
                'offerItems' => ['ITEM1']
            ]
        ]
    ],
    'travelers' => [
        [
            'objectKey' => 'T1',
            'passengerType' => 'ADT'
        ]
    ]
]);

$seats = Verteil::getSeatAvailability($seatRequest);

// Get service list
$serviceRequest = ServiceList::create('pre', [
    'query' => [
        'originDestinations' => [
            [
                'flights' => [$flight]
            ]
        ]
    ],
    'travelers' => [
        [
            'passengerType' => 'ADT'
        ]
    ]
]);

$services = Verteil::getServiceList($serviceRequest);
```

### Order Change Example

```php
use Santosdave\VerteilWrapper\DataTypes\OrderChange;

$payment = Builder::createPaymentCardType([
    'cardNumber' => '4111111111111111',
    'cvv' => '123',
    'expiryDate' => '1225',
    'holderName' => 'John Doe',
    'brand' => 'VI'
]);

// Create order change request
$orderChangeParams = [
    'orderId' => [
        'owner' => 'BA',
        'orderId' => 'ABC123'
    ],
    'changes' => [
        [
            'type' => 'FLIGHT_CHANGE',
            'segments' => [
                [
                    'origin' => 'LHR',
                    'destination' => 'JFK',
                    'departureDate' => '2024-12-01',
                    'departureTime' => '09:00',
                    'airlineCode' => 'BA',
                    'flightNumber' => '123'
                ]
            ]
        ]
    ],
    'payments' => [
        [
            'amount' => 100.00,
            'currency' => 'USD',
            'card' => [
                'number' => '4111111111111111',
                'securityCode' => '123',
                'holderName' => 'John Doe',
                'expiryDate' => '1225'
            ]
        ]
    ]
];

// Change order
$response = Verteil::changeOrder($orderChangeParams);
```

## Helper Classes

The package includes a `VerteilRequestBuilder` class with helper methods to create common data structures:

```php
use Santosdave\VerteilWrapper\DataTypes\VerteilRequestBuilder as Builder;

// Create passenger name
$name = Builder::createNameType([
    'given' => 'John',
    'surname' => 'Doe',
    'title' => 'Mr'
]);


// Create contact information
$contacts = Builder::createContactType([
    'phoneNumber' => '1234567890',
    'phoneCountryCode' => '44',
    'email' => 'john.doe@example.com',
    'street' => '123 Main St',
    'city' => 'London',
    'postalCode' => '12345',
    'countryCode' => 'GB'
]);

// Create passport/document information
$document = Builder::createPassengerDocumentType([
    'documentNumber' => 'AB123456',
    'issuingCountry' => 'GB',
    'type' => 'PT',
    'expiryDate' => '2030-01-01'
]);

// Create payment card details
$payment = Builder::createPaymentCardType([
    'cardNumber' => '4111111111111111',
    'cvv' => '123',
    'expiryDate' => '1225',
    'holderName' => 'John Doe',
    'brand' => 'VI'
]);;

// Create flight details
$flight = Builder::createFlightType([
    'departureAirport' => 'LHR',
    'arrivalAirport' => 'JFK',
    'departureDate' => '2024-12-01',
    'departureTime' => '09:00',
    'airlineCode' => 'BA',
    'flightNumber' => '123',
    'arrivalDate' => '2024-12-01',
    'arrivalTime' => '12:00',
    'classOfService' => 'Y'
]);

// Create price information
$price = Builder::createPriceType([
    'baseAmount' => 1000.00,
    'taxAmount' => 200.00,
    'currency' => 'USD'
]);
```

### Order Reshop Example

```php
use Santosdave\VerteilWrapper\DataTypes\OrderReshop;

$reshopParams = [
    'owner' => 'BA',
    'orderId' => 'ABC123',
    'qualifiers' => [
        [
            'type' => 'CABIN',
            'cabin' => 'Y',
            'preferenceLevel' => 'Preferred'
        ],
        [
            'type' => 'FARE',
            'fareTypes' => ['PUBL', 'CORP'],
            'fareBasis' => 'Y1N2C3'
        ]
    ],
    'segments' => [
        [
            'segmentKey' => 'SEG1',
            'newFlight' => [
                'origin' => 'LHR',
                'destination' => 'JFK',
                'departureDate' => '2024-12-01',
                'departureTime' => '09:00',
                'airlineCode' => 'BA',
                'flightNumber' => '123'
            ]
        ]
    ],
    'searchAlternateDates' => true
];

$response = Verteil::reshopOrder($reshopParams);
```

### Itinerary Reshop Example

```php
use Santosdave\VerteilWrapper\DataTypes\ItinReshop;

// Create itinerary reshop request
$itinReshopParams = [
    'orderId' => [
        'owner' => 'BA',
        'value' => 'ABC123'
    ],
    'itineraryChanges' => [
        [
            'type' => 'SEGMENT_CHANGE',
            'oldSegment' => [
                'origin' => 'LHR',
                'destination' => 'JFK',
                'departure' => [
                    'date' => '2024-12-01',
                    'time' => '09:00'
                ],
                'airline' => 'BA',
                'flightNumber' => '123'
            ],
            'newSegment' => [
                'origin' => 'LHR',
                'destination' => 'JFK',
                'departure' => [
                    'date' => '2024-12-02',
                    'time' => '11:00'
                ],
                'airline' => 'BA',
                'flightNumber' => '175'
            ]
        ]
    ],
    'pricingQualifiers' => [
        [
            'type' => 'CABIN',
            'code' => 'Y'
        ]
    ],
    'party' => [
        'type' => 'CORPORATE',
        'code' => 'CORP123',
        'name' => 'Example Corp'
    ]
];

// Reshop itinerary
$response = Verteil::reshopItinerary($itinReshopParams);
```

### Order Change Notification Example

```php
use Santosdave\VerteilWrapper\DataTypes\OrderChangeNotif;

// Create order change notification
$notificationData = OrderChangeNotif::create([
    'orderId' => [
        'owner' => 'BA',
        'value' => 'ABC123'
    ],
    'notification' => [
        'type' => 'SCHEDULE_CHANGE',
        'reason' => 'OPERATIONAL',
        'severity' => 'WARNING',
        'description' => 'Flight schedule has been modified due to operational constraints',
        'affectedSegments' => [
            [
                'segmentRef' => 'SEG1',
                'changeType' => 'TIME_CHANGE',
                'description' => 'Departure time changed',
                'oldValue' => '09:00',
                'newValue' => '11:00',
                'impacts' => [
                    'duration' => [
                        'change' => 120,
                        'unit' => 'MIN'
                    ]
                ]
            ]
        ],
        'customerNotification' => [
            'required' => true,
            'method' => 'EMAIL',
            'template' => 'SCHEDULE_CHANGE_NOTIFICATION'
        ]
    ],
    'serviceImpact' => [
        [
            'serviceId' => 'SVC1',
            'serviceType' => 'MEAL',
            'status' => 'MODIFIED',
            'description' => 'Meal service adjusted due to new flight time',
            'compensation' => [
                'type' => 'VOUCHER',
                'amount' => 15.00,
                'currency' => 'USD'
            ]
        ]
    ],
    'alternatives' => [
        [
            'type' => 'RESCHEDULE',
            'description' => 'Alternative flight options',
            'validity' => [
                'start' => '2024-12-01',
                'end' => '2024-12-03'
            ],
            'segments' => [
                [
                    'origin' => 'LHR',
                    'destination' => 'JFK',
                    'airline' => 'BA',
                    'flightNumber' => '175',
                    'departure' => [
                        'date' => '2024-12-01',
                        'time' => '14:00'
                    ],
                    'arrival' => [
                        'date' => '2024-12-01',
                        'time' => '17:00'
                    ]
                ]
            ],
            'pricing' => [
                'difference' => 0,
                'currency' => 'USD'
            ]
        ]
    ]
]);

$response = Verteil::sendOrderChangeNotification($notificationData);
```

## Error Handling

The package includes a custom exception class for handling Verteil API errors:

```php
use Santosdave\VerteilWrapper\Exceptions\VerteilApiException;

try {
    $response = Verteil::airShopping($request);
} catch (VerteilApiException $e) {
    // Handle API-specific errors
    $errorMessage = $e->getErrorMessage();
    $errorResponse = $e->getErrorResponse();

    // Log the error or notify administrators
    Log::error('Verteil API Error', [
        'message' => $errorMessage,
        'response' => $errorResponse
    ]);
} catch (\Exception $e) {
    // Handle other errors
}
```

### Caching

The package includes built-in caching for appropriate endpoints:

```php

// Check cache
$response = Verteil::getCache()->get('airShopping', $params);

// Clear cache for specific endpoint
Verteil::flushCache('airShopping');

// Clear all cache
Verteil::flushCache();

```

### Rate Limiting

Rate limiting is handled automatically, but you can configure limits in the config file:

```php
// config/verteil.php
'rate_limits' => [
    'default' => [
        'requests' => 60,
        'duration' => 60 // seconds
    ],
    'airShopping' => [
        'requests' => 30,
        'duration' => 60
    ]
]
```

### Health Monitoring

The package includes a health monitoring system:

This will display metrics including:

- API uptime
- Response times
- Error rates
- Cache hit rates
- Rate limit status
- Token status

```bash
php artisan verteil:health
```

### Security Features

The package includes several security features:

- Automatic input sanitization
- Secure token storage with encryption
- Request validation
- XSS protection
- SQL injection protection

### Logging

All API interactions are automatically logged. You can customize the logging channel in the config:

```php
// config/verteil.php
'logging' => [
    'channel' => 'verteil',
    'level' => 'debug'
]
```

### Console Commands

Available artisan commands:

```bash
php artisan verteil:health
php artisan verteil:cache:flush
php artisan verteil:cache:flush airShopping
```

### Events

The package dispatches several events that you can listen for:

- ApiRequestEvent
- ApiResponseEvent
- ApiErrorEvent
- TokenRefreshEvent

## Testing

Run the test suite:

```bash
composer test
```

### Advanced Configuration

The package provides extensive configuration options:

```php
// config/verteil.php
return [
    'credentials' => [
        'username' => env('VERTEIL_USERNAME'),
        'password' => env('VERTEIL_PASSWORD'),
    ],
    'base_url' => env('VERTEIL_BASE_URL'),
    'timeout' => env('VERTEIL_TIMEOUT', 30),
    'verify_ssl' => env('VERTEIL_VERIFY_SSL', true),
    'retry' => [
        'max_attempts' => 3,
        'delay' => 100, // milliseconds
        'multiplier' => 2
    ],
    'cache' => [
        'enabled' => true,
        'ttl' => [
            'airShopping' => 5, // minutes
            'flightPrice' => 2,
            'serviceList' => 5
        ]
    ],
    'monitoring' => [
        'enabled' => true,
        'metrics_retention' => 24 // hours
    ],
    'notifications' => [
        'slack_webhook_url' => env('VERTEIL_SLACK_WEBHOOK'),
        'notification_email' => env('VERTEIL_NOTIFICATION_EMAIL')
    ]
];
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email 51959957+santosdave@users.noreply.github.com instead of using the issue tracker.

## Credits

santosdave  

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
