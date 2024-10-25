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
    private string $filter;
    private int $rpp;
    public function __construct(Connection $connection)
    {
        $this->_conn = $connection;
        /*
        2023年7月，搬家。所以扔掉了一些书。在数据库的处理上，将location设置为了na或者--，所以为了更好地进行藏书管理，
        对涉及书籍的数据库操作，都需要增加一个filter
        */
        $this->filter = ' b.location <>"na" and b.location <> "--"';
        //$this->filter=' 1=1';
        $this->rpp = 10;
    }
    public function summary(Connection $connection): JsonResponse
    {
        $sql = "select count(id) bc, sum(page) pc, sum(kword) wc from book_book b where $this->filter";
        $data = $this->_conn->fetchAssociative($sql);
        return new JsonResponse($data);
    }
    public function latest(int $count): JsonResponse
    {
        $sql = "select title, bookid, author, region,  purchdate from book_book b where $this->filter order by b.id desc limit 0, $count";
        $data = $this->_conn->fetchAllAssociative($sql);
        if ($count == 1) {
            $data = $data[0];
        }

        return new JsonResponse($data);
    }
    public function random(int $count, string $base_uri): JsonResponse
    {
        $sql = "select b.*, count(v.vid) vc, max(v.visitwhen) lvt from book_book b, book_visit v where b.id=v.bookid and $this->filter group by b.id order by rand() limit 0, $count";
        $data = $this->_conn->fetchAllAssociative($sql);

        foreach ($data as &$r) {
            $img_uri = $r['bookid'];
            $r['img'] = "$base_uri/covers/$img_uri.jpg";
        }


        return new JsonResponse($data);
    }

    public function detail(string $bookid): JsonResponse
    {
        $sql = "select b.*, pub.name pu_name, pl.name pu_place, count(v.vid) vc, max(v.visitwhen) lvt from book_book b, book_visit v, book_publisher pub, book_place pl where b.bookid=:bookid and pub.id=b.publisher and pl.id=b.place and v.bookid=b.id and ".$this->filter. " group by v.bookid";
        $stmt = $this->_conn->prepare($sql);
        $q = $stmt->executeQuery([":bookid" => $bookid]);
        $res = $q->fetchAssociative();

        if(!$res) // Not found
        {
            return new JsonResponse(false);
        }
        // Add a visit record for this book
        $this->updateVisit($res['id']);

        $sql2 = "select r.title rt, r.datein, r.uri, b.title bt
                from book_headline h, book_review r, book_book b
                where h.hid=r.hid
                and h.bid=b.id
                and b.bookid=:bookid";
        $stmt2 = $this->_conn->prepare($sql2);
        $q2 = $stmt2->executeQuery([":bookid" => $bookid]);
        $res2 = $q2->fetchAllAssociative();
        $res['reviews'] = $res2;
        return new JsonResponse($res);

    }

    private function updateVisit(string $id):void
    {
        $when = new \DateTime();
        $sql = "insert into book_visit (bookid, visitwhen) value(:id, :when)";
        $stmt = $this->_conn->prepare($sql);
        $stmt->bindValue(":id", $id);
        $stmt->bindValue(":when", $when->format("Y-m-d H:i:s"));
        $stmt->executeStatement();
    }

    public function tags(string $bookid): JsonResponse
    {
        $sql = "SELECT t.tag FROM book_taglist t, book_book b where b.id=t.bid and b.bookid=:bookid";
        $stmt = $this->_conn->prepare($sql);
        $q = $stmt->executeQuery([":bookid" => $bookid]);
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
        $q = $stmt->executeQuery([':id' => $id]);
        $res = $q->fetchAllAssociative(); // Current tags

        $current_tags = [];
        foreach ($res as $r) {
            $current_tags[] = $r['tag'];
        }

        $newTagList = explode(' ', $tags);
        foreach ($newTagList as $tag) {
            if (!in_array($tag, $current_tags)) {
                $q = $stmtInsert->executeQuery([':id' => $id, ':tag' => $tag]);
            }
        }

        return new JsonResponse(['status' => 'success']);
    }

    public function list(string $type, string $value, int $page, string $base_uri): JsonResponse
    {
        $start = ($page - 1) * $this->rpp;
        $sqlSearch = "";
        $sqlPage = "";

        $value = htmlspecialchars($value);
        $type = htmlspecialchars($type);
        if ($value == '-') // match for all
        {
            $value = '';
        }

        $finalFilter = "'%$value%'";
        switch ($type) {
            case "title":
                $sqlSearch = "select * from book_book b where title like $finalFilter and $this->filter order by id desc limit $start, $this->rpp";
                $sqlPage = "select count(*) as bc from book_book b where title like $finalFilter and $this->filter";
                break;
            case "author":
                $sqlSearch = "select * from book_book b where author like $finalFilter and $this->filter order by id desc limit $start, $this->rpp";
                $sqlPage = "select count(*) as bc from book_book b where author like $finalFilter and $this->filter";
                break;
            case "tag":
                $sqlSearch = "select b.* from book_book b, book_taglist t where t.tag like $finalFilter and b.id=t.bid and $this->filter order by b.id desc limit $start, $this->rpp";
                $sqlPage = "select count(b.id) as bc from book_book b, book_taglist t where t.tag like $finalFilter and b.id=t.bid and $this->filter";
                break;
            case "misc":
                $sqlPage = "select count(b.id) bc from book_book b "
                    . "where title like $finalFilter "
                    . "or author like $finalFilter "
                    . "or id in (select bid from book_taglist where tag like $finalFilter)"
                    . " and $this->filter";
                $sqlSearch = "select b.* from book_book b "
                    . "where title like $finalFilter "
                    . "or author like $finalFilter  "
                    . "or id in (select bid from book_taglist where tag like $finalFilter) "
                    . " and $this->filter "
                    . " order by id desc "
                    . " limit $start, $this->rpp";

                break;
        }
        $selectStmt = $this->_conn->prepare($sqlSearch);
        $selectQ = $selectStmt->executeQuery();
        $res1 = $selectQ->fetchAllAssociative(); //All books returned

        foreach ($res1 as &$r) {
            $bookid = $r['bookid'];

            //TODO: interesting to notice that you have to make an API call to get the result right!
            //FIXME: Need to change the fixed api uri and put it in .env
            $tags = json_decode(file_get_contents("$base_uri/book/tags/$bookid"));
            $r['tags'] = $tags;
        }

        $pageStmt = $this->_conn->prepare($sqlPage);
        $pageQ = $pageStmt->executeQuery();
        $res2 = $pageQ->fetchAssociative();
        $books_count = $res2['bc'];
        $totalPages = ceil($books_count / $this->rpp);

        return new JsonResponse(['books' => $res1, 'pages' => $totalPages]);
    }

    public function today(): JsonResponse
    {
        //TODO: Shall I make this function call more flexible, i.e, by passing in the y/m/d?
        $sql = "select * from book_book where month(purchdate)=:m and day(purchdate)=:d and year(purchdate)<>:y order by year(purchdate)";
        $y = date('Y');
        $m = date('m');
        $d = date('d');

        $stmt = $this->_conn->prepare($sql);
        $q = $stmt->executeQuery([
            ":y" => $y,
            ":m" => $m,
            ":d" => $d
        ]);
        $res = $q->fetchAllAssociative();

        return new JsonResponse($res);
    }

//     public function visitByDay($span = 14): JsonResponse
//     {
//         $sql = "SELECT count(vid) vc, date(visitwhen) vd
// FROM book_visit 
// where date(visitwhen) >=date_sub(now(), interval $span day)
// group by vd
// order by vd desc ";
//         $stmt=$this->_conn->prepare($sql);
//         $q=$stmt->executeQuery();
//         $res = $q->fetchAllAssociative();
//         return new JsonResponse($res);   
//     }
}
