<?php

namespace Santosdave\VerteilWrapper\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Santosdave\VerteilWrapper\Cache\VerteilCache;
use Santosdave\VerteilWrapper\Exceptions\VerteilApiException;
use Santosdave\VerteilWrapper\Logging\VerteilLogger;
use Santosdave\VerteilWrapper\RateLimit\RateLimiter;
use Santosdave\VerteilWrapper\Responses\AirShoppingResponse;
use Santosdave\VerteilWrapper\Responses\FlightPriceResponse;
use Santosdave\VerteilWrapper\Responses\ItinReshopResponse;
use Santosdave\VerteilWrapper\Responses\OrderChangeNotifResponse;
use Santosdave\VerteilWrapper\Responses\OrderChangeResponse;
use Santosdave\VerteilWrapper\Responses\OrderReshopResponse;
use Santosdave\VerteilWrapper\Responses\OrderViewResponse;
use Santosdave\VerteilWrapper\Responses\SeatAvailabilityResponse;
use Santosdave\VerteilWrapper\Responses\ServiceListResponse;
use Santosdave\VerteilWrapper\Retry\RetryHandler;
use Santosdave\VerteilWrapper\Security\SanitizesInput;
use Santosdave\VerteilWrapper\Security\SecureTokenStorage;

class VerteilService
{
    use SanitizesInput;

    protected Client $client;
    protected array $config;
    protected ?string $token = null;

    protected SecureTokenStorage $tokenStorage;
    protected VerteilCache $cache;
    protected RateLimiter $rateLimiter;
    protected RetryHandler $retryHandler;
    protected VerteilLogger $logger;


    public function __construct(array $config)
    {
        $this->config = $config;
        $this->tokenStorage = new SecureTokenStorage();
        $this->cache = new VerteilCache();
        $this->rateLimiter = new RateLimiter();
        $this->retryHandler = new RetryHandler();
        $this->logger = new VerteilLogger();
        $this->initializeClient();
    }

    protected function initializeClient(): void
    {
        $this->client = new Client([
            'base_uri' => $this->config['base_url'],
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'verify' => $this->config['verify_ssl'] ?? true,
            'timeout' => $this->config['timeout'] ?? 30,
        ]);
    }

    // Authentication Methods
    public function authenticate()
    {
        try {
            // Check if we have a valid cached token
            if ($this->tokenStorage->hasValidToken()) {
                $this->token = $this->tokenStorage->retrieveToken();
                return $this;
            }
            // Sanitize credentials
            $username = $this->sanitizeString($this->config['username']);
            $password = $this->sanitizeString($this->config['password']);

            $response = $this->client->post('/oauth2/token', [
                'auth' => [$username, $password],
                'query' => [
                    'grant_type' => 'client_credentials',
                    'scope' => 'api'
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            $this->token = $data['access_token'];

            // Store token securely
            $this->tokenStorage->storeToken($this->token);
            return $this;
        } catch (\Exception $e) {
            Log::error('Verteil authentication failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new VerteilApiException('Authentication failed: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    // Protected Methods
    public function setAuthorizationHeader()
    {
        if (!$this->token) {
            $this->authenticate();
        }

        $this->client = new Client([
            'base_uri' => $this->config['base_url'],
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'verify' => $this->config['verify_ssl'] ?? true,
            'timeout' => $this->config['timeout'] ?? 30,
        ]);

        return $this;
    }

    // Air Shopping Methods
    public function airShopping(array $params)
    {
        $response = $this->makeRequest('airShopping', $params);
        return new AirShoppingResponse($response);
    }

    // Flight Price Methods
    public function flightPrice(array $params): FlightPriceResponse
    {
        $response = $this->makeRequest('flightPrice', $params);
        return new FlightPriceResponse($response);
    }

    // Order Creation Methods
    public function createOrder(array $params)
    {
        $response = $this->makeRequest('orderCreate', $params);
        return new OrderViewResponse($response);
    }

    // Order Retrieval Methods
    public function retrieveOrder(array $params): OrderViewResponse
    {
        $response = $this->makeRequest('orderRetrieve', $params);
        return new OrderViewResponse($response);
    }

    // Order Cancellation Methods   
    public function cancelOrder(array $params): array
    {
        return $this->makeRequest('orderCancel', $params);
    }

    // Seat Availability Methods
    public function getSeatAvailability(array $params): SeatAvailabilityResponse
    {
        $response = $this->makeRequest('seatAvailability', $params);
        return new SeatAvailabilityResponse($response);
    }

    // Service List Methods
    public function getServiceList(array $params): ServiceListResponse
    {
        $type = $params['type'] ?? 'pre';
        $response = $this->makeRequest($type . 'ServiceList', $params);
        return new ServiceListResponse($response);
    }

    // Order Change Methods
    public function changeOrder(array $params): OrderChangeResponse
    {
        $response = $this->makeRequest('orderChange', $params);
        return new OrderChangeResponse($response);
    }

    // Order Reshop Methods
    public function reshopOrder(array $params): OrderReshopResponse
    {
        $response = $this->makeRequest('orderReshop', $params);
        return new OrderReshopResponse($response);
    }

    // Itinerary Reshop Methods
    public function reshopItinerary(array $params): ItinReshopResponse
    {
        $response = $this->makeRequest('itinReshop', $params);
        return new ItinReshopResponse($response);
    }

    // Order Change Notification Methods
    public function sendOrderChangeNotification(array $params): OrderChangeNotifResponse
    {
        $response = $this->makeRequest('orderChangeNotif', $params);
        return new OrderChangeNotifResponse($response);
    }

    /**
     * Flush the cache
     *
     * @param string|null $endpoint
     * @return void
     */
    public function flushCache(?string $endpoint = null): void
    {
        $this->cache->clear($endpoint);
    }

    /**
     * Get cache instance
     *
     * @return VerteilCache
     */
    public function getCache(): VerteilCache
    {
        return $this->cache;
    }

    // Protected Methods
    protected function makeRequest(string $endpoint, array $params)
    {
        // Check cache first
        if ($cachedResponse = $this->cache->get($endpoint, $params)) {
            $this->logger->logRequest($endpoint, ['cached' => true]);
            return $cachedResponse;
        }

        // Check rate limit
        if (!$this->rateLimiter->attempt($endpoint)) {
            $retryAfter = $this->rateLimiter->retryAfter($endpoint);
            throw new VerteilApiException(
                "Rate limit exceeded. Try again in {$retryAfter} seconds.",
                429
            );
        }

        // Execute request with retry logic
        return $this->retryHandler->execute(function () use ($endpoint, $params) {
            $this->logger->logRequest($endpoint, $params);

            // Sanitize input parameters
            $sanitizedParams = $this->sanitize($params);

            $this->setAuthorizationHeader();

            $requestClass = "Santosdave\\VerteilWrapper\\Requests\\" . ucfirst($endpoint) . "Request";
            $request = new $requestClass($sanitizedParams);

            try {
                $response = $this->client->post($request->getEndpoint(), [
                    'json' => $request->toArray(),
                    'headers' => array_filter($request->getHeaders())
                ]);

                $responseData = json_decode($response->getBody(), true);

                // Cache successful response
                $this->cache->put($endpoint, $params, $responseData);

                $this->logger->logResponse($endpoint, $response->getStatusCode(), $responseData);

                return $responseData;
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 401) {
                    // Token might be expired, clear it and retry once
                    $this->tokenStorage->clearToken();
                    $this->token = null;

                    return $this->makeRequest($endpoint, $params);
                }

                $this->logger->logError($endpoint, $e);

                if ($e->hasResponse()) {
                    $errorResponse = json_decode($e->getResponse()->getBody(), true);
                    throw new VerteilApiException(
                        $errorResponse['Errors']['Error'][0]['value'] ?? 'Unknown error',
                        $e->getCode(),
                        $e,
                        $errorResponse
                    );
                }
                throw $e;
            }
        }, $endpoint);
    }
}
