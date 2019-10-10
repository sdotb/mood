<?php
namespace SdotB\Mood;

class ActionSkeleton extends Mold {
    /**
     * Action Driver and Unit Worker Skeleton
     */
    public function action(array $ayData, array $params = []): array {
        try {
            $params = $this->parseParams($params);
            if (!$this->mysesExtend($params['callFrom'])) {
                throw new MoodException("invalid session or session expired", 401);
            }
            $opt['mapFilters']['in'] = array_flip($this->getMapKSA(
                $this->{$params['mapInput']}, 
                [], 
                ['id','timestamp','lastedit','status']
            ));
            $opt['mapFilters']['out'] = $this->getMapKSA(
                $this->{$params['mapOutput']}, 
                [], 
                ['id','timestamp','status'], 
                []
            );
            $opt['otherOption'] = "Value";
            foreach ($ayData as $data) {
                $this->ayOutput[] = $this->actionUnit($data, $opt);
            }
        } catch (MoodException $e) {
            $this->ayOutput[] = $e->export();
            // $this->ayOutput[] = [
            //     "data"=>$e->getData(),
            //     "msg"=>static::class."::".__FUNCTION__."##".$e->getMessage(),
            //     "scod"=>(string)$e->getCode(),
            //     "status"=>"KO",
            // ];
        }
        return $this->ayOutput;
    }

    protected function actionUnit(array $data, array $opt): array {
        $unitOutput = [];
        try {
            $data = $this->remapFilter($data, $opt['mapFilters']['in']);

            $data = $this->remapFilter($data, $opt['mapFilters']['out']);
            ksort($data);

            $unitOutput = [
                "data"=>[$data],
                "status"=>"OK",
            ];

        } catch (MoodException $e) {
            $unitOutput = $e->export();
        }
        return $unitOutput;
    }
}