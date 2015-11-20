<?php
namespace Info\Model;

use Application\Model\SampleTable;
use Application\Service\ApplicationService;
use Composer\Command\AboutCommand;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class DeliveryTable extends SampleTable
{
    protected $table = 'info_delivery';

    public function __construct(Adapter $adapter)
    {
        if (ApplicationService::isDomainZone('by')) {
            $this->table = 'by_info_delivery';
        }
		
		$this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new Delivery());
        $this->initialize();
    }
}
