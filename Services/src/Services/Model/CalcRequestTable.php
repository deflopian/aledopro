<?php
namespace Services\Model;

use Application\Model\SampleTable;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class CalcRequestTable extends SampleTable
{
    protected $table ='service_re_calculation';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new CalcRequest());
        $this->initialize();
    }
}
