<?php
namespace Catalog\Model;

use Application\Model\SampleTable;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class SectionTable extends SampleTable
{
    protected $table ='catalog_section';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new Section());
        $this->initialize();
    }

    public function del($id)
    {
        parent::del($id);

        //todome: сделать каскадное удаление всего входящего
    }
}
