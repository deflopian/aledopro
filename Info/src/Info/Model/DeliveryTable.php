<?php
namespace Info\Model;

use Application\Model\SampleTable;
use Composer\Command\AboutCommand;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class DeliveryTable extends SampleTable
{
    protected $table ='info_delivery';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new Delivery());
        $this->initialize();
    }
}
