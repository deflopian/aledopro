<?php
namespace Services\Form;

use Application\Service\ApplicationService;
use Services\Controller\ServicesController;
use Zend\InputFilter;
use Zend\Form\Form;
use Zend\Form\Element;
use Zend\Validator;

class ModernisationRequestForm extends Form
{
    public function __construct($name = null)
    {
        parent::__construct($name);
        $this->addElements();
        $this->setInputFilter($this->createInputFilter());
    }

    public function addElements()
    {
        $textFields = $this->getTextFields();

        foreach($textFields as $name=>$required){
            $text = new Element\Text($name);
            $this->add($text);
        }

        $file = new Element\File('file');
        $this->add($file);
    }

    public function createInputFilter()
    {
        $inputFilter = new InputFilter\InputFilter();

        $messages = ApplicationService::getValidationFormMessages();
        $textFields = $this->getTextFields();
        $fileFields = $this->getFileFields();

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
                            'target' => $_SERVER['DOCUMENT_ROOT'] . '/uploads/services/',
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
            'name' => true,
            'mail' => true,
            'phone' => true,

            'city' => false,
            'comment' => false
        );
    }

    private function getFileFields()
    {
        return array(
            'file'=>false,
        );
    }
}