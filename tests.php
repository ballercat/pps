<?php

require('mysql_tests.php');

class Tests {
    Use mysql_tests;

    public $mysql;

    public function __construct( $server ) {

        $this->mysql = new mysql_server( $server['addr'], $server['user'], $server['pass'], $server['db'] );
    }

    public function ok() { echo "[OK]"; }
    public function x() { echo "[X]"; return false; }

    public function run( $name ) {

        if( method_exists( 'Tests', $name ) ) {

            echo "Test: ";
            if( $this->$name() )
                echo ": PASS\n";
            else
                echo ": FAIL\n";
        }
    }
}

?>
