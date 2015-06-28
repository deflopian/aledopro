<?php
namespace Application\Controller;

use Application\Model\PageInfo;
use Info\Service\SeoService;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Service\ApplicationService;

class SampleAdminController extends AbstractActionController
{
    protected $table;
    protected $tableImg;
    protected $imgFields = array();
    protected $entityName;
    protected $entityImgName;
    protected $url;

    protected function setData()
    {
        $routeInfo = $this->getEvent()->getRouteMatch();
        $controller = $routeInfo->getParam('controller');
        $entityName = substr($controller, 0, -5);

        if($entityName == 'Catalog'){
            $this->tableImg = 'Catalog\Model\\'.$this->tableImg;
            return;
        }
        if (!$this->table) {
            $this->table = $entityName.'Table';
        }

        $this->tableImg = $entityName.'ImgTable';

        $this->url = strtolower($entityName);
    }

    public function indexAction()
    {
        $this->setData();
        $entities = $this->getServiceLocator()->get($this->table)->fetchAll('order asc');

        return array(
            'entities' => $entities
        );
    }

    public function addEntityAction()
    {
        $this->setData();
        $type = false;
        $request = $this->getRequest();
        if ($request->isXmlHttpRequest()) {
            $title = $request->getPost('title', false);
            $type = $request->getPost('page_info_type', false);
            $success = 0;

            if ($title) {
                $data = array('title' => $title,);

                $entity = new $this->entityName;
                $entity->exchangeArray($data);

                $newId = $this->getServiceLocator()->get($this->table)->save($entity);

                if($newId){
                    if ($type !== false) {
                        $this->updateLastModified($type);
                    }
                    $success = 1;
                }
            }

            $returnArr = array('success' => $success);
            if($success){
                $returnArr['newId'] = $newId;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode($returnArr));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/'.$this->url);
    }

    public function viewAction()
    {
        $this->setData();

        $id = (int) $this->params()->fromRoute('id', 0);
        if (!$id) {
            return $this->redirect()->toRoute('zfcadmin/'.$this->url);
        }

        $entitу = $this->getServiceLocator()->get($this->table)->find($id);


        if ($entitу === false) {
            return $this->redirect()->toRoute('zfcadmin/'.$this->url);
        }


        if ($this->imgFields) {
            $fileTable = $this->getServiceLocator()->get('FilesTable');
            foreach ($this->imgFields as $imgField) {
                if ($entitу->$imgField) {
                    $file = $fileTable->find($entitу->$imgField);
                    if ($file) {
                        $imgFieldAndName = $imgField . "_name";
                        $entitу->$imgFieldAndName = $file->name;
                    }
                }
            }
        }


        return array(
            'entity' => $entitу,
        );
    }

    public function updateEditableAction()
    {
        $this->setData();
        $type = false;
        $request = $this->getRequest();
        if ($request->isXmlHttpRequest()) {
            $post = $request->getPost()->toArray();
            $success = 0;

            if ($post['page_info_type']) {
                $type = $post['page_info_type'];
            }
            if ($post['pk']) {
                $data['id'] = $post['pk'];
                $data[$post['name']] = $post['value'];

                $entity = new $this->entityName;
                $entity->exchangeArray($data);

                $this->getServiceLocator()->get($this->table)->save($entity);

                if ($type !== false) {
                    $this->updateLastModified($type);
                }
                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/'.$this->url);
    }

    public function delEntityAction()
    {
        $this->setData();

        $request = $this->getRequest();
        if ($request->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $type = $request->getPost('page_info_type', false);
            $success = 0;

            if ($id) {
                $table = $this->table;

                $this->getServiceLocator()->get($table)->del($id);
                if ($type !== false) {
                    $this->updateLastModified($type);
                }
                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/'.$this->url);
    }

    public function changeOrderAction()
    {
        $this->setData();

        $request = $this->getRequest();
        if ($request->isXmlHttpRequest()) {
            $order = $request->getPost('order', false);
            $isImg = $request->getPost('isImg', false);
            $success = 0;

            if ($order) {
                $tableName = $isImg ? $this->tableImg : $this->table;
                $table = $this->getServiceLocator()->get($tableName);
                foreach($order as $id=>$serialNum){
                    $entity = $table->find($id);
                    if($entity){
                        $entity->order = $serialNum;
                        $table->save($entity);
                    }
                }

                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/'.$this->url);
    }

    public function saveImgAction()
    {
        $this->setData();

        $request = $this->getRequest();
        if ($request->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $type = $request->getPost('page_info_type', false);
            $return['success'] = 0;

            if($id){
                $data = $this->getRequest()->getFiles()->toArray();

                $filename = $data['image']['name'];

                $adapter = new \Zend\File\Transfer\Adapter\Http();

                $adapter->setDestination($this->getFoler('/images/'.$this->url));

                $adapter->addFilter('File\Rename',
                    array(
                        'target' => $adapter->getDestination($filename).'/img.jpg',
                        'randomize' => true,
                    ));

                if($adapter->receive($filename)){
                    $table = $this->getServiceLocator()->get($this->table);
                    $image = $table->find($id);

                    if($image->img){
                        $this->unlinkFile('/images/'. $this->url .'/'.$image->img);
                    }

                    $image->img = $adapter->getFileName(null, false);
                    $table->save($image);

                    if ($type !== false) {
                        $this->updateLastModified($type);
                    }

                    $return['success'] = 1;
                    $return['imgs'] = array(
                        array(
                            'url' => $image->img,
                        )
                    );
                }
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode( $return ));
            return $response;
        }

        return $this->redirect()->toRoute('zfcadmin');
    }

    public function saveGalleryAction()
    {
        $this->setData();

        $request = $this->getRequest();
        if ($request->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $type = $request->getPost('type', false);
            $pi_type = $request->getPost('page_info_type', false);
            $minHeight = $request->getPost('min_height', false);
            $return['success'] = 0;
            $lastPicId = 0;
            if($id){
                $data = $this->getRequest()->getFiles()->toArray();

                $savedData = array();
                foreach($data['images'] as $i=>$image){

                    //todome: сделать проверку файлов. валидацию.

                    //todome: сделать работу с видео!!!!
                    $filename = $image['name'];

                    $adapter = new \Zend\File\Transfer\Adapter\Http();
                    $adapter->setDestination($this->getFoler('/images/'.$this->url));
                    $adapter->addFilter('File\Rename',
                        array(
                            'target' => $adapter->getDestination($filename).'/img.jpg',
                            'randomize' => true,
                        ));

                    if($adapter->receive($filename)){
                        $imgData['parent_id'] = $id;
                        $imgData['type'] = ApplicationService::MEDIA_TYPE_IMG;

                        //todome: проверку типа - изображение ли это?! или видео!?

                        $imgData['url'] = $adapter->getFileName('images_'.$i.'_', false);

                        if ($minHeight) {
                            list($width, $height) = getimagesize($this->getFoler('/images/'.$this->url) . '/' . $imgData['url']);

                            $percent = $minHeight/$height;
                            if ($percent < 1) {
                                $newheight = $minHeight;
                                $newwidth = round($width * $percent);

                                $thumb = imagecreatetruecolor($newwidth, $newheight);
                                imagealphablending($thumb, false);
                                $source = imagecreatefromjpeg($this->getFoler('/images/'.$this->url) . '/' . $imgData['url']);
                                imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

                                $res = imagejpeg($thumb, $this->getFoler('/images/'.$this->url) . '/min_' . $imgData['url']);
//                                imagedestroy($thumb);
                            }
// Масштабирование

                        }

                        if ($lastPicId != 0) {
                            $imgData['id'] = ++$lastPicId;
                        }

                        $image = new $this->entityImgName;
                        $image->exchangeArray($imgData);

                        $newId = $this->getServiceLocator()->get($this->tableImg)->save($image);
                        $lastPicId = $newId;

                        if ($type == 'series') {
                            $ser = $this->getServiceLocator()->get('Catalog\Model\SeriesTable')->find($id);
                            if (!$ser->preview && !$ser->img) {
                                $ser->img = $image->url;
                                $this->getServiceLocator()->get('Catalog\Model\SeriesTable')->save($ser);
                            }
                        }

                        $savedData[] = array(
                            'id'  => $newId,
                            'url' => $image->url
                        );
                    }
                }
                if ($pi_type !== false) {
                    $this->updateLastModified($pi_type);
                }
                $return['success'] = 1;
                $return['imgs'] = $savedData;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode( $return ));
            return $response;
        }

        return $this->redirect()->toRoute('zfcadmin');
    }

    public function delImgAction()
    {
        $this->setData();

        $request = $this->getRequest();
        if ($request->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $pi_type = $request->getPost('page_info_type', false);

            $return['success'] = 0;

            if($id){
                $table = $this->getServiceLocator()->get($this->tableImg);
                $img = $table->find($id);

                if($img){
                    $this->unlinkFile('/images/'. $this->url .'/'. $img->url);

                    $table->del($img->id);
                    if ($pi_type !== false) {
                        $this->updateLastModified($pi_type);
                    }
                    $return['success'] = 1;
                }
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode( $return ));
            return $response;
        }

        return $this->redirect()->toRoute('zfcadmin');
    }

    public function updateLastModified($type) {
        $pageInfoTable = $this->getServiceLocator()->get('PageInfoTable');
        $pageInfo = $pageInfoTable->fetchByConds(array('type' => $type));

        if (is_array($pageInfo) && count($pageInfo) > 0) {
            $pageInfo = $pageInfo[0];
        } else {
            $pageInfo = new PageInfo();
            $pageInfo->type = $type;
        }

        $pageInfo->last_modified = time();
        $pageInfoTable->save($pageInfo);
        return true;
    }

    protected function unlinkFile($url)
    {
        unlink($_SERVER['DOCUMENT_ROOT'] . $url);
    }

    protected function getFoler($url)
    {

        return $_SERVER['DOCUMENT_ROOT'] . $url;
    }
}