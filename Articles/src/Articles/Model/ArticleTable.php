<?php
namespace Articles\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Application\Model\SampleTable;

class ArticleTable extends SampleTable
{
    protected $table ='article';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new Article());
        $this->initialize();
    }
}
