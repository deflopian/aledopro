<?php
namespace Terms\Controller;

use Application\Controller\SampleAdminController;
use Application\Service\ApplicationService;
use Terms\Model\Terms;

class AdminController extends SampleAdminController
{
    protected $entityName = 'Terms\Model\Terms';

    public function indexAction()
    {
        parent::setData();
        $entities = $this->getServiceLocator()->get($this->table)->fetchAll('letter asc');
        $letters = ApplicationService::getLettersArr();
        array_unshift($letters,'не указана');

        return array(
            'entities' => $entities,
            'letters'  => $letters,
        );
    }

    public function viewAction()
    {
        $return = parent::viewAction();

        $letters = ApplicationService::getLettersArr();
        array_unshift($letters,'не указана');

        $return['letters'] = $letters;
        return $return;
    }
}