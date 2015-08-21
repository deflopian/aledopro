<?php
namespace IPGeoBase\Model;

use Application\Model\SampleTable;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class GeoBannerTable extends SampleTable
{
    protected $table ='geo_banners';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new GeoBanner());
        $this->initialize();
    }
}
