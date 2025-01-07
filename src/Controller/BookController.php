<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use GeoIp2\Database\Reader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\VarDumper\Cloner\AbstractCloner;

class BookController extends AbstractController
{
    // TODO: Do we need to santize all the inputs
    private Connection $_conn;
    private string $filter;
    private int $rpp;

    public function __construct(Connection $connection)
    {
        $this->_conn = $connection;

        /*
         * 2023年7月，搬家。所以扔掉了一些书。在数据库的处理上，将location设置为了na或者--，所以为了更好地进行藏书管理，
         * 对涉及书籍的数据库操作，都需要增加一个filter
         */
        $this->filter = ' b.location <>"na" and b.location <> "--"';
        // $this->filter=' 1=1';
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
        $sql = 'select b.*, pub.name pu_name, pl.name pu_place, count(v.vid) vc, max(v.visitwhen) lvt from book_book b, book_visit v, book_publisher pub, book_place pl where b.bookid=:bookid and pub.id=b.publisher and pl.id=b.place and v.bookid=b.id and ' . $this->filter . ' group by v.bookid';
        $stmt = $this->_conn->prepare($sql);
        $q = $stmt->executeQuery([':bookid' => $bookid]);
        $res = $q->fetchAssociative();

        if (!$res)  // Not found
        {
            return new JsonResponse(false);
        }
        // Add a visit record for this book
        $this->updateVisit($res['id']);
        // Get the reviews
        $sql2 = 'select r.title rt, r.datein, r.uri, b.title bt
                from book_headline h, book_review r, book_book b
                where h.hid=r.hid
                and h.bid=b.id
                and b.bookid=:bookid';
        $stmt2 = $this->_conn->prepare($sql2);
        $q2 = $stmt2->executeQuery([':bookid' => $bookid]);
        $res2 = $q2->fetchAllAssociative();
        $res['reviews'] = $res2;

        return new JsonResponse($res);
    }

    public function related(string $bookid): JsonResponse
    {
        // 获取书籍标签
        $resp = $this->tags($bookid);
        $tags = json_decode($resp->getContent(), true);

        // 确保标签数组不为空
        if (empty($tags)) {
            return new JsonResponse([
                'status' => 'error',
                'message' => '未找到书籍标签',
                'related_books' => []
            ]);
        }

        // 获取标签权重
        $weights = $this->getTagWeights($tags);

        // 生成相关书籍的 SQL 查询
        $sql = $this->generateRelatedBooksSQL($bookid, $tags, $weights);

        // 执行查询
        $conn = $this->_conn;
        $stmt = $conn->prepare($sql);

        // 绑定 bookId
        $stmt->bindValue(':bookId', $bookid, \PDO::PARAM_INT);

        // 绑定 tags
        foreach ($tags as $index => $tag) {
            $stmt->bindValue(":tag$index", $tag, \PDO::PARAM_STR);
        }

        $q=$stmt->execute();
        $relatedBooks = $q->fetchAllAssociative();

        return new JsonResponse($relatedBooks);
    }

    private function getTagWeights(array $tags): array
    {
        $conn = $this->_conn;  // 使用现有的数据库连接

        // 构建 SQL 查询
        $placeholders = rtrim(str_repeat('?,', count($tags)), ',');  // 创建占位符
        $sql = "SELECT tag, COUNT(*) as usage_count
            FROM book_taglist
            WHERE tag IN ($placeholders)
            GROUP BY tag";

        $stmt = $conn->prepare($sql);
        $q2 = $stmt->execute($tags);  // 直接传递标签数组

        $results = $q2->fetchAllAssociative();

        $weights = [];
        $maxUsage = max(array_column($results, 'usage_count'));

        foreach ($results as $result) {
            // 使用对数计算权重
            $weight = 5 - (4 * log($result['usage_count']) / log($maxUsage));
            $weights[$result['tag']] = round($weight, 2);
        }

        return $weights;
    }

    private function generateRelatedBooksSQL(int $bookId, array $tags, array $weights): string
    {
        // 基础 SQL 部分
        $sql = "SELECT 
        b.id, b.bookid,
        b.title,
        b.author,
        b.publisher,
        DATE_FORMAT(b.pubdate, '%Y-%m-%d') as publish_date,
        COUNT(bt.tag) as matching_tags_count,
        GROUP_CONCAT(DISTINCT bt.tag) as matching_tags,
        (COUNT(bt.tag) * 10 + ";

        // 为每个标签生成 CASE 语句，使用权重
        $caseClauses = [];
        foreach ($tags as $tag) {
            $weight = $weights[$tag] ?? 0;  // 获取标签的权重
            $caseClauses[] = "CASE 
            WHEN GROUP_CONCAT(DISTINCT bt.tag) LIKE '%{$tag}%' THEN {$weight}
            ELSE 0 
        END";
        }

        // 将所有 CASE 语句连接起来
        $sql .= implode(' + ', $caseClauses);

        // 完成 SQL
        $sql .= ') AS relevance_score
        FROM 
            book_book b
            INNER JOIN book_taglist bt ON b.id = bt.bid
        WHERE 
            b.id != :bookId
            AND bt.tag IN (' . implode(',', array_map(fn($i) => ":tag$i", array_keys($tags))) . ') and ' . $this->filter . '
        GROUP BY 
            b.id, b.title, b.author, b.publisher, b.pubdate
        HAVING 
            matching_tags_count > 0
        ORDER BY 
            relevance_score DESC,
            matching_tags_count DESC,
            publish_date DESC
        LIMIT 6';

        return $sql;
    }

    private function updateVisit(string $id): void
    {
        $when = new \DateTime();

        // Get visitor's information from request parameters
        $request = Request::createFromGlobals();
        $ip = $request->query->get('ip', 'Unknown');
        $country = $request->query->get('country', 'Unknown');
        $city = $request->query->get('city', 'Unknown');
        $region = $request->query->get('region', 'Unknown');

        $sql = 'insert into book_visit (bookid, visitwhen, ip_address, country, city, region) 
                values (:id, :when, :ip, :country, :city, :region)';

        $stmt = $this->_conn->prepare($sql);
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':when', $when->format('Y-m-d H:i:s'));
        $stmt->bindValue(':ip', $ip);
        $stmt->bindValue(':country', $country);
        $stmt->bindValue(':city', $city);
        $stmt->bindValue(':region', $region);

        $stmt->executeStatement();
    }

    public function tags(string $bookid): JsonResponse
    {
        $sql = 'SELECT t.tag FROM book_taglist t, book_book b where b.id=t.bid and b.bookid=:bookid';
        $stmt = $this->_conn->prepare($sql);
        $q = $stmt->executeQuery([':bookid' => $bookid]);
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
        $sql = 'select tag from book_taglist where bid=:id';
        $stmt = $this->_conn->prepare($sql);

        $insertSql = 'insert into book_taglist (bid, tag) values (:id, :tag)';
        $stmtInsert = $this->_conn->prepare($insertSql);
        $q = $stmt->executeQuery([':id' => $id]);
        $res = $q->fetchAllAssociative();  // Current tags

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
        $sqlSearch = '';
        $sqlPage = '';

        $value = htmlspecialchars($value);
        $type = htmlspecialchars($type);
        if ($value == '-')  // match for all
        {
            $value = '';
        }

        $finalFilter = "'%$value%'";
        switch ($type) {
            case 'title':
                $sqlSearch = "select * from book_book b where title like $finalFilter and $this->filter order by id desc limit $start, $this->rpp";
                $sqlPage = "select count(*) as bc from book_book b where title like $finalFilter and $this->filter";
                break;
            case 'author':
                $sqlSearch = "select * from book_book b where author like $finalFilter and $this->filter order by id desc limit $start, $this->rpp";
                $sqlPage = "select count(*) as bc from book_book b where author like $finalFilter and $this->filter";
                break;
            case 'tag':
                $sqlSearch = "select b.* from book_book b, book_taglist t where t.tag like $finalFilter and b.id=t.bid and $this->filter order by b.id desc limit $start, $this->rpp";
                $sqlPage = "select count(b.id) as bc from book_book b, book_taglist t where t.tag like $finalFilter and b.id=t.bid and $this->filter";
                break;
            case 'misc':
                $sqlPage = 'select count(b.id) bc from book_book b '
                    . "where title like $finalFilter "
                    . "or author like $finalFilter "
                    . "or id in (select bid from book_taglist where tag like $finalFilter)"
                    . " and $this->filter";
                $sqlSearch = 'select b.* from book_book b '
                    . "where title like $finalFilter "
                    . "or author like $finalFilter  "
                    . "or id in (select bid from book_taglist where tag like $finalFilter) "
                    . " and $this->filter "
                    . ' order by id desc '
                    . " limit $start, $this->rpp";

                break;
        }
        $selectStmt = $this->_conn->prepare($sqlSearch);
        $selectQ = $selectStmt->executeQuery();
        $res1 = $selectQ->fetchAllAssociative();  // All books returned

        foreach ($res1 as &$r) {
            $bookid = $r['bookid'];

            // TODO: interesting to notice that you have to make an API call to get the result right!
            // FIXME: Need to change the fixed api uri and put it in .env
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
        // TODO: Shall I make this function call more flexible, i.e, by passing in the y/m/d?
        $sql = "select * from book_book b where $this->filter and month(b.purchdate)=:m and day(b.purchdate)=:d and year(b.purchdate)<>:y order by year(b.purchdate)";
        
        $y = date('Y');
        $m = date('m');
        $d = date('d');

        $stmt = $this->_conn->prepare($sql);
        $q = $stmt->executeQuery([
            ':y' => $y,
            ':m' => $m,
            ':d' => $d
        ]);
        $res = $q->fetchAllAssociative();

        return new JsonResponse($res);
    }

    private function getTagWeight(array $tags): array
    {
        $sql = 'SELECT tag, COUNT(*) as usage_count
                FROM book_taglist
                WHERE tag IN (:tags)
                GROUP BY tag';

        $stmt = $conn->prepare($sql);
        $stmt->bindValue('tags', $tags, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);
        $results = $stmt->executeQuery()->fetchAllAssociative();

        $weights = [];
        $maxUsage = max(array_column($results, 'usage_count'));

        foreach ($results as $result) {
            $weight = 5 - (4 * log($result['usage_count']) / log($maxUsage));
            $weights[$result['tag']] = round($weight, 2);
        }

        return $weights;
    }
}
