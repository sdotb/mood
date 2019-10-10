<?php
namespace SdotB\Mood;

use SdotB\Utils\Utils;

class DBSession {

    public $id;
    public $duration;
    public $ayLogs = [];
    public $db;

    // Costruttore della classe, inizializza le variabili
    public function __construct($id = "", $time = 3600, $db){
        $this->id = ($id == "") ? md5(uniqid(microtime().mt_rand())) : $id;
        $this->duration = $time;
        $this->db = $db;
    }

    // Avvia o aggiorna la sessione
    function set(){
        $current_time = time();
        $expire_time = $current_time + $this->duration;
        $res = $this->db->run("SELECT session_id FROM tbl_sessions WHERE session_id = '{$this->id}'");
        if(count($res)==0){
            $this->db->run("INSERT INTO tbl_sessions (session_id, session_vars, session_date, session_expire, session_lastedit) VALUES ('{$this->id}','',$current_time,$expire_time,'".date("Y-m-d H:i:s")."')");
            // TODO: implementare Gestione Errore su insert dovuto a Chiave Unica
        } else {
            $this->db->run("UPDATE tbl_sessions SET session_expire = $expire_time, session_lastedit = '".date("Y-m-d H:i:s")."' WHERE session_id = '{$this->id}'");
        }
    }

    // Verifico se la sessione esiste e la prolungo
    function extend()
    {
        $this->gc();
        $this->verb([__METHOD__.':id' => $this->id]);
        $res = $this->db->run("SELECT session_id FROM tbl_sessions WHERE session_id = '{$this->id}'");
        if ($res === false) {
            throw new \Exception("Session Internal Error", 500);
        }
        if(count($res)==0){
            $this->verb([__METHOD__.':res' => 'session_id not found']);
            return false;
        } else {
            $current_time = time();
            $expire_time = $current_time + $this->duration;
            $this->db->run("UPDATE tbl_sessions SET session_expire = $expire_time, session_lastedit = '".date("Y-m-d H:i:s")."' WHERE session_id = '{$this->id}'");
            $this->verb([__METHOD__.':res' => 'extended: '.$expire_time]);
            return true;
        }
    }

    // Verifico se la sessione esiste ed è ancora valida, non influisce sulla sessione
    public function alive(): bool
    {
        $this->gc();
        $res = $this->db->run("SELECT session_id FROM tbl_sessions WHERE session_id = '{$this->id}'");
        if ($res === false) {
            throw new \Exception("Session Internal Error", 500);
        }
        if(count($res) == 0){
            return false;
        } else {
            return true;
        }
    }

    /* Registra una variabile nel db, sostituisce valore se lo trova (chiave,valore) */
    function var_set($key,$value)
    {
        $vars = array();
        $res = $this->db->run("SELECT session_vars FROM tbl_sessions WHERE session_id = '{$this->id}'");
        if(count($res) > 0){
            $vars = unserialize($res[0]['session_vars']);
        }
        $vars[$key]=$value;
        $this->db->run("UPDATE tbl_sessions SET session_vars = '".serialize($vars)."', session_lastedit = '".date("Y-m-d H:i:s")."' WHERE session_id = '{$this->id}'");
        // TODO: return true o false in base al risultato
    }

    /* Legge e restituisce una variabile dal db, false se non la trova (chiave) */
    function var_get($key){
        $vars = array();
        $res = $this->db->run("SELECT session_vars FROM tbl_sessions WHERE session_id = '{$this->id}'");
        if(count($res) > 0){
            $vars = unserialize($res[0]['session_vars']);
        }
        if(isset($vars[$key]) || array_key_exists($key,$vars)){
            return $vars[$key];
        } else {
            return false;
        }
        // Return false se non esiste session_vars
    }

    /**
     * Registra un array di variabili nel db,
     * override true sostituisce per intero l'array,
     * false sostituisce le chiavi che trova e crea le altre (ay(chiave=>valore))
     */
    function ayvar_set(array $ay, $override = true)
    {
        if($override){
            $this->db->run("UPDATE tbl_sessions SET session_vars = '".serialize($ay)."' WHERE session_id = '{$this->id}'");
        } else {
            $vars = array();
            $res = $this->db->run("SELECT session_vars FROM tbl_sessions WHERE session_id = '{$this->id}'");
            if(count($res) > 0){
                $vars = unserialize($res[0]['session_vars']);
            }
            foreach($ay as $key => $value){
                $vars[$key]=$value;
            }
            $this->db->run("UPDATE tbl_sessions SET session_vars = '".serialize($vars)."', session_lastedit = '".date("Y-m-d H:i:s")."' WHERE session_id = '{$this->id}'");
        }
        // Return true o false in base al risultato
    }

    /* Legge e restituisce l'array delle variabili dal db, false se non trova nulla () */
    function ayvar_get()
    {
        $res = $this->db->run("SELECT session_vars FROM tbl_sessions WHERE session_id = '{$this->id}'");
        if(count($res) > 0){
            return unserialize($res[0]['session_vars']);
        } else {
            return false;
        }
    }

    /* Restituisce una variabile dal db solo se trova corrispondenza di valore di un'altra chiave passata in ingresso */
    function var_get_if_var_set($key,$var_key,$var_value)
    {
        if($this->var_get($var_key) == $var_value) {
            return $this->var_get($key);
        } else {
            return false;
        }
    }

    /*   distrugge la sessione, rimuovendo i relativi dati (non cancella il record) */
    function destroy(): void
    {
       $this->db->run("UPDATE tbl_sessions SET session_vars = '', session_lastedit = '".date("Y-m-d H:i:s")."' WHERE session_id = '{$this->id}'");
    }

    // procedura di garbage collection
    function gc(): void
    {
       $this->db->run("DELETE FROM tbl_sessions WHERE session_vars = '' OR session_expire < ".time());
    }

    //  restituisce l'array dei logs
    function verb($log = null): array
    {
        if (!is_null($log)) {
            $this->ayLogs[Utils::mTS()] = $log;
        }
        return (array)$this->ayLogs;
    }

    function createtable($db): void
    {
        // Query per creare Tabella
        $query = "
            CREATE TABLE IF NOT EXISTS `tbl_sessions` (
              `session_id` varchar(32) NOT NULL DEFAULT '',
              `session_vars` mediumtext ,
              `session_date` bigint(20) unsigned NOT NULL DEFAULT '0',
              `session_expire` bigint(20) unsigned NOT NULL DEFAULT '0',
              `session_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `session_lastedit` datetime NOT NULL,
              `session_status` tinyint(1) NOT NULL DEFAULT '1',
              UNIQUE KEY `session_id` (`session_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";
        $this->db->run($query);
    }
}
