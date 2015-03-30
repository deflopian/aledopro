<?php
return array(
    'controllers' => array(
        'invokables' => array(
            'User\Controller\User' => 'User\Controller\UserController'
        ),
    ),

    'router' => array(
        'routes' => array(
            'cabinet' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/cabinet[/:action][/:id][/]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id' => '[0-9]*',
                    ),
                    'defaults' => array(
                        'controller' => 'User\Controller\User',
                        'action'     => 'index',
                    ),
                ),
            ),
        ),
    ),

    'view_manager' => array(
        'template_path_stack' => array(
            'user' => __DIR__ . '/../view',
        ),
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),

    'bjyauthorize' => array(
        'guards'                => array(
            'BjyAuthorize\Guard\Route' => array(
                array('route' => 'cabinet', 'roles' => array('guest','user')),
            ),
            'BjyAuthorize\Guard\Controller' => array(
                array('controller' => 'User\Controller\User', 'action'=>'index', 'roles' => array('user')),
                array('controller' => 'User\Controller\User', 'action'=>'editorder', 'roles' => array('user')),
                array('controller' => 'User\Controller\User', 'action'=>'godModeOn', 'roles' => array('manager', 'admin')),
                array('controller' => 'User\Controller\User', 'action'=>'godModeOff', 'roles' => array('manager', 'admin')),
                array('controller' => 'User\Controller\User', 'action'=>'saveOrder', 'roles' => array('user')),
                array('controller' => 'User\Controller\User', 'action'=>'register', 'roles' => array('guest')),
                array('controller' => 'User\Controller\User', 'action'=>'login', 'roles' => array('guest', 'user')), //user - чтобы потом можно быть аяксово отсечь повторюшек
                array('controller' => 'User\Controller\User', 'action'=>'fakelogin', 'roles' => array('guest', 'user')), //user - чтобы потом можно быть аяксово отсечь повторюшек
                array('controller' => 'User\Controller\User', 'action'=>'changepassword', 'roles' => array('user')),
                array('controller' => 'User\Controller\User', 'action'=>'updateRegisterInfo', 'roles' => array('user')),
                array('controller' => 'User\Controller\User', 'action'=>'forgot', 'roles' => array('guest')),
                array('controller' => 'User\Controller\User', 'action'=>'rememberpassword', 'roles' => array('guest')),
            ),
        ),
    ),
);

