<?php

class EnotpayApiClient
{
    private $apiUrl;
    private $apiKey;

    public function __construct($apiUrl, $apiKey)
    {
        $this->apiUrl = rtrim((string) $apiUrl, '/') . '/';
        $this->apiKey = (string) $apiKey;
    }

    public function request($path, $method = 'get', array $payload = [])
    {
        $url = $this->apiUrl . ltrim($path, '/');
        $body = json_encode($payload);

        $curl = curl_init();
        if (!$curl) {
            return ['error' => 'Unable to initialize cURL.'];
        }

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);

        if (strtolower($method) === 'post') {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        } else {
            curl_setopt($curl, CURLOPT_POST, 0);
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . $this->apiKey,
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response === false) {
            return ['error' => $error ?: 'Unknown cURL error.'];
        }

        if ((int) $httpCode !== 200) {
            return ['error' => 'Unexpected response code: ' . $httpCode];
        }

        $decoded = json_decode($response, true);

        if ($decoded === null) {
            return ['error' => 'Invalid JSON response.'];
        }

        return $decoded;
    }
}
