<?php
namespace Application\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class FooterBlockTable extends SampleTable
{
    protected $table ='footer_block_table';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new FooterBlock());
        $this->initialize();
    }
}