<?php
/**
 * Created by PhpStorm.
 * User: Вадим
 * Date: 18.06.14
 * Time: 10:09
 */

namespace Catalog\Service;


class SubsectionsAggregator {
    private $subsections = array();
    private $susBySid = array();
    private $order = array();

    private static $instance = null;
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new SubsectionsAggregator();
        }
        return self::$instance;
    }

    /**
     * @param integer $sId
     * @return \Catalog\Model\SubSection[]
     *
     */
    public function getSubsections($sId = 0)
    {

        if ($sId != 0) {
            $subsections = array();
            if (array_key_exists($sId, $this->susBySid)) {
                foreach ($this->susBySid[$sId] as $oneSSId) {
                    $subsections[$oneSSId] = $this->subsections[$oneSSId];
                }
            }

            return $subsections;
        }
        return $this->subsections;
    }

    public function addSubsections($subsections) {
        if (is_array($subsections)) {
            foreach ($subsections as $one) {
                if (!array_key_exists($one->id, $this->subsections)) {
                    $this->subsections[$one->id] = $one;
                    $this->susBySid[$one->section_id][] = $one->id;
                    $this->order[] = $one->id;
                }
            }
        } else {
            $this->subsections[$subsections->id] = $subsections;
            $this->susBySid[$subsections->section_id][] = $subsections->id;
            $this->order[] = $subsections->id;
        }

        return $this;
    }
} 