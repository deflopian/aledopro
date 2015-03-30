<?php
namespace Documents\Model;

use Application\Model\SampleTable;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class DocumentTable extends SampleTable
{
    protected $table ='documents';

    const TYPE_CATALOG = 1;
    const TYPE_COMMENT = 2;
    const TYPE_CERTIFICATE = 3;
    const TYPE_INSTRUCTION = 4;

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new Document());
        $this->initialize();
    }
}
