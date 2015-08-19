<?php
namespace Developers\Model;

use Application\Model\SampleTable;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class DeveloperMemberTable extends SampleTable
{
    protected $table ='developer_member';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new DeveloperMember());
        $this->initialize();
    }
}
