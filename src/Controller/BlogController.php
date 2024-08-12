<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\VarDumper\Cloner\AbstractCloner;
use Doctrine\Persistence\ManagerRegistry;

class BlogController extends AbstractController
{
    private Connection $_conn;
    public function __construct(ManagerRegistry $doctrine)
    {
        $blogManager = $doctrine->getManager('blog');
        $this->_conn = $blogManager->getConnection();
    }

    public function latest($count = 1): JsonResponse
    {
        $sql = "select wp.post_date pd, wp.post_excerpt excerpt, wpm2.meta_value media, wp.guid link, wp.post_title title
from wp_postmeta wpm1, wp_postmeta wpm2, wp_posts wp
where wpm1.meta_value=wpm2.post_id
and wpm1.meta_key like '%thumb%'
and wpm2.meta_key like '%attached%'
and wp.ID=wpm1.post_id
order by wp.post_date desc
limit 0, $count";


        $latest = $this->_conn->fetchAllAssociative($sql);
        if ($count == 1) {
            $latest = $latest[0];
        }

        return new JsonResponse($latest);
    }
    public function summary(): JsonResponse
    {
        $sql = "SELECT count(ID) as total, sum(comment_count) as comments FROM wordpress.wp_posts where post_type='post'";


        $summary = $this->_conn->fetchAssociative($sql);

        return new JsonResponse($summary);
    }

    public function today()
    {
        $today = new \DateTime();
        $m = $today->format('m');
        $d = $today->format('d');
        $y = $today->format('Y');

        $sql = "
SELECT wpp.post_title title, wpm.meta_value pv, wpp.post_content_filtered content, wpp.id link, year(wpp.post_date) year from wp_posts wpp, wp_postmeta wpm
where wpp.ID=wpm.post_id
and month(wpp.post_date)=:m
and day(wpp.post_date)=:d
and year(wpp.post_date)<>:y
and wpm.meta_key='post_view'
and wpp.post_type='post'
and wpp.post_status='publish'
order by wpp.id
";
        $stmt = $this->_conn->prepare($sql);
        $q = $stmt->executeQuery([":m" => $m, ":d" => $d, ":y" => $y]);
        $res = $q->fetchAllAssociative();
        return new JsonResponse($res);

    }






}
