<?php
namespace Articles\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Application\Model\SampleTable;

class TagToArticleTable extends SampleTable
{
    protected $table ='tag_to_article';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new TagToArticle());
        $this->initialize();
    }
}
