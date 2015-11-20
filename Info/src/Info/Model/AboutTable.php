<?php
namespace Info\Model;

use Application\Model\SampleTable;
use Application\Service\ApplicationService;
use Composer\Command\AboutCommand;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class AboutTable extends SampleTable
{
    protected $table = 'about';

    public function __construct(Adapter $adapter)
    {
        if (ApplicationService::isDomainZone('by')) {
            $this->table = 'by_about';
        }
		
		$this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new About());
        $this->initialize();
    }
}
