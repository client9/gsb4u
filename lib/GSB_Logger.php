<?php
/** Quick Logger class.  Completely replaceable with apache 
 *  apache log4php or equiv http://logging.apache.org/log4php/
 *
 *
 */
class GSB_Logger {
    // Duck Type Methods

    public function fatal($message) {
        if ($this->level > 0) {
            $this->_log("FATAL $message");
        }
    }
    public function error($message) {
        if ($this->level > 1) {
            $this->_log("ERROR $message");
        }
    }
    public function warn($message) {
        if ($this->level > 2) {
            $this->_log("WARN $message");
        }
    }
    public function info($message) {
        if ($this->level > 3) {
            $this->_log("INFO $message");
        }
    }
    public function debug($message) {
        if ($this->level > 4) {
            $this->_log("DEBUG $message");
        }
    }


    // -1, none, 0 fatal only, 4 all
    function __construct($level) {
        $this->level = $level;
    }

    private function _log($message) {
        $now = strftime("%FT%T");
        print "$now $message\n";
    }
}