<?php
namespace Discount\Model;

use Application\Model\SampleModel;
use Application\Service\ApplicationService;
use Catalog\Mapper\CatalogMapper;
use Zend\Db\Sql\Predicate;
use Application\Model\SampleTable;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Predicate\Operator;
use Zend\Db\Sql\Predicate\PredicateSet;
use Zend\ServiceManager\ServiceLocatorInterface;

class DiscountTable extends SampleTable
{
    protected $table = 'discounts';

    public function __construct(Adapter $adapter)
    {
        if (ApplicationService::isDomainZone('by')) {
            $this->table = 'by_discounts';
        }
		
		$this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new Discount());
        $this->initialize();
    }

    /**
     * Собираем скидки. При этом, если скидка пользователя где-то перекрывает скидку группы,
     * то эта скидка перекрывает и все вложенные скидки группы, включая отдельно выставленные.
     * - пробный вариант. Изначально не перекрывала отдельно выставленные.
     * @param $userId
     * @param int $groupId
     * @param bool $fullDiscount
     * @param int $specialLvl
     * @param ServiceLocatorInterface|null $sl
     * @return array
     */
    public function fetchByUserId($userId, $groupId = 0, $fullDiscount = false, $specialLvl = 0, $sl = null, $am = false) {
        /** @var \Discount\Model\Discount[] $results */
        /** @var \Discount\Model\Discount[] $groupDiscounts */
//        $overwriteSpecial = true;
//        $baseDiscounts = array();
//        $userDiscounts = array();
//        $discounts = array();
        if ($groupId) {
            $discountsRes = $this->fetchByConds(array('user_id' => $groupId, 'is_group' => 1));
        } else {
            $discountsRes = $this->fetchByConds(array('user_id' => $userId, 'is_group' => 0));
        }

        foreach ($discountsRes as $gd) {
            if (!$fullDiscount) {
                $discounts[$gd->section_type][$gd->section_id] = $gd->discount;
            } else {
                $discounts[$gd->section_type][$gd->section_id] = $gd;
            }
        }
//
//       /* if ($specialLvl) {
//            $results = $this->fetchByConds(array('user_id' => $userId, 'is_group' => 0, 'section_type' => $specialLvl));
//        } else {*/
//            $results = $this->fetchByConds(array('user_id' => $userId, 'is_group' => 0));
//       // }

//        if (!is_null($sl)) {
//            $cm = CatalogMapper::getInstance($sl);
//        } else {
//            $cm = false;
//        }
//
//
//
//        $ids = array();
//        foreach ($results as $result) {
//            if (!$fullDiscount) {
//                $discounts[$result->section_type][$result->section_id] = $result->discount;
//                $userDiscounts[$result->section_type][$result->section_id] = $result->discount;
//            } else {
//                $discounts[$result->section_type][$result->section_id] = $result;
//                $userDiscounts[$result->section_type][$result->section_id] = $result;
//            }
//
//            $ids[$result->section_type][] = $result->section_id;
//        }
//
//        if ($cm) {
//
//            ksort($ids);
//            $tree = $ids;
//            foreach ($ids as $key => $val) {
//                $tree = $cm->getChildTreeIds($key, $val, $tree);
//                break;
//            }
//
//            foreach ($baseDiscounts as $type => $bdIds) {
//                foreach ($bdIds as $oneBdId => $discount) {
//
//                    if (in_array($oneBdId, $tree[$type])) {
//
//                        //unset($baseDiscounts[$type][$oneBdId]);
//                    } else {
//                        $discounts[$type][$oneBdId] = $discount;
//                    }
//                }
//            }
//        }

        return $specialLvl ? $discounts[$specialLvl] : $discounts;
    }

    public function fetchAll($userId, $isGroup, $sortedByTypes = false) {
        $discounts = $this->fetchByConds(array('user_id' => $userId, 'is_group' => $isGroup));

        if ($sortedByTypes) {
            $sorted = array();
            foreach ($discounts as $discount) {
                $sorted[$discount->section_type][$discount->section_id] = $discount;
            }
            $discounts = $sorted;
        }

        return $discounts;
    }

    public function delById($id) {
        return $this->delete(array('id' => $id));
    }

    public function del($userId, $sectionId, $sectionType, $isGroup = 0)
    {
        $this->delete(array('user_id' => $userId, 'section_id' => $sectionId, 'section_type' => $sectionType, 'is_group' => $isGroup ? 1 : 0));
    }

    public function save(SampleModel $entity)
    {
        $data = $entity->toArray();
        foreach($data as $col=>$val){
            if($val === null){
                unset($data[$col]);
            }
        }

        $returnId = 0;
        if ($data['id'] && $this->find($data['id'])) {
            $returnId = $data['id'];
            $this->update($data, array('id' => $data['id']));
        } else {
            $this->insert($data);
            $returnId = $this->lastInsertValue;
        }


        return $returnId;
    }
}
