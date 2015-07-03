<?php
namespace Developers\Model;

use Application\Model\SampleTable;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class DeveloperTable extends SampleTable
{
    protected $table ='developers';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new Developer());
        $this->initialize();
    }
}
