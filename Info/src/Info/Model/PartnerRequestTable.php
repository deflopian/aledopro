<?php
namespace Info\Model;

use Application\Model\SampleTable;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class PartnerRequestTable extends SampleTable
{
    protected $table ='partner_req';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new PartnerRequest());
        $this->initialize();
    }
}
