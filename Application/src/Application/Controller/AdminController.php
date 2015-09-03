<?php
namespace Application\Controller;

use Api\Model\File;
use Api\Model\FileTable;
use Application\Model\BannerImg;
use Info\Service\SeoService;
use Zend\Json\Json;

class AdminController extends SampleAdminController
{
    protected $imgFields = array("img");
    protected $table = "MainPageBlocksTable";

    public function indexAction()
    {
        $sl = $this->getServiceLocator();
        $blocks = $sl->get('MainPageBlocksTable')->fetchAll();
        $blocksJson = Json::encode($blocks);

        $footerBlocks = $sl->get('FooterBlocksTable')->fetchAll();
        $footerBlocksJson = Json::encode($footerBlocks);
		
		$seoData = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::INFO, 1 );
		
        return array(
            'entities' => $blocks,
			'seoData' => $seoData,
            'entitiesJson' => $blocksJson,
            'footerBlocksJson' => $footerBlocksJson
        );
    }

    public function blockAction()
    {
        $sl = $this->getServiceLocator();
        $this->table = "MainPageBlocksTable";
        $return = parent::viewAction();
        $entity = $return['entity'];
        $entityJson = \Zend\Json\Json::encode($entity);
        $return['entityJson'] = $entityJson;

        $images = $sl->get('MainPageBlockImagesTable')->fetchByCond('parentId', (int) $this->params()->fromRoute('id', 0));
        $imagesJson = Json::encode($images);
        $return['entitiesJson'] = $imagesJson;
        return $return;
    }
    public function footerBlockAction()
    {
        $this->table = "FooterBlocksTable";
        $return = parent::viewAction();
        $entity = $return['entity'];
        $entityJson = \Zend\Json\Json::encode($entity);
        $return['entityJson'] = $entityJson;
        return $return;
    }
    public function blockimageAction()
    {
        $sl = $this->getServiceLocator();
        $this->table = 'MainPageBlockImagesTable';
        $return = parent::viewAction();
        $entity = $return['entity'];
        /** @var FileTable $fileTable */
        $fileTable = $sl->get('FilesTable');


        if ($return['entity']->img) {
            /** @var File $file */
            $file = $fileTable->find($return['entity']->img);
            if ($file) {
                $return['entity']->imgName = $file->name;
                $return['entity']->imgRealName = $file->real_name;
            }
        }

        $entityJson = \Zend\Json\Json::encode($return['entity']);
        $return['entityJson'] = $entityJson;
        return $return;
    }
}