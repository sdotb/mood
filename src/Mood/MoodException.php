<?php
namespace SdotB\Mood;
/**
 * Mood Exception for managing data objects in exceptions
 */
class MoodException extends \Exception
{

    private $_data;
    private $caller;

    public function __construct($message, 
                                $code = 0, 
                                Exception $previous = null, 
                                $data = []) 
    {
        /**
         * Prepend caller NS\Class\Method to message, not working correctly because of wrong late binding class of backtrace function
         * Proposed, in base ad array definito, verificare se non c'è campo messaggio allora usare msg predefiniti in array
         */

        $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,2);
        $class = isset($dbt[1]['class']) ? $dbt[1]['class'].'::' : '';
        $caller = isset($dbt[1]['function']) ? $class.$dbt[1]['function'] : $class;
        if (!empty($caller)) {
            $this->caller = $caller;
        }
        // activate when correct late binding of backtrace
        // $message = "$caller##$message";

        parent::__construct($message, $code, $previous);

        $this->_data = $data; 
    }

    public function GetData() { return $this->_data; }

    public function export(string $caller =  "")
    {
        if (!empty($caller)) {
            $this->caller = $caller;
        }
        $caller = !empty($this->caller) ? $this->caller."##": "";
        return ["data"=>$this->getData(),"msg"=>$caller.$this->getMessage(),"scod"=>(string)$this->getCode(),"status"=>"KO"];
    }
}