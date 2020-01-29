<?php
namespace  SdotB\Mood;
//  Implementare una classe che mi ritorna l'istanza della classe che mi serve (Mysql, Postgres, sqlite) in base alla connessione al db... FACTORY??

/**
 *
 */
class TableManagerMysql extends TableManager {

    protected function checkDriver (){
        if ($this->driverType != 'mysql') throw new MoodException(__METHOD__." ## DB type not managed from this class for ".$this->driverType, 1);
    }

    protected function getFieldDefinition($type = 'string', array $attributes, array $options = []){
        $type = (string)$type;
        $parsed = $this->filterFieldDefinition($type, $attributes, $options);  //  Filtro gli argomenti in base al manifest
        $attributes = array_shift($parsed);
        $options = array_shift($parsed);
        $qs = "";
        switch ($type) {
            case 'string':
                if ($attributes['lenght'] <= 0) throw new MoodException("Lenght must be positive", 1);
                if ($attributes['lenght'] <= 1024) {
                    $qs = "varchar(".$attributes['lenght'].")";
                } elseif ($attributes['lenght'] <= 4096) {
                    $qs = "text";
                } else {
                    $qs = "mediumtext";
                }
                break;
            case 'number':
                if ($attributes['lenght'] <= 0) throw new MoodException("Lenght must be positive", 1);
                if ($attributes['decimals'] > $attributes['lenght']) throw new MoodException("Decimals must be equal or less than full number lenght", 1);
                if ($attributes['floating'] == true) {
                    $qs = "double";
                } else {
                    if ($attributes['decimals'] == 0) {
                        if ($attributes['lenght'] <= 4) {
                            $qs = "tinyint(".$attributes['lenght'].")";
                        } elseif ((5 < $attributes['lenght']) && ($attributes['lenght'] <= 11)) {
                            $qs = "int(".$attributes['lenght'].")";
                        } elseif ((12 < $attributes['lenght']) && ($attributes['lenght'] <= 20)) {
                            $qs = "bigint(".$attributes['lenght'].")";
                        } else {
                            throw new MoodException("Value not permitted for ".$type."[lenght]", 1);
                        }
                    } elseif ($attributes['decimals'] <= 30) {
                        if ($attributes['lenght'] <= 65) {
                            $qs = "decimal(".$attributes['lenght'].",".$attributes['decimals'].")";
                        } else {
                            throw new MoodException("Value not permitted for ".$type."[lenght]", 1);
                        }
                    } else {
                        throw new MoodException("Value not permitted for ".$type."[decimals]", 1);
                    }
                }
                break;
            case 'boolean':
                if ($attributes['stringified'] == true) {
                    $qs = "varchar(5)";    //  Se stringified in db scrive "true" o "false" sotto forma di stringa
                } else {
                    $qs = "tinyint(1)";
                }
                break;
            case 'date':
                switch ($attributes['type']) {
                    case 'timestamp':
                        $qs = "bigint(20)";
                        break;
                    case 'full':
                        $qs = "datetime";
                        break;
                    case 'date':
                        $qs = "date";
                        break;
                    case 'time':
                        $qs = "time";
                        break;
                    default:
                        # code...
                        break;
                }
                break;
            default:
                # code...
                break;
        }
        $qs .= ' NOT NULL';
        //  Per ora le options sono un array contenente una stringa
        if (!empty($options[0])) {
            $qs .= ' '.$options[0];
        }
        return $qs;
    }

    protected function tableCreate ($tblName,$tblIdentifier,$tblMap) {
        if ($this->driverType != 'mysql') throw new MoodException(__METHOD__." ## DB type not managed from this class for ".$this->driverType, 1); //  Nella Construct????
        $sql = "
        CREATE TABLE IF NOT EXISTS `".$tblName."` (
          `".$tblIdentifier."_id` ".$this->getFieldDefinition('number',['lenght'=>11],[])." AUTO_INCREMENT,
          `".$tblIdentifier."_cod` ".$this->getFieldDefinition('string',['lenght'=>32],[]).",\n";
          //TODO: Valutare se eliminare creazione cod default o introdurre parametro per gestire caso (per esempio i bri non ne hanno bisogno)
        foreach ($tblMap as $key => $value) {
            if (!isset($value[1])) {
                $value[1] = [];
            }
            if (!isset($value[2])) {
                $value[2] = [];
            }
            if (!in_array($key, ["id","cod","isparent","parentcod","timestamp","lastedit","status"])) {
                $sql .= "`".$tblIdentifier."_".$key."` ".$this->getFieldDefinition($value[0],$value[1],$value[2]).",\n";
            }
        }
        foreach ($tblMap as $key => $value) {
            if (!isset($value[1])) {
                $value[1] = [];
            }
            if (!isset($value[2])) {
                $value[2] = [];
            }
            if (in_array($key, ["isparent","parentcod"])) {
                $sql .= "`".$tblIdentifier."_".$key."` ".$this->getFieldDefinition($value[0],$value[1],$value[2]).",\n";
            }
        }
        $ay_append = [];
        foreach ($ay_append as $key => $value) {
            if (!empty($key)) {
                $sql .= "`".$tblIdentifier."_".$key."` ".$this->getFieldDefinition($value[0],$value[1],$value[2]).",\n";
            }
        }
        $sql .= "
          `".$tblIdentifier."_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `".$tblIdentifier."_lastedit` datetime NOT NULL,
          `".$tblIdentifier."_status` tinyint(1) NOT NULL DEFAULT '1',
          PRIMARY KEY (`".$tblIdentifier."_id`),
          UNIQUE KEY `cod` (`".$tblIdentifier."_cod`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;";
        //print $sql;
        $res = $this->db->run($sql);
        if ($res == true) {
            return ["d"=>["status"=>"OK","data"=>["tableCreate"=>(string)$res]]];
        } else {
            throw new MoodException(__METHOD__." ## Error in CREATE TABLE for ".$tblName, 1);
        }
    }

    protected function tableDrop ($tblName) {
        if ($this->driverType != 'mysql') throw new MoodException(__METHOD__." ## DB type not managed from this class for ".$this->driverType, 1); //  Nella Construct????
        $res = $this->db->run("DROP TABLE IF EXISTS `".$tblName."`;");
        if ($res == true) {
            return ["status"=>"OK","data"=>["drop"=>(string)$res]];
        } else {
            throw new MoodException(__METHOD__." ## Error in DROP TABLE for ".$tblName, 1);
        }
    }

    protected function tableTruncate ($tblName) {
        if ($this->driverType != 'mysql') throw new MoodException(__METHOD__." ## DB type not managed from this class for ".$this->driverType, 1); //  Nella Construct????
        $res = $this->db->run("TRUNCATE TABLE IF EXISTS `".$tblName."`;");
        if ($res == true) {
            return ["status"=>"OK","data"=>["truncate"=>(string)$res]];
        } else {
            throw new MoodException(__METHOD__." ## Error in DROP TABLE for ".$tblName, 1);
        }
    }
}
?>
