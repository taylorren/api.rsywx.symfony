<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Attributes as OA;

class MiscController
{
    #[OA\Get(
        path: "/misc/wotd",
        summary: "Get Word of the Day",
        description: "Returns a random word of the day with its meaning, example sentence, and word type",
        tags: ["Miscellaneous"],
        security: [["ApiKeyAuth" => []]]
    )]
    #[OA\Response(
        response: 200,
        description: "Word of the Day",
        content: new OA\JsonContent(
            properties: [
                "success" => new OA\Property(property: "success", type: "boolean", example: true),
                "data" => new OA\Property(
                    property: "data",
                    type: "object",
                    properties: [
                        "id" => new OA\Property(property: "id", type: "integer", example: 42),
                        "word" => new OA\Property(property: "word", type: "string", example: "serendipity"),
                        "meaning" => new OA\Property(property: "meaning", type: "string", example: "The occurrence of events by chance in a happy way"),
                        "sentence" => new OA\Property(property: "sentence", type: "string", example: "It was pure serendipity that led to their meeting."),
                        "type" => new OA\Property(property: "type", type: "string", example: "noun")
                    ]
                ),
                "cached" => new OA\Property(property: "cached", type: "boolean", example: false)
            ]
        )
    )]
    public function wordOfTheDay(Request $request, Response $response)
    {
        try {
            $wordModel = new \App\Models\WordOfTheDay();
            $result = $wordModel->getWordOfTheDay();
            
            $data = [
                'success' => true,
                'data' => $result['data'],
                'cached' => $result['from_cache']
            ];
            
            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $errorData = [
                'success' => false,
                'message' => $e->getMessage()
            ];
            
            $response->getBody()->write(json_encode($errorData));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    #[OA\Get(
        path: "/misc/qotd",
        summary: "Get Quote of the Day",
        description: "Returns a random quote from the collection",
        tags: ["Miscellaneous"],
        security: [["ApiKeyAuth" => []]]
    )]
    #[OA\Response(
        response: 200,
        description: "Quote of the Day",
        content: new OA\JsonContent(
            properties: [
                "success" => new OA\Property(property: "success", type: "boolean", example: true),
                "data" => new OA\Property(
                    property: "data",
                    type: "object",
                    properties: [
                        "id" => new OA\Property(property: "id", type: "integer", example: 42),
                        "quote" => new OA\Property(property: "quote", type: "string", example: "The only way to do great work is to love what you do."),
                        "source" => new OA\Property(property: "source", type: "string", example: "Steve Jobs")
                    ]
                ),
                "cached" => new OA\Property(property: "cached", type: "boolean", example: false)
            ]
        )
    )]
    public function qotd(Request $request, Response $response)
    {
        try {
            $quoteModel = new \App\Models\QuoteOfTheDay();
            $result = $quoteModel->getQuoteOfTheDay();
            
            $data = [
                'success' => true,
                'data' => $result['data'],
                'cached' => $result['from_cache']
            ];
            
            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $errorData = [
                'success' => false,
                'message' => $e->getMessage()
            ];
            
            $response->getBody()->write(json_encode($errorData));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    #[OA\Get(
        path: "/misc/weather/current",
        summary: "Get Current Weather",
        description: "Returns current weather conditions using QWeather API",
        tags: ["Miscellaneous"],
        security: [["ApiKeyAuth" => []]]
    )]
    #[OA\Parameter(
        name: "location",
        in: "query",
        description: "Location (city name, coordinates, or location ID)",
        required: false,
        schema: new OA\Schema(type: "string", example: "beijing")
    )]
    #[OA\Parameter(
        name: "refresh",
        in: "query",
        description: "Force refresh cache",
        required: false,
        schema: new OA\Schema(type: "boolean", example: false)
    )]
    #[OA\Response(
        response: 200,
        description: "Current weather conditions",
        content: new OA\JsonContent(
            properties: [
                "success" => new OA\Property(property: "success", type: "boolean", example: true),
                "data" => new OA\Property(
                    property: "data",
                    type: "object",
                    properties: [
                        "location" => new OA\Property(property: "location", type: "string", example: "beijing"),
                        "temperature" => new OA\Property(property: "temperature", type: "string", example: "25"),
                        "feels_like" => new OA\Property(property: "feels_like", type: "string", example: "27"),
                        "condition" => new OA\Property(property: "condition", type: "string", example: "Sunny"),
                        "humidity" => new OA\Property(property: "humidity", type: "string", example: "65"),
                        "pressure" => new OA\Property(property: "pressure", type: "string", example: "1013"),
                        "wind_speed" => new OA\Property(property: "wind_speed", type: "string", example: "15"),
                        "update_time" => new OA\Property(property: "update_time", type: "string", example: "2025-08-07T14:30+08:00")
                    ]
                ),
                "cached" => new OA\Property(property: "cached", type: "boolean", example: true)
            ]
        )
    )]
    public function currentWeather(Request $request, Response $response)
    {
        try {
            $queryParams = $request->getQueryParams();
            $location = $queryParams['location'] ?? '101190401'; // Suzhou, Jiangsu
            $forceRefresh = isset($queryParams['refresh']) && $queryParams['refresh'] === 'true';
            
            $weatherModel = new \App\Models\Weather();
            $result = $weatherModel->getCurrentWeather($location, $forceRefresh);
            
            $data = [
                'success' => true,
                'data' => $result['data'],
                'cached' => $result['from_cache']
            ];
            
            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $errorData = [
                'success' => false,
                'message' => $e->getMessage()
            ];
            
            $response->getBody()->write(json_encode($errorData));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    #[OA\Get(
        path: "/misc/weather/forecast",
        summary: "Get Weather Forecast",
        description: "Returns weather forecast using QWeather API",
        tags: ["Miscellaneous"],
        security: [["ApiKeyAuth" => []]]
    )]
    #[OA\Parameter(
        name: "location",
        in: "query",
        description: "Location (city name, coordinates, or location ID)",
        required: false,
        schema: new OA\Schema(type: "string", example: "beijing")
    )]
    #[OA\Parameter(
        name: "days",
        in: "query",
        description: "Number of forecast days (1-7)",
        required: false,
        schema: new OA\Schema(type: "integer", minimum: 1, maximum: 7, example: 3)
    )]
    #[OA\Parameter(
        name: "refresh",
        in: "query",
        description: "Force refresh cache",
        required: false,
        schema: new OA\Schema(type: "boolean", example: false)
    )]
    #[OA\Response(
        response: 200,
        description: "Weather forecast",
        content: new OA\JsonContent(
            properties: [
                "success" => new OA\Property(property: "success", type: "boolean", example: true),
                "data" => new OA\Property(
                    property: "data",
                    type: "object",
                    properties: [
                        "location" => new OA\Property(property: "location", type: "string", example: "beijing"),
                        "forecast" => new OA\Property(
                            property: "forecast",
                            type: "array",
                            items: new OA\Items(
                                type: "object",
                                properties: [
                                    "date" => new OA\Property(property: "date", type: "string", example: "2025-08-07"),
                                    "temp_max" => new OA\Property(property: "temp_max", type: "string", example: "28"),
                                    "temp_min" => new OA\Property(property: "temp_min", type: "string", example: "18"),
                                    "condition_day" => new OA\Property(property: "condition_day", type: "string", example: "Sunny"),
                                    "condition_night" => new OA\Property(property: "condition_night", type: "string", example: "Clear")
                                ]
                            )
                        )
                    ]
                ),
                "cached" => new OA\Property(property: "cached", type: "boolean", example: true)
            ]
        )
    )]
    public function weatherForecast(Request $request, Response $response)
    {
        try {
            $queryParams = $request->getQueryParams();
            $location = $queryParams['location'] ?? '101190401'; // Suzhou, Jiangsu
            $days = isset($queryParams['days']) ? max(1, min(7, (int)$queryParams['days'])) : 3;
            $forceRefresh = isset($queryParams['refresh']) && $queryParams['refresh'] === 'true';
            
            $weatherModel = new \App\Models\Weather();
            $result = $weatherModel->getWeatherForecast($location, $days, $forceRefresh);
            
            $data = [
                'success' => true,
                'data' => $result['data'],
                'cached' => $result['from_cache']
            ];
            
            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $errorData = [
                'success' => false,
                'message' => $e->getMessage()
            ];
            
            $response->getBody()->write(json_encode($errorData));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}