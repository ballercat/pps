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
ini_set('display_errors', 1);
error_reporting(E_ALL);

$INFO= array();

define('FRESH', 1);
define('CONNECTED', 2);
define('RECONNECTING', 4);
define('DISCONNECTED', 8);
define('IDLE', 16);

include "refresh.php";
include "server.php";
//include "pps_player.php";
include "irc_server.php";
include "soldat_server.php";
include 'mysql_server.php';
include "pps_base.php";
require ("ppsconfig.php");

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
    
    public $m_TPC;		/* Total Player count */
    public $m_SQL_ON; /* Boolean */
    
    public $servers; /* Server Array */
    
    public $mysql_info;
    public $db;

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function __construct( $server_list )
    /* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */                                    
    { 
        $this->m_start = time();
        $this->m_version = "v0.6.0b updated(08/15/14)";
        
        $this->servers = array();
        foreach( $server_list as $sinfo ) {
            if( $sinfo["type"] == "soldat" ) {
                $this->add_game_server( $sinfo["addr"], $sinfo["port"], $sinfo["pass"] );
            }
            else if( $sinfo["type"] == "irc" ) {
                $this->add_game_server( gethostbyname($sinfo["addr"]), $sinfo["port"], $sinfo["nick"], $sinfo["chan"] );
            }
            else if( $sinfo["type"] == "mysql" ) {
                $this->db = new mysql_server( $sinfo["addr"], $sinfo["user"], $sinfo["pass"], $sinfo["db"] );
            }
        }
        $this->m_connected = false;
        $this->m_TPC = 0;
        $this->m_SQL_ON = true;

        echo "Progressive Play System\n";
        echo "PPS stats script. By ]{ing, whinemore@gmail.com\n";
        echo date('l jS \of F Y h:i:s A') . "\n";
    }

    function __destruct(){
    }

    private function add_game_server( $ip, $port, $adminlog ) {
        $this->servers["$ip:$port"] = new soldat_server( $this, $ip, $port, $adminlog );
    }

    private function add_chat_server( $ip, $port, $nick, $chan ) {
        $this->servers["$ip:$port"] = new irc_server( $ip, $port, $nick, $chan );
        $this->servers["$ip:$port"]->pps = $this;
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function pps_connect(){
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */
        
        "--> Connecting to ". count($this->servers) . " servers...";
        foreach( $this->servers as $key => $server ){
            echo "[$key:";
            if( $server->type === SERVER_TYPE_SOLDAT )
                echo " soldat server\n";
            if( $server->type === SERVER_TYPE_IRC ) {
                if( false ) continue;
                echo ".irc.";
            } 

            if( $server->connect() ){
                /* Error */
                echo "\nServer fail: $key\n";
                echo "ERROR: ", socket_strerror(socket_last_error()) , "\n";
                echo "continuing without server...\n";
            }else{                  
                echo "].";
            }
        }

		$this->db->connect();
		
        echo ".";

        $this->m_connected = true;
        echo ". OK\n";
    }
    
    public function get_sockets()
    {
        $sockets = [];
         
        foreach( $this->servers as $key => $server ){
            if( !$server->connected  && $server->type === SERVER_TYPE_SOLDAT ) {
                echo "attempt reconnect\n";
                $server->reconnect();
                continue;
            }
            echo "Found available server, hoocking stats\n";
            $server->stats = new base_stats( $this->db, $this->servers[$key], $this->servers[$key]->get_refreshx());
            $this->m_TPC += $server->stats->pc;
            $sockets[] = $server->sock;
        }

        return $sockets;
    }
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */
    /*******************************************************
     *     Main monitoring function PROXY will setup         *
     *    the enviornment and load mode apropriate functions *
     *******************************************************/
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */
    public function pps_monitor() 
    {   if(!$this->m_connected) return;
        
        set_time_limit(0);
        
        
        $this->m_buffer = '';
        $sockets = $this->get_sockets();;
         
        /*foreach( $this->servers as $key => $server ){
            if( $server->type === SERVER_TYPE_IRC ) {
                if( true ) {
                    echo "IRC TEST\n";
                    $server->hooked = true;
                    $server->parse_line("]{ing!~art@me65536d0.tmodns.net PRIVMSG #soldat.na :!ls");
                    return;   
                }
                echo "Hook irc\n";
                if( true )
                    $sockets[] = $server->sock;
                continue;
            }
            if( !$server->connected ) continue;
            echo "Found available server, hoocking stats\n";
            $server->stats = new base_stats( $this->db, $this->servers[$key], $this->servers[$key]->get_refreshx());
            $this->m_TPC += $server->stats->pc;
            $sockets[] = $server->sock;
        }*/

        echo "--> Now monitoring...\n";
        $this->f_monitor($sockets);
       
    
        echo "--> Ubnormal Termination => 'connection lost'\n\r";
        foreach( $this->servers as $server ){
            socket_close($server->sock);
        }
        $this->m_is_connected = false;
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */
    private function f_monitor( $socks )/* Live Monitoring function */
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */
    { if( !count($socks) ) return;
        do
        {
            //Inside the loop an empty socks variable means there were connections
            //lost. So we try to reconnect here, by getting new socket resources.
            if( !count($socks) ) {
                echo "Empty socket list\n";
                $socks = $this->get_sockets();

                if( !count($socks) ) {
                    //Reconnect(s) failed
                    continue;
                }
            }
            $r = $socks;
            $w = NULL;
            $e = NULL;
            $tv_sec = NULL;
            if( false === socket_select($r, $w, $e, $tv_sec) ) {
                return 1;
            }else{
                foreach( $r as $socket ){
                    socket_getpeername( $socket, $IP, $PORT );
                    if( !$this->servers["$IP:$PORT"]->readbuffer() ) {
                        //Zero byte buffer recieved. Attempt reconnect(s);
                        echo "Zero byte buffer\n";
                        $socks = $this->get_sockets();
                    }
                }
            }
			
			/*if ( $this->db->connected && !$this->m_TPC ){
				$this->db->disconnect();
            }*/
        }while( true );
    }

    /* This function will attempt to get any info for server $ip:$port. Null if not acessible */
    public function get_info( $key )
    {
        $keys = array_keys( $this->servers  );
        
        if( count( $keys ) ) {
            $info = [];
            foreach( $keys as $k ) {
                if( strpos($k, $key) !== false ) {
                    $info[] = $this->servers[$k]->get_info();
                }
            }
            if( count($info) ) return $info;
        }

        return array("No server found with this info: $key");
    }

    public function bind_user_auth( $name, $auth, $code ) 
    {
        return $this->db->bind_user_auth( $name, $auth, $code );
    }

    public function get_auth_stats_string( $auth ) {
        $record = $this->db->get_auth_stats( $auth );
        if( !$record ) return null;

        $name = $record['name'];
        $rating = $record['rating'];
        $KD = $record['kd'];
        $kills = $record['kills'];
        $deaths = $record['deaths'];
        $caps = $record['caps'];
        $CG = $record['cg'];
        $grabs = $record['grabs'];
        $played = $record['time_played'];
        $pm = $record['plusminus'];

        //IRC message string
        return "$name rating: $rating KD: $kd CG: $CG +/-:$pm played(minutes):$played";
    }

    public function get_auth_stats( $auth ) {
        return $this->db->auth_stats( $auth );
    }

    public function get_player_rank( $name, $user_id = null, $code = null, $auth = null, $hwid = null )
    {
        return $this->db->get_player_rank( $name, $user_id, $code, $auth, $hwid );
    }
}

$test = new pps( $SERVER_LIST );


$test->pps_connect();
$test->pps_monitor();

?>
