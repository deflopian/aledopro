<?php
/**
 * Created by PhpStorm.
 * User: Вадим
 * Date: 03.02.15
 * Time: 17:31
 */

namespace Reports\Controller;



use Application\Controller\SampleAdminController;
use Reports\Config\ReportConfig;
use Reports\Mapper\ReportMapper;

class AdminController extends SampleAdminController {
    protected $entityName = 'Reports\Model\Report';

    public function viewAction()
    {
        $sl = $this->getServiceLocator();
        $reportMapper = ReportMapper::getInstance($sl);
        $id = (int) $this->params()->fromRoute('id', 0);
        if (!$id) {
            return $this->redirect()->toRoute('zfcadmin/'.$this->url);
        }
        $report = $reportMapper->get($id);
        if (!$report) {
            return $this->redirect()->toRoute('zfcadmin/'.$this->url);
        }

        $type = ReportConfig::$infoByTypes[$report->type]["name"];

        return array(
            'entity' => $report,
            'type' => $type
        );
    }

    public function indexAction()
    {
        $sl = $this->getServiceLocator();
        $reportMapper = ReportMapper::getInstance($sl);
        $reports = $reportMapper->getList();

        return array(
            'entities' => $reports
        );
    }
} 