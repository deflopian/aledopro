<?php
namespace Contacts\Model;

use Application\Model\SampleTable;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class AledoContactTable extends SampleTable
{
    protected $table ='aledo_contact';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new AledoContact());
        $this->initialize();
    }
}
