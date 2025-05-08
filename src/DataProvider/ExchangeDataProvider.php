<?php
declare(strict_types=1);

namespace App\DataProvider;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ExchangeDataProvider
{
    private HttpClientInterface $httpClient;
    private string $apiKey;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $_ENV['EXCHANGE_API_KEY'];
    }

    public function fetchExchangeRates(string $date, array $symbols = []): array
    {
        $url = 'https://api.exchangeratesapi.io/' . $date;
        $query = [
            'access_key' => $this->apiKey,
        ];
        if (!empty($symbols)) {
            $query['symbols'] = implode(',', $symbols);
        }

        $response = $this->httpClient->request('GET', $url, [
            'query' => $query,
        ]);

        return $response->toArray();
    }
}