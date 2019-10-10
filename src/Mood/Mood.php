<?php
namespace SdotB\Mood;

use SdotB\Utils\Utils;

/**
 * Le rimappature servono a tradurre le chiavi tra i vari oggetti da e verso client e/o database
 * Int definisce la chiave che si utilizza nella logica della programamzione
 * Ext definisce la chiave che viene tradotta quando un dato arriva da o va verso il client
 * DB definisce la chiave verso il database
 * 
 * possibile variazione: IK (internal key) EK (external key) DK (database key)
 */

/**
 * Classe astratta dalla quale estendere tutte le classi relative al sistema mood
 */
abstract class Mood //implements iMoodMaps
{
    /**
     * general properties
     */
    protected $db;
    protected $tk;
    protected $ignitor;
    protected $tblPrefix = "tbl_";
    protected $tblIdentifier = "Mood";
    protected $tblName;
    protected $opt = [
        'buildSession' => false,
        'dbRequested' => false,
        'sessionDuration' => 7200,
    ];
    protected $store = [];
    protected $ayOutput = [];
    protected $session;
    protected $mapIntManifest;
    protected $mapIntInt;
    protected $mapIntExt;
    protected $mapExtInt;
    protected $mapIntDb;
    protected $mapExtDb;

    /**
     * $opt parameters:
     * buildSession -> [true / false] instantiate or get the session at invoke time, optional
     * sessionDuration -> [7200] time of session duration
     */

    /**
     * Arhivio LOG di esecuzione dell'istanza, dove colleziono info di debug
     * formato standard: array di oggetti chiave => valore
     * chiave = mTS
     * valore = qualsiasi tipo
     */
    public $logs = [];

    //  Potrei unificare tutti questi metodi di creazione mappe per non ciclare più volte lo stesso array ma non potrei overraidarne solo uno per volta
    //  Genero mappatura manifest per operazioni su tabella
    //  TODO    DA RIVEDERE QUESTO METODO IN BASE ALL'IMPLEMENTAZIONE DI c_abFieldMgr
    protected function getMapIntManifest(array $fieldsMap){
        $mapIntManifest = [];
        foreach ($fieldsMap as $keyInt => $value) {
            $mapIntManifest[$keyInt] = (empty($value[1])) ? [] : $value[1];   //  Se uso i valori di default non avrò $value[1], qui non serve questo metodo
        }
        return $mapIntManifest;
    }
    //  Genero mappatura campi interni duplicati, utile per filtrare
    protected function getMapIntInt(array $fieldsMap): array {
        $mapIntInt = [];
        foreach($fieldsMap as $keyInt => $value){
            $mapIntInt[$keyInt] = $keyInt;
        }
        return $mapIntInt;
    }
    //  Genero mappatura tra campi interni e campi estarni
    protected function getMapIntExt(array $fieldsMap): array {
        $mapIntExt = [];
        foreach($fieldsMap as $keyInt => $value){
            $mapIntExt[$keyInt] = $value[0];
        }
        return $mapIntExt;
    }

    /**
     * Genera correlazione tra chiavi oggetto (Int o Ext) e chiavi campi database
     * se passo in ingresso mapIntInt int -> db
     * se passo in ingresso mapIntExt ext -> db
     */
    protected function getMapDb(array $inMap): array {
        $outMap = [];
        foreach ($inMap as $keyDB => $keyForeign) {
            $outMap[$keyForeign] = $this->tblIdentifier."_".$keyDB;
        }
        return $outMap;
    }
    /**
     * Prepara l'array chiave->valore corretto partendo da una lista di campi
     * se passo [Int] genera mapIntInt e successivamente int -> db
     */
    protected function getMapKeyDb(array $inMap): array {
        $outMap = [];
        foreach ($inMap as $keyDB => $keyForeign) {
            $outMap[$keyForeign] = $keyForeign;
        }
        return $this->getMapDb($outMap);
    }
   
    //  Ottengo un filtro sui campi di cui ho bisogno
    protected function getMapKSA(array $map = [], array $ay_keep = [], array $ay_scrap = [], array $ay_append = []): array {
        //  Se non passo mappa inizializza array vuoto
        if (empty($map)){$map = [];}
        //  Keep:   quali campi mantenere della mappa passata in ingresso
        if (!empty($ay_keep)){
            $mapFilter = [];
            foreach($ay_keep as $item){
                if(isset($map[$item])){$mapFilter[$item] = $map[$item];}
            }
        } else {
            $mapFilter = $map;
        }
        //  Scrap:   quali campi scartare della mappa residua
        if (!empty($ay_scrap)){
            foreach($ay_scrap as $item){
                unset($mapFilter[$item]);
            }
        }
        //  Append: quali campi aggiungere alla mappa
        if (!empty($ay_append)){
            foreach($ay_append as $key => $value){
                $mapFilter[$key] = $value;
            }
        }
        return $mapFilter;
    }

    // Alias di getMapKSA
    protected function getMapFilter(array $map = [], array $ay_keep = [], array $ay_scrap = [], array $ay_append = []){
        return $this->getMapKSA($map, $ay_keep, $ay_scrap, $ay_append);
    }

    //  Dato un ay[oldkey] e una mappa in ingresso oldkey=>newkey, sostituisce ove corrispondenza ay[oldkey] con ay[newkey]
    protected function remapFilter(array $data = [], array $map = []){
        $remapFilter = [];
        foreach ($map as $oldkey => $newkey){
            if(isset($data[$oldkey])){
                $remapFilter[$newkey] = $data[$oldkey];
            }
        }
        return $remapFilter;
    }

    /**
     * genera una stringa alias sql da una mappa di chiavi
     * IntDB per risultati query con chiavi interne
     * ExtDB per risultati query con chiavi esterne
     */
    protected function getAliasDB(array $inMap): string {
        $aliasQueryStr = "";
        foreach($inMap as $keyForeign => $keyDB){
            $aliasQueryStr .= $keyDB." AS ".$keyForeign.", ";
        }
        return substr($aliasQueryStr, 0, -2);
    }

    //  ------------------------------------------------------------------------------  //

    /**
     * Se ignitor contiene un istanza di mood usa quella, altrimenti riceve in ingresso i parametri singoli di mood
     */
    public function __construct(array $ignitor){
        $this->db = (!empty($ignitor['db']) && ($ignitor['db'] instanceof DB)) ? $ignitor['db']: "";
        $this->tk = (!empty($ignitor['tk']) && is_string($ignitor['tk'])) ? (string)$ignitor['tk']: "";

        if ($this->opt['dbRequested'] == true) {
            if (!($this->db instanceof DB)) {
                throw new MoodException("DB Instance requested", 0);
            }
        }
        
        $this->setIgnitor();
        
        $this->tblIdentifier = "reg".substr(static::class,strrpos(static::class, '\\')+1)."s";
        $this->tblName = $this->tblPrefix.$this->tblIdentifier;

        $this->__init();

        // Mappature Nomi Campi
        //  $this->mapIntManifest = $this->getMapIntManifest($this->fieldsMap);     //Questa mappatura la uso solo quando devo operare sulla tabella, qui non serve credo
        $this->mapIntInt = $this->getMapIntInt($this->fieldsMap);
        $this->mapIntExt = $this->getMapIntExt($this->fieldsMap);
        $this->mapExtInt = array_flip($this->mapIntExt);    //  Da POST a Interni
        $this->mapIntDb = $this->getMapDb($this->mapIntInt); //  Da Interni a DataBase
        $this->mapExtDb = $this->getMapDb($this->mapIntExt); //  Da POST a DataBase

        //  Istanzia o meno per default la sessione per tutta la classe, nelle varie actions posso decidere se istanziare la sessione se non la trovo o se non cagarla se non serve
        if(defined(SESSION_DURATION_TIME)) $this->opt['sessionDuration'] = (int)SESSION_DURATION_TIME;
        if ($this->opt['buildSession'] === true){
            $this->session = new DBSession($this->tk,$this->opt['sessionDuration'],$this->db);
        }
    }

    /**
     * Inizializzazione generale chiamata da __construct
     */
    protected function __init(){
        $this->init();        
    }

    //  Implements init fucntion in childs
    protected function init(){
        //  Override in child for implementations
    }

    protected function setIgnitor(): void {
        $this->ignitor = [
            'db' => $this->db,
            'tk' => $this->tk,
        ];
    }

    //  Method used to clear session if posted a request that need no session active
    protected function sessionDestroyIfExist(){
        if($this->tk != ""){		// Ho postato un token, lo cerco e se esiste distruggo la sessione
            if (!($this->session instanceof DBSession)) $this->session = new DBSession($this->tk,$this->opt['sessionDuration'],$this->db);
            $this->session->destroy();
            unset($this->session);
            $this->tk = "";
        }
    }

    protected function parseParams(array $params = []): array {
        //  Parsing e settings dei valori params di default
        if (empty($params['callFrom'])) {
            $params['callFrom'] = 'external';
        }
        if (empty($params['mapInput'])) {
            $params['mapInput'] = $params['callFrom'];
        }
        if (empty($params['mapOutput'])) {
            $params['mapOutput'] = $params['callFrom'];
        }
        switch ($params['mapInput']) {
            case 'internal':
                $params['mapInput'] = 'mapIntInt';
            break;
            case 'external':
                $params['mapInput'] = 'mapIntExt';
            break;
            default:
                throw new MoodException("Invalid input map filter", 400);
            break;
        }
        switch ($params['mapOutput']) {
            case 'internal':
                $params['mapOutput'] = 'mapIntInt';
            break;
            case 'external':
                $params['mapOutput'] = 'mapIntExt';
            break;
            default:
                throw new MoodException("Invalid output map filter", 400);
            break;
        }
        return $params;
    }

    protected function mysesExtend($callFrom){
        if ($callFrom == 'internal') {
            return true;
        }
        //  Controllo ulteriore di sessione instanziata
        if (!is_a($this->session, 'Mood\DBSession')) {
            $dbt=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,2);
            $class = static::class;
            $caller = isset($dbt[1]['function']) ? $class.'::'.$dbt[1]['function'] : $class;
            $this->verb([$caller.':noSession' => 'No session found, tk: '.$this->tk]);
            $this->session = new DBSession($this->tk, $this->opt['sessionDuration'], $this->db);
        }
        return $this->session->extend();
    }

    abstract public function permittedActions();

    public function verb($log = null): array {
        if (!is_null($log)) {
            $this->logs[Utils::mTS()] = $log;
            usleep(1000);
        }
        return (array)$this->logs;
    }

    protected function tableManager($action){
        $driverType = $this->db->getAttribute(\PDO::ATTR_DRIVER_NAME);
        switch ($driverType) {
            case 'mysql':
                $tableMgr = new TableManagerMysql($this->db);
                break;
            case 'sqlite':
                $tableMgr = new TableManagerSQLite($this->db);
                break;
            default:
                throw new MoodException(__METHOD__." ## Unable to know DB driver type for ".$driverType, 1);
                break;
        }
        //  Ottengo mappatura manifest per operare sulla tabella
        //  TODO da aggiungere altro campo per options 'cod' => ['cod',['number',['lenght' => 20]],['options']],
        //var_dump($this->fieldsMap);
        $this->mapIntManifest = $this->getMapIntManifest($this->fieldsMap);

        //var_dump($this->mapIntManifest);

        //  Una volta instanziato oggetto devo fagli fare l'azione (ricalcare gate.php per come muoversi)
        return $tableMgr->$action($this->tblPrefix,$this->tblIdentifier,$this->mapIntManifest);

    }

}

?>
