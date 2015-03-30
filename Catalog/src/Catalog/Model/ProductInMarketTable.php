<?php
namespace Catalog\Model;

use Application\Model\SampleModel;
use Zend\Db\Sql\Predicate;
use Application\Model\SampleTable;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Predicate\Operator;
use Zend\Db\Sql\Predicate\PredicateSet;

class ProductInMarketTable extends SampleTable
{
    protected $table ='product_market';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new ProductInMarket());
        $this->initialize();
    }
}
