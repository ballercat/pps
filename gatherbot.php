<?php
/*
This file is part of the PPS project <https://github.com/ballercat/pps>

Copyright: (C) 2014 Arthur, B. aka ]{ing <whinemore@gmail.com>

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.
 .
 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU General Public License for more details.
 .
 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 .
*/

define( 'ABS_PATH', dirname(__FILE__) );

date_default_timezone_set('America/New_York');
ini_set("log_errors", 1);
ini_set("error_log", ABS_PATH . "/php-error.log");

include "refresh.php";

require( ABS_PATH . "/server/server.php" );
require( ABS_PATH . "/server/irc_server.php" );
require( ABS_PATH . "/server/gather_server.php" );
require( ABS_PATH . "/server/mysql_server.php" );
require( ABS_PATH . "/server/server_tools.php" );
require( ABS_PATH . "/utility/actions.php" );

require_once("ppsconfig.php");

require('tests.php');


/* Just a wrapper to manage the irc_server */
/* This also is responsible for tying the irc class with 
 * the soldat server class */
class pps_gather {
    Use server_tools;
    Use actions;

    public $available_game_server = [];

    public function __construct($server_list) 
    {
        $this->init_servers( $server_list );    
        $this->gather_mode = true;

        foreach( $this->servers as $key => $server ) {
            if( $server->type === SERVER_TYPE_SOLDAT ) {
                $this->available_game_server[$key] = true;
            }
        }
    }

    public function __destruct()
    {
    }

    public function test( $ip, $port, $buffer ) 
    {
        $this->servers["$ip:$port"]->parse_line( $buffer );
    }

    public function test_gather( $ip, $port, $players )
    {
        $this->servers["$ip:$port"]->test_gather( $players );
    }

    public function connect()
    {
        $this->irc_connect(); 
    }

}
/*
$handle = fopen(ABS_PATH . "/refreshx.txt", 'r');
$content = fread($handle, filesize(ABS_PATH . "/refreshx.txt"));
fclose($handle);
$lines = explode('\n', $content);
echo $content;
print_r($lines);
exit(0);
foreach( $lines as $line ) {
    $refresh = unserialize($line);
    print_r($refresh);
}
exit(0);*/
$pps = new pps_gather( $GATHER_LIST );
$pps->connect();
$pps->monitor();

?>
