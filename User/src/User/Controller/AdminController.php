<?php
namespace User\Controller;

use Application\Controller\SampleAdminController;
use Application\Service\MailService;
use User\Model\ManagerToUser;
use User\Model\ManagerToUserTable;
use User\Model\User;
use User\Model\UserHistoryTable;
use User\Service\UserService;
use User\Model\RoleLinker;
use Zend\Crypt\Password\Bcrypt;

class AdminController extends SampleAdminController
{
    protected $entityName = 'User\Model\User';

    public function indexAction()
    {
        $this->setData();

        $roles = $this->getRoles();
        $entities = $this->getServiceLocator()->get('UserTable')->fetchAll();
        // не получаем админов.

        $roleLinkers = $this->getServiceLocator()->get('RoleLinkerTable')->fetchAll();

        $managers = array(0 => '-');
        $managersIds = array();
        foreach ($entities as $entity) {
            $managersIds[] = $entity->manager_id;
        }
        if (count($managersIds)) {
            $allManagers = $this->getServiceLocator()->get('UserTable')->fetchByCond('user_id', $managersIds);
            foreach ($allManagers as $currentManager) {
                $managers[$currentManager->user_id] = $currentManager->username;
            }
        }

        foreach ($entities as &$entity1) {
            $entity1->user_id = (int)$entity1->user_id;
            if (isset($roleLinkers[$entity1->user_id])) {
                $entity1->true_role = $roleLinkers[$entity1->user_id];
            }
            if (isset($managers[$entity1->manager_id])) {
                $entity1->manager_name = $managers[$entity1->manager_id];
            }
        }

        $entitiesJson = \Zend\Json\Json::encode($entities);

        return array(
            'managers' => $managers,
            'usersJson' => $entitiesJson,
            'entities' => $entities,
            'roles'  => $roles,
            'roleLinkers'  => $roleLinkers
        );
    }


    /**
     * @return UserHistoryTable
     */
    public function getHistoryTable()
    {
        $sl = $this->getServiceLocator();
        return $sl->get('UserHistoryTable');
    }

    public function calendarAction()
    {
        $userId = $this->params()->fromRoute('id', 0);

        $year = $this->params()->fromQuery('year', false);
        $request = $this->getRequest();
        $htable = $this->getHistoryTable();



        $time = time();

        $monthNum = $this->params()->fromQuery('month', date('n', $time)); //если месяц не выбран, берём текущий
        $year = $this->params()->fromQuery('year', date('Y', $time));//если год не выбран, берём текущий
        $firstDayOfMonth = mktime(4,0,0,$monthNum,1,$year,-1); //метка первого дня года (+4 часа на всякий случай) - костыльно, согласен

        $daysCount = date('t', $firstDayOfMonth); //получаем количество дней в выбранном месяце

        $weekFirstDayNum = date('w', $firstDayOfMonth); //день недели, соответствующий первому числу
        $weekFirstDayNum = $weekFirstDayNum == 0 ? 7 : $weekFirstDayNum; //на прогнившем Западе отсчёт идёт с воскресенья...
        $todayMonth = date('n', $time);
        $todayYear = date('Y', $time);
        if ($todayMonth == $monthNum && $todayYear == $year) {
            $today = date('j', $time);
        } else {
            $today = false;
        }

        $const = UserService::USER_HISTORY_MONTH_30;
        switch ($daysCount) {
            case 28 :
                $const = UserService::USER_HISTORY_MONTH_28;
                break;
            case 29 :
                $const = UserService::USER_HISTORY_MONTH_29;
                break;
            case 30 :
                $const = UserService::USER_HISTORY_MONTH_30;
                break;
            case 31 :
                $const = UserService::USER_HISTORY_MONTH_31;
                break;
        };

        if (!$today) {
            $history = $htable->fetchUserHistoryByPeriod($userId, $const, mktime(23,0,0,$monthNum+1,0,$year,-1));
        } else {
            $history = $htable->fetchUserHistoryByPeriod($userId, $const, $time);
        }

        $actions = array();
        foreach ($history as $action) {
            $actions[date('j', $action->timer)][] = $action;
        }



        return array(
            'daysCount' => $daysCount,
            'weekFirstDayNum' => $weekFirstDayNum,
            'today' => $today,
            'actions' => $actions,
            'userId' => $userId,
            'year' => $year,
            'month' => $monthNum,
        );

    }


    public function historyAction()
    {
        $userId = $this->params()->fromRoute('id', 0);
        $request = $this->getRequest();
        $htable = $this->getHistoryTable();

        $year = $this->params()->fromQuery('year', 0);
        $month = $this->params()->fromQuery('month', 0);
        $day = $this->params()->fromQuery('day', 0);

        $history = array();
        if ($request->isPost()) {
            $to = $this->params()->fromPost('to', false);
            $period = $this->params()->fromPost('period', false);
            $type = $this->params()->fromPost('type', false);

            if ($type) {
                if (!$to && !$period) {
                    $history = $htable->fetchUserHistoryByType($userId, $type);
                } else {
                    $history = $htable->fetchUserHistoryByPeriodAndType($userId, $type, $period, $to);
                }

            } else {
                if ($to || $period) {
                    if (!$period) {
                        $period = UserService::USER_HISTORY_DAY;
                    }
                    $history = $htable->fetchUserHistoryByPeriod($userId, $period, $to);
                } else {
                    $history = $htable->fetchUserHistoryAll($userId);
                }
            }

        } else {
            if ($year == 0 && $month == 0 && $day == 0) {
                $history = $htable->fetchUserHistoryAll($userId);
            } else {
                $history = $htable->fetchUserHistoryByPeriod($userId, UserService::USER_HISTORY_DAY, mktime(23, 59, 59, $month, $day, $year));
            }

        }



        uasort($history, function ($a, $b) {
            if ($a->timer == $b->timer) return 0;
            return ($a->timer - $b->timer > 0) ? -1 : 1;
        } );

        return array(
            'history'   => $history,
            'period'      => isset($period) ? $period : false,
            'to'      => isset($to) ? $to : false,
            'type'      => isset($type) ? $type : false,
            'userId' => $userId,
        );
    }

    public function orphansAction()
    {
        $this->setData();

        $roles = $this->getRoles();

        $roleLinkers = $this->getServiceLocator()->get('RoleLinkerTable')->fetchAll();
        $managersIds = array();
        foreach ($roleLinkers as $id => $role) {
            if ($role == 'admin' || $role == 'manager') {
                $managersIds[] = $id;
            }
        }
        $entities = $this->getServiceLocator()->get('UserTable')->fetchByConds(array('manager_id' => 0), array('user_id' => $managersIds));
        // не получаем админов.

        foreach ($entities as &$entity1) {
            $entity1->user_id = (int)$entity1->user_id;
            if (isset($roleLinkers[$entity1->user_id])) {
                $entity1->true_role = $roleLinkers[$entity1->user_id];
            }
        }

        $entitiesJson = \Zend\Json\Json::encode($entities);


        return array(
            'entities' => $entities,
            'usersJson' => $entitiesJson,
            'roles'  => $roles,
            'roleLinkers'  => $roleLinkers
        );
    }

    public function setManagerToUserAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $post = $request->getPost()->toArray();
            $managerId = $post['managerId'];
            $userId = $post['userId'];

            if (!$managerId || !$userId) {
                return false;
            }
            /** @var ManagerToUserTable $m2uTable */
            $m2uTable = $this->getServiceLocator()->get('ManagerToUserTable');
            $m2u = new ManagerToUser();
            $m2u->manager_id = $managerId;
            $m2u->user_id = $userId;
            $m2uTable->save($m2u);
        } else {
            return $this->redirect()->toRoute('home');
        }


    }

    public function viewAction()
    {
        $this->setData();
        $identity = $this->zfcUserAuthentication()->getIdentity();
        $id = (int) $this->params()->fromRoute('id', 0);
        if (!$id) {
            return $this->redirect()->toRoute('zfcadmin/' . $this->url);
        }

        $entity = $this->getServiceLocator()->get('UserTable')->find($id);
        if ($entity === false) {
            return $this->redirect()->toRoute('zfcadmin/' . $this->url);
        }

        $roles = $this->getRoles();
        $roleLinkers = $this->getServiceLocator()->get('RoleLinkerTable')->find($id, 'user_id');
        $sortedRoles = array();
        foreach($roles as $id=>$role){
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

        return array(
            'entity' => $entity,
            'managers' => $managers,
            'clients' => $clients,
            'currentManager' => $currentManager,
            'roleLinkers' => $roleLinkers,
            'roles'  => $sortedRoles,
            'partnerGroups' => $partnerGroups,
            'managerRole' => $this->getServiceLocator()->get('RoleLinkerTable')->find($identity->getId(), 'user_id'),
            'user' => $identity,
        );
    }

    public function updateEditableAction()
    {
        $this->setData();
        $identity = $this->zfcUserAuthentication()->getIdentity();
        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $post = $request->getPost()->toArray();
            $success = 0;

            if ($post['pk']) {
                $data['user_id'] = $post['pk'];

                if ($post['name'] == 'role_id') {
                    if ($identity->getId() !=  $post['pk']) {
                        $userRole = $this->getServiceLocator()->get('RoleLinkerTable')->find($post['pk'], 'user_id');
                        $userRole->role_id = $post['value'];
                        $this->getServiceLocator()->get('RoleLinkerTable')->save($userRole, 'user_id');
                    }

                } else {
					$flag = true;
                    $data[$post['name']] = $post['value'];
                    if ($post['name'] == 'manager_id') {
                        $oldEntity = $this->getServiceLocator()->get('UserTable')->find($post['pk']);
                        if ($oldEntity->manager_id != $post['value'] && $post['value'] != 0) {
                            list($email, $mailView) = MailService::prepareNewManagerUserMailData($this->getServiceLocator(), $oldEntity, $post['value']);
                            MailService::sendMail($email, $mailView, "Вам назначен менеджер на Aledo");

                            list($email, $mailView) = MailService::prepareNewManagerManagerMailData($this->getServiceLocator(), $oldEntity, $post['value']);
                            MailService::sendMail($email, $mailView, "Вам назначен новый клиент на Aledo номер " . $oldEntity->user_id);
                        }
                    }
                    if ($post['name'] == 'is_partner' && $post['value'] == 1) {
                        $data['partner_group'] = 0;
                    }
					if ($post['name'] == 'password') {
						$bcrypt = new Bcrypt();
						$bcrypt->setCost(4);
						$data['password'] = $bcrypt->create($post['value']);
					}
					if ($post['name'] == 'email' && $post['value']) {
						$someUsers = $this->getServiceLocator()->get('UserTable')->fetchByCond('email', $post['value']);
						foreach($someUsers as $someUser) {
							if ($someUser->user_id == $data['user_id']) continue;
							$flag = false;
						}
					}
					
					if ($flag) {
						$entity = new $this->entityName;
						$entity->exchangeArray($data);
						$this->getServiceLocator()->get('UserTable')->save($entity);
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
                $data = array(
					'username' => $title,
					'password' => '',
					'is_spamed' => 0,
					'state' => 1,
					'manager_id' => 0,
					'is_partner' => 0,
					'partner_group' => 0,
					'god_mode_id' => 0
					);

                $entity = new $this->entityName;
                $entity->exchangeArray($data);

                $newId = $this->getServiceLocator()->get($this->table)->save($entity);
				
				$userRole = new RoleLinker();
				$userRole->user_id = $newId;
				$userRole->role_id = 'user';
				$sm = $this->getServiceLocator();
				$roleLinker = $sm->get('RoleLinkerTable');
				$roleLinker->save($userRole, "user_id");

                if($newId){
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

    public function delEntityAction()
    {
        $this->setData();

        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $success = 0;

            if ($id) {
                $this->getServiceLocator()->get($this->table)->del($id);
                $this->getServiceLocator()->get($this->roletable)->del($id);
                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/'.$this->url);
    }

    private function getNotAdminRoles()
    {
        return array(
            1 => 'просто пользователь',
            2 => 'супер партнер',
        );
    }

    private function getRoles()
    {
        return $this->getServiceLocator()->get('UserRoleTable')->fetchAll();
    }
}