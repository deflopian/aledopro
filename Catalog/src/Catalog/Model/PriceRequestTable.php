<?php
namespace Catalog\Model;

use Application\Model\SampleTable;
use Application\Service\ApplicationService;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;

class PriceRequestTable extends SampleTable
{
    protected $table = 'price_request';

    public function __construct(Adapter $adapter)
    {
        if (ApplicationService::isDomainZone('by')) {
            $this->table = 'by_price_request';
        }
		
		$this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new PriceRequest());
        $this->initialize();
    }

    public function fetchAllSorted() {
        $requests = $this->fetchAll();

        $sorted = array();
        foreach ($requests as $request) {
            $sorted[$request->section_type][$request->section_id] = $request;
        }

        return $sorted;
    }

    public function delById($id) {
        return $this->delete(array('id' => $id));
    }

    public function save(PriceRequest $entity)
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
