<?php
namespace Application\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class MainPageBlockImageTable extends SampleTable
{
    protected $table ='mainpage_block_image_table';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new MainPageBlockImage());
        $this->initialize();
    }
}