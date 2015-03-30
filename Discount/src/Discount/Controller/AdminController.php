<?php
namespace Discount\Controller;

use Application\Controller\SampleAdminController;
use Application\Service\MailService;
use Catalog\Controller\CatalogController;
use Catalog\Mapper\CatalogMapper;
use Catalog\Service\CatalogService;
use Discount\Mapper\DiscountMapper;
use Discount\Model\DiscountTable;
use Discount\Service\DiscountService;
use Info\Service\SeoService;
use Discount\Model\Discount;
use Discount\Model\PartnerGroup;
use User\Service\UserService;
use Zend\Http\Response;

class AdminController extends SampleAdminController
{
    protected $entityName = 'Discount\Model\Discount';

    public function indexAction()
    {
        $sl =  $this->getServiceLocator();
        $this->setData();
        $allUsers =  $sl->get('UserTable')->fetchAll();
        $partners = array();
        $nonPartners = array();
        foreach ($allUsers as $user) {
            if ($user->is_partner == 1) {
                $partners[] = $user;
            } else {
                $nonPartners[] = $user;
            }
        }

        $data = DiscountService::getSeriesAndTags($nonPartners);

        $allGroups =  $sl->get('PartnerGroupTable')->fetchAll();
        $groupsNamesById = array(0 => '-');
        foreach( $allGroups as $group) {
            $groupsNamesById[$group->id] = $group->name;
        }

        $managers = array(0 => '-');
        $managersIds = array();
        foreach ($allUsers as $entity) {
            $managersIds[] = $entity->manager_id;
        }
        if (count($managersIds)) {
            $allManagers = $this->getServiceLocator()->get('UserTable')->fetchByCond('user_id', $managersIds);
            foreach ($allManagers as $currentManager) {
                $managers[$currentManager->user_id] = $currentManager->username;
            }
        }

        foreach ($partners as &$entity1) {
            $entity1->user_id = (int)$entity1->user_id;
            if (isset($groupsNamesById[$entity1->partner_group])) {
                $entity1->group_name = $groupsNamesById[$entity1->partner_group];
            }
            if (isset($managers[$entity1->manager_id])) {
                $entity1->manager_name = $managers[$entity1->manager_id];
            }
        }

        $entitiesJson = \Zend\Json\Json::encode($partners);

        return array(
            'entities' => $partners,
            'usersJson' => $entitiesJson,
            'managers' => $managers,
            'partnerGroups' => $allGroups,
            'groupsNamesById' => $groupsNamesById,
            'tags' => \Zend\Json\Json::encode($data['tags']),
        );
    }


    public function addGroupAction()
    {
        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $name = $request->getPost('name', false);

            $success = 0;

            if ($name) {
                $data = array(
                    'name' => $name,
                );

                $entity = new PartnerGroup();
                $entity->exchangeArray($data);

                $newId = $this->getServiceLocator()->get('PartnerGroupTable')->save($entity);
                $success = 1;
            }

            $returnArr = array('success' => $success);
            if($success){
                $returnArr['newId'] = $newId;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode($returnArr));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/discounts');
    }

    public function delGroupAction()
    {
        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $success = 0;

            if ($id) {
                $this->getServiceLocator()->get('PartnerGroupTable')->del($id);
                $success = 1;
            }

            $returnArr = array('success' => $success);
            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode($returnArr));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/discounts');
    }

    public function viewGroupAction()
    {
        $id = (int) $this->params()->fromRoute('user_id', 0); //не юзер айди, а обычный айдишник
        if (!$id) {
            return $this->redirect()->toRoute('zfcadmin/discounts');
        }

        $sl = $this->getServiceLocator();
        $group = $sl->get('PartnerGroupTable')->find($id);

        if ($group === false) {
            return $this->redirect()->toRoute('zfcadmin/discounts');
        }
        $partners = $sl->get('UserTable')->fetchByCond('partner_group', $group->id);
        $nonPartners = $sl->get('UserTable')->fetchByConds(array(), array('partner_group' => $group->id));


        $data = DiscountService::getSeriesAndTags($nonPartners);
        $sl = $this->getServiceLocator();

        if (!$group) {
            return $this->redirect()->toRoute('zfcadmin/discounts');
        }

        // user_id - не ошибка, в данном случае group_id идёт как айдишка обычного юзера, только с пометкой is_group
        $sectionsAllTypes = $sl->get('DiscountTable')->fetchByConds(array( 'user_id' => $id , 'is_group' => 1));
        $sections = array();
        $discounts = array();
        $sectionsIds = array();
        foreach ($sectionsAllTypes as $oneSection) {
            if ($oneSection->section_type == \Catalog\Controller\AdminController::SECTION_TABLE) {
                $section = $sl->get('Catalog\Model\SectionTable')->find($oneSection->section_id);
                if ($section) {
                    $sections[$section->id] = $section;
                    $sectionsIds[] = $section->id;
                    $discounts[$section->id] = $oneSection;
                }
            }
        }

        $allSections = $sl->get('Catalog\Model\SectionTable')->fetchAll('order asc');
        $dataSections = CatalogService::getSeriesAndTags($allSections, 0, $sectionsIds);

        $managers = array(0 => '-');
        $managersIds = array();
        foreach ($partners as $entity) {
            $managersIds[] = $entity->manager_id;
        }
        if (count($managersIds)) {
            $allManagers = $this->getServiceLocator()->get('UserTable')->fetchByCond('user_id', $managersIds);
            foreach ($allManagers as $currentManager) {
                $managers[$currentManager->user_id] = $currentManager->username;
            }
        }

        $discountTable = $sl->get('DiscountTable');
        $sortedDiscounts = $discountTable->fetchAll($id, 1, true);



        $cm = CatalogMapper::getInstance($this->getServiceLocator());
        $sections = $cm->fetchAllSections();
        $subsections = $cm->fetchAllSubsections(true);
        $series = $cm->fetchAllSeries(true);
        $products = $cm->fetchAllProducts(true);
        $treeDateByLvl = array();
        $treeHierarchy = array();
        foreach ($sections as $section) {
            $discVal = 0;
            $originalId = 0;
            if (isset($sortedDiscounts[\Catalog\Controller\AdminController::SECTION_TABLE][$section->id])) {
                $discVal = $sortedDiscounts[\Catalog\Controller\AdminController::SECTION_TABLE][$section->id]->discount;
                $originalId = $sortedDiscounts[\Catalog\Controller\AdminController::SECTION_TABLE][$section->id]->id;
            }
            $treeDateByLvl[\Catalog\Controller\AdminController::SECTION_TABLE][$section->id] = array('title' => $section->title, 'discount' => $discVal, 'inherited' => 0, 'dId' => ($originalId > 0 ? $originalId : false));
            $treeHierarchy[$section->id] = array();
        }
        foreach ($subsections as $subsection) {
            if (isset($treeHierarchy[$subsection->section_id])) {
                $treeHierarchy[$subsection->section_id][$subsection->id] = array();
                $discVal = 0;
                $inherited = 0;
                $originalId = 0;
                if (isset($sortedDiscounts[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$subsection->id])) {
                    $discVal = $sortedDiscounts[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$subsection->id]->discount;
                    $originalId = $sortedDiscounts[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$subsection->id]->id;
                } else {
                    $discVal = $treeDateByLvl[\Catalog\Controller\AdminController::SECTION_TABLE][$subsection->section_id]['discount'];
                    $inherited = 1;
                }
                $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$subsection->id] = array('title' => $subsection->title, 'parentId' => $subsection->section_id, 'discount' => $discVal, 'inherited' => $inherited, 'dId' => ($originalId > 0 ? $originalId : false));
                if (!$inherited) {
                    $treeDateByLvl[\Catalog\Controller\AdminController::SECTION_TABLE][$subsection->section_id]['shown'] = true;
//                    $treeDateByLvl[\Catalog\Controller\AdminController::SECTION_TABLE][$subsection->section_id]['dId'] = $sortedDiscounts[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$subsection->id]->discount;

                }
            }
        }
        foreach ($series as $oneser) {

            $subsection = $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$oneser->subsection_id];
            if ($subsection) {
                $sectionId = $subsection['parentId'];
                if (isset($treeHierarchy[$sectionId][$oneser->subsection_id])) {
                    $treeHierarchy[$sectionId][$oneser->subsection_id][$oneser->id] = array();
                    $discVal = 0;
                    $originalId = 0;
                    $inherited = 0;
                    if (isset($sortedDiscounts[\Catalog\Controller\AdminController::SERIES_TABLE][$oneser->id])) {
                        $discVal = $sortedDiscounts[\Catalog\Controller\AdminController::SERIES_TABLE][$oneser->id]->discount;
                        $originalId = $sortedDiscounts[\Catalog\Controller\AdminController::SERIES_TABLE][$oneser->id]->id;
                    } else {
                        $discVal = $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$oneser->subsection_id]['discount'];
                        $inherited = 1;
                    }
                    $treeDateByLvl[\Catalog\Controller\AdminController::SERIES_TABLE][$oneser->id] = array('title' => $oneser->title, 'parentId' => $oneser->subsection_id, 'discount' => $discVal, 'inherited' => $inherited, 'dId' => ($originalId > 0 ? $originalId : false));
                    if (!$inherited) {
                        $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$oneser->subsection_id]['shown'] = true;
                        $treeDateByLvl[\Catalog\Controller\AdminController::SECTION_TABLE][$treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$oneser->subsection_id]['parentId']]['shown'] = true;
                    }
                }
            }


        }
        foreach ($products as $product) {
            $series = $treeDateByLvl[\Catalog\Controller\AdminController::SERIES_TABLE][$product->series_id];
            $subsectionId = $series['parentId'];
            $subsection = $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$subsectionId];
            if ($subsection) {
                $sectionId = $subsection['parentId'];
                if (isset($treeHierarchy[$sectionId][$subsectionId][$product->series_id])) {
                    $treeHierarchy[$sectionId][$subsectionId][$product->series_id][$product->id] = $product->id;
                    $discVal = 0;
                    $inherited = 0;
                    $originalId = 0;
                    if (isset($sortedDiscounts[\Catalog\Controller\AdminController::PRODUCT_TABLE][$product->id])) {
                        $discVal = $sortedDiscounts[\Catalog\Controller\AdminController::PRODUCT_TABLE][$product->id]->discount;
                        $originalId = $sortedDiscounts[\Catalog\Controller\AdminController::PRODUCT_TABLE][$product->id]->id;
                    } else {
                        $discVal = $treeDateByLvl[\Catalog\Controller\AdminController::SERIES_TABLE][$product->series_id]['discount'];
                        $inherited = 1;
                    }

                    $treeDateByLvl[\Catalog\Controller\AdminController::PRODUCT_TABLE][$product->id] = array('title' => $product->title, 'parentId' => $product->series_id, 'discount' => $discVal, 'inherited' => $inherited, 'dId' => ($originalId > 0 ? $originalId : false));
                    if (!$inherited) {
                        $treeDateByLvl[\Catalog\Controller\AdminController::SERIES_TABLE][$product->series_id]['shown'] = true;
                        $prevSer = $treeDateByLvl[\Catalog\Controller\AdminController::SERIES_TABLE][$product->series_id];
                        $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$prevSer['parentId']]['shown'] = true;
                        $prevSS = $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$prevSer['parentId']];
                        $treeDateByLvl[\Catalog\Controller\AdminController::SECTION_TABLE][$prevSS['parentId']]['shown'] = true;
                    }
                }
            }
        }

        $treeDateByLvlJson = \Zend\Json\Json::encode($treeDateByLvl);
        $treeHierarchyJson = \Zend\Json\Json::encode($treeHierarchy);

        $return['treeDateByLvlJson'] = $treeDateByLvlJson;

        $return['treeHierarchyJson'] = $treeHierarchyJson;



        return array(
            'group' => $group,
            'managers' => $managers,
            'partners' => $partners,
            'treeHierarchyJson' => $treeHierarchyJson,
            'treeDateByLvlJson' => $treeDateByLvlJson,
            'sections'  => $sections,
            'discounts' => $discounts,
            'userTags' => \Zend\Json\Json::encode($data['tags']),
            'sectionTags' => \Zend\Json\Json::encode($dataSections['tags']),
        );
    }

    public function sendDiscountsNotifyAction()
    {
        $id = (int) $this->params()->fromRoute('user_id', 0);

        if (!$id) {
            return $this->redirect()->toRoute('zfcadmin/discounts');
        }


        $sl = $this->getServiceLocator();
        $user = $sl->get('UserTable')->find($id);
        /** @var DiscountTable $discountTable */
        $discountTable =  $sl->get('DiscountTable');
        $discounts = $discountTable->fetchByUserId($id, $user->partner_group, true, 0, $sl);

        $flatDiscounts = array();
        foreach ($discounts as $discountLevel) {
            foreach ($discountLevel as $discount) {
                $flatDiscounts[] = $discount;
            }
        }

        list($mail, $dataUser, $dataManager, $from) = MailService::prepareDiscountMailData($sl, $flatDiscounts, $user);
        MailService::sendMail($mail, $dataUser, "Вам назначена скидка на Aledo", $from);

        if ($from != MailService::$currentManagerMail) {
            MailService::sendMail($from, $dataManager, "Пользователю номер " . $user->user_id . " назначена скидка на Aledo");
        }

        MailService::sendMail(MailService::$currentManagerMail, $dataManager, "Пользователю номер " . $user->user_id . " назначена скидка на Aledo");
        return $this->redirect()->toRoute('zfcadmin/discounts');
    }

    public function sendDiscountsNotifyMultiplyAction()
    {
        if (!$this->getRequest()->isPost()) {
            $this->redirect()->toRoute('zfcadmin/discounts');
        }
        $data = \Zend\Json\Json::decode($this->getRequest()->getContent(), \Zend\Json\Json::TYPE_ARRAY);
        $ids = $data['ids'];
        if (!$ids) {
            return false;
        }

        $sl = $this->getServiceLocator();
        foreach ($ids as $id) {
            $user = $sl->get('UserTable')->find($id);
            if (!$user) {
                continue;
            }
            /** @var DiscountTable $discountTable */
            $discountTable =  $sl->get('DiscountTable');
            $discounts = $discountTable->fetchByUserId($id, $user->partner_group, true, 0, $sl);
            if (!$discounts) {
                continue;
            }
            $flatDiscounts = array();
            foreach ($discounts as $discountLevel) {
                foreach ($discountLevel as $discount) {
                    $flatDiscounts[] = $discount;
                }
            }

            list($mail, $dataUser, $dataManager, $from) = MailService::prepareDiscountMailData($sl, $flatDiscounts, $user);
            MailService::sendMail($mail, $dataUser, "Вам назначена скидка на Aledo", $from);

//            list($mail, $dataUser, $dataManager, $from) = MailService::prepareDiscountMailData($sl, $flatDiscounts, $user);
            MailService::sendMail(MailService::$currentManagerMail, $dataUser, "Копия письма со скидками, адрес: " . $mail, $from);
        }
        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(200);
    }



    private function getRoles()
    {
        return $this->getServiceLocator()->get('UserRoleTable')->fetchAll();
    }

    public function partnersAction() {
        $aid = (int) $this->params()->fromRoute('id', 0);
        if (!$aid) {
            $id = (int) $this->params()->fromRoute('user_id', 0);
            if (!$id) {
                return $this->redirect()->toRoute('zfcadmin/discounts');
            }
        } else {
            $id = $aid;
        }


        $sl = $this->getServiceLocator();
        $entity = $sl->get('UserTable')->find($id);

        if (!$entity) {
            return $this->redirect()->toRoute('zfcadmin/discounts');
        }

        //todome: подумать, если партнер в группе, выводить ли ему сюда скидки группы
        //$sectionsAllTypes = $sl->get('DiscountTable')->fetchByConds(array( 'user_id' => $id , 'is_group' => 0));
        /** @var DiscountTable $discountTable */
        $discountTable = $sl->get('DiscountTable');
        if ($entity->partner_group) {
            $sectionsAllTypes = $discountTable->fetchByUserId($id, $entity->partner_group, true, \Catalog\Controller\AdminController::SECTION_TABLE, $this->getServiceLocator());
        } else {
            $sectionsAllTypes = $discountTable->fetchByUserId($id, 0, true, \Catalog\Controller\AdminController::SECTION_TABLE, $this->getServiceLocator());
        }




        $sections = array();
        $discounts = array();
        $sectionsIds = array();
        foreach ($sectionsAllTypes as $oneSection) {
            $section = $sl->get('Catalog\Model\SectionTable')->find($oneSection->section_id);
            if ($section) {
                $sections[$section->id] = $section;
                if (!$oneSection->is_group) {
                    $sectionsIds[] = $section->id;
                }
                $discounts[$section->id] = $oneSection;
            }
        }

        $allSections = $sl->get('Catalog\Model\SectionTable')->fetchAll('order asc');
        $data = CatalogService::getSeriesAndTags($allSections, 0, $sectionsIds);


        $identity = $this->zfcUserAuthentication()->getIdentity();
        $roles = $this->getRoles();
        $roleLinkers = $this->getServiceLocator()->get('RoleLinkerTable')->find($id, 'user_id');
        $sortedRoles = array();
        foreach($roles as $idd=>$role){
            $sortedRoles[] = array('value'=> $role->role_id, 'text'=>$role->role_id);
        }

        $managersRoles = $this->getServiceLocator()->get('RoleLinkerTable')->fetchByCond('role_id', 'manager');

        $currentManager = "";
        if ($entity->manager_id) {
            $currentManagerEntity = $this->getServiceLocator()->get('UserTable')->find($entity->manager_id);
            $currentManager = $currentManagerEntity->username;
        }
        $managers = array(0 => 'Нет менеджера');

        foreach ($managersRoles as $managerRole) {
            $managers[$managerRole->user_id] = $this->getServiceLocator()->get('UserTable')->find($managerRole->user_id)->username;
        }

        $adminsRoles = $this->getServiceLocator()->get('RoleLinkerTable')->fetchByCond('role_id', 'admin');

        foreach ($adminsRoles as $managerRole) {
            $managers[$managerRole->user_id] = $this->getServiceLocator()->get('UserTable')->find($managerRole->user_id)->username;
        }

        $clients = array();

        if ($roleLinkers->role_id == 'admin' || $roleLinkers->role_id == 'manager') {
            $clients = $this->getServiceLocator()->get('UserTable')->fetchByCond('manager_id', $entity->user_id);

        }
        $partnerGroups = array('-1' => '', '0' => 'Нет группы');
        $partnerGroupsNonSorted = $this->getServiceLocator()->get('PartnerGroupTable')->fetchAll();
        foreach ($partnerGroupsNonSorted as $group) {
            $partnerGroups[strval($group->id)] = $group->name;
        }

        if ($entity->partner_group > 0) {
            $sortedDiscounts = $discountTable->fetchAll($entity->partner_group, 1, true);
        } else {
            $sortedDiscounts = $discountTable->fetchAll($id, 0, true);
        }


        $cm = CatalogMapper::getInstance($this->getServiceLocator());
        $sections = $cm->fetchAllSections();
        $subsections = $cm->fetchAllSubsections(true);
        $series = $cm->fetchAllSeries(true);
        $products = $cm->fetchAllProducts(true);
        $treeDateByLvl = array();
        $treeHierarchy = array();
        foreach ($sections as $section) {
            $discVal = 0;
            $originalId = 0;
            if (isset($sortedDiscounts[\Catalog\Controller\AdminController::SECTION_TABLE][$section->id])) {
                $discVal = $sortedDiscounts[\Catalog\Controller\AdminController::SECTION_TABLE][$section->id]->discount;
                $originalId = $sortedDiscounts[\Catalog\Controller\AdminController::SECTION_TABLE][$section->id]->id;

            }
            $treeDateByLvl[\Catalog\Controller\AdminController::SECTION_TABLE][$section->id] = array('title' => $section->title, 'discount' => $discVal, 'inherited' => 0, 'dId' => ($originalId > 0 ? $originalId : false));
            $treeHierarchy[$section->id] = array();
        }
        foreach ($subsections as $subsection) {
            if (isset($treeHierarchy[$subsection->section_id])) {
                $treeHierarchy[$subsection->section_id][$subsection->id] = array();
                $discVal = 0;
                $inherited = 0;
                $originalId = 0;
                if (isset($sortedDiscounts[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$subsection->id])) {
                    $discVal = $sortedDiscounts[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$subsection->id]->discount;
                    $originalId = $sortedDiscounts[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$subsection->id]->id;
                } else {
                    $discVal = $treeDateByLvl[\Catalog\Controller\AdminController::SECTION_TABLE][$subsection->section_id]['discount'];
                    $inherited = 1;
                }
                $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$subsection->id] = array('title' => $subsection->title, 'parentId' => $subsection->section_id, 'discount' => $discVal, 'inherited' => $inherited, 'dId' => ($originalId > 0 ? $originalId : false));
                if (!$inherited) {
                    $treeDateByLvl[\Catalog\Controller\AdminController::SECTION_TABLE][$subsection->section_id]['shown'] = true;
                }
            }
        }
        foreach ($series as $oneser) {

            $subsection = $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$oneser->subsection_id];
            if ($subsection) {
                $sectionId = $subsection['parentId'];
                if (isset($treeHierarchy[$sectionId][$oneser->subsection_id])) {
                    $treeHierarchy[$sectionId][$oneser->subsection_id][$oneser->id] = array();
                    $discVal = 0;
                    $inherited = 0;
                    $originalId = 0;
                    if (isset($sortedDiscounts[\Catalog\Controller\AdminController::SERIES_TABLE][$oneser->id])) {
                        $discVal = $sortedDiscounts[\Catalog\Controller\AdminController::SERIES_TABLE][$oneser->id]->discount;
                        $originalId = $sortedDiscounts[\Catalog\Controller\AdminController::SERIES_TABLE][$oneser->id]->id;
                    } else {
                        $discVal = $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$oneser->subsection_id]['discount'];
                        $inherited = 1;
                    }
                    $treeDateByLvl[\Catalog\Controller\AdminController::SERIES_TABLE][$oneser->id] = array('title' => $oneser->title, 'parentId' => $oneser->subsection_id, 'discount' => $discVal, 'inherited' => $inherited, 'dId' => ($originalId > 0 ? $originalId : false));
                    if (!$inherited) {
                        $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$oneser->subsection_id]['shown'] = true;
                        $treeDateByLvl[\Catalog\Controller\AdminController::SECTION_TABLE][$treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$oneser->subsection_id]['parentId']]['shown'] = true;
                    }
                }
            }


        }
        foreach ($products as $product) {
            $series = $treeDateByLvl[\Catalog\Controller\AdminController::SERIES_TABLE][$product->series_id];
            $subsectionId = $series['parentId'];
            $subsection = $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$subsectionId];
            if ($subsection) {
                $sectionId = $subsection['parentId'];
                if (isset($treeHierarchy[$sectionId][$subsectionId][$product->series_id])) {
                    $treeHierarchy[$sectionId][$subsectionId][$product->series_id][$product->id] = $product->id;
                    $discVal = 0;
                    $inherited = 0;
                    $originalId = 0;
                    if (isset($sortedDiscounts[\Catalog\Controller\AdminController::PRODUCT_TABLE][$product->id])) {
                        $discVal = $sortedDiscounts[\Catalog\Controller\AdminController::PRODUCT_TABLE][$product->id]->discount;
                        $originalId = $sortedDiscounts[\Catalog\Controller\AdminController::PRODUCT_TABLE][$product->id]->id;
                    } else {
                        $discVal = $treeDateByLvl[\Catalog\Controller\AdminController::SERIES_TABLE][$product->series_id]['discount'];
                        $inherited = 1;
                    }

                    $treeDateByLvl[\Catalog\Controller\AdminController::PRODUCT_TABLE][$product->id] = array('title' => $product->title, 'parentId' => $product->series_id, 'discount' => $discVal, 'inherited' => $inherited, 'dId' => ($originalId > 0 ? $originalId : false));
                    if (!$inherited) {
                        $treeDateByLvl[\Catalog\Controller\AdminController::SERIES_TABLE][$product->series_id]['shown'] = true;
                        $prevSer = $treeDateByLvl[\Catalog\Controller\AdminController::SERIES_TABLE][$product->series_id];
                        $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$prevSer['parentId']]['shown'] = true;
                        $prevSS = $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$prevSer['parentId']];
                        $treeDateByLvl[\Catalog\Controller\AdminController::SECTION_TABLE][$prevSS['parentId']]['shown'] = true;
                    }
                }
            }
        }

        $treeDateByLvlJson = \Zend\Json\Json::encode($treeDateByLvl);
        $treeHierarchyJson = \Zend\Json\Json::encode($treeHierarchy);

        $return['treeDateByLvlJson'] = $treeDateByLvlJson;

        $return['treeHierarchyJson'] = $treeHierarchyJson;


        return array(
            'managers' => $managers,
            'clients' => $clients,
            'currentManager' => $currentManager,
            'roleLinkers' => $roleLinkers,
            'treeDateByLvlJson' => $treeDateByLvlJson,
            'treeHierarchyJson' => $treeHierarchyJson,
            'roles'  => $sortedRoles,
            'partnerGroups' => $partnerGroups,
            'managerRole' => $this->getServiceLocator()->get('RoleLinkerTable')->find($identity->getId(), 'user_id'),
            'user' => $identity,

            'sections'  => $sections,
            'discounts' => $discounts,
            'entity'      => $entity,
            'tags'      => \Zend\Json\Json::encode($data['tags']),
        );
    }

    public function saveTagitAction()
    {
        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $userId = $request->getPost('userId', false);
            $seriesIds = $request->getPost('tagitIds', false);
            $type = $request->getPost('type', false);
            $isGroup = $request->getPost('isGroup', false);
            $success = 0;

            if ($seriesIds) {
                $sl = $this->getServiceLocator();
                if($type && $type == \Catalog\Controller\AdminController::USERS_TABLE){
                    $table = $sl->get(CatalogService::getTableName(\Catalog\Controller\AdminController::USERS_TABLE));

                    foreach(explode(',', $seriesIds) as $seriesId){
                        $user = $table->find($seriesId);
                        if($user){
                            $prevIsPartner = $user->is_partner;

                            $user->is_partner = 1;
                            $table->save($user);

                            if (!$prevIsPartner) {
                                list($email, $mailView) = MailService::prepareUserPartnershipMailData($this->getServiceLocator(), $user);
                                MailService::sendMail($email, $mailView, "Вы подключены к партнёрскому сервису на Aledo");
                            }

                            $time = time();
                            if ($user->is_partner) {
                                UserService::addHistoryAction(
                                    $this->getServiceLocator(),
                                    $user->user_id,
                                    UserService::USER_ACTION_RECEIVED_A_PARTNERSHIP,
                                    "/admin/discounts/partners/$user->user_id/",
                                    $time
                                );
                            }
                            if ($this->zfcUserAuthentication()->hasIdentity()) {
                                $kuser = $this->zfcUserAuthentication()->getIdentity();
                                UserService::addHistoryAction(
                                    $this->getServiceLocator(),
                                    $kuser->getId(),
                                    UserService::USER_ACTION_GAVE_A_PARTNERSHIP,
                                    "/admin/discounts/partners/$user->user_id/",
                                    $time,
                                    $user->user_id
                                );
                            }
                        }
                    }
                    $success = 1;
                } else if ($type == \Catalog\Controller\AdminController::PARTNER_GROUP_TABLE){
                    $table = $sl->get('UserTable');
                    foreach(explode(',', $seriesIds) as $seriesId){
                        $user = $table->find($seriesId);
                        $user->partner_group = $id;


                        $prevIsPartner = $user->is_partner;
                        $user->is_partner = 1;
                        $table->save($user);
                        if (!$prevIsPartner) {
                            list($email, $mailView) = MailService::prepareUserPartnershipMailData($this->getServiceLocator(), $user);
                            MailService::sendMail($email, $mailView, "Вы подключены к партнёрскому сервису на Aledo");
                        }
                    }

                    $success = 1;
                }  else if ($type && $userId) {
                    $table = $sl->get('DiscountTable');
                    foreach(explode(',', $seriesIds) as $seriesId){
                        $discountSection = new Discount();
                        $discountSection->user_id = $userId;
                        $discountSection->section_id = $seriesId;
                        $discountSection->section_type = $type;
                        $discountSection->discount = 0;
                        $discountSection->is_group = $isGroup ? 1 : 0;
                        $table->save($discountSection);
                    }

                    $success = 1;
                }
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/discounts');
    }

    public function clearPersonalDiscountsAction() {
        $userId = (int) $this->params()->fromPost('id', false);

        if (!$userId) {
            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => 0, 'error' => 'userId not found')));
            return $response;
        }

        $sl = $this->getServiceLocator();

        $dm = DiscountMapper::getInstance($sl);
        $dm->clearPersonalDiscounts($userId, DiscountMapper::USER_DISCOUNT);

        $response = $this->getResponse();
        $response->setContent(\Zend\Json\Json::encode(array('success' => 1)));
        return $response;
    }

    public function clearPersonalDiscountsGroupAction() {
        $groupId = (int) $this->params()->fromPost('id', false);

        if (!$groupId) {
            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => 0, 'error' => 'groupId not found')));
            return $response;
        }

        $sl = $this->getServiceLocator();

        $dm = DiscountMapper::getInstance($sl);
        $dm->clearPersonalDiscounts($groupId, DiscountMapper::GROUP_DISCOUNT);

        $response = $this->getResponse();
        $response->setContent(\Zend\Json\Json::encode(array('success' => 1)));
        return $response;
    }

    public function removeParentTagitAction()
    {
        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $userId = $request->getPost('userId', false);
            $seriesId = $request->getPost('parentId', false);
            $type = $request->getPost('type', false);
            $isGroup = $request->getPost('isGroup', 0);
            $success = 0;

            if ($id) {
                $sl = $this->getServiceLocator();
                if($type){
                    switch($type){

                        case \Catalog\Controller\AdminController::USERS_TABLE:
                            $table = $sl->get(CatalogService::getTableName(\Catalog\Controller\AdminController::USERS_TABLE));
                            $user = $table->find($id);
                            if($user){
                                $user->is_partner = 0;
                                $table->save($user);
                                $success = 1;
                            }
                            break;
                        case \Catalog\Controller\AdminController::PARTNER_GROUP_TABLE:
                            $table = $sl->get('UserTable');
                            $user = $table->find($id);
                            if($user){
                                $user->partner_group = 0;
                                $table->save($user);
                                $success = 1;
                            }
                            break;

                        default :
                            $table = $sl->get('DiscountTable');
                            $table->del($userId, $id, $type, $isGroup);
                            $success = 1;
                            break;
                    }
                } else {

                }
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/discounts');
    }


    public function sectionsAction() {
        $userId = (int) $this->params()->fromRoute('user_id', 0);
        $id = (int) $this->params()->fromRoute('id', 0);

        $sl = $this->getServiceLocator();
        $user = $sl->get('UserTable')->find($userId);

        if (!$user) {
            return $this->redirect()->toRoute('zfcadmin/discounts');
        }

        //$sectionsAllTypes = $sl->get('DiscountTable')->fetchByConds(array( 'user_id' => $userId ));
        $discountTable =  $sl->get('DiscountTable');
        $sectionsAllTypes = $discountTable->fetchByUserId($userId, $user->partner_group, true, \Catalog\Controller\AdminController::SUBSECTION_TABLE, $this->getServiceLocator());
        $sections = array();
        $discounts = array();
        $sectionsIds = array();
        $mainSection = $sl->get('Catalog\Model\SectionTable')->find($id);
        foreach ($sectionsAllTypes as $sectionId => $oneSection) {
            $idField = DiscountService::getParentIdMethodByType(\Catalog\Controller\AdminController::SUBSECTION_TABLE);

            if ($oneSection->section_type == \Catalog\Controller\AdminController::SUBSECTION_TABLE) {
                $section = $sl->get('Catalog\Model\SubSectionTable')->find($oneSection->section_id);
                if (($section->section_id == $id || !$id)) {
                    $sections[$section->id] = $section;
                    if (!$oneSection->is_group) {
                        $sectionsIds[] = $section->id;
                    }
                    $discounts[$section->id] = $oneSection;
                }
            }
        }
        $currentDiscount = $sl->get('DiscountTable')->fetchByConds(array( 'user_id' => $userId, 'section_type' => \Catalog\Controller\AdminController::SECTION_TABLE, 'section_id' => $id, 'is_group' => 0));
        $currentDiscount = is_array($currentDiscount) ? reset($currentDiscount) : $currentDiscount;
        if ($id) {
            $allSections = $sl->get('Catalog\Model\SubSectionTable')->fetchByCond('section_id', $id, 'order asc');
        } else {
            $allSections = $sl->get('Catalog\Model\SubSectionTable')->fetchAll('order asc');
        }
        $data = CatalogService::getSeriesAndTags($allSections, 0, $sectionsIds);
        return array(
            'sections'  => $sections,
            'section'  => $mainSection,
            'currentDiscount'  => $currentDiscount,
            'discounts' => $discounts,
            'user'      => $user,
            'tags'      => \Zend\Json\Json::encode($data['tags']),
        );
    }

    public function sectionsGroupsAction() {
        $userId = (int) $this->params()->fromRoute('user_id', 0);
        $id = (int) $this->params()->fromRoute('id', 0);

        $sl = $this->getServiceLocator();
        $group = $sl->get('PartnerGroupTable')->find($userId);

        if (!$group) {
            return $this->redirect()->toRoute('zfcadmin/discounts');
        }

        $sectionsAllTypes = $sl->get('DiscountTable')->fetchByConds(array( 'user_id' => $userId, 'is_group' => 1 ));
        $sections = array();
        $discounts = array();
        $sectionsIds = array();
        $mainSection = $sl->get('Catalog\Model\SectionTable')->find($id);
        foreach ($sectionsAllTypes as $oneSection) {
            $idField = DiscountService::getParentIdMethodByType(\Catalog\Controller\AdminController::SUBSECTION_TABLE);

            if ($oneSection->section_type == \Catalog\Controller\AdminController::SUBSECTION_TABLE) {
                $section = $sl->get('Catalog\Model\SubSectionTable')->find($oneSection->section_id);
                if (($section->section_id == $id || !$id)) {
                    $sections[$section->id] = $section;
                    $sectionsIds[] = $section->id;
                    $discounts[$section->id] = $oneSection;
                }
            }
        }
        $currentDiscount = $sl->get('DiscountTable')->fetchByConds(array( 'user_id' => $userId, 'section_type' => \Catalog\Controller\AdminController::SECTION_TABLE, 'section_id' => $id, 'is_group' => 1));
        $currentDiscount = is_array($currentDiscount) ? reset($currentDiscount) : $currentDiscount;
        if ($id) {
            $allSections = $sl->get('Catalog\Model\SubSectionTable')->fetchByCond('section_id', $id, 'order asc');
        } else {
            $allSections = $sl->get('Catalog\Model\SubSectionTable')->fetchAll('order asc');
        }
        $data = CatalogService::getSeriesAndTags($allSections, 0, $sectionsIds);
        return array(
            'sections'  => $sections,
            'section'  => $mainSection,
            'currentDiscount'  => $currentDiscount,
            'discounts' => $discounts,
            'group'      => $group,
            'tags'      => \Zend\Json\Json::encode($data['tags']),
        );
    }

    public function subsectionsAction() {
        $userId = (int) $this->params()->fromRoute('user_id', 0);
        $id = (int) $this->params()->fromRoute('id', 0);

        $sl = $this->getServiceLocator();
        $user = $sl->get('UserTable')->find($userId);

        if (!$user) {
            return $this->redirect()->toRoute('zfcadmin/discounts');
        }

//        $sectionsAllTypes = $sl->get('DiscountTable')->fetchByConds(array( 'user_id' => $userId, 'is_group' => 0));
        /** @var DiscountTable $discountTable */
        $discountTable =  $sl->get('DiscountTable');
        $sectionsAllTypes = $discountTable->fetchByUserId($userId, $user->partner_group, true, \Catalog\Controller\AdminController::SERIES_TABLE, $this->getServiceLocator());
        $sections = array();
        $discounts = array();
        $sectionsIds = array();
        $mainSection = $sl->get('Catalog\Model\SubSectionTable')->find($id);
        foreach ($sectionsAllTypes as $oneSection) {

            if ($oneSection->section_type == \Catalog\Controller\AdminController::SERIES_TABLE) {
                $section = $sl->get('Catalog\Model\SeriesTable')->find($oneSection->section_id);
                if ($section->subsection_id == $id || !$id) {
                    $sections[$section->id] = $section;
                    if (!$oneSection->is_group) {
                        $sectionsIds[] = $section->id;
                    }
                    $discounts[$section->id] = $oneSection;
                }
            }
        }
        $currentDiscount = $sl->get('DiscountTable')->fetchByConds(array( 'user_id' => $userId, 'section_type' => \Catalog\Controller\AdminController::SUBSECTION_TABLE, 'section_id' => $id));
        $currentDiscount = is_array($currentDiscount) ? reset($currentDiscount) : $currentDiscount;
        if ($id) {
            $allSections = $sl->get('Catalog\Model\SeriesTable')->fetchByCond('subsection_id', $id, 'order asc');
        } else {
            $allSections = $sl->get('Catalog\Model\SeriesTable')->fetchAll('order asc');
        }
        $data = CatalogService::getSeriesAndTags($allSections, 0, $sectionsIds);

        return array(
            'sections'  => $sections,
            'section'  => $mainSection,
            'currentDiscount' => $currentDiscount,
            'discounts' => $discounts,
            'user'      => $user,
            'parents' => $parents = DiscountService::getHierarchy($sl, \Catalog\Controller\AdminController::SUBSECTION_TABLE, $id),
            'tags'      => \Zend\Json\Json::encode($data['tags']),
        );
    }

    public function subsectionsGroupsAction() {
        $userId = (int) $this->params()->fromRoute('user_id', 0);
        $id = (int) $this->params()->fromRoute('id', 0);

        $sl = $this->getServiceLocator();
        $group = $sl->get('PartnerGroupTable')->find($userId);

        if (!$group) {
            return $this->redirect()->toRoute('zfcadmin/discounts');
        }

        $sectionsAllTypes = $sl->get('DiscountTable')->fetchByConds(array( 'user_id' => $userId, 'is_group' => 1 ));
        $sections = array();
        $discounts = array();
        $sectionsIds = array();
        $mainSection = $sl->get('Catalog\Model\SubSectionTable')->find($id);
        foreach ($sectionsAllTypes as $oneSection) {

            if ($oneSection->section_type == \Catalog\Controller\AdminController::SERIES_TABLE) {
                $section = $sl->get('Catalog\Model\SeriesTable')->find($oneSection->section_id);
                if ($section->subsection_id == $id || !$id) {
                    $sections[$section->id] = $section;
                    $sectionsIds[] = $section->id;
                    $discounts[$section->id] = $oneSection;
                }
            }
        }
        $currentDiscount = $sl->get('DiscountTable')->fetchByConds(array( 'user_id' => $userId, 'section_type' => \Catalog\Controller\AdminController::SUBSECTION_TABLE, 'section_id' => $id, 'is_group' => 1));
        $currentDiscount = is_array($currentDiscount) ? reset($currentDiscount) : $currentDiscount;
        if ($id) {
            $allSections = $sl->get('Catalog\Model\SeriesTable')->fetchByCond('subsection_id', $id, 'order asc');
        } else {
            $allSections = $sl->get('Catalog\Model\SeriesTable')->fetchAll('order asc');
        }
        $data = CatalogService::getSeriesAndTags($allSections, 0, $sectionsIds);

        return array(
            'sections'  => $sections,
            'section'  => $mainSection,
            'currentDiscount' => $currentDiscount,
            'discounts' => $discounts,
            'group'      => $group,
            'parents' => $parents = DiscountService::getHierarchy($sl, \Catalog\Controller\AdminController::SUBSECTION_TABLE, $id),
            'tags'      => \Zend\Json\Json::encode($data['tags']),
        );
    }

    public function seriesAction() {
        $userId = (int) $this->params()->fromRoute('user_id', 0);
        $id = (int) $this->params()->fromRoute('id', 0);

        $sl = $this->getServiceLocator();
        $user = $sl->get('UserTable')->find($userId);

        if (!$user) {
            return $this->redirect()->toRoute('zfcadmin/discounts');
        }

//        $sectionsAllTypes = $sl->get('DiscountTable')->fetchByConds(array( 'user_id' => $userId, 'is_group' => 0 ));
        $discountTable =  $sl->get('DiscountTable');
        $sectionsAllTypes = $discountTable->fetchByUserId($userId, $user->partner_group, true, \Catalog\Controller\AdminController::PRODUCT_TABLE, $this->getServiceLocator());
        $sections = array();
        $discounts = array();
        $sectionsIds = array();
        $mainSection = $sl->get('Catalog\Model\SeriesTable')->find($id);
        foreach ($sectionsAllTypes as $oneSection) {
            if ($oneSection->section_type == \Catalog\Controller\AdminController::PRODUCT_TABLE) {
                $section = $sl->get('Catalog\Model\ProductTable')->find($oneSection->section_id);
                if ($section->series_id == $id || !$id) {
                    $sections[$section->id] = $section;
                    if (!$oneSection->is_group) {
                        $sectionsIds[] = $section->id;
                    }
                    $discounts[$section->id] = $oneSection;
                }
            }

        }
        $currentDiscount = $sl->get('DiscountTable')->fetchByConds(array( 'user_id' => $userId, 'section_type' => \Catalog\Controller\AdminController::SERIES_TABLE, 'section_id' => $id, 'is_group' => 0));
        $currentDiscount = is_array($currentDiscount) ? reset($currentDiscount) : $currentDiscount;
        if ($id) {
            $allSections = $sl->get('Catalog\Model\ProductTable')->fetchByCond('series_id', $id, 'order asc');
        } else {
            $allSections = $sl->get('Catalog\Model\ProductTable')->fetchAll('order asc');
        }

        $data = CatalogService::getSeriesAndTags($allSections, 0, $sectionsIds);
        return array(
            'sections'  => $sections,
            'section'  => $mainSection,
            'currentDiscount' => $currentDiscount,
            'discounts' => $discounts,
            'parents' => $parents = DiscountService::getHierarchy($sl, \Catalog\Controller\AdminController::SERIES_TABLE, $id),
            'user'      => $user,
            'tags'      => \Zend\Json\Json::encode($data['tags']),
        );
    }

    public function seriesGroupsAction() {
        $userId = (int) $this->params()->fromRoute('user_id', 0);
        $id = (int) $this->params()->fromRoute('id', 0);

        $sl = $this->getServiceLocator();
        $group = $sl->get('PartnerGroupTable')->find($userId);

        if (!$group) {
            return $this->redirect()->toRoute('zfcadmin/discounts');
        }

        $sectionsAllTypes = $sl->get('DiscountTable')->fetchByConds(array( 'user_id' => $userId, 'is_group' => 1 ));
        $sections = array();
        $discounts = array();
        $sectionsIds = array();
        $mainSection = $sl->get('Catalog\Model\SeriesTable')->find($id);
        foreach ($sectionsAllTypes as $oneSection) {
            if ($oneSection->section_type == \Catalog\Controller\AdminController::PRODUCT_TABLE) {
                $section = $sl->get('Catalog\Model\ProductTable')->find($oneSection->section_id);
                if ($section->series_id == $id || !$id) {
                    $sections[$section->id] = $section;
                    $sectionsIds[] = $section->id;
                    $discounts[$section->id] = $oneSection;
                }
            }

        }
        $currentDiscount = $sl->get('DiscountTable')->fetchByConds(array( 'user_id' => $userId, 'section_type' => \Catalog\Controller\AdminController::SERIES_TABLE, 'section_id' => $id, 'is_group' => 1));
        $currentDiscount = is_array($currentDiscount) ? reset($currentDiscount) : $currentDiscount;
        if ($id) {
            $allSections = $sl->get('Catalog\Model\ProductTable')->fetchByCond('series_id', $id, 'order asc');
        } else {
            $allSections = $sl->get('Catalog\Model\ProductTable')->fetchAll('order asc');
        }

        $data = CatalogService::getSeriesAndTags($allSections, 0, $sectionsIds);
        return array(
            'sections'  => $sections,
            'section'  => $mainSection,
            'currentDiscount' => $currentDiscount,
            'discounts' => $discounts,
            'parents' => $parents = DiscountService::getHierarchy($sl, \Catalog\Controller\AdminController::SERIES_TABLE, $id),
            'group'      => $group,
            'tags'      => \Zend\Json\Json::encode($data['tags']),
        );
    }

    public function productsAction() {
        $userId = (int) $this->params()->fromRoute('user_id', 0);
        $id = (int) $this->params()->fromRoute('id', 0);

        $sl = $this->getServiceLocator();
        $user = $sl->get('UserTable')->find($userId);

        if (!$user) {
            return $this->redirect()->toRoute('zfcadmin/discounts');
        }

//        $sectionsAllTypes = $sl->get('DiscountTable')->fetchByConds(array( 'user_id' => $userId, 'is_group' => 0 ));
        $discountTable =  $sl->get('DiscountTable');
        $sectionsAllTypes = $discountTable->fetchByUserId($id, $user->partner_group, true, \Catalog\Controller\AdminController::PRODUCT_TABLE, $this->getServiceLocator());
        $sections = array();
        $discounts = array();
        $sectionsIds = array();
        $mainSection = $sl->get('Catalog\Model\ProductTable')->find($id);
        foreach ($sectionsAllTypes as $oneSection) {
            $section = DiscountService::getSectionByLevelId($sl, $oneSection->section_id, \Catalog\Controller\AdminController::PRODUCT_TABLE, $oneSection->section_type);

            if ($section) {
                    $sections[$section->id] = $section;
                    $sectionsIds[] = $section->id;
                    if ($oneSection->section_type == \Catalog\Controller\AdminController::PRODUCT_TABLE) {
                        $discounts[$section->id] = $oneSection;
                    }
            }

        }
        $currentDiscount = $sl->get('DiscountTable')->fetchByConds(array( 'user_id' => $userId, 'section_type' => \Catalog\Controller\AdminController::PRODUCT_TABLE, 'section_id' => $id, 'is_group' => 0));
        $currentDiscount = is_array($currentDiscount) ? reset($currentDiscount) : $currentDiscount;
        return array(
            'sections'  => $sections,
            'section'  => $mainSection,
            'currentDiscount' => $currentDiscount,
            'discounts' => $discounts,
            'parents' => $parents = DiscountService::getHierarchy($sl, \Catalog\Controller\AdminController::PRODUCT_TABLE, $id),
            'user'      => $user,
        );
    }

    public function productsGroupsAction() {
        $userId = (int) $this->params()->fromRoute('user_id', 0);
        $id = (int) $this->params()->fromRoute('id', 0);

        $sl = $this->getServiceLocator();
        $group = $sl->get('PartnerGroupTable')->find($userId);

        if (!$group) {
            return $this->redirect()->toRoute('zfcadmin/discounts');
        }

        $sectionsAllTypes = $sl->get('DiscountTable')->fetchByConds(array( 'user_id' => $userId, 'is_group' => 1 ));
        $sections = array();
        $discounts = array();
        $sectionsIds = array();
        $mainSection = $sl->get('Catalog\Model\ProductTable')->find($id);
        foreach ($sectionsAllTypes as $oneSection) {
            $section = DiscountService::getSectionByLevelId($sl, $oneSection->section_id, \Catalog\Controller\AdminController::PRODUCT_TABLE, $oneSection->section_type);

            if ($section) {
                    $sections[$section->id] = $section;
                    $sectionsIds[] = $section->id;
                    if ($oneSection->section_type == \Catalog\Controller\AdminController::PRODUCT_TABLE) {
                        $discounts[$section->id] = $oneSection;
                    }
            }

        }
        $currentDiscount = $sl->get('DiscountTable')->fetchByConds(array( 'user_id' => $userId, 'section_type' => \Catalog\Controller\AdminController::PRODUCT_TABLE, 'section_id' => $id, 'is_group' => 1));
        $currentDiscount = is_array($currentDiscount) ? reset($currentDiscount) : $currentDiscount;
        return array(
            'sections'  => $sections,
            'section'  => $mainSection,
            'currentDiscount' => $currentDiscount,
            'discounts' => $discounts,
            'parents' => $parents = DiscountService::getHierarchy($sl, \Catalog\Controller\AdminController::PRODUCT_TABLE, $id),
            'group'      => $group,
        );
    }

    public function updateEditableAction()
    {
        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $post = $request->getPost()->toArray();
            $success = 0;

            if ($post['pk']) {
                $typeNid = is_array($post['pk']) ? explode('-',$post['pk']) : array(1 => $post['pk']);

                if (strpos($post['pk'], '-')) {
                    $typeNid = explode('-',$post['pk']);
                }

                if ($typeNid[0] == \Catalog\Controller\AdminController::PARTNER_GROUP_TABLE) {


                    $table = $this->getServiceLocator()->get('PartnerGroupTable');
                    $group = $table->find($typeNid[1]);
                    $field = $post['name'];
                    $group->$field = $post['value'];
                    $table->save($group);
                    $success = 1;
                } elseif ($typeNid[0] == \Catalog\Controller\AdminController::USERS_TABLE) {


                    $table = $this->getServiceLocator()->get('UserTable');
                    $user = $table->find($typeNid[1]);
                    if ($post['name'] == 'role_id') {
                        if ($this->zfcUserAuthentication()->getIdentity()->getId() !=  $typeNid[1]) {
                            $userRole = $this->getServiceLocator()->get('RoleLinkerTable')->find($typeNid[1], 'user_id');
                            $userRole->role_id = $post['value'];
                            $this->getServiceLocator()->get('RoleLinkerTable')->save($userRole, 'user_id');
                        }

                    } else {
                        $field = $post['name'];
                        $user->$field = $post['value'];

                        if ($post['name'] == 'manager_id') {
                            $oldEntity = $this->getServiceLocator()->get('UserTable')->find($typeNid[1]);
                            if ($oldEntity->manager_id != $post['value'] && $post['value'] != 0) {
                                list($email, $mailView) = MailService::prepareNewManagerUserMailData($this->getServiceLocator(), $oldEntity, $post['value']);
                                MailService::sendMail($email, $mailView, "Вам назначен менеджер на Aledo");

                                list($email, $mailView) = MailService::prepareNewManagerManagerMailData($this->getServiceLocator(), $oldEntity, $post['value']);
                                MailService::sendMail($email, $mailView, "Вам назначен новый клиент на Aledo номер " . $oldEntity->user_id);
                            }
                        }
                        $table->save($user);
                    }
                    $success = 1;
                } else {

                    /** @var \Discount\Model\DiscountTable $discountTable */
                    $discountTable = $this->getServiceLocator()->get('DiscountTable');
                    $discount = $discountTable->find($typeNid[1]);
                    $field = $post['name'];
                    $discount->$field = $post['value'];
                    $discountTable->save($discount);

                    $type = "";
                    switch ($discount->section_type) {
                        case \Catalog\Controller\AdminController::SECTION_TABLE :
                            $type = "sections";
                            break;
                        case \Catalog\Controller\AdminController::SUBSECTION_TABLE :
                            $type = "subsections";
                            break;
                        case \Catalog\Controller\AdminController::SERIES_TABLE :
                            $type = "series";
                            break;
                        case \Catalog\Controller\AdminController::PRODUCT_TABLE :
                            $type = "products";
                            break;
                    }

                    $time = time();
                    //тут я не проверяю партнёрство, потому что скидки в принципе назначаются только партнёрам
                    UserService::addHistoryAction(
                        $this->getServiceLocator(),
                        $discount->user_id,
                        UserService::USER_ACTION_RECEIVED_A_DISCOUNT,
                        "/admin/discounts/" . $type . "/" . $discount->user_id . "/" . $discount->section_id . "/",
                        $time
                    );
                    if ($this->zfcUserAuthentication()->hasIdentity()) {
                        $user = $this->zfcUserAuthentication()->getIdentity();
                        UserService::addHistoryAction(
                            $this->getServiceLocator(),
                            $user->getId(),
                            UserService::USER_ACTION_GAVE_A_DISCOUNT,
                            "/admin/discounts/" . $type . "/" . $discount->user_id . "/" . $discount->section_id . "/",
                            $time,
                            $discount->user_id
                        );
                    }

                    $success = 1;

                }
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/catalog');
    }
}