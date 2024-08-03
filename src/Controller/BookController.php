<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\VarDumper\Cloner\AbstractCloner;

class BookController extends AbstractController
{
    //TODO: Do we need to santize all the inputs
    private Connection $_conn;
    private $filter;
    private $rpp;
    public function __construct(Connection $connection)
    {
        $this->_conn = $connection;
        /*
        2023年7月，搬家。所以扔掉了一些书。在数据库的处理上，将location设置为了na或者--，所以为了更好地进行藏书管理，
        对涉及书籍的数据库操作，都需要增加一个filter
        */
        $this->filter = ' location <>"na" and location <> "--"'; 
        //$this->filter=' 1=1';
        $this->rpp = 10;
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
            $r['img'] = "http://api/covers/$img_uri.jpg";
        }


        return new JsonResponse($data);
    }

    public function detail($bookid): JsonResponse
    {
        $sql = "select b.*, pub.name pu_name, pl.name pu_place, count(v.vid) vc, max(v.visitwhen) lvt from book_book b, book_visit v, book_publisher pub, book_place pl where b.bookid=:bookid and pub.id=b.publisher and pl.id=b.place and v.bookid=b.id group by v.bookid";
        $stmt = $this->_conn->prepare($sql);
        $q = $stmt->execute([":bookid" => $bookid]);
        $res = $q->fetchAssociative();
        //TODO: Need to add a visit record for this book
        return new JsonResponse($res);

    }

    public function tags($bookid): JsonResponse
    {
        $sql = "SELECT t.tag FROM book_taglist t, book_book b where b.id=t.bid and b.bookid=:bookid";
        $stmt = $this->_conn->prepare($sql);
        $q = $stmt->execute([":bookid" => $bookid]);
        $res = $q->fetchAllAssociative();
        $tags = [];
        foreach ($res as $r) {
            $tags[] = $r['tag'];
        }
        return new JsonResponse($tags);
        
    }

    public function addTags(Request $req): JsonResponse
    {
        $id = $req->getPayload()->get('id');
        $tags = $req->getPayload()->get('tags');
        $sql = "select tag from book_taglist where bid=:id";
        $stmt = $this->_conn->prepare($sql);

        $insertSql = "insert into book_taglist (bid, tag) values (:id, :tag)";
        $stmtInsert = $this->_conn->prepare($insertSql);
        $q = $stmt->execute([':id' => $id]);
        $res = $q->fetchAllAssociative(); // Current tags

        $current_tags = [];
        foreach ($res as $r) {
            $current_tags[] = $r['tag'];
        }

        $newTagList = explode(' ', $tags);
        foreach ($newTagList as $tag) {
            if (!in_array($tag, $current_tags)) {
                $q = $stmtInsert->execute([':id' => $id, ':tag' => $tag]);
            }
        }

        return new JsonResponse(['status' => 'success']);
    }

    public function list($type, $value, $page): JsonResponse
    {
        $start=($page-1)*$this->rpp;
        $sqlSearch = "";
        $sqlPage = "";
        
        if($value=='-') // match for all
        {
            $value='';
        }

        $finalFilter="'%$value%'";

        switch ($type) {
            case "title":
                $sqlSearch="select * from book_book where title like $finalFilter and $this->filter order by id desc limit $start, $this->rpp";
                $sqlPage="select count(*) as bc from book_book where title like $finalFilter and $this->filter";
                break;
            case "author":
                $sqlSearch="select * from book_book where author like $finalFilter and $this->filter order by id desc limit $start, $this->rpp";
                $sqlPage="select count(*) as bc from book_book where author like $finalFilter and $this->filter";
                break;
            case "tag":
                break;
            case "misc":
                break;
        }

        $selectStmt = $this->_conn->prepare($sqlSearch);
        $selectQ=$selectStmt->execute();
        $res1=$selectQ->fetchAllAssociative(); //All books returned

        foreach($res1 as &$r)
        {
            $bookid=$r['bookid'];
            
            //TODO: interesting to notice that you have to make an API call to get the result right!
            //FIXME: Need to change the fixed api uri and put it in .env
            $tags=json_decode(file_get_contents("http://api/book/tags/$bookid"));
            $r['tags']=$tags;
        }

        $pageStmt = $this->_conn->prepare($sqlPage);
        $pageQ=$pageStmt->execute();
        $res2=$pageQ->fetchAssociative();
        $books_count=$res2['bc'];
        $totalPages=ceil($books_count/$this->rpp);

        return new JsonResponse(['books'=>$res1, 'pages'=> $totalPages]);
    }

    public function today():JsonResponse
    {
        //TODO: Shall I make this function call more flexible, i.e, by passing in the y/m/d?
        $sql="select * from book_book where month(purchdate)=:m and day(purchdate)=:d and year(purchdate)<>:y order by year(purchdate)";
        $y=date('Y');
        $m=date('m');
        $d=date('d');

        $stmt=$this->_conn->prepare($sql);
        $q = $stmt->execute([
            ":y"    =>  $y,
            ":m"    =>  $m,
            ":d"    =>  $d
        ]);       
        $res = $q->fetchAllAssociative();
        
        return new JsonResponse($res);
    }


}