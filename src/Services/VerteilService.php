<?php

namespace Santosdave\VerteilWrapper\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Santosdave\VerteilWrapper\Exceptions\VerteilApiException;
use Santosdave\VerteilWrapper\Responses\AirShoppingResponse;
use Santosdave\VerteilWrapper\Responses\OrderViewResponse;
use Santosdave\VerteilWrapper\Responses\SeatAvailabilityResponse;

class VerteilService
{
    protected $client;
    protected $config;
    protected $token;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->client = new Client([
            'base_uri' => $config['base_url'],
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    // Authentication Methods
    public function authenticate()
    {
        $response = $this->client->post('/oauth2/token', [
            'auth' => [$this->config['username'], $this->config['password']],
            'query' => [
                'grant_type' => 'client_credentials',
                'scope' => 'api'
            ]
        ]);

        $data = json_decode($response->getBody(), true);
        $this->token = $data['access_token'];
        return $this;
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
    public function flightPrice(array $params)
    {
        return $this->makeRequest('flightPrice', $params);
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
    public function getServiceList(array $params): array
    {
        $type = $params['type'] ?? 'pre';
        return $this->makeRequest($type . 'ServiceList', $params);
    }


    // Protected Methods
    protected function makeRequest(string $endpoint, array $params)
    {
        $this->setAuthorizationHeader();

        $requestClass = "Verteil\\Laravel\\Requests\\" . ucfirst($endpoint) . "Request";
        $request = new $requestClass($params);

        try {
            $response = $this->client->post($request->getEndpoint(), [
                'json' => $request->toArray(),
                'headers' => array_filter($request->getHeaders())
            ]);

            return json_decode($response->getBody(), true);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            if ($e->hasResponse()) {
                $errorResponse = json_decode($e->getResponse()->getBody(), true);
                throw new VerteilApiException(
                    $errorResponse['Errors']['Error'][0]['value'] ?? 'Unknown error',
                    $e->getCode()
                );
            }
            throw $e;
        }
    }
}
