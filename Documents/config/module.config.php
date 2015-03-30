<?php
return array(
    'controllers' => array(
        'invokables' => array(
            'DocumentsController' => 'Documents\Controller\DocumentController',
        ),
    ),

    'router' => array(
        'routes' => array(
            'documents' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/documents[/:action][/:id][/]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'DocumentsController',
                        'action'     => 'index',
                    ),
                ),
            ),
        ),
    ),

    'view_manager' => array(
        'template_path_stack' => array(
            'documents' => __DIR__ . '/../view',
        ),
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),

    'bjyauthorize' => array(
        'guards'                => array(
            'BjyAuthorize\Guard\Route' => array(
                array('route' => 'documents', 'roles' => array('guest', 'user')),
            ),
            'BjyAuthorize\Guard\Controller' => array(
                array(
                    'controller' => 'DocumentsController',
                    'roles' => array('guest','user')
                ),
            ),
        ),
    ),
);
