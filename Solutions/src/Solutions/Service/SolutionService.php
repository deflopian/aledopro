<?php
namespace Solutions\Service;

use Zend\View\Model\ViewModel;

class SolutionService
{
    public static function renderPopupNav($serviceLocator, $prevEntity, $nextEntity, $folder, $robot = false)
    {
        $htmlViewPart = new ViewModel();
        $htmlViewPart->setTerminal(true)
            ->setTemplate('solutions/solutions/part/part/popup-nav')
            ->setVariables(array(
                'prevEntity' => $prevEntity,
                'nextEntity' => $nextEntity,
                'folder' => $folder,
                'robot' => $robot,
            ));

        return $serviceLocator->get('viewrenderer')->render($htmlViewPart);
    }
}