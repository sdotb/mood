<?php
namespace SdotB\Mood;
/**
 *  Extension of abstract class Mood
 */
use SdotB\Utils\Utils;

abstract class Mold extends Mood {

    protected static $baseFieldsMap = [
        'cod' => ['id',['string',['lenght' => 32]]],
        'isparent' => ['isParent',['number',['lenght' => 1],['DEFAULT 0']]],   //  Se anagrafica è genitore di altre anagrafiche
        'lastedit' => ['lastEdit',['date',['type'=>"full"]]],
        'parentcod' => ['parentId',['string',['lenght' => 32]]],  //  Codice dell'anagrafica genitore per relazione di appartenenza
        'status' => ['statusBe',['number',['lenght' => 1],['DEFAULT 1']]],
    ];

    protected function composeFieldsMap(): void
    {
        $this->fieldsMap = array_merge(Mold::$baseFieldsMap, $this->fieldsMap);
        ksort($this->fieldsMap);
    }

    protected final function __init(): void
    {
        $this->composeFieldsMap();
        $this->init();        
    }

    public function permittedActions(): array
    {
        $actions = [
            "create",
            "edit",
            "delete",
            "getList",
            "search",
            "tableCreate",
            "tableEmpty",
            "view",
            "__actions",
            "__fields",
        ];
        if (in_array("actions",get_class_methods($this))) {
            $actions = array_merge($this->actions(), $actions);
        }
        sort($actions);
        return $actions;
    }

    public function actions(): array
    {
        return [];
    }

    public function __actions(): array
    {
        $this->ayOutput['data'] = $this->permittedActions();
        return $this->ayOutput;
    }

    public function __fields(): array
    {
        $this->ayOutput['data'] = array_values($this->mapIntExt);
        return $this->ayOutput;
    }

    /**
     * Implementare qualcosa tipo Import/Export di Shark
     * importParsing vuol dire che in riferimento al backend (Be) importo qualcosa, FrontEnd Fe o DataBase Db
     * exportParsing al contrario, da Be esporto verso FrontEnd o Database
     */
    protected function importFe(array $data, string $action = ''): array
    {
        return $this->__importFe($data, $action);
    }

    /**
     * This define general default import parsing of fields coming from Fe
     * Find a way to permit specific override of fields (i.e. changing fieldkeys: __email)
     */
    protected final function __importFe(array $data, string $action = ''): array
    {
        switch ($action) {
            default:
                # none applied
                break;
        }
        return $data;
    }

    protected function importDb(array $data, string $action = ''): array
    {
        return $this->__importDb($data, $action);
    }

    protected final function __importDb(array $data, string $action = ''): array
    {
        switch ($action) {
            case 'getList':
                foreach ($data as $fieldkey => $fieldvalue) {
                    switch ($fieldkey) {
                        default:
                            //  Parsing predefinito per tutti i campi
                            break;
                    }
                }
                break;
            case 'search':
                foreach ($data as $fieldkey => $fieldvalue) {
                    switch ($fieldkey) {
                        default:
                            //  Parsing predefinito per tutti i campi
                            break;
                    }
                }
                break;
            case 'view':
                foreach ($data as $fieldkey => $fieldvalue) {
                    switch ($fieldkey) {
                        default:
                            //  Parsing predefinito per tutti i campi
                            break;
                    }
                }
                break;
            default:
                # none applied
                break;
        }
        return $data;
        
    }

    //  DEFINIZIONE DELLE AZIONI ESEGUIBILI ------------------------------------
    public function create(array $ayData, array $params = []): array
    {
        try {
            $params = $this->parseParams($params);
            if (!$this->mysesExtend($params['callFrom'])) {
                throw new MoodException("invalid session or session expired", 401);
            }
            //  Option to pass to Unit worker
            $opt['mapFilters']['in'] = array_flip($this->getMapKSA(
                $this->{$params['mapInput']}, 
                [], 
                ['id','cod','lastedit','timestamp','status'],
                []
            ));
            $opt['mapFilters']['out'] = $this->getMapKSA(
                $this->{$params['mapOutput']}, 
                [], 
                ['id','timestamp','status'], 
                []
            );
            $opt['mapFilters']['requiredFields'] = $this->getMapKSA(
                $this->{$params['mapOutput']}, 
                ['_none'], 
                [], 
                []
            );
            $opt['creationPrefix'] = "";
            foreach ($ayData as $data) {
                $this->ayOutput[] = $this->createUnit($data, $opt);
            }
        } catch (MoodException $e) {
            $this->ayOutput[] = $e->export();
        }
        return $this->ayOutput;
    }

    /**
     * Testing: not implemented final "__" version of method like __importFe
     * here apply filter if fields need to be parsed everywhere for my project
     * i.e. want to validate email field in all entities extended over Mold,
     * in child instance i will implement "local" filter and inside Mold::createFilterData
     * only if I need to apply de default filtering implemented here
     */
    protected function createFilterData($data): array
    {
        foreach ($data as $fieldkey => $fieldvalue) {
            switch ($fieldkey) {
                default:
                    // Parsing for unmatching fields
                    break;
            }
        }
        return $data;
    }

    protected function createUnit(array $data, array $opt): array
    {
        return $this->__createUnit($data, $opt);
    }

    protected final function __createUnit(array $data, array $opt): array
    {
        /**
         * Se voglio chiamare dall'esterno questa classe dovrò passare:
         * mappe (ingresso, scrittura db, lettura db, uscita)
         * campi richiesti
         * prefisso codice per creazione
         * parsing dei campi prima della scrittua in db
         * opzioni varie
         */
        $unitOutput = [];
        try {
            // filtro l'oggetto e ne copnverto le chiavi
            $data = $this->remapFilter($data, $opt['mapFilters']['in']);

            // verifico campi richiesti
            $ayRequiredField = [];
            foreach ($opt['mapFilters']['requiredFields'] as $key => $value) {
                if (empty($data[$key])) {
                    $ayRequiredField[$value] = $value;
                }
            }
            if (!empty($ayRequiredField)) {
                sort($ayRequiredField);
                //  Stampa dei campi richiesti mancanti, con MoodException potrei restituire l'oggetto
                $requiredFields = " ".implode(', ',array_values($ayRequiredField));
                //$requiredFields = substr($requiredFields, 0, -2);
                throw new MoodException("missing required field:".$requiredFields, 401);
            }
            
            // Garantisco univocità in fase di creazione, per ora appogiata a logica DB
            $code = $opt['creationPrefix'].Utils::mTS();
            $i = 0;
            while ($this->db->insert($this->tblName, array($this->mapIntDb["cod"] => $code)) == false) {
                ++$code;
                if(++$i >= $this->opt['createMaxAttempts']) throw new MoodException("create: maximum attempts reached, retry", 500);
            }

            //  Importo i dati convertendoli dove necessario prima di scriverli in Db
            $data = $this->createFilterData($data);

            /**
             * Lascio interna allo UW la rimappatura/filtro dei dati che vanno scritti in db, di fatto mantengo tutti quelli permessi in ingresso e appendo lastedit e status
             * estrarla nell'AD non sarebbe molto vantaggioso, se ad esempio appendessi un nuovo campo dovrei anche implementare l'aggiunta internamente all'UW (quindi override)
             * se devo fare override dell'UW tantovale implementare internamente anche il filtro relativo a queste logiche
             */
            $data['lastedit'] = date("Y-m-d H:i:s");
            $data['status'] = 1;
            $keep = array_keys($data);
            $scrap = [];
            $append = $this->getMapKeyDb(['lastedit','status']);
            $dataDb = $this->remapFilter($data, $this->getMapKSA($this->mapIntDb, $keep, $scrap, $append));

            $res = $this->db->update($this->tblName, $dataDb, $this->mapIntDb["cod"]." = :cod", [":cod"=>$code]);
            if (($res === false) or ($res == 0)) {
                $this->db->delete($this->tblName, $this->mapIntDb["cod"]." = :cod", ["cod"=>$code]);
                throw new MoodException("unable to create", 500);
            } else {
                // Azioni da effettuare a creazione avvenuta
                $data['cod'] = $code;
                $data = $this->remapFilter($data, $opt['mapFilters']['out']);
                $data['_count'] = (int)$res;
                ksort($data);
                $unitOutput = ["status"=>"OK","data"=>$data];
            }
        } catch (MoodException $e) {
            $unitOutput = $e->export();
        }
        return $unitOutput;
    }

    public function delete(array $ayData, array $params = []): array
    {
        try {
            $params = $this->parseParams($params);
            if (!$this->mysesExtend($params['callFrom'])) {
                throw new MoodException("invalid session or session expired", 401);
            }
            $opt['mapFilters']['in'] = array_flip($this->getMapKSA(
                $this->{$params['mapInput']}, 
                ['cod']));
            foreach ($ayData as $data) {
                $this->ayOutput[] = $this->deleteUnit($data, $opt);
            }
        } catch (MoodException $e) {
            $this->ayOutput[] = $e->export();
        }
        return $this->ayOutput;
    }

    protected function deleteUnit(array $data, array $opt): array
    {
        return $this->__deleteUnit($data, $opt);
    }

    protected final function __deleteUnit(array $data, array $opt): array
    {
        $unitOutput = [];
        try {
            $data = $this->remapFilter($data, $opt['mapFilters']['in']);
            $data['lastedit'] = date("Y-m-d H:i:s");
            $data['status'] = 0;
            $dataDb = $this->remapFilter(
                $data,
                $this->getMapKeyDb(["lastedit","status"])   // Qui devo mantenere solo i campi appesi, non necessaria mapKSA
            );
            $res = $this->db->update(
                $this->tblName,
                $dataDb,
                $this->mapIntDb['cod']." = :cod AND ".$this->mapIntDb['status']." >= 1",
                [":cod" => $data['cod']]
            );
            if ($res === false) {
                throw new MoodException("error deleting row", 0);
            } elseif ($res == 0) {
                throw new MoodException("nothing found to delete", 0);
            } else {
                $unitOutput = ["data"=>["deleted"=>(string)$res],"status"=>"OK"];
            }
        } catch (MoodException $e) {
            $unitOutput = $e->export();
        }
        return $unitOutput;
    }

    public function edit(array $ayData, array $params = []): array
    {
        try {
            $params = $this->parseParams($params);
            //  If session protected needs
            if (!$this->mysesExtend($params['callFrom'])) {
                throw new MoodException("invalid session or session expired", 401);
            }
            //  Option to pass to Unit worker
            $opt['mapFilters']['in'] = array_flip($this->getMapKSA(
                $this->{$params['mapInput']}, 
                [], 
                ['id','timestamp','status']
            ));
            $opt['mapFilters']['out'] = $this->getMapKSA(
                $this->{$params['mapOutput']}, 
                [], 
                ['id','timestamp','status'], 
                []
            );
            $opt['mapFilters']['requiredFields'] = $this->getMapKSA(
                $this->{$params['mapOutput']}, 
                ['_none'], 
                [], 
                []
            );
            foreach ($ayData as $data) {
                $this->ayOutput[] = $this->editUnit($data, $opt);
            }
        } catch (MoodException $e) {
            $this->ayOutput[] = $e->export();
        }
        return $this->ayOutput;
    }

    protected function editFilterData($data): array
    {
        foreach ($data as $fieldkey => $fieldvalue) {
            switch ($fieldkey) {
                default:
                    // Parsing for unmatching fields
                    break;
            }
        }
        return $data;
    }

    protected function editUnit(array $data, array $opt): array
    {
        return $this->__editUnit($data, $opt);
    }

    protected final function __editUnit(array $data, array $opt): array
    {
        $unitOutput = [];
        try {
            $data = $this->remapFilter($data, $opt['mapFilters']['in']);
            // cod è un campo necessario per identificare l'elemento da modificare, senza non posso procedere
            if (empty($data['cod'])) { 
                throw new MoodException("missing required argument: ".array_flip($opt['mapFilters']['in'])['cod'], 400);
            } else {
                $code = $data['cod'];
                unset($data['cod']);
            }
            // verifico campi richiesti
            $ayRequiredValue = [];
            foreach ($opt['mapFilters']['requiredFields'] as $key => $value) {
                if (isset($data[$key]) AND empty($data[$key])) {
                    $ayRequiredValue[$value] = $value;
                }
            }
            if (!empty($ayRequiredValue)) {
                sort($ayRequiredValue);
                //  Stampa dei campi richiesti mancanti, con MoodException potrei restituire l'oggetto
                $requiredFields = " ".implode(', ',array_values($ayRequiredValue));
                //$requiredFields = substr($requiredFields, 0, -2);
                throw new MoodException("cannot be empty:".$requiredFields, 401);
            }
            //  Importo i dati convertendoli dove necessario prima di scriverli in Db
            $data = $this->editFilterData($data);

            //  Appendo valori ulteriori
            $data['lastedit'] = date("Y-m-d H:i:s");

            //  Definisco Array valori Update
            $keep = array_keys($data);
            $append = $this->getMapKeyDb(["lastedit"]);
            $data = $this->remapFilter($data, $this->getMapFilter($this->mapIntDb, $keep, [], $append));
            //  Definisco condizioni Query Update
            $where = $this->mapIntDb["cod"]." = :cod AND ".$this->mapIntDb["status"]." >= 1";
            $bind = [":cod" => $code];
            $res = $this->db->update($this->tblName, $data, $where, $bind);
            if ($res === false) {
                throw new MoodException("internal error updating", 0);
            } elseif ($res == 0) {
                throw new MoodException("unable to find object", 0);
            } else {
                $unitOutput = ["data"=>["updated"=>(string)$res],"status"=>"OK"];
            }
        } catch (MoodException $e) {
            $unitOutput = $e->export();
        }
        return $unitOutput;
        
    }

    public function getList(array $ayData, array $params = []): array
    {
        try {
            $params = $this->parseParams($params);
            //  If session protected needs
            if (!$this->mysesExtend($params['callFrom'])) {
                throw new MoodException("invalid session or session expired", 401);
            }
            //  Option to pass to Unit worker
            $opt['mapFilters']['in'] = array_flip($this->getMapKSA(
                $this->{$params['mapInput']}, 
                ['_none'], 
                [],
                ['data'=>'data','s'=>'start','l'=>'length','t'=>'total']
            ));
            $opt['mapFilters']['out'] = $this->getMapKSA(
                $this->{$params['mapOutput']}, 
                [], 
                ['id','timestamp','status'], 
                ['data'=>'data','s'=>'start','l'=>'length','t'=>'total']
            );
            $opt['mapFilters']['selectFields'] = $this->getMapKSA(
                $this->mapIntDb, 
                [], 
                ['id','timestamp','status'],
                []
            );
            $opt['where'] = "{$this->mapIntDb['status']} >= 1";
            $opt['mapFilters']['or'] = $this->getMapKSA(
                $this->mapIntDb, 
                ['description']
            );
            $orblock = '';
            foreach ($opt['mapFilters']['or'] as $key => $val) {
                $orblock .= "$val LIKE :search OR ";
            }
            if (!empty($orblock)) {
                $orblock = substr($orblock,0,-4);
                $where .= " AND ($orblock)";
            }
            $opt['where'] = $where;
            foreach ($ayData as $data) {
                $this->ayOutput[] = $this->getListUnit($data, $opt);
            }
        } catch (MoodException $e) {
            $this->ayOutput[] = $e->export();
        }
        return $this->ayOutput;
    }

    protected function getListUnit(array $data, array $opt): array
    {
        return $this->__getListUnit($data, $opt);
    }

    protected final function __getListUnit(array $data, array $opt): array
    {
        $unitOutput = [];
        try {
            $data = $this->remapFilter($data, $opt['mapFilters']['in']);
            if (!isset($data['data'])) {
                $data['data'] = "";
            }
            $bind = array(":search"=>"%".$data['data']."%");
            $sql = "SELECT count(*) FROM {$this->tblName} WHERE {$opt['where']}";
            $t = $this->db->run($sql, $bind);
            if (!isset($data['s'])) {
                $data['s'] = 0;
            }
            if (!isset($data['l'])) {
                $data['l'] = 1000;
            }

            $fields = $this->getAliasDB($opt['mapFilters']['selectFields']);
            $where = "{$opt['where']} LIMIT ".$data['s']." , ".$data['l'];
            $res = $this->db->select($this->tblName, $where, $bind, $fields);
            foreach ($res as $itemKey => $itemVal) {
                $res[$itemKey] = $this->importDb($res[$itemKey],'getList');
                $res[$itemKey] = $this->remapFilter($res[$itemKey], $opt['mapFilters']['out']);
                ksort($res[$itemKey]);
            }
            $unitOutput = [
                "status"=>"OK",
                $opt['mapFilters']['out']['s']=>(string)$data['s'],
                $opt['mapFilters']['out']['l']=>(string)$data['l'],
                $opt['mapFilters']['out']['t']=>(string)$t[0]['count(*)'],
                $opt['mapFilters']['out']['data']=>$res
            ];
        } catch (MoodException $e) {
            $unitOutput = $e->export();
        }
        return $unitOutput;
    }

    public function search(array $ayData, array $params = []): array
    {
        try {
            $params = $this->parseParams($params);
            if (!$this->mysesExtend($params['callFrom'])) {
                throw new MoodException("invalid session or session expired", 401);
            }
            $opt['mapFilters']['in'] = array_flip($this->getMapKSA(
                $this->{$params['mapInput']}, 
                ['cod','parentcod','description','name','firstname','lastname','email','tag'], 
                ['id','timestamp','lastedit','status']
            ));
            $opt['mapFilters']['out'] = $this->getMapKSA(
                $this->{$params['mapOutput']}, 
                [], 
                ['id','timestamp','status'], 
                []
            );
            $opt['mapFilters']['selectFields'] = $this->getMapKSA(
                $this->mapIntDb, 
                [], 
                ['id','timestamp','status'],
                []
            );
            foreach ($ayData as $data) {
                $this->ayOutput[] = $this->searchUnit($data, $opt);
            }
        } catch (MoodException $e) {
            $this->ayOutput[] = $e->export();
        }
        return $this->ayOutput;
    }

    protected function searchUnit(array $data, array $opt): array
    {
        return $this->__searchUnit($data, $opt);
    }

    protected final function __searchUnit(array $data, array $opt): array
    {
        $unitOutput = [];
        try {
            $data = array_filter($this->remapFilter($data, $opt['mapFilters']['in'])); //  Rimappo per usare mie chiavi e rimuovo valori vuoti con array_filter
            if (empty($data)) {
                throw new MoodException("missing required argument", 401);
            }

            $fields = $this->getAliasDB($opt['mapFilters']['selectFields']);
            $where = "{$this->mapIntDb['status']} >= 1";
            $andblock = '';
            foreach ($data as $key => $val) {
                $andblock .= "{$this->mapIntDb[$key]} LIKE :$key AND ";
                $bind[":".$key] = "%".$val."%";
            }
            if (!empty($andblock)) {
                $andblock = substr($andblock,0,-5);
                $where .= " AND ($andblock)";
            }
            $res = $this->db->select($this->tblName, $where, $bind, $fields);
            foreach ($res as $itemKey => $itemVal) {
                $res[$itemKey] = $this->importDb($res[$itemKey],'search');
                $res[$itemKey] = $this->remapFilter($res[$itemKey], $opt['mapFilters']['out']);
                ksort($res[$itemKey]);
            }
            $unitOutput = ["data"=>$res,"status"=>"OK"];
        } catch (MoodException $e) {
            $unitOutput = $e->export();
        }
        return $unitOutput;
    }

    public function view(array $ayData, array $params = []): array
    {
        try {
            $params = $this->parseParams($params);
            if (!$this->mysesExtend($params['callFrom'])) {
                throw new MoodException("invalid session or session expired", 401);
            }
            $opt['mapFilters']['in'] = array_flip($this->getMapKSA(
                $this->{$params['mapInput']}, 
                ['cod']
            ));
            $opt['mapFilters']['out'] = $this->getMapKSA(
                $this->{$params['mapOutput']}, 
                [], 
                ['id','lastedit','timestamp','status']
            );
            $opt['mapFilters']['selectFields'] = $this->getMapKSA(
                $this->mapIntDb, 
                [], 
                ['id','lastedit','timestamp','status'],
                []
            );
            foreach ($ayData as $data) {
                $this->ayOutput[] = $this->viewUnit($data, $opt);
            }
        } catch (MoodException $e) {
            $this->ayOutput[] = $e->export();
        }
        return $this->ayOutput;
    }

    protected function viewUnit(array $data, array $opt): array
    {
        return $this->__viewUnit($data, $opt);
    }

    protected final function __viewUnit(array $data, array $opt): array
    {
        $unitOutput = [];
        try {
            $data = $this->remapFilter($data, $opt['mapFilters']['in']);
            if (empty($data['cod'])) {
                throw new MoodException("missing required argument", 400);
            }
            $fields = $this->getAliasDB($opt['mapFilters']['selectFields']);
            $where = $this->mapIntDb['status']." >= 1 AND ".$this->mapIntDb['cod']." = :cod";
            $bind = [":cod" => $data['cod']];
            $res = $this->db->select($this->tblName, $where, $bind, $fields);
            foreach ($res as $itemKey => $itemVal) {
                $res[$itemKey] = $this->importDb($res[$itemKey],'view');
                $res[$itemKey] = $this->remapFilter($res[$itemKey], $opt['mapFilters']['out']);
                ksort($res[$itemKey]);
            }
            $unitOutput = ["data"=>$res[0],"status"=>"OK"];
        } catch (MoodException $e) {
            $unitOutput = $e->export();
        }
        return $unitOutput;
    }
    //  ------------------------------------------------------------------------
    //  GESTIONE DELLA tabella

    public function tableCreate($overwrite)
    {
        return $this->tableManager('create');
    }

    public function tableEmpty()
    {
        $sql = "TRUNCATE TABLE `".$this->tblIdentifier."`; ";
        return $this->db->run($sql);
    }

}
?>
