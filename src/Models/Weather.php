<?php

namespace App\Models;

use App\Cache\ApcuCache;
use App\Config\Config;
use App\Exceptions\ApiException;
use App\Exceptions\UpstreamException;
use App\Exceptions\ValidationException;

class Weather
{
    private ApcuCache $cache;
    private ?string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->cache = new ApcuCache();
        $this->apiKey = $_ENV['QWEATHER_API_KEY'] ?? null;
        $this->baseUrl = rtrim(Config::get('QWEATHER_BASE_URL', 'https://ne5rk4nwr6.re.qweatherapi.com/v7') ?? '', '/');

        if (!$this->apiKey) {
            throw new ApiException('Weather service is not configured', 500, 'QWEATHER_API_KEY missing from environment');
        }
    }

    public function getCurrentWeather(string $location = '101190401', bool $forceRefresh = false): array
    {
        $cacheKey = "weather_current_{$location}";

        // Cache weather data for 10 minutes (weather doesn't change that frequently)
        $weatherCacheTtl = 600; // 10 minutes

        // Get cached data
        $weatherData = null;
        if (!$forceRefresh) {
            $weatherData = $this->cache->get($cacheKey);
        }

        $fromCache = ($weatherData !== null);

        // If not cached, fetch from QWeather API
        if ($weatherData === null) {
            $weatherData = $this->fetchCurrentWeatherFromApi($location);

            // Cache the result
            $this->cache->set($cacheKey, $weatherData, $weatherCacheTtl);
        }

        return [
            'data' => $weatherData,
            'from_cache' => $fromCache
        ];
    }

    public function getWeatherForecast(string $location = '101190401', int $days = 3, bool $forceRefresh = false): array
    {
        $cacheKey = "weather_forecast_{$location}_{$days}d";

        // Cache forecast data for 30 minutes
        $forecastCacheTtl = 1800; // 30 minutes

        // Get cached data
        $forecastData = null;
        if (!$forceRefresh) {
            $forecastData = $this->cache->get($cacheKey);
        }

        $fromCache = ($forecastData !== null);

        // If not cached, fetch from QWeather API
        if ($forecastData === null) {
            $forecastData = $this->fetchWeatherForecastFromApi($location, $days);

            // Cache the result
            $this->cache->set($cacheKey, $forecastData, $forecastCacheTtl);
        }

        return [
            'data' => $forecastData,
            'from_cache' => $fromCache
        ];
    }

    private function fetchCurrentWeatherFromApi(string $location): array
    {
        $url = "{$this->baseUrl}/weather/now";
        $params = [
            'location' => $location,
            'key' => $this->apiKey
        ];

        $response = $this->makeApiRequest($url, $params);

        if (!isset($response['code']) || $response['code'] !== '200') {
            $errorMsg = isset($response['code']) ? $response['code'] : 'No response code';
            throw new UpstreamException('Weather service returned an error response', "QWeather current API error code: {$errorMsg}. Response: " . json_encode($response));
        }

        $weather = $response['now'];

        return [
            'location' => $location,
            'temperature' => $weather['temp'],
            'feels_like' => $weather['feelsLike'],
            'condition' => $weather['text'],
            'condition_code' => $weather['icon'],
            'humidity' => $weather['humidity'],
            'pressure' => $weather['pressure'],
            'visibility' => $weather['vis'],
            'wind_direction' => $weather['windDir'],
            'wind_speed' => $weather['windSpeed'],
            'wind_scale' => $weather['windScale'],
            'update_time' => $weather['obsTime'],
            'api_response_code' => $response['code']
        ];
    }

    private function fetchWeatherForecastFromApi(string $location, int $days): array
    {
        // QWeather v7 only supports 3d/7d/10d/15d/30d endpoints.
        // Snap the requested day count to the nearest supported value.
        $supported = [3, 7, 10, 15, 30];
        $endpointDays = $supported[0];
        foreach ($supported as $s) {
            if ($days <= $s) {
                $endpointDays = $s;
                break;
            }
            $endpointDays = $s;
        }

        $url = "{$this->baseUrl}/weather/{$endpointDays}d";
        $params = [
            'location' => $location,
            'key' => $this->apiKey
        ];

        $response = $this->makeApiRequest($url, $params);

        if (!isset($response['code']) || $response['code'] !== '200') {
            $errorMsg = isset($response['code']) ? $response['code'] : 'No response code';
            throw new UpstreamException('Weather service returned an error response', "QWeather forecast API error code: {$errorMsg}. Response: " . json_encode($response));
        }

        $forecast = [];
        foreach ($response['daily'] as $day) {
            $forecast[] = [
                'date' => $day['fxDate'],
                'temp_max' => $day['tempMax'],
                'temp_min' => $day['tempMin'],
                'condition_day' => $day['textDay'],
                'condition_night' => $day['textNight'],
                'icon_day' => $day['iconDay'],
                'icon_night' => $day['iconNight'],
                'humidity' => $day['humidity'],
                'pressure' => $day['pressure'],
                'wind_direction' => $day['windDirDay'],
                'wind_speed' => $day['windSpeedDay'],
                'wind_scale' => $day['windScaleDay'],
                'sunrise' => $day['sunrise'],
                'sunset' => $day['sunset']
            ];
        }

        return [
            'location' => $location,
            'forecast' => $forecast,
            'update_time' => $response['updateTime'],
            'api_response_code' => $response['code']
        ];
    }

    private function makeApiRequest(string $url, array $params): array
    {
        $queryString = http_build_query($params);
        $fullUrl = "{$url}?{$queryString}";

        // Use cURL for better handling of compressed responses
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'RSYWX-API/1.0');
        curl_setopt($ch, CURLOPT_ENCODING, ''); // Enable automatic decompression
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($error)) {
            throw new UpstreamException('Weather service is unreachable', "URL: {$fullUrl}. cURL error: {$error}");
        }

        if ($httpCode !== 200) {
            // 4xx from QWeather means the client sent an invalid parameter
            // (e.g. a city name instead of a LocationID or coordinates).
            if ($httpCode >= 400 && $httpCode < 500) {
                if ($httpCode === 401 || $httpCode === 403) {
                    throw new ApiException('Weather service is misconfigured', 500, "QWeather returned HTTP {$httpCode} (auth failure). URL: {$fullUrl}. Response: {$response}");
                }
                throw new ValidationException('Invalid weather request parameters', "QWeather returned HTTP {$httpCode}. URL: {$fullUrl}. Response: {$response}");
            }
            throw new UpstreamException('Weather service error', "QWeather returned HTTP {$httpCode}. URL: {$fullUrl}. Response: {$response}");
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            throw new UpstreamException('Invalid response from weather service', "Unparseable JSON from QWeather. Raw response: {$response}");
        }

        return $decoded;
    }

    public function clearWeatherCache(?string $location = null): void
    {
        if ($location) {
            $this->cache->delete("weather_current_{$location}");
            // Clear forecast caches for this location
            for ($days = 1; $days <= 7; $days++) {
                $this->cache->delete("weather_forecast_{$location}_{$days}d");
            }
        } else {
            // Clear all weather cache
            $this->cache->clear();
        }
    }
}
