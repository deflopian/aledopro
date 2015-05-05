<?php
namespace Cart\Form;

use Application\Service\ApplicationService;
use Cart\Controller\CartController;
use Zend\InputFilter;
use Zend\Form\Form;
use Zend\Form\Element;
use Zend\Validator;

class OrderForm extends Form
{
    private $registred;

    public function __construct($name = null, $isuser = false)
    {
        $this->registred = $isuser;

        parent::__construct($name);
        $this->addElements();
        $this->setInputFilter($this->createInputFilter());

    }

    public function addElements()
    {
        $textFields = $this->getTextFields();

        foreach($textFields as $name=>$require){
            $text = new Element\Text($name);
            $this->add($text);
        }

        if (!$this->registred) {
            $textUserFields = $this->getUserTextFields();
            foreach($textUserFields as $name=>$require){
                $text = new Element\Text($name);
                $this->add($text);
            }
        }

        $file = new Element\File('file');
        $this->add($file);
    }

    public function createInputFilter()
    {
        $inputFilter = new InputFilter\InputFilter();

        $messages = ApplicationService::getValidationFormMessages();
        $textFields = $this->getTextFields();
        $userFields = $this->getUserTextFields();
        $fileFields = $this->getFileFields();

        if (!$this->registred) {
            foreach($userFields as $name=>$required){
                $inputFilter->add(array(
                    'name' =>  $name,
                    'required' => $required,
                    'validators' => $messages['text'],
                    'filters' => array(
                        array('name' => 'stringtrim'),
                        array('name' => 'striptags')
                    )
                ));
            }
        }

        foreach($textFields as $name=>$required){
            $inputFilter->add(array(
                'name' => $name,
                'required' => $required,
                'validators' => $messages['text'],
                'filters' => array(
                    array('name' => 'stringtrim'),
                    array('name' => 'striptags')
                )
            ));
        }

        foreach($fileFields as $name=>$required){
            $inputFilter->add(array(
                'name' => $name,
                'required' => $required,
                'validators' => $messages['file'],
                'filters' => array(
                    array(
                        'name' => 'filerenameupload',
                        'options' => array(
                            'target' => $_SERVER['DOCUMENT_ROOT'] . CartController::UPLOAD_PATH,
                            'use_upload_name'      => true,
                            'use_upload_extension' => true,
                            'overwrite'            => false,
                            'randomize'            => true,
                        )
                    ),
                )
            ));
        }

        return $inputFilter;
    }


    private function getTextFields()
    {
        return array(
            'order_comment'=>false
        );
    }

    private function getUserTextFields()
    {
        return array(
            'user_name'=>true,
            'user_email'=>true,
            'user_tel'=>true,
            'user_city'=>false,
        );
    }

    private function getFileFields()
    {
        return array(
            'order_file'=>false,
        );
    }
}