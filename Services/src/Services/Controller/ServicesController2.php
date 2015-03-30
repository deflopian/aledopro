<?php
namespace Services\Controller;

use Services\Form\ConsultRequestForm;
use Services\Form\ModernisationRequestForm;
use Services\Form\MontajRequestForm;
use Services\Form\ProjRequestForm;
use Services\Model\ConsultRequest;
use Services\Model\ModernisationRequest;
use Services\Model\MontajRequest;
use Services\Model\ProjRequest;
use Zend\Mvc\Controller\AbstractActionController;
use Services\Model\CalcRequest;
use Services\Model\CalcRequestTable;
use Services\Form\CalculationForm;
use Services\Model\ProjRequestTable;

class ServicesController extends AbstractActionController
{
    const CONSULT_ORDER_FORM = 2;
    const PROJECT_ORDER_FORM = 3;
    const CALCULATION_FORM = 5;
    const MODERNISATION_FORM = 1;
    const MONTAJ_FORM = 8;

    public function indexAction(){
        $this->getResponse()->setStatusCode(404);
        return;
    }

    public function saveFormAjaxAction()
    {
        $request = $this->getRequest();
        if ($request->isXmlHttpRequest()) {
            $sl = $this->getServiceLocator();
            $post = $request->getPost();
            $success = 0;
            $messages = array();

            $type = $post['form-type'];
            $data = $this->getFormData($type);

            $form = $data['form'];
            $form->setData($post);

            if ($form->isValid()) {
                $saveData = $form->getData();
                $entity = $data['model'];
                $entity->exchangeArray($saveData);

                $sl->get($data['table'])->save($entity);
                $success = 1;
            } else {
                $messages = $form->getMessages();
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(
                array(
                    'success' => $success,
                    'messages' => $messages,
                )
            ));
            return $response;
        }
        return $this->redirect()->toRoute('home');
    }

    private function getFormData($type)
    {
        $res = array(
            'form' => '',
            'model' => '',
            'table' => '',
        );

        switch($type){
            case self::CALCULATION_FORM:
                $res['form'] = new CalculationForm();
                $res['model'] = new CalcRequest();
                $res['table'] = 'CalcRequestTable';
                break;

            case self::PROJECT_ORDER_FORM:
                $res['form'] = new ProjRequestForm();
                $res['model'] = new ProjRequest();
                $res['table'] = 'ProjRequestTable';
                break;

            case self::CONSULT_ORDER_FORM:
                $res['form'] = new ConsultRequestForm();
                $res['model'] = new ConsultRequest();
                $res['table'] = 'ConsultRequestTable';
                break;

            case self::MODERNISATION_FORM:
                $res['form'] = new ModernisationRequestForm();
                $res['model'] = new ModernisationRequest();
                $res['table'] = 'ModernRequestTable';
                break;

            case self::MONTAJ_FORM:
                $res['form'] = new MontajRequestForm();
                $res['model'] = new MontajRequest();
                $res['table'] = 'MontajRequestTable';
                break;
        }

        return $res;
    }

    public static function getGoals()
    {
        return array(
            1 => 'Офисно-административное помещение (чистое)',
            2 => 'Офисно-административное помещение (грязное)',
            3 => 'Офисно-административное помещение (уродливое)',
        );
    }
}