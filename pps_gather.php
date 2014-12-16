<?php
/*
This file 'pps.php' is part of the PPS project <http://code.google.com/p/fracsnetpps/>

Copyright: (C) 2009 Arthur, B. aka ]{ing <whinemore@gmail.com>

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
ini_set("error_log", ABS_PATH . "/pps-error.log");

$INFO= array();

define('FRESH', 1);
define('CONNECTED', 2);
define('RECONNECTING', 4);
define('DISCONNECTED', 8);
define('IDLE', 16);

include "refresh.php";
include ABS_PATH . "/server/server.php";
include ABS_PATH . "/server/irc_server.php";
include ABS_PATH . "/server/soldat_server.php";
include ABS_PATH . '/server/mysql_server.php';
require( ABS_PATH . '/server/server_tools.php' );
require( ABS_PATH . '/utility/actions.php' );
require( 'pps_base.php' );

require_once("ppsconfig.php");

/*//////////////////////////////////////////////////////////////////////////////////////////////////////////////// */        
class pps{
/*//////////////////////////////////////////////////////////////////////////////////////////////////////////////// */
    /* Stats info */
    private $stats;
    private $m_connected;
    
    /* Number of players needed to rate */
    private $m_rate = 2;
    private $m_start;
    private $m_version;

    Use server_tools, actions;

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function __construct( $server_list )
    /* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */                                    
    { 
        date_default_timezone_set("America/New_York");

        $this->m_start = time();
        $this->m_version = "v0.6.0b updated(08/15/14)";
        
        $this->m_connected = false;
        $this->m_TPC = 0;
        $this->m_SQL_ON = true;

        $this->init_servers( $server_list );

        echo "Progressive Play System\n";
        echo "PPS stats script. By ]{ing, whinemore@gmail.com\n";
        echo date('l jS \of F Y h:i:s A') . "\n\n";
    }

    function __destruct(){
    }

    public function connect()
    {
        $this->connect_all_game_servers();
    } 
}

$pps = new pps( $GATHER_LIST );
$pps->connect();
$pps->monitor();

?>
