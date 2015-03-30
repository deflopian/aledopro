<?php
return array(
    'controllers' => array(
        'invokables' => array(
            'Vacancies\Controller\Vacancies' => 'Vacancies\Controller\VacanciesController',
        ),
    ),

    'router' => array(
        'routes' => array(
            'vacancies' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/vacancies[/:action][/:id][/]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Vacancies\Controller\Vacancies',
                        'action'     => 'index',
                    ),
                ),
            ),
        ),
    ),

    'view_manager' => array(
        'template_path_stack' => array(
            'vacancies' => __DIR__ . '/../view',
        ),
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),

    'bjyauthorize' => array(
        'guards'                => array(
            'BjyAuthorize\Guard\Route' => array(
                array('route' => 'vacancies', 'roles' => array('guest', 'user')),
            ),
            'BjyAuthorize\Guard\Controller' => array(
                array('controller' => 'Vacancies\Controller\Vacancies', 'roles' => array('guest','user')),
            ),
        ),
    ),
);
