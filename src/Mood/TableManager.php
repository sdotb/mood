<?php
namespace SdotB\Mood;
    /**
    *
    *   Per l'utilizzo della classe deve essere necessario chiamare il metodo tableCreate passando nome della tabella e mappa campi composta da [nomecampo => [$type,$attributes,$options]]
    *   Esempio: ['nome'=>['string',['lenght' => 40]],'saldo_euro_messaggi'=>['number',['lenght' => 8,'decimals'=>4]]] ??? in JSON ???
    *
    *   TODO    Istanziare la classe passando l'istanza di connessione al DB $db, in base a db capisce per quale db creare le query
    *           Inglobare questa classe assieme alla gestione del db (in modo da ottenere metodi standard di creazione tabelle e campi)
    *
    */

abstract class TableManager{
    protected $manifest = [
            'string' => ['lenght' => ['integer',64]],  //  Valutare se rimuovere il campo 0 e utilizzare il gettype del valore per determinare il tipo
            'number' => ['lenght' => ['integer',11],'decimals' => ['integer',0],'floating' => ['boolean',false,[true,false]]],
            'boolean' => ['stringified' => ['boolean',false,[true,false]]],
            'date' => ['type' => ['string','timestamp',['date','time','full','timestamp']]]
        ];
    protected $db;
    protected $driverType;
    protected $tblName, $tblPrefix, $tblIdentifier;
    protected $fieldType = "";
    protected $fieldAttributes = [];
    protected $fieldOptions = []; //  Ad esempio AUTO_INCREMENT - COMMENT 'commento' - DEFAULT '1'

    public function __construct ($db){
        $this->db = $db;
        $this->driverType = $this->db->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $this->checkDriver();
    }

    abstract protected function checkDriver();

    public function create ($tblPrefix, $tblIdentifier, $tblMap, $overwrite = false) {
        $ay_output = [];
        try {
            $tblName = $tblPrefix.$tblIdentifier;
            if ($overwrite === true) {
                $dropResult = $this->tableDrop($tblName);
            }
            // Setto attributi e options di default alla mappa passata
            $defaultMaps = $this->fieldsListDefaults();
            foreach ($tblMap as $key => $value) {
                if ($value == []) {
                    $tblMap[$key] = array_merge($defaultMaps[$key]['attr'],$defaultMaps[$key]['opt']);
                    //$tblMap[$key][] = $defaultMaps[$key]['opt'];
                }
            }
            //var_dump($tblMap);
            $ay_output = $this->tableCreate($tblName,$tblIdentifier,$tblMap);
        } catch (MoodException $e) {
            $ay_output = ["status"=>"KO","scod"=>(string)$e->getCode(),"msg"=>static::class."::".__FUNCTION__."##".$e->getMessage()];
        }
        return $ay_output;
    }

    public function drop ($tblName) {
        $ay_output = [];
        try {
            $ay_output = $this->tableDrop($tblName);
        } catch (MoodException $e) {
            $ay_output = ["status"=>"KO","scod"=>(string)$e->getCode(),"msg"=>static::class."::".__FUNCTION__."##".$e->getMessage()];
        }
        return $ay_output;
    }

    public function truncate ($tblName) {
        $ay_output = [];
        try {
            $ay_output = $this->tableTruncate($tblName);
        } catch (MoodException $e) {
            $ay_output = ["status"=>"KO","scod"=>(string)$e->getCode(),"msg"=>static::class."::".__FUNCTION__."##".$e->getMessage()];
        }
        return $ay_output;
    }

    abstract protected function tableCreate ($tblName,$tblIdentifier,$tblMap);

    abstract protected function tableDrop ($tblName);

    abstract protected function tableTruncate ($tblName);

    protected function filterFieldDefinition($type = 'string', array $attributes, array $options = []){
        $type = (string)$type;
        if (!in_array($type, array_keys($this->manifest))) throw new MoodException("FieldType not supported: $type", 1);
        $attributes = array_intersect_key($attributes, $this->manifest[$type]);
        foreach ($attributes as $key => $value) {
            switch ($this->manifest[$type][$key][0]) {
                case 'boolean':
                    if ((mb_strtolower($attributes[$key]) === 'true') OR ($attributes[$key] === true))  {
                        $value = (bool)true;
                    } elseif ((mb_strtolower($attributes[$key]) === 'false') OR ($attributes[$key] === false)) {
                        $value = (bool)false;
                    }
                    break;
                /*case 'string':
                    //  Con la data dovrò parsare correttamente il dato in ingresso??

                    break;
                case 'number':
                    //  Con la data dovrò parsare correttamente il dato in ingresso??

                    break;
                case 'date':
                print("\nfilterFieldDefinition date\n");
                    //  Con la data dovrò parsare correttamente il dato in ingresso??

                    break;*/
                default:
                    settype($value,$this->manifest[$type][$key][0]);
                    $attributes[$key] = $value;
                    break;
            }
            if (gettype($attributes[$key]) != $this->manifest[$type][$key][0]) {
                throw new MoodException("Unable to cast correct datatype in ".$type."[".$key."]", 1);
            }
            //var_dump($this->manifest[$type][$key][2],$attributes[$key]);
            if (!empty($this->manifest[$type][$key][2]) AND is_array($this->manifest[$type][$key][2]) AND !in_array($attributes[$key],$this->manifest[$type][$key][2],true)) {
                throw new MoodException("Value not permitted for ".$type."[".$key."]", 1);
            }
        }
        //  prepara array valori di default per la creazione del campo, da sostituire con quelli che ho
        //  Se non indico [] attributi nella mappa allora uso quelli di default, se li indico male o incompleti o non ho il default settato allora ho questa
        //  ultima spiaggia nella quale setto il default relativo al tipo di campo (indicati nel manifest)
        foreach ($this->manifest[$type] as $key => $value) {
            $defaults[$key] = $value[1];
        }
        $attributes = array_replace_recursive($defaults, $attributes);

        //  Terminare la gestione delle options e tornare array con attributes e options
        return [$attributes,$options];
    }

    abstract protected function getFieldDefinition($type, array $attributes, array $options);

    protected function fieldsListDefaults (){
        return [
            'address' => ['attr'=>['string',['lenght'=>128]],'opt'=>[]],
            'addressnumber' => ['attr'=>['string',['lenght'=>16]],'opt'=>[]],
            'addressprefix' => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            'aliases' => ['attr'=>['string',['lenght'=>4096]],'opt'=>[]],
            "bank" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "barcode" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "brand" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "businessname" => ['attr'=>['string',['lenght'=>128]],'opt'=>[]],
            "businesssector" => ['attr'=>['string',['lenght'=>16]],'opt'=>[]],
            "carrierbarcode" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "cc" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "city" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "cod" => ['attr'=>['string',['lenght'=>32]],'opt'=>["COMMENT 'identificativo univoco principale'"]],
            "code" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "codalfa" => ['attr'=>['string',['lenght'=>64]],'opt'=>["COMMENT 'in caso di codice alfanumerico'"]],
            "codBranch" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "codUser" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "codHolder" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "codLocation" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "codTour" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "codPFK" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "codSFK" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "codTFK" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "companyname" => ['attr'=>['string',['lenght'=>128]],'opt'=>[]],
            "country" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "customdata" => ['attr'=>['string',['lenght'=>65535]],'opt'=>[]],
            "notes" => ['attr'=>['string',['lenght'=>65535]],'opt'=>[]],
            "pictures" => ['attr'=>['string',['lenght'=>65535]],'opt'=>[]],
            "vocals" => ['attr'=>['string',['lenght'=>65535]],'opt'=>[]],
            "datefrom" => ['attr'=>['date',['type'=>"full"]],'opt'=>[]],
            "dateto" => ['attr'=>['date',['type'=>"full"]],'opt'=>[]],
            "deliverystatus" => ['attr'=>['string',['lenght'=>16]],'opt'=>[]],
            "deliveryname" => ['attr'=>['string',['lenght'=>128]],'opt'=>[]],
            "deliveryaddress" => ['attr'=>['string',['lenght'=>128]],'opt'=>[]],
            "deliverynumber" => ['attr'=>['string',['lenght'=>16]],'opt'=>[]],
            "deliveryzip" => ['attr'=>['string',['lenght'=>16]],'opt'=>[]],
            "deliverycity" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "deliveryprovince" => ['attr'=>['string',['lenght'=>16]],'opt'=>[]],
            "deliveryregion" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "deliverycountry" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "deliverycustomdata" => ['attr'=>['string',['lenght'=>4096]],'opt'=>[]],
            "deliverfromdate" => ['attr'=>['date',['type'=>"date"]],'opt'=>[]],
            "delivertodate" => ['attr'=>['date',['type'=>"date"]],'opt'=>[]],
            "deliverts" => ['attr'=>['date',['type'=>"timestamp"]],'opt'=>[]],
            "depth" => ['attr'=>['number',['lenght'=>16,'decimals'=>4]],'opt'=>[]],
            "description" => ['attr'=>['string',['lenght'=>128]],'opt'=>[]],
            "deviceid" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "docuid" => ['attr'=>['string',['lenght'=>64]],'opt'=>["COMMENT 'contiene id univoco del documento (Codice Fiscale o altri)'"]],
            "email" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "emailpec" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "fax" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "filename" => ['attr'=>['string',['lenght'=>1024]],'opt'=>[]],
            "firstname" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "fiscalcode" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "height" => ['attr'=>['number',['lenght'=>16,'decimals'=>4]],'opt'=>[]],
            "holdercod" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "iban" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "id" => ['attr'=>['number',['lenght'=>11]],'opt'=>["AUTO_INCREMENT"]],
            "isparent" => ['attr'=>['number',['lenght'=>1]],'opt'=>[]],
            "isparentcod" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "isparentalfa" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "internalnote" => ['attr'=>['string',['lenght'=>65535]],'opt'=>[]],
            "internalstatus" => ['attr'=>['number',['lenght'=>1]],'opt'=>["DEFAULT '1'"]],
            "lastdeliverystatus" => ['attr'=>['string',['lenght'=>16]],'opt'=>[]],
            "lastedit" => ['attr'=>['date',['type'=>"full"]],'opt'=>[]],
            "lastholdercod" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "lastlocationcod" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "lastname" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "laststatus" => ['attr'=>['string',['lenght'=>16]],'opt'=>[]],
            "lat" => ['attr'=>['number',['lenght'=>12,'decimals'=>9]],'opt'=>[]],
            "lon" => ['attr'=>['number',['lenght'=>12,'decimals'=>9]],'opt'=>[]],
            "lifetime" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "locationcod" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "mobile" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "model" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "name" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "note" => ['attr'=>['string',['lenght'=>4096]],'opt'=>[]],
            "ordercod" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "ordernumber" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "ordernotes" => ['attr'=>['string',['lenght'=>65535]],'opt'=>[]],
            "orderpictures" => ['attr'=>['string',['lenght'=>65535]],'opt'=>[]],
            "ordervocals" => ['attr'=>['string',['lenght'=>65535]],'opt'=>[]],
            "parentcod" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "parentalfa" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "password" => ['attr'=>['string',['lenght'=>512]],'opt'=>[]],
            "passworddefault" => ['attr'=>['string',['lenght'=>512]],'opt'=>["DEFAULT 'b109f3bbbc244eb82441917ed06d618b9008dd09b3befd1b5e07394c706a8bb980b1d7785e5976ec049b46df5f1326af5a2ea6d103fd07c95385ffab0cacbc86'"]],
            "peoplecount" => ['attr'=>['number',['lenght'=>11]],'opt'=>[]],
            "permissions" => ['attr'=>['string',['lenght'=>2048]],'opt'=>[]],
            'pickupname' => ['attr'=>['string',['lenght'=>128]],'opt'=>[]],
            "pickupaddress" => ['attr'=>['string',['lenght'=>128]],'opt'=>[]],
            "pickupnumber" => ['attr'=>['string',['lenght'=>16]],'opt'=>[]],
            "pickupzip" => ['attr'=>['string',['lenght'=>16]],'opt'=>[]],
            "pickupcity" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "pickupprovince" => ['attr'=>['string',['lenght'=>16]],'opt'=>[]],
            "pickupregion" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "pickupcountry" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "pickupcustomdata" => ['attr'=>['string',['lenght'=>4096]],'opt'=>[]],
            "phone" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "phonemobile" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "phoneinternal" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "plate" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "position" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "price" => ['attr'=>['number',['lenght'=>16,'decimals'=>4]],'opt'=>[]],
            "pricetotal" => ['attr'=>['number',['lenght'=>16,'decimals'=>4]],'opt'=>[]],
            "pricevat" => ['attr'=>['number',['lenght'=>16,'decimals'=>4]],'opt'=>[]],
            "province" => ['attr'=>['string',['lenght'=>16]],'opt'=>[]],
            "publicnote" => ['attr'=>['string',['lenght'=>65535]],'opt'=>[]],
            "publicstatus" => ['attr'=>['number',['lenght'=>1]],'opt'=>[]],
            "receivername" => ['attr'=>['string',['lenght'=>128]],'opt'=>[]],
            "recipient" => ['attr'=>['string',['lenght'=>512]],'opt'=>[]],
            "recipientcontact" => ['attr'=>['string',['lenght'=>128]],'opt'=>[]],
            "recipientphone" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "recipientmobile" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "recipienttimefrom" => ['attr'=>['date',['type'=>"time"]],'opt'=>[]],
            "recipienttimeto" => ['attr'=>['date',['type'=>"time"]],'opt'=>[]],
            "referencedate" => ['attr'=>['date',['type'=>"date"]],'opt'=>[]],
            "regid" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "returnflycode" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "returnflyfrom" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "returnflydatetime" => ['attr'=>['date',['type'=>"full"]],'opt'=>[]],
            "revisiontime" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "sdi" => ['attr'=>['string',['lenght'=>16]],'opt'=>[]],
            "senderbarcode" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "services" => ['attr'=>['string',['lenght'=>65535]],'opt'=>[]],
            "settings" => ['attr'=>['string',['lenght'=>2048]],'opt'=>[]],
            "sex" => ['attr'=>['string',['lenght'=>16]],'opt'=>[]],
            "shippingcod" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "status" => ['attr'=>['number',['lenght'=>1]],'opt'=>[]],
            'statuses' => ['attr'=>['string',['lenght'=>4096]],'opt'=>[]],
            "statusfe" => ['attr'=>['number',['lenght'=>1]],'opt'=>[]],
            "sourceholdercod" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "sourcename" => ['attr'=>['string',['lenght'=>256]],'opt'=>[]],
            "tag" => ['attr'=>['string',['lenght'=>1024]],'opt'=>[]],
            "type" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "timestamp" => ['attr'=>['date',['type'=>'timestamp']],'opt'=>["DEFAULT CURRENT_TIMESTAMP"]],
            "tkrbarcode" => ['attr'=>['string',['lenght'=>128]],'opt'=>[]],
            "tkrordernumber" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "tkrtrackingnumber" => ['attr'=>['string',['lenght'=>128]],'opt'=>[]],
            "totalparcels" => ['attr'=>['number',['lenght'=>11]],'opt'=>[]],
            "tourlist" => ['attr'=>['string',['lenght'=>4096]],'opt'=>[]],
            "tournumber" => ['attr'=>['string',['lenght'=>16]],'opt'=>[]],
            "trackingnumber" => ['attr'=>['string',['lenght'=>128]],'opt'=>[]],
            "usercod" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "userdata" => ['attr'=>['string',['lenght'=>65535]],'opt'=>[]],
            "username" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "vatnumber" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "vehiclecod" => ['attr'=>['string',['lenght'=>32]],'opt'=>[]],
            "vehicledata" => ['attr'=>['string',['lenght'=>65535]],'opt'=>[]],
            "volume" => ['attr'=>['number',['lenght'=>16,'decimals'=>4]],'opt'=>[]],
            "volweight" => ['attr'=>['number',['lenght'=>16,'decimals'=>4]],'opt'=>[]],
            "website" => ['attr'=>['string',['lenght'=>64]],'opt'=>[]],
            "weight" => ['attr'=>['number',['lenght'=>16,'decimals'=>4]],'opt'=>[]],
            "width" => ['attr'=>['number',['lenght'=>16,'decimals'=>4]],'opt'=>[]],
            "zip" => ['attr'=>['string',['lenght'=>16]],'opt'=>[]],
        ];
    }
}
?>
