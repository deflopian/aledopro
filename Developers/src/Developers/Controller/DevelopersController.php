<?php
namespace Developers\Controller;

use Catalog\Controller\AdminController;
use Catalog\Mapper\LinkToLinkMapper;
use Catalog\Service\CatalogService;
use Developers\Model\Developer;
use Documents\Model\DocumentTable;
use Info\Service\SeoService;
use Zend\Mvc\Controller\AbstractActionController;
use Developers\Model\DeveloperTable;
use Developers\Model\DeveloperImgTable;
use Developers\Model\DeveloperMemberTable;
use Developers\Model\ProdToProjTable;
use Zend\View\Model\ViewModel;

class DevelopersController extends AbstractActionController
{
    private $developerTable;
    private $imgTable;
    protected $pageInfoType = SeoService::DEVELOPERS;

    public function viewAction() {
        $sl = $this->getServiceLocator();

        $id = $this->params()->fromRoute('id', 0);

        /** @var Developer $developer */
        if (is_numeric($id)) {
            $developer = $this->getDeveloperTable()->find($id);
        } else {
            $developer = $this->getDeveloperTable()->fetchByCond('alias', $id);
            $developer = reset($developer);

        }

        if (!$developer) return $this->redirect()->toRoute('brands');

        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( \Info\Service\SeoService::DEVELOPERS, $developer->id );
        $fileTable = $this->getServiceLocator()->get('FilesTable');

        if ($developer->preview) {
            $file = $fileTable->find($developer->preview);
            if ($file) {
                $developer->previewName = $file->name;
            }
        }
        if ($developer->img) {
            $file = $fileTable->find($developer->img);
            if ($file) {
                $developer->imgName = $file->name;
            }
        }

        $documentsTable = $this->getServiceLocator()->get('DocumentsTable');
        $catalogs = $documentsTable->fetchByCond('type', DocumentTable::TYPE_DEVELOPERS_CATALOG . $developer->id);

        foreach ($catalogs as &$catalog) {
            if ($catalog->img) {
                $file = $fileTable->find($catalog->img);
                if ($file) {
                    $catalog->img_name = $file->name;
                }
            }
            if ($catalog->file) {
                $file = $fileTable->find($catalog->file);
                if ($file) {
                    $catalog->file_name = $file->name;
                }
            }
        }

        $this->layout()->pageTitle = $developer->title;
        $this->layout()->breadCrumbs  = array(
            array('link'=> $this->url()->fromRoute('home'), 'text'=>ucfirst('Главная')),
            array('link'=> $this->url()->fromRoute('developers'), 'text'=>ucfirst('Производители'))
        );
        $htmlViewPart = new ViewModel();
        $htmlViewPart->setVariables(array(
                'developer'   => $developer,
                'pageTitle' => $developer->title,
                'catalogs' => $catalogs,
                'breadCrumbs'  => array(
                    array('link'=> $this->url()->fromRoute('home'), 'text'=>ucfirst('Главная')),
                    array('link'=> $this->url()->fromRoute('developers'), 'text'=>ucfirst('Производители')),
                ),
                'seoData' => $seoData,
                'sl'        => $sl,
            ));
        return $htmlViewPart;
    }



    public function indexAction()
    {
        /** @var Developer[] $developers */
        $developers = $this->getServiceLocator()->get('DevelopersTable')->fetchByCond('deleted', 0, 'order asc');


        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( \Info\Service\SeoService::DEVELOPERS, 1 );

        $arrayIds = array();
        $fileTable = $this->getServiceLocator()->get('FilesTable');
        $sortedDevelopers = array();
        foreach ($developers as &$one) {
            if ($one->preview) {
                $file = $fileTable->find($one->preview);
                if ($file) {
                    $one->previewName = $file->name;
                }
            }
            if ($one->img) {
                $file = $fileTable->find($one->img);
                if ($file) {
                    $one->imgName = $file->name;
                }
            }
            $arrayIds[] = $one->id;
            $sortedDevelopers[$one->rubric_id][] = $one;
        }


        $this->layout()->pageTitle = 'Производители';
        $this->layout()->seoData = $seoData;

        return array(
            'seoData' => $seoData,
            'pageTitle' => 'Производители',
            'breadCrumbs'  => array(
                array('link'=> $this->url()->fromRoute('home'), 'text'=>ucfirst('Главная')),
            ),
            'developers' => $sortedDevelopers,
            'parentUrl'     => '/brands/',
        );
    }

    /**
     * @return DeveloperTable array|object
     */
    public function getDeveloperTable()
    {
        if (!$this->developerTable) {
            $sm = $this->getServiceLocator();
            $this->developerTable = $sm->get('DevelopersTable');
        }
        return $this->developerTable;
    }

    /**
     * @return DeveloperImgTable array|object
     */
    public function getImgTable()
    {
        if (!$this->imgTable) {
            $sm = $this->getServiceLocator();
            $this->imgTable = $sm->get('DevelopersImgTable');
        }
        return $this->imgTable;
    }
}