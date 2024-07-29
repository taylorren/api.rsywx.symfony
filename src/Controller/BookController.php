<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\VarDumper\Cloner\AbstractCloner;

class BookController extends AbstractController
{
    private Connection $_conn;
    private $filter;
    public function __construct(Connection $connection)
    {
        $this->_conn = $connection;
        $this->filter = ' location <>"na" and location <> "--"';
        //$this->filter=' 1=1';

    }
    public function summary(Connection $connection): JsonResponse
    {
        $sql = "select count(id) bc, sum(page) pc, sum(kword) wc from book_book where" . $this->filter;
        $data = $this->_conn->fetchAssociative($sql);
        return new JsonResponse($data);
    }
    public function latest($count): JsonResponse
    {
        $sql = "select title, bookid, author, region,  purchdate from book_book where" . $this->filter . " order by id desc limit 0, $count";
        $data = $this->_conn->fetchAllAssociative($sql);
        if ($count == 1) {
            $data = $data[0];
        }

        return new JsonResponse($data);
    }
    public function random($count): JsonResponse
    {
        $sql = "select b.*, count(v.vid) vc, max(v.visitwhen) lvt from book_book b, book_visit v where b.id=v.bookid and" . $this->filter . " group by b.id order by rand() limit 0, $count";
        $data = $this->_conn->fetchAllAssociative($sql);

        foreach ($data as &$r) {
            $img_uri = $r['bookid'];
            $r['img'] = "https://api.rsywx.com/covers/$img_uri.jpg";
        }


        return new JsonResponse($data);
    }

    public function detail($bookid): JsonResponse
    {
        $sql = "select b.*, pub.name pu_name, pl.name pu_place, count(v.vid) vc, max(v.visitwhen) lvt from book_book b, book_visit v, book_publisher pub, book_place pl where b.bookid=:bookid and pub.id=b.publisher and pl.id=b.place and v.bookid=b.id group by v.bookid";
        $stmt = $this->_conn->prepare($sql);
        $q = $stmt->execute([":bookid" => $bookid]);
        $res = $q->fetchAssociative();

        return new JsonResponse($res);

    }

    public function tags($bookid):JsonResponse
    {
        $sql = "SELECT t.tag FROM book_taglist t, book_book b where b.id=t.bid and b.bookid=:bookid";
        $stmt = $this->_conn->prepare($sql);
        $q = $stmt->execute([":bookid" => $bookid]);
        $res = $q->fetchAllAssociative();
        $tags=[];
        foreach($res as $r)
        {
            $tags[]=$r['tag'];
        }
        
        return new JsonResponse($tags);
    }

    public function addTags(Request $req): JsonResponse
    {
        $id=$req->getPayload()->get('id');
        $tags=$req->getPayload()->get('tags');
        $sql = "select tag from book_taglist where bid=:id";
        $stmt=$this->_conn->prepare($sql);

        $insertSql="insert into book_taglist (bid, tag) values (:id, :tag)";
        $stmtInsert = $this->_conn->prepare($insertSql);
        $q=$stmt->execute([':id'=>$id]);
        $res=$q->fetchAllAssociative(); // Current tags

        $current_tags=[];
        foreach($res as $r)
        {
            $current_tags[]=$r['tag'];
        }
        
        $newTagList=explode(' ', $tags);
        foreach($newTagList as $tag)
        {
            if(!in_array($tag, $current_tags))
            {
                $q=$stmtInsert->execute([':id'=>$id, ':tag'=>$tag]);
            }
        }

        return new JsonResponse(['status'=>'success']);
    }

}