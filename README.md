# Verteil NDC API Laravel Wrapper

A Laravel package for easy integration with the Verteil NDC API for airline bookings, searches, and management.

## Features

- Easy-to-use interfaces for all major Verteil API operations
- Type-safe request building with helper classes
- Built-in validation and error handling
- Supports all Verteil NDC API endpoints:
  - Air Shopping
  - Flight Price
  - Order Creation
  - Order Retrieval
  - Order Cancellation
  - Seat Availability
  - Service List

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
```

## Basic Usage

### Initialization

```php
use Santosdave\VerteilWrapper\Facades\Verteil;
// or
use Santosdave\VerteilWrapper\Services\VerteilService;
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
                'flights' => [
                    Builder::createFlightType(
                        'LHR',
                        'JFK',
                        '2024-12-01',
                        '09:00',
                        'BA',
                        '123',
                        '2024-12-01',
                        '12:00',
                        'Y'
                    )
                ]
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

$request = OrderCreate::create([
    'query' => [
        'owner' => 'BA',
        'responseId' => 'SHOP123',
        'passengers' => [
            [
                'objectKey' => 'T1',
                'gender' => 'Male',
                'passengerType' => 'ADT',
                'name' => Builder::createNameType('John', 'Doe', 'Mr'),
                'contacts' => Builder::createContactType(
                    '1234567890',
                    'john.doe@example.com',
                    '123 Main St',
                    'London',
                    '12345',
                    'GB',
                    '44'
                ),
                'document' => Builder::createPassengerDocumentType(
                    'AB123456',
                    'GB',
                    'PT',
                    '2030-01-01'
                )
            ]
        ]
    ],
    'payments' => [
        Builder::createPaymentCardType(
            '4111111111111111',
            '123',
            '1225',
            'John Doe',
            'VI'
        )
    ]
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
                'flights' => [
                    Builder::createFlightType(
                        'LHR',
                        'JFK',
                        '2024-12-01',
                        '09:00',
                        'BA',
                        '123'
                    )
                ]
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

## Helper Classes

The package includes a `VerteilRequestBuilder` class with helper methods to create common data structures:

```php
use Santosdave\VerteilWrapper\DataTypes\VerteilRequestBuilder as Builder;

// Create passenger name
$name = Builder::createNameType('John', 'Doe', 'Mr');

// Create contact information
$contacts = Builder::createContactType(
    '1234567890',
    'john.doe@example.com',
    '123 Main St',
    'London',
    '12345',
    'GB',
    '44'
);

// Create passport/document information
$document = Builder::createPassengerDocumentType(
    'AB123456',
    'GB',
    'PT',
    '2030-01-01'
);

// Create payment card details
$payment = Builder::createPaymentCardType(
    '4111111111111111',
    '123',
    '1225',
    'John Doe',
    'VI'
);

// Create flight details
$flight = Builder::createFlightType(
    'LHR',
    'JFK',
    '2024-12-01',
    '09:00',
    'BA',
    '123'
);
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
} catch (\Exception $e) {
    // Handle other errors
}
```

## Testing

Run the test suite:

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email 51959957+santosdave@users.noreply.github.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.