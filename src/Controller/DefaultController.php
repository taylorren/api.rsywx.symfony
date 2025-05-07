<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\VarDumper\Cloner\AbstractCloner;

class DefaultController extends AbstractController
{
    private Connection $_conn;
    public function __construct(Connection $connection)
    {   
        $this->_conn=$connection;
        
    }
    public function __destruct()
    {
        if($this->_conn->isConnected())
        {
            $this->_conn->close();
        }
    }
    public function index(): JsonResponse
    {
        $data=[
            'Name'=>'RSYWX API',
            'Version'=>'4.0',
            'Framwork'=>'Symfony 7.1',
            'Developed By'=>'任氏有无轩主人',
            'Email'=>'taylor.ren@gmail.com'
        ];
        return new JsonResponse($data);
    }
    
    public function qotd(): JsonResponse
    {
        $sql="select * from qotd order by rand() limit 0, 1";
        $data=$this->_conn->fetchAssociative($sql);
        return new JsonResponse($data);
    }
}