<?php
namespace Vacancies\Model;

use Application\Model\SampleTable;
use Application\Service\ApplicationService;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class VacancyRequestTable extends SampleTable
{
    protected $table = 'vacancy_request';

    public function __construct(Adapter $adapter)
    {
        if (ApplicationService::isDomainZone('by')) {
            $this->table = 'by_vacancy_request';
        }
		
		$this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new VacancyRequest());
        $this->initialize();
    }
}
