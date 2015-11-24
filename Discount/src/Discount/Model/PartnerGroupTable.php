<?php
namespace Discount\Model;

use Application\Model\SampleTable;
use Application\Service\ApplicationService;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class PartnerGroupTable extends SampleTable
{
    protected $table = 'partner_groups';

    public function __construct(Adapter $adapter)
    {
        if (ApplicationService::isDomainZone('by')) {
            $this->table = 'by_partner_groups';
        }
		
		$this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new PartnerGroup());
        $this->initialize();
    }

}