<?php

namespace App\Controller;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\VarDumper\Cloner\AbstractCloner;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MiscController extends AbstractController
{
    private Connection $_conn;
    private HttpClientInterface $weather_client;

    public function __construct(Connection $connection)
    {
        $this->_conn = $connection;
    }
    /**
     * @return array<mixed>
     */
    private function _getWinLose(int $season):array
    {
        $win_lose_sql = "SELECT 
    SUM(CASE WHEN winlose = 'W' THEN 1 ELSE 0 END) AS win,
    SUM(CASE WHEN winlose = 'L' THEN 1 ELSE 0 END) AS loss
FROM 
    lakers
WHERE 
    season=:season and id>0";

        $stmt = $this->_conn->prepare($win_lose_sql);
        $q = $stmt->executeQuery([":season" => $season]);
        $res = $q->fetchAssociative();
        
        $win=(int)$res['win'];
        $loss=(int)$res['loss'];
        $total=$win+$loss;
        $per=0;
        if($total!=0)
        {
            $per=$win/$total*100;
        }
        
        $summary=[];
        $summary['win']=$win;
        $summary['loss']=$loss;
        $summary['per']=$per;

        return $summary;
    }
    /**
     * @return array<mixed>
     */
    private function _getGames(int $span): array
    {
        $currentDate = new \DateTime();
        // $pastDate = $currentDate->modify("-$span days")->format('Y-m-d');
        // $futureDate = $currentDate->modify("+$span days")->format('Y-m-d');

        $games_sql = "SELECT * FROM lakers
        where dateplayed between date_sub(now(), interval $span day) and date_add(now(), interval $span day)
        order by dateplayed";
        
        $stmt = $this->_conn->prepare($games_sql);
        
        //$q=$stmt->executeQuery([':pastDate' => $pastDate, ':futureDate' => $futureDate]);
        $q=$stmt->executeQuery();
        $games = $q->fetchAllAssociative();

        return $games;
    }

    public function lakers(int $season, int $span=14):JsonResponse
    {
        $summary=$this->_getWinLose($season);
        $games=$this->_getGames($span);

        $data=[];
        $data['summary']=$summary;
        $data['games']=$games;

        return new JsonResponse($data);
    }

    // Return a random word from the wotd table
    public function wotd(): JsonResponse
    {
        $sql = "SELECT word, meaning, sentence, type FROM wotd ORDER BY RAND() LIMIT 1";
        $stmt = $this->_conn->prepare($sql);
        $q = $stmt->executeQuery();
        $word = $q->fetchAssociative();

        return new JsonResponse([
            'word' => $word['word'],
            'meaning' => $word['meaning'],
            'sentence' => $word['sentence'],
            'type' => $word['type']
        ]);
    }

    // Return the current weather in Suzhou from 和风
    public function weather(): JsonResponse
    {
        $this->weather_client = HttpClient::create();
        $key = $this->getParameter('WEATHER_API_KEY');
        $weather_api = "https://devapi.qweather.com/v7/weather/now?location=101190401&key=$key";
        $response = $this->weather_client->request(
            'GET',
            $weather_api,
        );
        
        $res=$response->toArray();
        
        return new JsonResponse($res);
    }
}
