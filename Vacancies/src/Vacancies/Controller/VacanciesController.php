<?php
namespace Vacancies\Controller;

use Application\Service\MailService;
use Vacancies\Form\VacancyForm;
use Vacancies\Model\Vacancy;
use Vacancies\Model\VacancyRequest;
use Vacancies\Model\VacancyRequestTable;
use Zend\Mvc\Controller\AbstractActionController;
use Vacancies\Model\VacancyTable;

class VacanciesController extends AbstractActionController
{
    private $vacancyTable;
    
    public function indexAction()
    {
        $vacancies = $this->getVacancyTable()->fetchAll('order ASC');
        $form = new VacancyForm('vacancy');

        $formVacancies = array();
        foreach($vacancies as $vac){
            $formVacancies[$vac->id] = $vac->title;
        }
        $form->addVacancyElement($formVacancies);
        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( \Info\Service\SeoService::VACANCIES, 1 );

        $this->layout()->noBottomLine = true;
        $this->layout()->seoData = $seoData;
        $this->layout()->pageTitle = 'Вакансии';

        return array(
            'seoData' => $seoData,
            'vacancies' => $vacancies,
            'form' => $form
        );
    }


    public function saveFormAjaxAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $post = array_merge_recursive(
                $request->getPost()->toArray(),
                $request->getFiles()->toArray()
            );

            if (!$post) {
                $rest_json = file_get_contents("php://input");
                $post = json_decode($rest_json, true);
            }

            $success = 0;
            $messages = array();

            $form = new VacancyForm('vacancy');
            $form->setData($post);

            if ($form->isValid()) {
                $filename = $post['file']['name'];
                $adapter = new \Zend\File\Transfer\Adapter\Http();
                $adapter->setDestination($_SERVER['DOCUMENT_ROOT'] . '/images/vacancies_request');

                if($adapter->receive($filename)){
                    $data = $form->getData();
                    $data['file'] = $adapter->getFileName(null, false);
                    $vacancy = $this->getServiceLocator()->get('VacanciesTable')->find($data['vacancy']);
                    if (!$vacancy) {
                        $vacancy = new Vacancy();
                    }
                    //соискатель сам описал желаемую должность
                    if (!$data['vacancy'] && is_string($data['custom_vacancy']) && !empty($data['custom_vacancy'])) {
                        $vacancy->title = $data['custom_vacancy'];
                    }


                    $entity = new VacancyRequest();
                    $entity->exchangeArray($data);

                    $this->getServiceLocator()->get('VacancyRequestTable')->save($entity);
                    $entityId = $this->getServiceLocator()->get('VacancyRequestTable')->adapter->getDriver()->getLastGeneratedValue();
                    $success = 1;

                    //сообщаем менеджеру о новом ответе на вакансию
                    list($email, $mailView) = MailService::prepareVacancyMailData($this->serviceLocator, $entityId, $entity, $vacancy);
                    MailService::sendMail($email, $mailView, "Новое резюме номер " . $entityId . " на Aledo!");
//                        MailService::sendMail("deflopian@gmail.com", $mailView, "Новое резюме номер " . $entityId . " на Aledo!");
                }

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
        return $this->redirect()->toRoute('vacancies');
    }

    /**
     * @return VacancyTable array|object
     */
    public function getVacancyTable()
    {
        if (!$this->vacancyTable) {
            $sm = $this->getServiceLocator();
            $this->vacancyTable = $sm->get('VacanciesTable');
        }
        return $this->vacancyTable;
    }
}