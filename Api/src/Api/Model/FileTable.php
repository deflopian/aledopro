<?php
namespace Api\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Application\Model\SampleTable;

class FileTable extends SampleTable
{
    const TYPE_IMAGE = 1;
    const TYPE_FILE = 2;

    protected $table ='files';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new File());
        $this->initialize();
    }
}
