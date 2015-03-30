<?php
namespace Catalog\Model;

use Application\Model\SampleTable;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class SeriesImgTable extends SampleTable
{
    protected $table ='series_img';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new SeriesImg());
        $this->initialize();
    }
}
