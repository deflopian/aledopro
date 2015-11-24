<?php
namespace User\Model;

use Application\Model\SampleModel;
use Application\Model\SampleTable;
use Application\Service\ApplicationService;
use User\Service\UserService;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Select;
use Zend\Db;
use Zend\Db\TableGateway\AbstractTableGateway;

class UserHistoryTable extends SampleTable
{
    protected $table = 'user_history';

    public function __construct(Adapter $adapter)
    {
        if (ApplicationService::isDomainZone('by')) {
            $this->table = 'by_user_history';
        }
		
		$this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new UserHistory());
        $this->initialize();
    }

    public function fetchUserHistoryAll($userId) {
        $actions = $this->fetchByCond('user_id', $userId);
        return $actions;
    }

    /**
     * @param $userId
     * @param int $periodType
     * @param bool $to
     * @return UserHistory[]
     */
    public function fetchUserHistoryByPeriod($userId, $periodType = UserService::USER_HISTORY_DAY, $to = false) {
        if ($to === false) {
            $to = time();
        }
        $period = UserService::$periods[$periodType];
        $from = $to - $period;

        $select = $this->getSql()->select()->order(array('timer' => 'desc'))->where(array('user_id' => $userId, 'timer <= ?' => $to, 'timer >= ?' => $from));
        $actions = $this->selectWith($select);

        $history = array();
        foreach ($actions as $action) {
            $history[] = $action;
        }

        return $history;
    }

    public function fetchUserHistoryByType($userId, $type = UserService::USER_ACTION_OTHER) {

        $actions = $this->fetchByConds(array('user_id' => $userId, 'actionType' => $type));
        return $actions;
    }

    public function fetchUserHistoryByPeriodAndType($userId, $type = UserService::USER_ACTION_OTHER, $periodType = UserService::USER_HISTORY_DAY, $to = false) {
        if ($to === false) {
            $to = time();
        }

        $period = UserService::$periods[$periodType];
        $from = $to - $period;
        $select = $this->getSql()->select()->order(array('timer' => 'desc'))->where(array('user_id' => $userId, 'actionType' => $type, 'timer <= ?' => $to, 'timer >= ?' => $from));
        $actions = $this->selectWith($select);

        return $actions;
    }

    public function save(UserHistory $entity)
    {
        if ($entity->timer === false) {
            $entity->timer = time();
        }
        $data = $entity->toArray();

        $this->insert($data);
        return $this->lastInsertValue;
    }
}
