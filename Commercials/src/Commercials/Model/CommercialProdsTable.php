<?php
/**
 * Created by PhpStorm.
 * User: Вадим
 * Date: 03.02.15
 * Time: 17:13
 */
namespace Commercials\Model;

use Application\Model\SampleTable;
use Application\Service\ApplicationService;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class CommercialProdsTable extends SampleTable
{
    protected $table = 'commercial_prods';

    public function __construct(Adapter $adapter)
    {
        if (ApplicationService::isDomainZone('by')) {
            $this->table = 'by_commercial_prods';
        }
		
		$this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new CommercialProd());
        $this->initialize();
    }
}