<?php
namespace Catalog\Controller;

use Catalog\Model\FilterParam;
use Catalog\Model\ParamToSeries;
use Catalog\Model\Series;
use Catalog\Model\SeriesParams;
use News\Model\News;
use Catalog\Controller\AdminController;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\Controller\AbstractActionController;
use Catalog\Service\CatalogService;
use Catalog\Service\CronService;
use PHPExcel_Reader_CSV;
use Zend\View\Helper\ViewModel;
use Zend\XmlRpc\Value\String;

class CronController extends BaseController
{

    protected $csvName = 'kgoods.csv';
    protected $prevModifiedPath = 'prevmodif.txt';
    protected $lineCSVFilePath = 'line.txt';
    protected $entityImgName = 'Catalog\Model\SeriesImg';
    protected $url = 'series';

    private static $tables = array(
        AdminController::SERIES_TABLE => 'SeriesTable',
        AdminController::PRODUCT_TABLE => 'ProductTable',
        AdminController::FILTER_BY_SERIES_TABLE => 'SeriesParamsTable',
        AdminController::FILTER_PARAM_TABLE => 'FilterParamTable',
        AdminController::PARAM_TO_SERIES_TABLE => 'ParamToSeriesTable',
    );

    public function setEventManager(EventManagerInterface $events)
    {
        parent::setEventManager($events);

        $controller = $this;
        $events->attach('dispatch', function ($e) use ($controller) {
            $controller->layout('layout/empty');
        }, 100); // execute before executing action logic
    }


    public function getfileAction() {
        $token = $this->params()->fromQuery('token', null);
        if (is_null($token) || $token != 'fae6e2bf570d0443c8d51cd7b30d49fe') {
            $this->getResponse()->setStatusCode(404);
            return new ViewModel();
        }
        $url = 'http://www.planar.spb.ru/ekdb/kgoods.csv';
        $newPathFile =  $_SERVER['DOCUMENT_ROOT'] . '/' . $this->csvName;
        $prevModifiedPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $this->prevModifiedPath;
        $linePath = $_SERVER['DOCUMENT_ROOT'] . '/' . $this->lineCSVFilePath;


        @ $headers = get_headers($url, 1);
        if ($headers === false) {
            echo 'Не удалось достучаться до адреса <a href="' . $url . '">' . $url . '</a>. Хост недоступен. Возможно, следует проверить настройки прокси-сервера?';
            return array();
        }
        $last_modified = strtotime($headers['Last-Modified']);

        if (file_exists($prevModifiedPath)) {
            $prev_modified = file_get_contents( $prevModifiedPath );
        } else {
            $prev_modified = false;
        }

        if (($prev_modified &&
                $prev_modified < $last_modified) ||
            !$prev_modified) {

            file_put_contents($prevModifiedPath, $last_modified);


            $fileContent = file_get_contents($url);

            if ($fileContent !== false) {
                echo 'Файл успешно загружен';
            } else {
                echo 'Ошибка при загрузке файла с <a href="http://www.planar.spb.ru/ekdb/kgoods.csv">http://www.planar.spb.ru/ekdb/kgoods.csv</a>';
            }
            $result = file_put_contents($newPathFile, $fileContent);
            if ($result !== false) {
                echo 'Файл успешно сохранён на диск в директорию ' . $newPathFile;
            } else {
                echo 'Неизвестная ошибка при сохранении файла в директорию ' . $newPathFile;
            }

            if (file_exists($linePath)) {
                unlink( $linePath );
            }
        }
        return array();

    }

    public function sortAllProductsAction() {
        $token = $this->params()->fromQuery('token', null);
        if (is_null($token) || $token != 'fae6e2bf570d0443c8d51cd7b30d49fe') {
            $this->getResponse()->setStatusCode(404);
            return new ViewModel();
        }

        $seriesTable = $this->getServiceLocator()->get('Catalog\Model\SeriesTable');
        $allSeries = $seriesTable->fetchAll();
        foreach ($allSeries as $oneser) {
            CatalogService::makesort('free_balance', $oneser->id, 2, $this->getServiceLocator());
        }
        return false;
    }

    public function checksessionAction()
    {
        //var_dump(file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/' . $this->lineCSVFilePath));
    }


    /**
     * Разбирает csv-каталог на отдельные продукты, для каждого продукта определяет айди серии,
     * мин-макс параметры внутри каждой серии и возможные значения для каждого параметра
     * подготавливает основу для возможности фильтрации результатов
     *
     * по задумке, вызывается кроном каждые 15 минут
     *
     * @return array
     */
    public function parsexlsAction()
    {
        $token = $this->params()->fromQuery('token', null);
        if (is_null($token) || $token != 'fae6e2bf570d0443c8d51cd7b30d49fe') {
            $this->getResponse()->setStatusCode(404);
            return new ViewModel();
        }


        $insertedRows = 0;
        $insertedParams = 0;
        $insertedParamValues = 0;
        $insertedSeries = 0;
        $insertedSeriesMinMax = 0;

        $calculatedRows = 0;
        $time = microtime(true);

        $scvPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $this->csvName;
        $linePath = $_SERVER['DOCUMENT_ROOT'] . '/' . $this->lineCSVFilePath;
        if (!file_exists($scvPath)) {
            return array();
        }


        $productSeries = array();
        $products = CronService::parseXLStoDatabase($scvPath, $linePath, $calculatedRows);

        $filterParamTable = $this->getServiceLocator()->get('Catalog\Model\\' . self::$tables[AdminController::FILTER_PARAM_TABLE]);
        $paramToSeriesTable = $this->getServiceLocator()->get('Catalog\Model\\' . self::$tables[AdminController::PARAM_TO_SERIES_TABLE]);
        $filterParamsArray = BaseController::$discreteFilterParams;
        $allDiapasonParams = BaseController::$diapasonFilterParams;
        //значения с возможными вариантами
        //список возможных значений параметров из $filterParamsArray и максимальный на данный момент айдишник
        list($filterParams, $maxId) = CronService::getParamsVariation($filterParamsArray, $filterParamTable);
        list($allParamsToAllSeries, $maxParamsId) = CronService::getAllParamsToAllSeries($paramToSeriesTable);
        $filterParamsForSave = array();
        $paramToSeriesForSave = array();
        /** @var \Catalog\Model\Product[] $products */
        foreach ($products as $product) {
            $serName = $product->seriesName;



            //диапазоны значений по сериям
            if (!empty($serName)) {
                if (!empty($serName) && (is_string($serName) || is_numeric($serName))) {
                    if (array_key_exists($serName, $productSeries)) {
                        $product->series_id = $productSeries[$serName]['series_id'];

                        foreach ($allDiapasonParams as $diapasonParamName) {
                            $productSeries[$serName][$diapasonParamName]['min'] = min($productSeries[$serName][$diapasonParamName]['min'], $product->$diapasonParamName);
                            $productSeries[$serName][$diapasonParamName]['max'] = max($productSeries[$serName][$diapasonParamName]['max'], $product->$diapasonParamName);
                        }
                    } else {
                        $seriesTable = $this->getServiceLocator()->get('Catalog\Model\\' . self::$tables[AdminController::SERIES_TABLE]);

                        $series = $seriesTable->fetchByCond('title', $serName);
                        if ($series && is_array($series) && isset($series[0])) {
                            $product->series_id = $series[0]->id;

                            $productSeries[$serName]['series_id'] = $series[0]->id;
                            foreach ($allDiapasonParams as $diapasonParamName) {
                                $productSeries[$serName][$diapasonParamName]['min'] = $product->$diapasonParamName;
                                $productSeries[$serName][$diapasonParamName]['max'] = $product->$diapasonParamName;
                            }
                        } else {
                            $seriesEntity = new Series();
                            $seriesEntity->title = $serName;
                            $seriesEntity->visible_title = $serName;
                            $insertedSeries++;
                            $seriesTable->save($seriesEntity);
                            $lastId = $seriesTable->adapter->getDriver()->getLastGeneratedValue();
                            $product->series_id = $lastId;
                            $productSeries[$serName]['series_id'] = $lastId;
                            foreach ($allDiapasonParams as $diapasonParamName) {
                                $productSeries[$serName][$diapasonParamName]['min'] = $product->$diapasonParamName;
                                $productSeries[$serName][$diapasonParamName]['max'] = $product->$diapasonParamName;
                            }
                        }
                    }

                    /** @var \Catalog\Model\SeriesParamsTable $filterTable */
                    $filterTable = $this->getServiceLocator()->get('Catalog\Model\\' . self::$tables[AdminController::FILTER_BY_SERIES_TABLE]);
                    /** @var \Catalog\Model\SeriesParams $series */
                    $seriesArr = $filterTable->fetchByCond('series_id', $product->series_id);


                    if (isset($seriesArr[0])) {
                        $series = $seriesArr[0];

                        $changedFlag = false;

                        // тупанул с именами полей в таблице, цену придётся проверять отдельно
                        // I'm some kind of stupid with table fields, so we need to check price particularly

                        if ($productSeries[$serName]['wholesale_price']['min'] < $series->min_price) {
                            $changedFlag = true;
                            $series->min_price = $productSeries[$serName]['wholesale_price']['min'];
                        }
                        if ($productSeries[$serName]['wholesale_price']['max'] > $series->max_price) {
                            $changedFlag = true;
                            $series->max_price = $productSeries[$serName]['wholesale_price']['max'];
                        }

                        // остальное хуячим магией
                        // make some php-magic

                        foreach ($allDiapasonParams as $diapasonParamName) {
                            if ($diapasonParamName == $allDiapasonParams[0]) continue;
                            $minValueName = 'min_' . $diapasonParamName;
                            $maxValueName = 'max_' . $diapasonParamName;
                            if ($productSeries[$serName][$diapasonParamName]['min'] < $series->$minValueName) {
                                $changedFlag = true;
                                $series->$minValueName = $productSeries[$serName][$diapasonParamName]['min'];
                            }
                            if ($productSeries[$serName][$diapasonParamName]['max'] > $series->$maxValueName) {
                                $changedFlag = true;
                                $series->$maxValueName = $productSeries[$serName][$diapasonParamName]['max'];
                            }
                        }


                        if ($changedFlag) {
                            $insertedSeriesMinMax++;
                            $filterTable->save($series);
                        }
                    } else {
                        $seriesEntity = new SeriesParams();
                        $seriesEntity->series_id = $product->series_id;

                        $seriesEntity->min_price = $productSeries[$serName]['wholesale_price']['min'];
                        $seriesEntity->max_price = $productSeries[$serName]['wholesale_price']['max'];

                        // magic!
                        foreach ($allDiapasonParams as $diapasonParamName) {
                            if ($diapasonParamName == $allDiapasonParams[0]) continue;
                            $minValueName = 'min_' . $diapasonParamName;
                            $maxValueName = 'max_' . $diapasonParamName;

                            $seriesEntity->$minValueName = $productSeries[$serName][$diapasonParamName]['min'];
                            $seriesEntity->$maxValueName = $productSeries[$serName][$diapasonParamName]['max'];
                        }
                        $insertedSeriesMinMax++;
                        $filterTable->save($seriesEntity);
                    }

                }
            }

            foreach ($filterParamsArray as $param) {
                //var_dump($filterParams[$param]);
                //echo '<br>';
                if (!empty($product->$param)) {
                    if (!array_key_exists($product->$param, $filterParams[$param])) {

                        $newVariant = new FilterParam();
                        $newVariant->id = ++$maxId;
                        $newVariant->field = $param;
                        $newVariant->value = $product->$param;
                        $filterParams[$param][$product->$param] = $newVariant;
                        $product->$param = $newVariant->id;
                        $filterParamsForSave[] = $newVariant;

                        $newParamToSeries = new ParamToSeries();
                        $newParamToSeries->id = ++$maxParamsId;
                        $newParamToSeries->param_id = $newVariant->id;
                        $newParamToSeries->series_id = $product->series_id;
                        $allParamsToAllSeries[$product->series_id][] = $newParamToSeries->param_id;
                        $filterParamsForSave[] = $newParamToSeries;
                    } else {
                        if (!is_null( $filterParams[$param][$product->$param])) {
                            if (array_key_exists($product->series_id, $allParamsToAllSeries)) {
                                if (!in_array($filterParams[$param][$product->$param]->id, $allParamsToAllSeries[$product->series_id])) {
                                    $newParamToSeries = new ParamToSeries();
                                    $newParamToSeries->id = ++$maxParamsId;
                                    $newParamToSeries->param_id = $filterParams[$param][$product->$param]->id;
                                    $newParamToSeries->series_id = $product->series_id;
                                    $allParamsToAllSeries[$product->series_id][] = $newParamToSeries->param_id;
                                    $paramToSeriesForSave[] = $newParamToSeries;
                                }
                            } else {
                                $newParamToSeries = new ParamToSeries();
                                $newParamToSeries->id = ++$maxParamsId;
                                $newParamToSeries->param_id = $filterParams[$param][$product->$param]->id;
                                $newParamToSeries->series_id = $product->series_id;
                                $allParamsToAllSeries[$product->series_id][] = $newParamToSeries->param_id;
                                $paramToSeriesForSave[] = $newParamToSeries;
                            }
                        }
                        $product->$param = $filterParams[$param][$product->$param]->id;
                    }
                }
            }
        }

        foreach ($products as $key => $oneProd) {
            $products[$key] = $oneProd->toArray();
        }
        foreach ($filterParamsForSave as $key => $paramEntity) {
            $filterParamsForSave[$key] = $paramEntity->toArray();
        }
        foreach ($paramToSeriesForSave as $key => $paramEntity) {
            $paramToSeriesForSave[$key] = $paramEntity->toArray();
        }

        $insertedParamsValues = count($filterParamsForSave);
        $filterParamTable->saveAll($filterParamsForSave);

        $insertedSeriesParams = count($paramToSeriesForSave);
        $paramToSeriesTable->saveAll($paramToSeriesForSave);

        $insertedRows = count($products);

        $this->getServiceLocator()->get('Catalog\Model\\' . self::$tables[AdminController::PRODUCT_TABLE])->saveAll($products);

        echo '<br> Работа крона завершена!<br>';
        echo 'обработано строк файла: ' . $calculatedRows . '<br>';
        echo 'добавлено/обновлено продуктов в каталоге: ' . $insertedRows . '<br>';
        echo 'добавлено/обновлено серий в каталоге: ' . $insertedSeries . '<br>';
        echo 'добавлено/обновлено диапазонов значений по сериям: ' . $insertedSeriesMinMax . '<br>';
        echo 'добавлено/обновлено возможных значений параметров: ' . $insertedParamsValues . '<br>';
        echo 'добавлено/обновлено возможных параметров по серии: ' . $insertedSeriesParams . '<br>';
        echo 'затраченное время: ' . (microtime(true) - $time) . ' секунд <br>';

        return array('products' => $products);
    }
}