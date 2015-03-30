<?php
/**
 * Created by PhpStorm.
 * User: Вадим
 * Date: 18.06.14
 * Time: 10:09
 */

namespace Catalog\Service;


class SeriesAggregator {
    private $series = array();
    private $seBySSid = array();

    private static $instance = null;
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new SeriesAggregator();
        }
        return self::$instance;
    }

    /**
     * @param integer $ssId
     * @return \Catalog\Model\Series[]
     *
     */
    public function getSeries($ssId = 0)
    {
        if ($ssId != 0) {
            $series = array();
            if (array_key_exists($ssId, $this->seBySSid)) {
                foreach ($this->seBySSid[$ssId] as $oneSerId) {
                    $series[$oneSerId] = $this->series[$oneSerId];
                }
            }
            return $series;
        }
        return $this->series;
    }

    public function addSeries($series) {
        if (is_array($series)) {
            foreach ($series as $one) {
                if (!array_key_exists($one->id, $this->series)) {
                    $this->series[$one->id] = $one;
                    $this->seBySSid[$one->subsection_id][] = $one->id;
                }
            }
        } else {
            $this->series[$series->id] = $series;
            $this->seBySSid[$series->subsection_id][] = $series->id;
        }

        return $this;
    }
} 