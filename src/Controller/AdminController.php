<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\VarDumper\Cloner\AbstractCloner;

class AdminController extends AbstractController
{
    private Connection $_conn;
    public function __construct(Connection $connection)
    {
        $this->_conn = $connection;
        /*
        2023年7月，搬家。所以扔掉了一些书。在数据库的处理上，将location设置为了na或者--，所以为了更好地进行藏书管理，
        对涉及书籍的数据库操作，都需要增加一个filter
        */
    }
    public function visitByDay($span = 14): JsonResponse
    {
        $sql = "SELECT count(vid) vc, date(visitwhen) vd
FROM book_visit 
where date(visitwhen) >=date_sub(now(), interval $span day)
group by vd
order by vd";
        $stmt=$this->_conn->prepare($sql);
        $q=$stmt->executeQuery();
        $res = $q->fetchAllAssociative();
        return new JsonResponse($res);   
    }

}
