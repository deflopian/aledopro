<?php
namespace Catalog\Model;

use Application\Model\SampleTable;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class ProductParamsTable extends SampleTable
{
    protected $table ='product_params';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new ProductParam());
        $this->initialize();
    }

    public function fetchAll($order = "", $idsOnly = false)
    {
        $objArr = parent::fetchAll($order);
        $res = array();
        foreach($objArr as $enity){
            $res[$enity->field] = $enity;
        }
        return $res;
    }
}
