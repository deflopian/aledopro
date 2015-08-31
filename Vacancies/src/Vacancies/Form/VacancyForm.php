<?php
namespace Vacancies\Form;

use Application\Service\ApplicationService;
use Zend\InputFilter;
use Zend\Validator;
use Zend\Form\Form;
use Zend\Form\Element;

class VacancyForm extends Form
{
    public function __construct($name = null)
    {
        parent::__construct($name);
        $this->addElements();
        $this->setInputFilter($this->createInputFilter());
    }

    public function addElements()
    {
        $file = new Element\File('file');
        $file
            ->setLabel('Резюме')
            ->setAttributes(array(
                'id' => 'file',
            ));
        $this->add($file);

        $textFields = array_keys($this->getTextFields());

        foreach ($textFields as $name) {
            $text = new Element\Text($name);
            $this->add($text);
        }
    }

    public function addVacancyElement($formVacancies)
    {
        $this->add(array(
            'type' => 'Zend\Form\Element\Select',
            'name' => 'vacancy',
            'options' => array(
                'label' => 'Вакансия',
                'value_options' => $formVacancies,
            ),
        ));
    }


    public function createInputFilter()
    {
        $inputFilter = new InputFilter\InputFilter();

        $file = new InputFilter\FileInput('file');
        $file->setRequired(true);
        $file->getValidatorChain()
            ->attach(new Validator\File\UploadFile())
            ->attach(new Validator\File\Extension(array('doc', 'txt', 'pdf')))
            ->attachByName('filesize', array('max' => 2000000));
        $inputFilter->add($file);

        $textFields = $this->getTextFields();
        foreach ($textFields as $name=>$required) {
            $inputFilter->add(array(
                'name' => $name,
                'required' => $required,
                'validators' => ($name == 'mail') ? array(array('name' => 'EmailAddress')) : array(),
                'filters' => array(
                    array('name' => 'stringtrim'),
                    array('name' => 'striptags')
                )
            ));
        }

        return $inputFilter;
    }

    private function getTextFields()
    {
        return array(
            'name' => true,
			'phone' => false,
			'mail' => true,
			'vacancy' => false,
			'custom_vacancy' => false,
			'letter' => false
        );
    }
}