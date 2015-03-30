<?php
namespace Application\Controller;

use Api\Model\File;
use Api\Model\FileTable;
use Application\Model\BannerImg;
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

        return array(
            'entities' => $blocks,
            'entitiesJson' => $blocksJson
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