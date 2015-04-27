<?php
namespace Services\Controller;

use Application\Service\MailService;
use Services\Form\ConsultRequestForm;
use Services\Form\ModernisationRequestForm;
use Services\Form\MontajRequestForm;
use Services\Form\ProjRequestForm;
use Services\Model\ConsultRequest;
use Services\Model\ModernisationRequest;
use Services\Model\MontajRequest;
use Services\Model\ProjRequest;
use User\Service\UserService;
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
    const UPLOAD_PATH = '/uploads/services/';

    private $serviceName = UserService::USER_ACTION_SENT_SERVICE_REQUEST;
    private $servicePath = "";

    public function indexAction(){
        $this->getResponse()->setStatusCode(404);
        return;
    }

    public function saveFormAjaxAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $sl = $this->getServiceLocator();

            $post = array_merge_recursive(
                $request->getPost()->toArray(),
                $request->getFiles()->toArray()
            );

            if (!$post) {
                $rest_json = file_get_contents("php://input");
                $post = json_decode($rest_json, true);
            }

            var_dump($post);
            return $post;
            $success = 0;
            $messages = array();

            $type = $post['form-type'];
            $data = $this->getFormData($type);

            $form = $data['form'];

            $form->setData($post);

            if ($form->isValid()) {
                $saveData = $form->getData();
                $entity = $data['model'];

                if ($this->zfcUserAuthentication()->hasIdentity()) {
                    $user = $this->zfcUserAuthentication()->getIdentity();
                    if ($user->getUsername()) {
                        $saveData['name'] = $user->getUsername();
                    }
                    if ($user->getPhone()) {
                        $saveData['phone'] = $user->getPhone();
                    }
                    if ($user->getEmail()) {
                        $saveData['email'] = $user->getEmail();
                    }
                }

                $entity->exchangeArray($saveData);

                $filePath = null;
                if (!empty($saveData['file'])) {
                    $filePath = substr($saveData['file']['tmp_name'], strlen($_SERVER['DOCUMENT_ROOT'] . self::UPLOAD_PATH));
                    $entity->file = $filePath;
                    $filePath = self::UPLOAD_PATH . $filePath;
                }



                $entityId = $sl->get($data['table'])->save($entity);

                //ваша заявка принята, вы молодец
                list($email, $mailView) = MailService::prepareUserMailData($this->serviceLocator, $saveData, $entityId, $type);
                MailService::sendMail($email, $mailView, "Детали вашего заказа");

                //сообщаем менеджеру о новой заявке
                list($email, $mailView) = MailService::prepareManagerMailData($this->serviceLocator, $saveData, $entityId, $type, $filePath);
                MailService::sendMail($email, $mailView, "Новый заказ номер " . $entityId);

                if ($this->zfcUserAuthentication()->hasIdentity()) {
                    $user = $this->zfcUserAuthentication()->getIdentity();
                    $time = time();
                    if ($user->getIsPartner()) {
                        UserService::addHistoryAction(
                            $this->getServiceLocator(),
                            $user->getId(),
                            UserService::USER_ACTION_SENT_SERVICE_REQUEST,
                            "admin/requests/",
                            $time
                        );
                        UserService::addHistoryAction(
                            $this->getServiceLocator(),
                            $user->getId(),
                            $this->serviceName,
                            "admin/requests/" . $this->servicePath . $entityId,
                            $time
                        );
                    }
                }
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
                $this->serviceName = UserService::USER_ACTION_SENT_SERVICE_REQUEST_CALCULATION;
                $this->servicePath = "reqCalc/";
                break;

            case self::PROJECT_ORDER_FORM:
                $res['form'] = new ProjRequestForm();
                $res['model'] = new ProjRequest();
                $res['table'] = 'ProjRequestTable';
                $this->serviceName = UserService::USER_ACTION_SENT_SERVICE_REQUEST_PROJECT;
                $this->servicePath = "reqProject/";
                break;

            case self::CONSULT_ORDER_FORM:
                $res['form'] = new ConsultRequestForm();
                $res['model'] = new ConsultRequest();
                $res['table'] = 'ConsultRequestTable';
                $this->serviceName = UserService::USER_ACTION_SENT_SERVICE_REQUEST_CONSULT;
                $this->servicePath = "reqConsult/";
                break;

            case self::MODERNISATION_FORM:
                $res['form'] = new ModernisationRequestForm();
                $res['model'] = new ModernisationRequest();
                $res['table'] = 'ModernRequestTable';
                $this->serviceName = UserService::USER_ACTION_SENT_SERVICE_REQUEST_MODERNISATION;
                $this->servicePath = "reqModern/";
                break;

            case self::MONTAJ_FORM:
                $res['form'] = new MontajRequestForm();
                $res['model'] = new MontajRequest();
                $res['table'] = 'MontajRequestTable';
                $this->serviceName = UserService::USER_ACTION_SENT_SERVICE_REQUEST_MONTAGE;
                $this->servicePath = "reqMontaj/";

                break;
        }

        return $res;
    }

    public static function getGoals()
    {
        return array(
            1 => 'Офисно-административное помещение (чистое)',
            2 => 'Офисно-административное помещение (грязное)',
        );
    }
}