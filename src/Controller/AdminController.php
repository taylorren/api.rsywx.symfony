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
    private string $filter;

    public function __construct(Connection $connection)
    {
        $this->_conn = $connection;
        $this->filter = ' b.location <>"na" and b.location <> "--"';
        /*
        2023年7月，搬家。所以扔掉了一些书。在数据库的处理上，将location设置为了na或者--，所以为了更好地进行藏书管理，
        对涉及书籍的数据库操作，都需要增加一个filter
        */
    }
    public function __destruct()
    {
        if($this->_conn->isConnected())
        {
            $this->_conn->close();
        }
    }
    public function visitByDay(int $span = 30): JsonResponse
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

    public function hotBooks(): JsonResponse
    {
        $sql="SELECT b.title, b.bookid, count(v.vid) vc, max(v.visitwhen) lvt FROM book_book b, book_visit v
where b.id=v.bookid
group by b.id
order by vc desc 
limit 0, 20";

        $stmt=$this->_conn->prepare($sql);
        $q=$stmt->executeQuery();
        $res = $q->fetchAllAssociative();
        
        return new JsonResponse($res);   
    }
    
    public function coldBooks(): JsonResponse
    {
        $sql = "SELECT b.title, b.bookid, count(v.vid) vc, max(v.visitwhen) lvt FROM book_book b
        LEFT JOIN book_visit v ON b.id = v.bookid where ".$this->filter." 
        GROUP BY b.id
        ORDER BY vc ASC 
        LIMIT 0, 20";

        $stmt = $this->_conn->prepare($sql);
        $q = $stmt->executeQuery();
        $res = $q->fetchAllAssociative();
        
        return new JsonResponse($res);   
    }

    public function recentBooks():JsonResponse
    {
        // First get the recent books
        $sql = "SELECT b.title, b.bookid, count(v.vid) vc, max(v.visitwhen) lvt 
        FROM book_book b 
        JOIN book_visit v ON b.id = v.bookid 
        GROUP BY b.id
        ORDER BY lvt DESC 
        LIMIT 0, 20";

        $stmt = $this->_conn->prepare($sql);
        $q = $stmt->executeQuery();
        $books = $q->fetchAllAssociative();

        // Then get the city for each book's last visit
        foreach ($books as &$book) {
            $sql = "SELECT city FROM book_visit 
                   WHERE bookid = ? AND visitwhen = ? 
                   LIMIT 1";
            $stmt = $this->_conn->prepare($sql);
            $q = $stmt->executeQuery([$book['bookid'], $book['lvt']]);
            $result = $q->fetchAssociative();
            $book['city'] = $result ? $result['city'] : null;
        }

        return new JsonResponse($books);   
    }

    public function forgetBooks():JsonResponse
    {
        $sql = "SELECT b.title, b.bookid, count(v.vid) vc, max(v.visitwhen) lvt FROM book_book b, book_visit v
        where b.id=v.bookid and ".$this->filter." 
        group by b.id
        order by lvt
        limit 0, 20";

        $stmt = $this->_conn->prepare($sql);
        $q = $stmt->executeQuery();
        $res = $q->fetchAllAssociative();

        return new JsonResponse($res);   
    }
}
