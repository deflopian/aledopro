<?php
namespace Info\Controller;

use Application\Controller\SampleAdminController;
use Catalog\Mapper\LinkToLinkMapper;
use Info\Model\About;
use Info\Model\SeoData;
use Info\Service\SeoService;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Info\Model\InfoTable;

class AdminController extends SampleAdminController
{
    const ABOUT = 1;
    const DELIVERY = 2;
    const PARTNER = 3;
    const SHOWROOM = 4;

    private $infoTable;
    private $seoTable;


    public function aboutAction() {

        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::INFO, 1);

        $entity = $this->getServiceLocator()->get('AboutTable')->find(1);

        $this->imgFields = array('block_2_img_1', 'block_2_img_2', 'block_2_img_3', 'img', 'img_min');
        if ($this->imgFields) {
            $fileTable = $this->getServiceLocator()->get('FilesTable');
            foreach ($this->imgFields as $imgField) {
                if ($entity->$imgField) {
                    $file = $fileTable->find($entity->$imgField);
                    if ($file) {
                        $imgFieldAndName = $imgField . "_name";
                        $entity->$imgFieldAndName = $file->name;
                    }
                }
            }
        }

        $view = new ViewModel();
        $view
            ->setVariables(array(
                'entity' => $entity,
                'id'     => 0,
                'seoData' => $seoData,
                'links' =>  LinkToLinkMapper::getInstance($this->getServiceLocator())->fetchAll(0, \Catalog\Controller\AdminController::INFO_TABLE)
            ));

        return $view;
    }

    public function indexAction() {
        return array();
    }

    public function plusesAction() {
        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::INFO, 1);

        $entity = $this->getServiceLocator()->get('PlusesTable')->find(1);

        $this->imgFields = array('img');
        if ($this->imgFields) {
            $fileTable = $this->getServiceLocator()->get('FilesTable');
            foreach ($this->imgFields as $imgField) {
                if ($entity->$imgField) {
                    $file = $fileTable->find($entity->$imgField);
                    if ($file) {
                        $imgFieldAndName = $imgField . "_name";
                        $entity->$imgFieldAndName = $file->name;
                    }
                }
            }
        }

        $view = new ViewModel();
        $view
            ->setVariables(array(
                'entity' => $entity,
                'id'     => 0,
                'seoData' => $seoData,
                'links' =>  LinkToLinkMapper::getInstance($this->getServiceLocator())->fetchAll(0, \Catalog\Controller\AdminController::INFO_TABLE)
            ));

        return $view;

    }

    public function jobAction() {
        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::INFO, 1);

        $entity = $this->getServiceLocator()->get('JobsTable')->find(1);

        $this->imgFields = array('img', 'img_1000');
        if ($this->imgFields) {
            $fileTable = $this->getServiceLocator()->get('FilesTable');
            foreach ($this->imgFields as $imgField) {
                if ($entity->$imgField) {
                    $file = $fileTable->find($entity->$imgField);
                    if ($file) {
                        $imgFieldAndName = $imgField . "_name";
                        $entity->$imgFieldAndName = $file->name;
                    }
                }
            }
        }

        $view = new ViewModel();
        $view
            ->setVariables(array(
                'entity' => $entity,
                'id'     => 0,
                'seoData' => $seoData,
                'links' =>  LinkToLinkMapper::getInstance($this->getServiceLocator())->fetchAll(0, \Catalog\Controller\AdminController::INFO_TABLE)
            ));

        return $view;

    }

    public function filesAction() {
        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::INFO, 1);

        $entity = $this->getServiceLocator()->get('InfoFilesTable')->find(1);

        $this->imgFields = array('img_1', 'img_1_1000', 'img_2', 'img_2_1000');
        if ($this->imgFields) {
            $fileTable = $this->getServiceLocator()->get('FilesTable');
            foreach ($this->imgFields as $imgField) {
                if ($entity->$imgField) {
                    $file = $fileTable->find($entity->$imgField);
                    if ($file) {
                        $imgFieldAndName = $imgField . "_name";
                        $entity->$imgFieldAndName = $file->name;
                    }
                }
            }
        }

        $view = new ViewModel();
        $view
            ->setVariables(array(
                'entity' => $entity,
                'id'     => 0,
                'seoData' => $seoData,
                'links' =>  LinkToLinkMapper::getInstance($this->getServiceLocator())->fetchAll(0, \Catalog\Controller\AdminController::INFO_TABLE)
            ));

        return $view;

    }

    public function guaranteeAction() {
        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::INFO, 1);

        $entity = $this->getServiceLocator()->get('GuaranteeTable')->find(1);

        $view = new ViewModel();
        $view
            ->setVariables(array(
                'entity' => $entity,
                'id'     => 0,
                'seoData' => $seoData,
                'links' =>  LinkToLinkMapper::getInstance($this->getServiceLocator())->fetchAll(0, \Catalog\Controller\AdminController::INFO_TABLE)
            ));

        return $view;

    }

    public function deliveryAction() {
        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::INFO, 1);

        $entity = $this->getServiceLocator()->get('DeliveryTable')->find(1);

        $view = new ViewModel();
        $view
            ->setVariables(array(
                'entity' => $entity,
                'id'     => 0,
                'seoData' => $seoData,
                'links' =>  LinkToLinkMapper::getInstance($this->getServiceLocator())->fetchAll(0, \Catalog\Controller\AdminController::INFO_TABLE)
            ));

        return $view;
    }

    public function partnerAction() {
        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::INFO, 1);

        $entity = $this->getServiceLocator()->get('PartnersTable')->find(1);

        $this->imgFields = array('img1', 'img1_min', 'img2', 'img2_min');
        if ($this->imgFields) {
            $fileTable = $this->getServiceLocator()->get('FilesTable');
            foreach ($this->imgFields as $imgField) {
                if ($entity->$imgField) {
                    $file = $fileTable->find($entity->$imgField);
                    if ($file) {
                        $imgFieldAndName = $imgField . "_name";
                        $entity->$imgFieldAndName = $file->name;
                    }
                }
            }
        }

        $view = new ViewModel();
        $view
            ->setVariables(array(
                'entity' => $entity,
                'id'     => 0,
                'seoData' => $seoData,
                'links' =>  LinkToLinkMapper::getInstance($this->getServiceLocator())->fetchAll(0, \Catalog\Controller\AdminController::INFO_TABLE)
            ));

        return $view;

    }
    public function showroomAction() { return $this->renderPage(self::SHOWROOM); }

    public function getWysiwygBarAction()
    {
        if ($this->getRequest()->isXmlHttpRequest()) {
            $success = 0;

            $htmlViewPart = new ViewModel();
            $htmlViewPart->setTerminal(true)
                ->setTemplate('info/admin/wysiwyg')
                ->setVariables(array());

            $content = $this->getServiceLocator()->get('viewrenderer')->render($htmlViewPart);
            if($content){
                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(
                    array(
                        'success' => $success,
                        'content' => $content,
                    ))
            );
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin');
    }

    public function uploadWysiwygImgAction()
    {
        $files = $this->getRequest()->getFiles();
        $ckNum = $this->params()->fromQuery('CKEditorFuncNum', false);
        $url = '';

        if($ckNum && $files['upload']){
            $filename = $files['upload']['name'];
            $adapter = new \Zend\File\Transfer\Adapter\Http();
            $adapter->setDestination($_SERVER['DOCUMENT_ROOT']. '/images/uploaded');
            $adapter->addFilter('File\Rename',
                array(
                    'target' => $adapter->getDestination($filename).'/img.jpg',
                    'randomize' => true,
                ));

            if($adapter->receive($filename)){
                $uri = $this->getRequest()->getUri();
                $base = sprintf('%s://%s', $uri->getScheme(), $uri->getHost());

                //todome: ИСПРАВИТЬ, КОГДА БУДЕТ ЗАПУЩЕН ПРОД!!!!!!!!!
                $base = 'http://stage.aledo-pro.ru';
                //////////////////

                $url = $base. '/images/uploaded/' . $adapter->getFileName(null, false);
                $message = 'Файл успешно загружен!';
            } else {
                $message = 'Что-то пошло не так. Попытайтесь загрузить файл ещё раз.';
            }
        } else {
            $message = 'Вы не выбрали файл';
        }

        $return = '<script type="text/javascript">window.parent.CKEDITOR.tools.callFunction("'.$ckNum.'", "'.$url.'", "'.$message.'" );</script>';
        $response = $this->getResponse();
        $response->setContent( $return );
        return $response;
    }

    public function updateEditableAction()
    {
        $this->setData();

        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $post = $request->getPost()->toArray();
            $success = 0;

            if ($post['pk']) {
//                $pkData = explode('-',$post['pk']);
//                $type = false;
//

//
                $data[$post['name']] = $post['value'];
//
//                $table = $this->getInfoTable();
                $entity = null;

                switch ($post['pk']) {
                    case SeoService::ABOUT :
                    {
                        $data['id'] = 1;
                        $table = $this->getServiceLocator()->get('AboutTable');
                        $entity = $table->find(1);
                        $entity->exchangeArray($data);

                        $table->save($entity);
                        $success = 1;
                        break;
                    }
                    case SeoService::PARTNERS :
                    {
                        $data['id'] = 1;
                        $table = $this->getServiceLocator()->get('PartnersTable');
                        $entity = $table->find(1);
                        $entity->exchangeArray($data);

                        $table->save($entity);
                        $success = 1;
                        break;
                    }
                    case SeoService::PLUSES :
                    {
                        $data['id'] = 1;
                        $table = $this->getServiceLocator()->get('PlusesTable');
                        $entity = $table->find(1);
                        $entity->exchangeArray($data);

                        $table->save($entity);
                        $success = 1;
                        break;
                    }
                    case SeoService::GUARANTEE :
                    {
                        $data['id'] = 1;
                        $table = $this->getServiceLocator()->get('GuaranteeTable');
                        $entity = $table->find(1);
                        $entity->exchangeArray($data);

                        $table->save($entity);
                        $success = 1;
                        break;
                    }
                    case SeoService::DELIVERY :
                    {
                        $data['id'] = 1;
                        $table = $this->getServiceLocator()->get('DeliveryTable');
                        $entity = $table->find(1);
                        $entity->exchangeArray($data);

                        $table->save($entity);
                        $success = 1;
                        break;
                    }
                    default :
                    {
                        $success = 0;
                        break;
                    }
                }
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/info');
    }

    public function saveAction()
    {
        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $text = $request->getPost('text', false);
            $wid = $request->getPost('wid', false);
            $success = 0;


            if($id && $text !== false && $wid){
                switch ($id) {
                    case SeoService::ABOUT :
                    {
                        $table = $this->getServiceLocator()->get('AboutTable');
                        $entity = $table->find(1);
                        $data['id'] = 1;
                        $data[$wid] = $text;
                        $entity->exchangeArray($data);
                        $table->save($entity);
                        $success = 1;
                        break;
                    }
                    case SeoService::PARTNERS :
                    {
                        $table = $this->getServiceLocator()->get('PartnersTable');
                        $entity = $table->find(1);
                        $data['id'] = 1;
                        $data[$wid] = $text;

                        $entity->exchangeArray($data);
                        $table->save($entity);
                        $success = 1;
                        break;
                    }
                    case SeoService::PLUSES :
                    {
                        $table = $this->getServiceLocator()->get('PlusesTable');
                        $entity = $table->find(1);
                        $data['id'] = 1;
                        $data[$wid] = $text;
                        $entity->exchangeArray($data);
                        $table->save($entity);
                        $success = 1;
                        break;
                    }
                    case SeoService::GUARANTEE :
                    {
                        $table = $this->getServiceLocator()->get('GuaranteeTable');
                        $entity = $table->find(1);
                        $data['id'] = 1;
                        $data[$wid] = $text;
                        $entity->exchangeArray($data);
                        $table->save($entity);
                        $success = 1;
                        break;
                    }
                    case SeoService::DELIVERY :
                    {
                        $table = $this->getServiceLocator()->get('DeliveryTable');
                        $entity = $table->find(1);
                        $data['id'] = 1;
                        $data[$wid] = $text;
                        $entity->exchangeArray($data);
                        $table->save($entity);
                        $success = 1;
                        break;
                    }
                    default :
                    {
                        $success = 0;
                        break;
                    }
                }
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode( array( 'success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/info');
    }

    private function renderPage($id)
    {
        $entity = $this->getInfoTable()->find($id);

        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::INFO, $id);

        $view = new ViewModel();
        $view->setTemplate('info/admin/info')
            ->setVariables(array(
                'entity' => $entity,
                'id'     => $id,
                'seoData' => $seoData,
            ));

        return $view;
    }

    public function saveSeoAction()
    {
        $request = $this->getRequest();
        if ($request->isXmlHttpRequest()) {
            $success = 0;

            $post = $request->getPost();
            if($post){
                $seoData = new SeoData();
                $seoData->exchangeArray($post);
                $this->getSeoTable()->save($seoData);
                $success = 1;
            }


            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(
                    array( 'success' => $success, ))
            );
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin');
    }

    /**
     * @return InfoTable object
     */
    private function getInfoTable()
    {
        if (!$this->infoTable) {
            $sm = $this->getServiceLocator();
            $this->infoTable = $sm->get('InfoTable');
        }
        return $this->infoTable;
    }

    /**
     * @return InfoTable object
     */
    private function getSeoTable()
    {
        if (!$this->seoTable) {
            $sm = $this->getServiceLocator();
            $this->seoTable = $sm->get('SeoDataTable');
        }
        return $this->seoTable;
    }
}