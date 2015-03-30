<?php
namespace Projects\Model;

use Application\Model\SampleTable;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class ProjectRubricTable extends SampleTable
{
    protected $table ='project_rubric';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new ProjectRubric());
        $this->initialize();
    }
}
