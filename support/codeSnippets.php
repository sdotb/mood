<?php

/**
 * Usabile per verificare chiamante in funzione
 */
$dbt=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,2);
$caller = isset($dbt[1]['function']) ? $dbt[1]['function'] : null;

//For me debug_backtrace was hitting my memory limit, and I wanted to use this in production to log and email errors as they happen.

//Instead I found this solution which works brilliantly!

// Make a new exception at the point you want to trace, and trace it!
$e = new \Exception;
var_dump($e->getTraceAsString());

// Outputs the following 
#2 /usr/share/php/PHPUnit/Framework/TestCase.php(626): SeriesHelperTest->setUp()
#3 /usr/share/php/PHPUnit/Framework/TestResult.php(666): PHPUnit_Framework_TestCase->runBare()
#4 /usr/share/php/PHPUnit/Framework/TestCase.php(576): PHPUnit_Framework_TestResult->run(Object(SeriesHelperTest))
#5 /usr/share/php/PHPUnit/Framework/TestSuite.php(757): PHPUnit_Framework_TestCase->run(Object(PHPUnit_Framework_TestResult))
#6 /usr/share/php/PHPUnit/Framework/TestSuite.php(733): PHPUnit_Framework_TestSuite->runTest(Object(SeriesHelperTest), Object(PHPUnit_Framework_TestResult))
#7 /usr/share/php/PHPUnit/TextUI/TestRunner.php(305): PHPUnit_Framework_TestSuite->run(Object(PHPUnit_Framework_TestResult), false, Array, Array, false)
#8 /usr/share/php/PHPUnit/TextUI/Command.php(188): PHPUnit_TextUI_TestRunner->doRun(Object(PHPUnit_Framework_TestSuite), Array)
#9 /usr/share/php/PHPUnit/TextUI/Command.php(129): PHPUnit_TextUI_Command->run(Array, true)
#10 /usr/bin/phpunit(53): PHPUnit_TextUI_Command::main()
#11 {main}"

class Example extends Mood {
    /**
     * Action Driver (ad)
     * - define filters and options, control permission of executong action i.e. $this->mysesExtend();
     * - drive the unit worker (uw), parse every object passed in $ayData
     * - give an ordered and nested answer to caller
     */
    public function actionDocumented(array $ayData, array $params = []): array {
        try {
            $params = $this->parseParams($params);
            //  If session protected needs
            if (!$this->mysesExtend($params['callFrom'])) {
                throw new \Exception("invalid session or session expired", 401);
            }
            //  Option to pass to Unit worker
            $opt['mapFilters']['in'] = array_flip($this->getMapKSA(
                $this->{$params['mapInput']}, 
                [], 
                ['id',"cod",'timestamp','status']
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
            $opt['creationPrefix'] = "AC";
            foreach ($ayData as $data) {
                $this->ayOutput['d'][] = $this->actionDocumentedUnit($data, $opt);
            }
        } catch (\Exception $e) {
            $this->ayOutput['d'][] = ["status"=>"KO","scod"=>(string)$e->getCode(),"msg"=>__METHOD__." ## ".$e->getMessage()];
        }
        return $this->ayOutput;
    }

    /**
     * Unit Worker uw
     * - implement action specific related logic
     * - get data input form ad
     * - give a parsed output to ad
     */
    protected function actionDocumentedUnit(array $data, array $opt): array {
        $unitOutput = [];
        try {
            /**
             * Logic workflow HERE
             * 
             * - for Input/Create/Edit object:
             *  . remap/filter
             *  . required
             *  . uniqueness
             *  . import/translate
             *  . append data
             *  . remap/export
             *  . write persistence
             *  . analisys logic with actions
             *  . remap/output or Error
             * 
             * - for Read/View/List/Search/Output object:
             *  . remap/filter
             *  . query filter/import/translate
             *  . read persistence
             *  . import/export/translate
             *  . remap/sort/output or error
             */

            //  Rimappo per lavorare con le chiavi interne
            $data = $this->remapFilter($data, $opt['mapFilters']['in']);
            /**
             * Controllo la presenza di campi valorizzati, vige la convenzione che se arriva un campo vuoto lo salvo su db come vuoto
             * Se non arriva allora non lo tocco
             * Se non voglio permettere campi vuoti il controllo deve essere effettuato qui
             * In futuro possibile importare in importFeBe ed esportare l'elemento creato
             */

            /**
             * Verifiche campi obbligatori, se uso una mappa posso stampare nell'eccezione i nomi dei campi richiesti in formato FrontEnd e
             * posso confrontare con data[value] prima di fare il remap iniziale, in questo modo evito di rimappare se non ho i campi necessari.
             * Ovviamente devo prestare attenzione a permettere il rimappaggio dei campi in ingresso, altrimenti lo rimuovo erroneamente anche se mi serve
             */
            $ayRequired = [];
            foreach ($opt['mapFilters']['requiredFields'] as $key => $value) {
                if (empty($data[$key])) {
                    $ayRequired[$value] = $value;
                }
            }
            if (!empty($ayRequired)) {
                sort($ayRequired);
                //  Stampa dei campi richiesti
                $requiredFields = " ".implode(', ',array_values($ayRequired));
                $requiredFields = substr($requiredFields, 0, -2);
                throw new \Exception("missing required field:".$requiredFields, 401);
            }

            // Garantisco univocità in fase di creazione, per ora appogiata a logica DB
            $code = $opt['creationPrefix'].Utils::uTS();
            while ($this->db->insert($this->tblName, array($this->mapIntDb["cod"] => $code)) == false) {
                ++$code;
            }
            //  Import dei dati da Fe a Be con parsing
            foreach ($data as $fieldkey => $fieldvalue) {
                switch ($fieldkey) {
                    case "plate":
                        $data[$fieldkey] = strtoupper($fieldvalue);
                    break;
                    default:
                        //  Parsing predefinito per tutti i campi
                    break;
                }
            }
            $data['lastedit'] = date("Y-m-d H:i:s");
            $data['status'] = 1;

            $keep = array_keys($data);
            $append = $this->getMapKeyDb(["lastedit"]);
            $res = $this->db->update($this->tblName, $this->remapFilter($data, $this->getMapFilter($this->mapIntDb, $keep, [], $append)), $this->mapIntDb["cod"]." = :cod", ["cod"=>$code]);

            if (($res === false) or ($res == 0)) {
                $this->db->delete($this->tblName, $this->mapIntDb["cod"]." = :cod", ["cod"=>$code]);
                throw new \Exception("unable to create", 0);
            } else {
                // TODO eventuali azioni da effettuare a creazione avvenuta
                $data['cod'] = $code;
                $data = $this->remapFilter($data, $opt['mapFilters']['out']);
                ksort($data);
                $unitOutput = ["created"=>(string)$res,"data"=>$data,"status"=>"OK"];
            }
        } catch (\Exception $e) {
            $unitOutput = ["msg"=>"error inserting row: ".$e->getMessage(),"scod"=>(string)$e->getCode(),"status"=>"KO"];
        }
        return $unitOutput;
    }

    /**
     * Action Driver and Unit Worker Skeleton
     */
    public function action(array $ayData, array $params = []): array {
        try {
            $params = $this->parseParams($params);
            if (!$this->mysesExtend($params['callFrom'])) {
                throw new \Exception("invalid session or session expired", 401);
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
                $this->ayOutput['d'][] = $this->actionUnit($data, $opt);
            }
        } catch (\Exception $e) {
            $this->ayOutput['d'][] = ["msg"=>__METHOD__." ## ".$e->getMessage(),"scod"=>(string)$e->getCode(),"status"=>"KO"];
        }
        return $this->ayOutput;
    }

    protected function actionUnit(array $data, array $opt): array {
        $unitOutput = [];
        try {
            $data = $this->remapFilter($data, $opt['mapFilters']['in']);

            $data = $this->remapFilter($data, $opt['mapFilters']['out']);
            ksort($data);

            $unitOutput = ["data"=>[$data],"status"=>"OK"];

        } catch (\Exception $e) {
            $unitOutput = ["msg"=>"error: ".$e->getMessage(),"scod"=>(string)$e->getCode(),"status"=>"KO"];
        }
        return $unitOutput;
    }


}

/**
 * Some Required Fields inside uw
 * can be empty
 */

$ayRequiredFields = [];
foreach ($opt['mapFilters']['requiredFields'] as $key => $value) {
    if (!isset($data[$key])) {
        $ayRequiredFields[$value] = $value;
    }
}
if (!empty($ayRequiredFields)) {
    sort($ayRequiredFields);
    //  Stampa dei campi richiesti
    $requiredFields = " ".implode(', ',array_values($ayRequiredFields));
    throw new \Exception("missing required field:".$requiredFields, 500);
}

/**
 * Some required non empty field inside uw
 */
$ayEmptyFields = [];
foreach ($opt['mapFilters']['notEmptyFields'] as $key => $value) {
    if (isset($data[$key]) AND empty($data[$key])) {
        $ayEmptyFields[$value] = $value;
    }
}
if (!empty($ayEmptyFields)) {
    sort($ayEmptyFields);
    //  Stampa dei campi richiesti mancanti, con MoodException potrei restituire l'oggetto
    $emptyFields = " ".implode(', ',array_values($ayEmptyFields));
    throw new MoodException("cannot be empty:".$emptyFields, 500, null, $ayEmptyFields);
}

/**
 * Some Unique Creation inside uw
 */

// Garantisco univocità in fase di creazione, per ora appogiata a logica DB
$code = $opt['creationPrefix'].Utils::uTS();
while ($this->db->insert($this->tblName, array($this->mapIntDb["cod"] => $code)) == false) {
    ++$code;
}

/**
 * Some import/export/translation of data
 */

foreach ($data as $fieldkey => $fieldvalue) {
    switch ($fieldkey) {
        case "field":
            // example strtoupper
            $data[$fieldkey] = strtoupper($fieldvalue);
        break;
        default:
            //  Parsing predefinito per tutti i campi
        break;
    }
}

/**
 * Some remapping/filtering inside uw
 * i.e. cannot know MapKSA to map/filter before logic operation,
 * like i gotta keep all the keys i filtered by previous operations, in this case array_keys($data) it's different defined here than in Action Driver "ad"
 * 
 */
$data['lastedit'] = date("Y-m-d H:i:s");
$data['status'] = 1;

$keep = array_keys($data);
$scrap = [];
$append = $this->getMapKeyDb(["lastedit"]);
$exportDb = $this->remapFilter($data, $this->getMapFilter($this->mapIntDb, $keep, [], $append));

/**
 * Some analisys of creation/edit of persistence
 * in this examples:
 * - false or 0 it's equal
 * - false is an error, 0 it's a permitted behaviour
 */

$res = $this->db->update($this->tblName, $exportDb, $this->mapIntDb["cod"]." = :cod", [":cod"=>$code]);
if (($res === false) or ($res == 0)) {
    $this->db->delete($this->tblName, $this->mapIntDb["cod"]." = :cod", ["cod"=>$code]);
    throw new \Exception("unable to create", 0);
} else {
    // TODO eventuali azioni da effettuare a creazione avvenuta
    $data['cod'] = $code;
    $data = $this->remapFilter($data, $opt['mapFilters']['out']);
    ksort($data);
    $unitOutput = ["created"=>(string)$res,"data"=>$data,"status"=>"OK"];
}

$res = $this->db->update($this->tblName, $data, $where, $bind);
if ($res === false) {
    throw new \Exception("internal error updating", 0);
} elseif ($res == 0) {
    throw new \Exception("unable to find object / nothing to update", 0);
} else {
    $unit_output = ["status"=>"OK","data"=>array("updated"=>(string)$res)];
}

/**
 * Valutare nella view
 * KO: sempre array per coerenza
 */
if (!empty($res[0]['cod'])) {
    $res = $res[0];
} else {
    $res = [];
}



/**
 * CustomException
 */
class CustomException extends \Exception
{

    private $_options;

    public function __construct($message, 
                                $code = 0, 
                                Exception $previous = null, 
                                $options = array('params')) 
    {
        parent::__construct($message, $code, $previous);

        $this->_options = $options; 
    }

    public function GetOptions() { return $this->_options; }
}

/**
 * Usecase
 */
try 
{
   // some code that throws new CustomException($msg, $code, $previousException, $optionsArray)
}
catch (CustomException $ex)
{
   $options = $ex->GetOptions();
   // do something with $options[]...
}

/**
 * Removed from Mood, JSON and Serialized function
 */
class Utili {
protected function jsonSetVarsByKey(array $ayKeyVars, $json){
    $ayJson = json_decode($json, true);
    foreach ($ayKeyVars as $key => $value) {
        $ayJson[$key] = $value;
    }
    return json_encode($ayJson);
}

/*
Utile a riottenere un json di valori filtrati per chiave
protected function jsonGetVarsByKey(array $ayKeys, $json){
    $ayJson = json_decode($json, true);
    foreach ($ayJson as $key => $value) {
        if (in_array($key,$ayKeys)) {
            $ayGetVars[$key] = $value;
        }
    }
    return json_encode($ayGetVars);
}
*/

protected function jsonUnsetVarsByKey(array $ayKeys, $json){
    $ayJson = json_decode($json, true);
    foreach ($ayJson as $key => $value) {
        if (in_array($key,$ayKeys)) {
            unset($ayJson[$key]);
        }
    }
    return json_encode($ayJson);
}

protected function jsonSetVarsByValue(array $ayVars, $json){
    $ayJson = json_decode($json, true);
    return json_encode(array_values(array_unique(array_merge((array)$ayJson,$ayVars), SORT_REGULAR)));
    // foreach ($ayVars as $var) {
    //     if (!in_array($var,$ayJson)) {
    //         $ayJson[] = $var;
    //     }
    // }
    // return json_encode($ayJson);
}

protected function jsonUnsetVarsByValue(array $ayVars, $json){
    $ayJson = json_decode($json, true);
    return json_encode(array_values(array_diff((array)$ayJson,$ayVars)));
    // foreach ($ayVars as $var) {
    //     if (!in_array($var,$ayJson)) {
    //         $ayJson[] = $var;
    //     }
    // }
    // return json_encode($ayJson);
}

protected function serializedSetVars(array $ayVars, $serialized){
    $ayJson = unserialize($serialized);
    foreach ($ayVars as $key => $value) {
        $ayJson[$key] = $value;
    }
    return serialize($ayJson);
}

protected function serializedGetVars(array $ayKeys, $serialized){
    $ayJson = unserialize($serialized);
    foreach ($ayJson as $key => $value) {
        if (in_array($key,$ayKeys)) {
            $ayGetVars[$key] = $value;
        }
    }
    return serialize($ayGetVars);
}
}