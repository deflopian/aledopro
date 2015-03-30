<?php
namespace Vacancies\Model;

use Application\Model\SampleTable;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class VacancyRequestTable extends SampleTable
{
    protected $table ='vacancy_request';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new VacancyRequest());
        $this->initialize();
    }
}
