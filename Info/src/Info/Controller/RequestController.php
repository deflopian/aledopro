<?php
namespace Info\Controller;

use Services\Controller\ServicesController;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class RequestController extends AbstractActionController {

    // Это страничка, где будут выводиться ссылки на странички всех запросов
    public function indexAction() { }

    public function reqPartnerAction(){
        $reqs = $this->getServiceLocator()->get('PartnerRequestTable')->fetchAll();
        return array(
            'reqs' => $reqs
        );
    }
    public function reqPartnerViewAction(){
        $id = (int) $this->params()->fromRoute('id', 0);
        $req = $this->getServiceLocator()->get('PartnerRequestTable')->find($id);
        if ($req === false) {
            return $this->redirect()->toRoute('info');
        }
        return array(
            'req' => $req
        );
    }

    public function newpasswordAction() {
        return new ViewModel();
    }

    public function reqConsultAction()
    {
        $reqs = $this->getServiceLocator()->get('ConsultRequestTable')->fetchAll();
        return array(
            'reqs' => $reqs
        );
    }
    public function reqConsultViewAction(){
        $id = (int) $this->params()->fromRoute('id', 0);
        $req = $this->getServiceLocator()->get('ConsultRequestTable')->find($id);
        if ($req === false) {
            return $this->redirect()->toRoute('info');
        }
        return array(
            'req' => $req
        );
    }

    public function reqProjectAction()
    {
        $reqs = $this->getServiceLocator()->get('ProjRequestTable')->fetchAll();
        return array(
            'reqs' => $reqs
        );
    }
    public function reqProjectViewAction(){
        $id = (int) $this->params()->fromRoute('id', 0);
        $req = $this->getServiceLocator()->get('ProjRequestTable')->find($id);
        if ($req === false) {
            return $this->redirect()->toRoute('info');
        }
        return array(
            'req' => $req
        );
    }

    public function reqCalcAction()
    {
        $reqs = $this->getServiceLocator()->get('CalcRequestTable')->fetchAll();
        return array(
            'reqs' => $reqs
        );
    }
    public function reqCalcViewAction(){
        $id = (int) $this->params()->fromRoute('id', 0);
        $req = $this->getServiceLocator()->get('CalcRequestTable')->find($id);
        if ($req === false) {
            return $this->redirect()->toRoute('info');
        }
        $goals = ServicesController::getGoals();
        return array(
            'req' => $req,
            'goals' => $goals
        );
    }

    public function reqModernAction()
    {
        $reqs = $this->getServiceLocator()->get('ModernRequestTable')->fetchAll();
        return array(
            'reqs' => $reqs
        );
    }
    public function reqModernViewAction(){
        $id = (int) $this->params()->fromRoute('id', 0);
        $req = $this->getServiceLocator()->get('ModernRequestTable')->find($id);
        if ($req === false) {
            return $this->redirect()->toRoute('info');
        }
        return array(
            'req' => $req
        );
    }

    public function reqMontajAction()
    {
        $reqs = $this->getServiceLocator()->get('MontajRequestTable')->fetchAll();
        return array(
            'reqs' => $reqs
        );
    }
    public function reqMontajViewAction(){
        $id = (int) $this->params()->fromRoute('id', 0);
        $req = $this->getServiceLocator()->get('MontajRequestTable')->find($id);
        if ($req === false) {
            return $this->redirect()->toRoute('info');
        }
        return array(
            'req' => $req
        );
    }
}