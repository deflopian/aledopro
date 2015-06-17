<?php
namespace Catalog\Service;

use Application\Service\MailService;
use Catalog\Controller\AdminController;
use Catalog\Controller\CronController;
use Catalog\Model\FilterParam;
use Catalog\Model\ParamToSeries;
use Catalog\Model\ParamToSeriesTable;
use Catalog\Model\Product;
use Catalog\Model\Series;
use Catalog\Model\SeriesParams;
use Catalog\Model\SeriesTable;
use Reports\Mapper\ReportMapper;
use Reports\Model\Report;
use Reports\Model\ReportItem;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Session\Container;
use Zend\XmlRpc\Value\String;
use ZendTest\XmlRpc\Server\Exception;

class CronService {
    const SERIES_COLUMN = 50;
//    const LIMIT_PRODUCTS_PER_TIME = 1;
    const LIMIT_PRODUCTS_PER_TIME = 500;

    private static $tables = array(
        AdminController::SECTION_TABLE => 'SectionTable',
        AdminController::SUBSECTION_TABLE => 'SubSectionTable',
        AdminController::SERIES_TABLE => 'SeriesTable',
        AdminController::PRODUCT_TABLE => 'ProductTable',
        AdminController::DOC_TABLE => 'SeriesDocTable',
        AdminController::DIM_TABLE => 'SeriesDimTable',
    );

    private static $unEditableParams = array(
        'id', 'section_id', 'subsection_id', 'series_id', 'order'
    );

    public static function getTableName($id)
    {
        return isset(self::$tables[$id]) ? self::$tables[$id] : null;
    }

    public static function getUnEditableParams()
    {
        return self::$unEditableParams;
    }

    /**
     * @param $table \Catalog\Model\ProductTable
     * @param $dopProdTable \Catalog\Model\DopProdTable
     */
    public static function deleteOldProducts($table, $dopProdTable) {
        $prodsToDel = $table->fetchByConds(array('checked' => 0, 'add_by_user' => 0, 'file_custom' => '', 'is_offer' => 0));

        $table->deleteWhere(array('checked' => 0, 'add_by_user' => 0, 'file_custom' => '', 'is_offer' => 0));
        $table->deleteWhere(array('checked' => 0, 'add_by_user' => 0, 'file_custom' => null, 'is_offer' => 0));
        $table->updateWhere('`checked` = 1', '`checked` = 0');
//
//        foreach ($prodsToDel as $prod) {
//            $dopProdTable->deleteWhere(array('product_id' => $prod->id));
//        }
    }
    /**
     * @param $sl
     */
    public static function sortOldProducts($sl) {
        $series = $sl->get('Catalog\Model\\' . self::$tables[AdminController::SERIES_TABLE])->fetchAll();
        foreach ($series as $oneser) {
            if ($oneser->sorted_field) {
                CatalogService::makesort($oneser->sorted_field, $oneser->id, $oneser->sorted_order, $sl, true);
            }
        }
    }

    /**
     * парсит строку каталога в продукт
     * @param string $row
     * @return bool|Product
     */
    private static function parseCSVRowToProduct($row)
    {
        $product = new Product();
        $cells = str_getcsv($row, ";");
        $lumfx_abs = 0; //костыль для того, чтобы $luminous_flux хранился в базе в виде строки,
                        //но мы могли ещё и фильтровать по нему
        //порядок свойств в Product важен!
        $vars = get_object_vars($product);
        $key_vars = array_keys($vars);

        if (count($vars) >= (count($cells))) {
            $shit = 0; //ячейки без значения
            $expectedField = false;
            foreach ($cells as $num => $value) {

                if ($value == '##' || $value == '#') {
                    $shit++;
                    continue;
                }
                if ($expectedField) {
                    $shit++;
                    $expectedField = false;
                    continue;
                }
                if (!$expectedField && $key_vars[$num-$shit] == 'file') {
                    $expectedField = true;
                }

                if ($key_vars[$num-$shit] == 'power') {
                    $value = str_replace(",",".",$value);
                }

                if ($key_vars[$num-$shit] == 'luminous_flux') {
                    $lumfx_abs = floatval($value);
                }

                $vars[$key_vars[$num-$shit]] = trim($value, "^~");

            }

            $product->exchangeArray($vars);
            $product->lumfx_abs = $lumfx_abs;
        } else {
            echo 'error';
            return false;
        }


        return $product;
    }

    private static function setProductSeriesMinMax($productSeries, $serName, $diapasonParamName, $min, $max)
    {
        /*if ($min == null) {
            $min = 0;
        }
        if ($max == null) {
            $max = 0;
        }*/

        if ($diapasonParamName == 'luminous_flux') {
            if ($pos = strstr($min, 'лм/м')) {
                $min = substr($min, 0, strlen($pos));
                if (strstr($min, '+')) {
                    $minArr = explode('+', $min);
                    $min = 0;
                    foreach ($minArr as $minEl) {
                        $min += (float)$minEl;
                    }
                }

                $max = $min;
            } elseif ($pos = strstr($min, 'лм')) {
                $min = substr($min, 0, $pos);
                $max = $min;
            }
        }
        $productSeries[$serName][$diapasonParamName]['min'] = is_null($productSeries[$serName][$diapasonParamName]['min']) ? $min : min($productSeries[$serName][$diapasonParamName]['min'], $min);
        $productSeries[$serName][$diapasonParamName]['max'] = is_null($productSeries[$serName][$diapasonParamName]['max']) ? $max : max($productSeries[$serName][$diapasonParamName]['max'], $max);
        return $productSeries;
    }

    /**
     * @param $sl ServiceLocatorInterface
     * @param $product \Catalog\Model\Product
     * @param $productSeries
     * @param $serName
     * @param $allDiapasonParams
     * @param $insertedSeriesMinMax
     * @return array
     */
    private function saveFilterSeriesParams($sl, $product, $productSeries, $serName, $allDiapasonParams, &$insertedSeriesMinMax)
    {
        /** @var \Catalog\Model\SeriesParamsTable $filterTable */
        $filterTable = $sl->get('Catalog\Model\\' . CronController::$tables[AdminController::FILTER_BY_SERIES_TABLE]);
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

            $seriesEntity->min_price = $productSeries[$serName]['wholesale_price']['min'] == null ? 0 : $productSeries[$serName]['wholesale_price']['min'] ;
            $seriesEntity->max_price = $productSeries[$serName]['wholesale_price']['max'] == null ? 0 : $productSeries[$serName]['wholesale_price']['max'] ;

            // magic!
            foreach ($allDiapasonParams as $diapasonParamName) {
                if ($diapasonParamName == $allDiapasonParams[0]) continue;
                $minValueName = 'min_' . $diapasonParamName;
                $maxValueName = 'max_' . $diapasonParamName;

                $seriesEntity->$minValueName = $productSeries[$serName][$diapasonParamName]['min'] == null ? 0 : $productSeries[$serName][$diapasonParamName]['min'];
                $seriesEntity->$maxValueName = $productSeries[$serName][$diapasonParamName]['max'] == null ? 0 : $productSeries[$serName][$diapasonParamName]['max'];
            }
            $insertedSeriesMinMax++;
            $filterTable->save($seriesEntity);
        }

        return array($productSeries, $insertedSeriesMinMax, $product);
    }

    /**
     * @param $sl ServiceLocatorInterface
     * @param $product \Catalog\Model\Product
     * @param $productSeries array           в $productSeries храним текущие диапазоны значений для каждой из серий
     *                                       на этой итерации крона. Сюда же в первую очередь суёмся за айдишками серий
     *                                       для продуктов
     * @param $allDiapasonParams array
     * @param $insertedSeries array
     * @param $insertedSeriesMinMax array
     * @param $orphanSeriesReport Report | false
     * @param $reportMapper ReportMapper | false
     * @return array
     */
    public static function diapasonBySeries($sl, $product, $productSeries, $allDiapasonParams, &$insertedSeries, &$insertedSeriesMinMax, $reportMapper = false, &$orphanSeriesReport = false)
    {
        //строковое название серии (приходит из главной базы в csv-файле)
        $serName = $product->seriesName;
        //если нет названия, стало быть продукт не принадлежит к серии и ничего с ним делать не надо
        if (!empty($serName) && (is_string($serName) || is_numeric($serName))) {

            //если мы уже создавали эту серию на данной итерации крона, то просто обновляем для неё min-max
            //и выставляем айдишку у продукта
            if (array_key_exists($serName, $productSeries)) {
                $product->series_id = $productSeries[$serName]['series_id'];
                foreach ($allDiapasonParams as $diapasonParamName) {
                    $productSeries = self::setProductSeriesMinMax($productSeries, $serName, $diapasonParamName, $product->$diapasonParamName, $product->$diapasonParamName);
                }

            } else {
                //в противном случае, находим серию в таблице
                $seriesTable = $sl->get('Catalog\Model\\' . CronController::$tables[AdminController::SERIES_TABLE]);
                $series = $seriesTable->fetchByCond('title', $serName);

                if ($series && is_array($series) && isset($series[0])) {

                    //если нашли серию, добавляем во временный список
                    $product->series_id = $series[0]->id;

                    if ($reportMapper !== false && $series[0]->subsection_id == 0) {
                        $item = new ReportItem();
                        $item->linked_id = $series[0]->id;
                        $item->linked_type = AdminController::SERIES_TABLE;
                        $item->report_id = $orphanSeriesReport->id;
                        $item->title = $serName;
                        $item->url = "/admin/catalog/series/" . $item->linked_id . "/";

                        $orphanSeriesReport = $reportMapper->addItems($orphanSeriesReport, array($item));
                    }

                    $productSeries[$serName]['series_id'] = $series[0]->id;
                    foreach ($allDiapasonParams as $diapasonParamName) {
                        $productSeries = self::setProductSeriesMinMax($productSeries, $serName, $diapasonParamName, $product->$diapasonParamName, $product->$diapasonParamName);
                    }
                } else {

                    //нет такой серии, создаём и сохраняем в базу
                    $seriesEntity = new Series();
                    $seriesEntity->title = $serName;
                    $seriesEntity->visible_title = $serName;
                    $seriesEntity->sorted_field = "free_balance";
                    $seriesEntity->sorted_order = 2;

                    //статистика
                    $insertedSeries++;
                    $seriesTable->save($seriesEntity);


                    $lastId = $seriesTable->adapter->getDriver()->getLastGeneratedValue();
                    $product->series_id = $lastId;
                    $productSeries[$serName]['series_id'] = $lastId;

                    if ($reportMapper !== false) {
                        $item = new ReportItem();
                        $item->linked_id = $lastId;
                        $item->linked_type = AdminController::SERIES_TABLE;
                        $item->report_id = $orphanSeriesReport->id;
                        $item->title = $serName;
                        $item->url = "/admin/catalog/series/" . $item->linked_id . "/";

                        $orphanSeriesReport = $reportMapper->addItems($orphanSeriesReport, array($item));
                    }


                    foreach ($allDiapasonParams as $diapasonParamName) {
                        $productSeries = self::setProductSeriesMinMax($productSeries, $serName, $diapasonParamName, $product->$diapasonParamName, $product->$diapasonParamName);
                    }
                }
            }

            list($productSeries, $insertedSeriesMinMax, $product) = self::saveFilterSeriesParams($sl, $product, $productSeries, $serName, $allDiapasonParams, $insertedSeriesMinMax);

        }
        return array($productSeries, $product);
    }

    public static function changeYoLetter($what, $replace, $where) {
        if (strpos($where, $what) !== false) {
            $where = str_replace($what, $replace, $where);
        }

        return $where;
    }

    /**
     * @param $allParamsToAllSeries
     * @param $filterParamsArray
     * @param $product
     * @param $filterParams
     * @param $maxId
     * @param $maxParamsId
     * @param $filterParamsForSave
     * @param $paramToSeriesForSave
     * @return Product
     */
    public static function fillProductAndParams(&$allParamsToAllSeries, $filterParamsArray, $product, &$filterParams, &$maxId, &$maxParamsId, &$filterParamsForSave, &$paramToSeriesForSave)
    {
        ini_set('zend.multibyte', 'On');
        ini_set('zend.script_encoding', 'utf-8');
        foreach ($filterParamsArray as $param) {
            $product->$param = trim($product->$param);
            if (!empty($product->$param)) {
                if ($param == 'electro_power' && strpos($product->$param, 'mA')) {
                    $product->$param = str_replace('mA', 'мА', $product->$param);
                }


                if ($param == 'color_of_light' || $param == 'case_color' ) {
                    $product->$param = self::changeYoLetter('Зеленый', 'Зелёный', $product->$param);
                    $product->$param = self::changeYoLetter('зеленый', 'Зелёный', $product->$param);
                    $product->$param = self::changeYoLetter('Теплый', 'Тёплый', $product->$param);
                    $product->$param = self::changeYoLetter('теплый', 'Тёплый', $product->$param);
                    $product->$param = self::changeYoLetter('желтый', 'Жёлтый', $product->$param);
                    $product->$param = self::changeYoLetter('Желтый', 'Жёлтый', $product->$param);
                }

                $ucParam = is_string($product->$param) ? ucfirst($product->$param) : $product->$param;

                if (!array_key_exists($ucParam, $filterParams[$param])) {

                    $newVariant = new FilterParam();
                    $newVariant->id = ++$maxId;
                    $newVariant->field = $param;
                    $newVariant->value = $ucParam;
                    $filterParams[$param][$ucParam] = $newVariant;
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

        return $product;
    }

    /**
     * важно: если в файле встречаются битые ссылки, пропускает эти строки
     * @param string $filePath путь к файлу-каталогу
     * @param string $linePath текущее смещение указателя в файле
     * @param int $calculatedRows
     * @return Product[] $products
     */
    public static function parseXLStoDatabase($filePath, $linePath, &$calculatedRows)
    {
        $products = array();
        $offset = 0; //first row is a title

        //get pointer position from session or set new session if not exist

        if (file_exists($linePath)) {
            $offset = file_get_contents($linePath);

        } else {
            file_put_contents($linePath, $offset);
        }

        $handler = fopen($filePath, 'r');

        if (!$handler) return array();

        fseek($handler, $offset);
        $i = 0;
        while(!feof($handler) && ($i++ < self::LIMIT_PRODUCTS_PER_TIME)) {
            $row = fgets($handler);
            $row = iconv('cp1251', 'utf-8', $row);
            $row = trim($row, "#,\n\r");

            if ($offset == 0) {
                $offset += feof($handler) + 1;
                continue;
            }
            $calculatedRows++;
            if (($newprod = self::parseCSVRowToProduct($row)) !== false) {
                $products[] = $newprod;
            }
        }


        if (feof($handler)) {
            if (!@unlink($linePath)) {
                echo "Не удалось удалить файл " . $linePath . ". Файл отсутствует или доступ к нему запрещён";
            }
            if (!@unlink($filePath)) {//if file doesn't exist, other request from cron will be fast and light
                echo "Не удалось удалить файл " . $filePath . ". Файл отсутствует или доступ к нему запрещён";
            }


        } else {
            file_put_contents($linePath, ftell($handler)); //set pointer position to a file
        }
        fclose($handler);
        if (file_exists($linePath)) {
            echo file_get_contents($linePath);
        }
        return $products;
    }

    /**
     * Возвращает из базы возможные значения параметров для фильтра продуктов
     * @param array $filterParamsArray
     * @param \Catalog\Model\FilterParamTable $filterParamTable
     * @return array
     */
    public static function getParamsVariation($filterParamsArray, \Catalog\Model\FilterParamTable $filterParamTable) {
        $result = array();
        $maxId = 0;

        foreach ($filterParamsArray as $filterParam) {
            $variants = $filterParamTable->fetchByCond('field', $filterParam);
            $result[$filterParam] = array();
            foreach ($variants as $variant) {
                $result[$filterParam][$variant->value] = $variant;
                if ($variant->id > $maxId) {
                    $maxId = $variant->id;
                }
            }
        }

        return array($result, $maxId);
    }

    /**
     * Возвращает из базы возможные значения параметров для фильтра продуктов
     * @param \Catalog\Model\ParamToSeriesTable $paramToSeriesTable
     * @return array
     */
    public static function getAllParamsToAllSeries(\Catalog\Model\ParamToSeriesTable $paramToSeriesTable) {
        $result = array();
        $maxId = 0;

        $variants = $paramToSeriesTable->fetchAll();

        foreach ($variants as $variant) {

            $result[$variant->series_id][] = $variant->param_id;
            if ($variant->id > $maxId) {
                $maxId = $variant->id;
            }
        }

        return array($result, $maxId);
    }
}