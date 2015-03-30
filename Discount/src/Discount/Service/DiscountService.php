<?php
namespace Discount\Service;

use Catalog\Mapper\CatalogMapper;
use Catalog\Controller\AdminController;
use Catalog\Service\CatalogService;
use Discount\Model\Discount;

class DiscountService {


    public static function getSeriesAndTags($allUsers, $id = 0, $excludeIds = array())
    {
        $thisUsers = $sids = $saliases = $snames = array();
        foreach($allUsers as $user){
            if($excludeIds && in_array($user->user_id, $excludeIds)){
                continue;
            }

            if($id && $user->user_id == $id){
                $thisUsers[] = $user;
            } else {
                $name['label'] = $user->username;
                $name['value'] = $user->user_id;
                $snames[] = $name;

                $sid['label'] = $user->email;
                $sid['value'] = $user->user_id;
                $sids[] = $sid;

                $salias['label'] = $user->alias;
                $salias['value'] = $user->user_id;
                $saliases[] = $salias;
            }
        }

        $seriesTags = array_merge($snames,$sids,$saliases);

        return array(
            'series' => $thisUsers,
            'tags'   => $seriesTags
        );
    }

    /**
     * @param $sl
     * @param $discounts \Discount\Model\Discount[]
     */
    public static function sortDiscountByHierarchy($sl, $discounts) {
        $cm = CatalogMapper::getInstance($sl);
        $sections = array();
        $subsections = array();
        $series = array();
        $products = array();
        $sortedDiscounts = array();

        function addSection ($sectionId, $subsectionId, &$sections) {

           if (!array_key_exists($sectionId, $sections)) {
               if (is_null($subsectionId)) {
                   $sections[$sectionId] = array();
               } else {
                   $sections[$sectionId] = array($subsectionId);
               }

           } else {

               if (!is_null($subsectionId) && !in_array($subsectionId, $sections[$sectionId])) {

                   $sections[$sectionId][] = $subsectionId;
               }
           }
        };

        /**
         * @param $subsectionId
         * @param $seriesId
         * @param $sections
         * @param $subsections
         * @param $cm CatalogMapper
         */
        function addSubsection ($subsectionId,
                                $seriesId,
                                &$sections,
                                &$subsections,
                                $cm) {
            if (!array_key_exists($subsectionId, $subsections)) {
                if (is_null($seriesId)) {
                   $subsections[$subsectionId] = array();
                } else {
                   $subsections[$subsectionId] = array($seriesId);
                }

                $ss = $cm->getSubsection($subsectionId);

                if ($ss) {
                    addSection($ss->section_id, $subsectionId, $sections);
                }
            } else {
                if (!is_null($seriesId) && !in_array($seriesId, $subsections[$subsectionId])) {
                    $subsections[$subsectionId][] = $seriesId;
                }
            }

        };

        /**
         * @param $seriesId
         * @param $productId
         * @param $sections
         * @param $subsections
         * @param $series
         * @param $cm CatalogMapper
         */
        function addSeries ($seriesId, $productId, &$sections, &$subsections, &$series, $cm) {

           if (!array_key_exists($seriesId, $series)) {
               if (is_null($productId)) {
                   $series[$seriesId] = array();
               } else {

                   $series[$seriesId] = array($productId);
               }

                $ser = $cm->getSeriesOne($seriesId);
                if ($ser) {
                    addSubsection(
                        $ser->subsection_id,
                        $seriesId,
                        $sections,
                        $subsections,
                        $cm
                    );
                }

           } else {
               if (!is_null($productId) && !in_array($productId, $series[$seriesId])) {
                   $series[$seriesId][] = $productId;
               }
           }
        };

        /**
         * @param $productId
         * @param $sections
         * @param $subsections
         * @param $series
         * @param $products
         * @param $cm CatalogMapper
         */
        function addProduct ($productId, &$sections, &$subsections, &$series, &$products, $cm) {
            if (!array_key_exists($productId, $products)) {
                $products[$productId] = 1;

                $prod = $cm->getProduct($productId);

                if ($prod) {
                    addSeries($prod->series_id, $productId, $sections, $subsections, $series, $cm);
                }


            }
        };

        $discountSections = array();
        $discountSubsections = array();
        $discountSeries = array();
        $discountProducts = array();

        foreach ($discounts as $discount) {
            if ($discount->section_type == AdminController::SECTION_TABLE) { //дана скидки на раздел
                addSection($discount->section_id, null, $sections);
                $discountSections[$discount->section_id] = $discount;
            } elseif ($discount->section_type == AdminController::SUBSECTION_TABLE) {
                addSubsection($discount->section_id, null, $sections, $subsections, $cm);
                $discountSubsections[$discount->section_id] = $discount;
            } elseif ($discount->section_type == AdminController::SERIES_TABLE) {
                addSeries($discount->section_id, null, $sections, $subsections, $series, $cm);
                $discountSeries[$discount->section_id] = $discount;
            } elseif ($discount->section_type == AdminController::PRODUCT_TABLE) {
                addProduct($discount->section_id, $sections, $subsections, $series, $products, $cm);
                $discountProducts[$discount->section_id] = $discount;
            }
        }

        foreach ($sections as $oneKey => $oneSec) {
            if (array_key_exists($oneKey, $discountSections)) {
                $sortedDiscounts[] = $discountSections[$oneKey];
            } else {
                $d = new Discount();
                $d->section_id = $oneKey;
                $d->section_type = AdminController::SECTION_TABLE;
                $sortedDiscounts[] = $d;
            }

            foreach ($oneSec as $subsecId) {
                if (array_key_exists($subsecId, $discountSubsections)) {
                    $sortedDiscounts[] = $discountSubsections[$subsecId];
                } else {
                    $d = new Discount();
                    $d->section_id = $subsecId;
                    $d->section_type = AdminController::SUBSECTION_TABLE;
                    $sortedDiscounts[] = $d;
                }

                foreach ($subsections[$subsecId] as $seriesId) {
                    if (array_key_exists($seriesId, $discountSeries)) {
                        $sortedDiscounts[] = $discountSeries[$seriesId];
                    } else {
                        $d = new Discount();
                        $d->section_id = $seriesId;
                        $d->section_type = AdminController::SERIES_TABLE;
                        $sortedDiscounts[] = $d;
                    }
                    foreach ($series[$seriesId] as $prodId) {
                        $sortedDiscounts[] = $discountProducts[$prodId];
                    }
                }
            }
        }

        return array($sortedDiscounts, array_keys($sections), array_keys($subsections), array_keys($series), array_keys($products));
    }

    public static function getParentIdMethodByType($type) {
        $method = 'section_id';
        switch ($type) {
            case \Catalog\Controller\AdminController::SUBSECTION_TABLE :
                $method = 'section_id';
                break;
            case \Catalog\Controller\AdminController::SERIES_TABLE :
                $method = 'subsection_id';
                break;
            case \Catalog\Controller\AdminController::PRODUCT_TABLE :
                $method = 'series_id';
                break;
        }
        return $method;
    }

    public static function getSectionByLevelId($sl, $id, $typeWeNeed, $typeWeHave) {
        if ($typeWeNeed == $typeWeHave) {
            return $sl->get('Catalog\Model\\' . CatalogService::getTableName($typeWeHave))->find($id);
        }
        if ($typeWeNeed < $typeWeHave) {
            $parent = $sl->get('Catalog\Model\\' . CatalogService::getTableName($typeWeHave))->find($id);
            $idField = self::getParentIdMethodByType($typeWeHave);
            if ($parent) {
                return self::getSectionByLevelId($sl, $parent->$idField, $typeWeNeed, $typeWeHave-1);
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public static function getHierarchy($sl, $typeWeHave, $id, $parents = array()) {

        if ($typeWeHave == \Catalog\Controller\AdminController::SECTION_TABLE) {
            $parent = $sl->get('Catalog\Model\\' . CatalogService::getTableName($typeWeHave))->find($id);
            $parents[$typeWeHave] = $parent;
            return $parents;
        }
        if ($typeWeHave > \Catalog\Controller\AdminController::SECTION_TABLE) {
            $parent = $sl->get('Catalog\Model\\' . CatalogService::getTableName($typeWeHave))->find($id);
            $parents[$typeWeHave] = $parent;
            $idField = self::getParentIdMethodByType($typeWeHave);
            if ($parent) {
                return self::getHierarchy($sl, $typeWeHave-1, $parent->$idField, $parents);
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
}