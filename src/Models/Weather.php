<?php

namespace App\Models;

use App\Cache\MemoryCache;

class Weather
{
    private $cache;
    private $apiKey;
    private $baseUrl = 'https://ne5rk4nwr6.re.qweatherapi.com/v7';

    public function __construct()
    {
        $this->cache = new MemoryCache();
        $this->apiKey = $_ENV['QWEATHER_API_KEY'] ?? null;

        if (!$this->apiKey) {
            throw new \Exception('QWeather API key not configured');
        }
    }

    public function getCurrentWeather($location = '101190401', $forceRefresh = false)
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

    public function getWeatherForecast($location = '101190401', $days = 3, $forceRefresh = false)
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

    private function fetchCurrentWeatherFromApi($location)
    {
        $url = "{$this->baseUrl}/weather/now";
        $params = [
            'location' => $location,
            'key' => $this->apiKey
        ];

        $response = $this->makeApiRequest($url, $params);

        if (!isset($response['code']) || $response['code'] !== '200') {
            $errorMsg = isset($response['code']) ? $response['code'] : 'No response code';
            $fullResponse = json_encode($response);
            throw new \Exception("QWeather Current API error: {$errorMsg}. Response: {$fullResponse}");
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

    private function fetchWeatherForecastFromApi($location, $days)
    {
        $url = "{$this->baseUrl}/weather/{$days}d";
        $params = [
            'location' => $location,
            'key' => $this->apiKey
        ];

        $response = $this->makeApiRequest($url, $params);

        if (!isset($response['code']) || $response['code'] !== '200') {
            $errorMsg = isset($response['code']) ? $response['code'] : 'No response code';
            $fullResponse = json_encode($response);
            throw new \Exception("QWeather Forecast API error: {$errorMsg}. Response: {$fullResponse}");
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

    private function makeApiRequest($url, $params)
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
            throw new \Exception("Failed to fetch weather data from QWeather API. URL: {$fullUrl}. cURL Error: {$error}");
        }

        if ($httpCode !== 200) {
            throw new \Exception("QWeather API returned HTTP {$httpCode}. URL: {$fullUrl}. Response: {$response}");
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            throw new \Exception("Invalid JSON response from QWeather API. Raw response: {$response}");
        }

        return $decoded;
    }

    public function clearWeatherCache($location = null)
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
