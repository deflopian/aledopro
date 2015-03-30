<?php
namespace Discount\Model;

use Application\Model\SampleTable;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class PartnerGroupTable extends SampleTable
{
    protected $table ='partner_groups';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new PartnerGroup());
        $this->initialize();
    }

}