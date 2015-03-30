<?php
namespace Info\Model;

use Application\Model\SampleTable;
use Composer\Command\AboutCommand;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class JobTable extends SampleTable
{
    protected $table ='info_job';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new Job());
        $this->initialize();
    }
}
