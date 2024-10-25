<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\VarDumper\Cloner\AbstractCloner;

class MiscController extends AbstractController
{
    private Connection $_conn;
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

    public function lakers(int $season):JsonResponse
    {
        $summary=$this->_getWinLose($season);    

        $data=[];
        $data['summary']=$summary;
        return new JsonResponse($data);
    }
}

