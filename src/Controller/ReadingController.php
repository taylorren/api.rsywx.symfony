<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\VarDumper\Cloner\AbstractCloner;

class ReadingController extends AbstractController
{
    private Connection $_conn;
    public function __construct(Connection $connection)
    {
        $this->_conn = $connection;
    }
    public function summary(): JsonResponse
    {
        $sql = 'select count(r.id) rc, count(distinct(h.hid)) hc from book_review r, book_headline h where r.hid=h.hid';
        $summary = $this->_conn->fetchAssociative($sql);

        $res = [
            'hc' => $summary['hc'],
            'rc' => $summary['rc'],
        ];

        return new JsonResponse($res);
    }
    public function latest($count=1): JsonResponse
    {
        $sql = 'select h.*, r.*, b.title as book_title, b.bookid as book_bookid from book_headline as h, book_review as r, book_book as b where r.hid=h.hid and h.bid=b.id  order by r.datein desc limit 0,  ' . $count;

        $data = $this->_conn->fetchAllAssociative($sql);

        if($count==1)
        {
            $data=$data[0];
        }
        return new JsonResponse($data);
    }
}
