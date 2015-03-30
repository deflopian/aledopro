<?php
namespace Discount\Mapper;

use Discount\Model\DiscountTable;
use Zend\ServiceManager\ServiceLocatorInterface;

class DiscountMapper
{
    private static $instance;
    /** @var DiscountTable $table*/
    private $table;
    private $tableName = "DiscountTable";
    /** @var \Zend\ServiceManager\ServiceLocatorInterface $sl */
    private $sl;

    const USER_DISCOUNT = 0;
    const GROUP_DISCOUNT = 1;

    /**
     * @param $sl ServiceLocatorInterface
     */
    private function __construct($sl) {
        $this->table = $sl->get($this->tableName);
        $this->sl = $sl;
    }

    public static function getInstance($sl) {
        if (is_null(self::$instance)) {
            self::$instance = new DiscountMapper($sl);
        }

        return self::$instance;
    }

    public function createDiscount($parentId, $entityId, $entityType, $value, $isGroup = false) {
        //some code here
    }

    public function updateDiscount($parentId, $entityId, $entityType, $value, $isGroup = false) {
        //some code here
    }

    public function deleteDiscount($parentId, $entityId, $entityType, $value, $isGroup = false) {
        //some code here
    }

    public function clearPersonalDiscounts($parentId, $isGroup = false) {
        if (!$isGroup) {
            //удаляем скидки юзера
            $this->table->delete(array('user_id' => $parentId, 'is_group' => self::USER_DISCOUNT));
        } else {
            //удаляем скидки юзеров в группе
            $users = $this->sl->get('UserTable')->fetchByCond('partner_group', $parentId);
            foreach ($users as $user) {
                $this->table->delete(array('user_id' => $user->user_id, 'is_group' => self::USER_DISCOUNT));
            }
        }
    }

}