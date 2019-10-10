<?php
namespace SdotB\Mood;
/**
 *  Skeleton for extending abstract class Mold
 *
 *  This skeleton is useful to start a new Entity
 *  need to define:
 *  - $fieldsMap
 *  - permittedAction (merging with abstract)
 *  - permittedFields (according to needs)
 */
use Libs\Utils;

class MoldSkeleton extends Mold {

    protected $fieldsMap = [
        'cod' => ['id'],    // override default cod
        'description' => ['description'],
        'statusfe' => ['status'],
    ];

    public function permittedActions(){
        $actions = [
            "someotheraction",
        ];
        $actions = array_merge(parent::permittedActions(), $actions);
        sort($actions);
        return $actions;
    }

    public function permittedFields(){
        $this->ayOutput['d'][] = array_values($this->mapIntExt);
        return $this->ayOutput;
    }

    protected function init(){
        $this->fieldsMap = array_merge(Mold::$baseFieldsMap, $this->fieldsMap);
        ksort($this->fieldsMap);
    }

    /**
     * Action Drivers and Unit Workers HERE
     */

}
?>
