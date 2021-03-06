<?php
namespace SdotB\Mood;

use SdotB\Mood\MoodException;

class DB extends \PDO {
    private $error;
    private $sql;
    private $bind;
    private $errorCallbackFunction;
    private $errorMsgFormat;
    private $fetchType; // not already in use

    public function __construct($dsn, $user="", $passwd="", $options=[]) {
        $options = $options + [
            \PDO::ATTR_PERSISTENT => true,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        ];

        try {
            parent::__construct($dsn, $user, $passwd, $options);
        } catch (\PDOException $e) {
            if(isset($this)){
                $this->error = $e->getMessage();
            } else {
                // error manager if DB object is not already instantiated
                throw new \PDOException($e->getMessage());
            }
        }
    }

    private function debug() {
        if(!empty($this->errorCallbackFunction)) {
            $error = ["Error" => $this->error];
            if(!empty($this->sql))
                $error["SQL Statement"] = $this->sql;
            if(!empty($this->bind))
                $error["Bind Parameters"] = trim(print_r($this->bind, true));

            $backtrace = debug_backtrace();
            if(!empty($backtrace)) {
                foreach($backtrace as $info) {
                    if($info["file"] != __FILE__)
                        $error["Backtrace"] = $info["file"] . " at line " . $info["line"];
                }
            }

            $msg = "";
            if($this->errorMsgFormat == "html") {
                if(!empty($error["Bind Parameters"]))
                    $error["Bind Parameters"] = "<pre>" . $error["Bind Parameters"] . "</pre>";
                $css = trim(file_get_contents(dirname(__FILE__) . "/css/error.css"));
                $msg .= '<style type="text/css">' . "\n" . $css . "\n</style>";
                $msg .= "\n" . '<div class="db-error">' . "\n\t<h3>SQL Error</h3>";
                foreach($error as $key => $val)
                    $msg .= "\n\t<label>" . $key . ":</label>" . $val;
                $msg .= "\n\t</div>\n</div>";
            }
            elseif($this->errorMsgFormat == "text") {
                $msg .= "SQL Error\n" . str_repeat("-", 50);
                foreach($error as $key => $val)
                    $msg .= "\n\n$key:\n$val";
            } else {
                throw new MoodException("Error Processing Request", 500, null, ['error'=>$error]);
            }

            $func = $this->errorCallbackFunction;
            $func($msg);
        }
    }

    public function delete($table, $where, $bind="") {
        $sql = "DELETE FROM " . $table . " WHERE " . $where . ";";
        $this->run($sql, $bind);
    }

    private function filter($table, $info) {
        $driver = $this->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if($driver == 'sqlite') {
            $sql = "PRAGMA table_info('" . $table . "');";
            $key = "name";
        }
        elseif($driver == 'mysql') {
            $sql = "DESCRIBE " . $table . ";";
            $key = "Field";
        }
        else {
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = '" . $table . "';";
            $key = "column_name";
        }

        if(false !== ($list = $this->run($sql))) {
            $fields = [];
            foreach($list as $record)
                $fields[] = $record[$key];
            return array_values(array_intersect($fields, array_keys($info)));
        }
        return [];
    }

    private function cleanup($bind) {
        if(!is_array($bind)) {
            if(!empty($bind))
                $bind = [$bind];
            else
                $bind = [];
        }
        return $bind;
    }

    public function insert($table, $info) {
        $fields = $this->filter($table, $info);
        $sql = "INSERT INTO " . $table . " (" . implode( ", ", $fields) . ") VALUES (:" . implode(", :", $fields) . ");";
        $bind = [];
        foreach($fields as $field)
            $bind[":$field"] = $info[$field];
        return $this->run($sql, $bind);
    }

    public function run($sql, $bind="", $fetchType = \PDO::FETCH_ASSOC) {
        $this->sql = trim($sql);
        $this->bind = $this->cleanup($bind);
        $this->error = "";

        try {
            $pdostmt = $this->prepare($this->sql);
            if($pdostmt->execute($this->bind) !== false) {
                if(preg_match("/^(" . implode("|", ["select", "describe", "pragma"]) . ") /i", $this->sql))
                    return $pdostmt->fetchAll($fetchType);
                elseif(preg_match("/^(" . implode("|", ["delete", "insert", "update"]) . ") /i", $this->sql))
                    return $pdostmt->rowCount();
                else return true;
            }
        } catch (\PDOException $e) {
            $this->error = $e->getMessage();
            //$this->debug();
            return false;
        }
    }

    public function select($table, $where="", $bind="", $fields="*", $fetchType = \PDO::FETCH_ASSOC) {
        $sql = "SELECT " . $fields . " FROM " . $table;
        if(!empty($where))
            $sql .= " WHERE " . $where;
        $sql .= ";";
        return $this->run($sql, $bind, $fetchType);
    }

    public function setErrorCallbackFunction($errorCallbackFunction, $errorMsgFormat="html") {
        //Variable functions for won't work with language constructs such as echo and print, so these are replaced with print_r.
        if(in_array(strtolower($errorCallbackFunction), ["echo", "print"]))
            $errorCallbackFunction = "print_r";

        if(function_exists($errorCallbackFunction)) {
            $this->errorCallbackFunction = $errorCallbackFunction;
            if(!in_array(strtolower($errorMsgFormat), ["html", "text"]))
                $errorMsgFormat = "html";
            $this->errorMsgFormat = $errorMsgFormat;
        }
    }

    public function update($table, $info, $where, $bind="") {
        $fields = $this->filter($table, $info);
        $fieldSize = sizeof($fields);

        $sql = "UPDATE " . $table . " SET ";
        for($f = 0; $f < $fieldSize; ++$f) {
            if($f > 0)
                $sql .= ", ";
            $sql .= $fields[$f] . " = :update_" . $fields[$f];
        }
        $sql .= " WHERE " . $where . ";";

        $bind = $this->cleanup($bind);
        foreach($fields as $field)
            $bind[":update_$field"] = $info[$field];

        return $this->run($sql, $bind);
    }
}
