<?php
namespace Contacts\Controller;

use Application\Service\ApplicationService;
use Zend\Mvc\Controller\AbstractActionController;

class ContactsController extends AbstractActionController
{
    public function sitemapAction()
    {
        $this->layout()->pageTitle = 'Карта сайта';

        $sl = $this->getServiceLocator();
        $sections = ApplicationService::makeIdArrayFromObjectArray($sl->get('Catalog/Model/SectionTable')->fetchByCond('deleted', 0, 'order asc'));
        $subsections = $sl->get('Catalog/Model/SubsectionTable')->fetchAll();
        $seriesTable = $sl->get('Catalog/Model/SeriesTable');
        $projectsTable = $sl->get('ProjectsTable');
        $strangeIds = array(31, 33);

        $sectionsIds = array();
        foreach ($sections as $onesec) {
            $sectionsIds[] = $onesec->id;
        }

        foreach($subsections as $subsec){
            if (in_array($subsec->section_id, $sectionsIds)) {
                if(in_array($subsec->section_id, $strangeIds)){
                    $series = $seriesTable->fetchByConds(array('subsection_id' => $subsec->id, 'deleted' => 0 ));
                    $subsec->series = $series;
                }
                $sections[$subsec->section_id]->subsecs[] = $subsec;
            }

        }

        $projects = $projectsTable->fetchAll();

        return array(
            'sections' => array_values($sections),
            'projects' => $projects,
        );
    }

    public function indexAction()
    {
        $this->layout()->pageTitle = 'Контакты';

        $sl = $this->getServiceLocator();
//        $contacts = self::getContacts($sl);
        $contacts = $sl->get('ContactsTable')->fetchAll();
        if (!isset($contacts[1])) {
            $contacts[1] = $contacts[0];
        }

        return array(
            'contacts' => $contacts,

            'pageTitle' => 'Контакты',
            'breadCrumbs'  => array(
                array('link'=> $this->url()->fromRoute('home'), 'text'=>ucfirst('Главная')),
            ),
        );
    }

    public static function getContacts(\Zend\ServiceManager\ServiceManager $sm)
    {
        return $sm->get('ContactsTable')->fetchAll();
    }
}