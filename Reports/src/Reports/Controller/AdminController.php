<?php
/**
 * Created by PhpStorm.
 * User: Вадим
 * Date: 03.02.15
 * Time: 17:31
 */

namespace Reports\Controller;



use Application\Controller\SampleAdminController;
use Application\Service\ApplicationService;
use Application\Service\MailService;
use Reports\Config\ReportConfig;
use Reports\Mapper\ReportItemMapper;
use Reports\Mapper\ReportMapper;

class AdminController extends SampleAdminController {
    protected $entityName = 'Reports\Model\Report';

    public function viewAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
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

    public function delEntityAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$this->setData();

        $request = $this->getRequest();
        if ($request->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $type = $request->getPost('page_info_type', false);
            $success = 0;

            if ($id) {
                if ($type == "report") {
                    ReportMapper::getInstance($this->getServiceLocator())->delete($id, true);
                    $success = 1;
                } elseif ($type == "report_item") {
                    ReportItemMapper::getInstance($this->getServiceLocator())->delete($id);
                    $success = 1;
                }
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/'.$this->url);
    }

    public function indexAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$sl = $this->getServiceLocator();
        $reportMapper = ReportMapper::getInstance($sl);

        $entities = array();
        $reportsNP = $reportMapper->getList(ReportMapper::REPORT_TYPE_NEW_PRODUCTS);
        if (count($reportsNP)) {
            $сonfig = ReportConfig::$infoByTypes[ReportMapper::REPORT_TYPE_NEW_PRODUCTS];
            $entities[] = array($сonfig['name'], $reportsNP);
        }
        $reportsOP = $reportMapper->getList(ReportMapper::REPORT_TYPE_ORPHAN_PRODUCTS);
        if (count($reportsOP)) {
            $сonfig = ReportConfig::$infoByTypes[ReportMapper::REPORT_TYPE_ORPHAN_PRODUCTS];
            $entities[] = array($сonfig['name'], $reportsOP);
        }
        $reportsOS = $reportMapper->getList(ReportMapper::REPORT_TYPE_ORPHAN_SERIES);
        if (count($reportsOS)) {
            $сonfig = ReportConfig::$infoByTypes[ReportMapper::REPORT_TYPE_ORPHAN_SERIES];
            $entities[] = array($сonfig['name'], $reportsOS, '/admin/reports/sendOrphanSeriesReport');
        }
        $reportsZP = $reportMapper->getList(ReportMapper::REPORT_TYPE_PRODUCT_ZERO_PRICE);
        if (count($reportsZP)) {
            $сonfig = ReportConfig::$infoByTypes[ReportMapper::REPORT_TYPE_PRODUCT_ZERO_PRICE];
            $entities[] = array($сonfig['name'], $reportsZP);
        }
        $reportsWP = $reportMapper->getList(ReportMapper::REPORT_TYPE_WITHOUT_PREVIEW);
        if (count($reportsWP)) {
            $сonfig = ReportConfig::$infoByTypes[ReportMapper::REPORT_TYPE_WITHOUT_PREVIEW];
            $entities[] = array($сonfig['name'], $reportsWP);
        }
        $reportsSM = $reportMapper->getList(ReportMapper::REPORT_TYPE_SEND_MAIL);
        if (count($reportsSM)) {
            $сonfig = ReportConfig::$infoByTypes[ReportMapper::REPORT_TYPE_SEND_MAIL];
            $entities[] = array($сonfig['name'], $reportsSM);
        }


        return array(
            'entities' => $entities
        );
    }
	
	public function sendOrphanSeriesReportAction()
	{
		if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$this->setData();
		
		$request = $this->getRequest();
		$success = 0;
		
        $sl = $this->getServiceLocator();
		$reportMapper = ReportMapper::getInstance($sl);
			
		$orphanSeriesReport = $reportMapper->getLast(ReportMapper::REPORT_TYPE_ORPHAN_SERIES);
		
		if (isset($orphanSeriesReport->items) && count($orphanSeriesReport->items) > 0) {
            list($email, $mailView) = MailService::prepareReportData($sl, $orphanSeriesReport);
            MailService::sendMail($email, $mailView, "Отчёт по добавленным сериям номер " . $orphanSeriesReport->id);
			
			$success = 1;
        }
		
		if ($request->isXmlHttpRequest()) {
			$response = $this->getResponse();
			$response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
		
			return $response;
		}
		else return $this->redirect()->toRoute('zfcadmin/'.$this->url);
	}
} 