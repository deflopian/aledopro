<?php
/**
 * Created by PhpStorm.
 * User: Вадим
 * Date: 03.02.15
 * Time: 17:05
 */
return array(
    'controllers' => array(
        'invokables' => array(
            'ReportController' => 'Reports\Controller\ReportController',
        ),
    ),
    'router' => array(
        'routes' => array(
            'reports' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/reports[/:action][/:id][/]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'ReportController',
                        'action'     => 'index',
                    ),
                ),
            ),
        ),
    ),
    'view_manager' => array(
        'template_path_stack' => array(
            'reports' => __DIR__ . '/../view',
        ),
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),
);