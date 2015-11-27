<?php
namespace Contacts\Controller;

use Application\Service\ApplicationService;
use Catalog\Service\CatalogService;
use Zend\Mvc\Controller\AbstractActionController;

class ContactsController extends AbstractActionController
{
    public function sitemapAction()
    {
        $this->layout()->pageTitle = 'Карта сайта';

        $sl = $this->getServiceLocator();
        $_sections = $sl->get('Catalog/Model/SectionTable')->fetchByCond('deleted', 0, 'order asc');
		
		$sections = array();
		foreach ($_sections as $sec) {
			if (CatalogService::isByCatalogHidden($this->getServiceLocator(), $sec->id, 1))	continue;
			$sections[] = $sec;
		}
		$sections = ApplicationService::makeIdArrayFromObjectArray($sections);
		
        $subsections = $sl->get('Catalog/Model/SubsectionTable')->fetchAll();
        $seriesTable = $sl->get('Catalog/Model/SeriesTable');
        $projectsTable = $sl->get('ProjectsTable');
        $strangeIds = array(31, 33);

        $sectionsIds = array();
        foreach ($sections as &$onesec) {
            $sectionsIds[] = $onesec->id;
        }

        foreach($subsections as $subsec){
            if (in_array($subsec->section_id, $sectionsIds)) {
                if(in_array($subsec->section_id, $strangeIds)){
                    $_series = $seriesTable->fetchByConds(array('subsection_id' => $subsec->id, 'deleted' => 0 ));
					
					$series = array();
					foreach ($_series as $ser) {
						if (CatalogService::isByCatalogHidden($this->getServiceLocator(), $ser->id, 3))	continue;
						$series[] = $ser;
					}
					
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