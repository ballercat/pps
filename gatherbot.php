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

include "refresh.php";

require( ABS_PATH . "/server/server.php" );
require( ABS_PATH . "/server/irc_server.php" );
require( ABS_PATH . "/server/gather_server.php" );
require( ABS_PATH . "/server/mysql_server.php" );

require_once("ppsconfig.php");

require('tests.php');


/* Just a wrapper to manage the irc_server */
/* This also is responsible for tying the irc class with 
 * the soldat server class */
class mock_pps {
    public $servers     = [];
    public $sockets     = [];
    public $database    = null;

    public $available_game_server = [];

    public $nullserver = null;

    public function __construct() 
    {
    }

    public function __destruct()
    {
    }

    public function add_mysql_server( $ip, $user, $pass, $db )
    {
        $this->database = new mysql_server( $ip, $user, $pass, $db );
        $this->database->set_player_table_name( "gather_players" );
    }

    public function add_game_server( $ip, $port, $adminlog ) {
        $this->servers["$ip:$port"] = new gather_server( $this, $ip, $port, $adminlog );
        $this->available_game_server["$ip:$port"] = true;
    }

    public function add_chat_server( $ip, $port, $nick, $chan ) {
        $this->servers["$ip:$port"] = new irc_server( $ip, $port, $nick, $chan );
        $this->servers["$ip:$port"]->pps = $this;
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
        foreach( $this->servers as $key => $server ) {
            if( $server->type === SERVER_TYPE_IRC ) {
                $server->connect();
                $this->sockets[] = $server->sock;
            }
        }
    }

    public function monitor()
    {
        $socks = array_values( $this->sockets );
        do{
            if( count($socks) != count($this->sockets) ) {
                $socks = array_values($this->sockets);
            } 

            $r = $socks;
            $w = null;
            $e = null;
            $tv_sec = null;

            if( false === socket_select($r, $w, $e, $tv_sec) ) {
                echo "Socket select fail\n";
                break;
            }
            else {
                foreach( $r as $socket ) {
                    socket_getpeername( $socket, $IP, $PORT );
                    $this->servers["$IP:$PORT"]->readbuffer();
                }
            }
        }while( true );
    }

    /* This function will attempt to get any info for server $ip:$port. Null if not acessible */
    public function get_info( $key )
    {
        
        $info = [];
        foreach( $this->servers as $server ) {

            if( $server->type == SERVER_TYPE_SOLDAT ) {

                $info[] = $server->get_info();
            }
        }

        if( count($info) ) return $info;

        return array("No servers found");
    }

    public function get_account_users( $auth, $code )
    {
        $this->database->connect( false );
        $result = $this->database->get_account_users( $auth, $code );
        $this->database->disconnect();
        return $result;
    }

    public function bind_user_auth( $name, $auth, $code ) 
    {
        $this->database->connect( false );
        echo "Bind user auth: $name $auth $code\n";
        $result = $this->database->bind_user_auth( $name, $auth, $code );
        $this->database->disconnect();
        return $result; 
    }

    public function get_auth_stats_string( $auth )
    {
        $this->database->connect( false );
        $record = $this->database->get_auth_stats( $auth );
        $this->database->disconnect();

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
        return "$name rating: $rating KD: $KD CG: $CG +/-:$pm played(minutes):$played";
    }

    public function get_auth_stats( $auth ) {
        $this->database->connect();
        $result = $this->database->get_auth_stats( $auth );
        $this->database->disconnect();
        return $result;
    }

    public function get_player_rank( $name, $user_id = null, $code = null, $auth = null, $hwid = null )
    {
        $this->database->connect();
        $result = $this->database->get_player_rank( $name, $user_id, $code, $auth, $hwid );
        $this->database->disconnect();
        return $result;
    }

    //Return a server reference or null
    public function &request_game_server() {
        foreach( $this->available_game_server as $key => $available ) {
            if( $available ) {
                $this->available_game_server[$key] = false;
                $this->servers[$key]->connect();
                $this->sockets[$key] = $this->servers[$key]->sock;

                return $this->servers[$key];
            }
        }
        return $this->nullserver;
    }

    public function release_game_server( $server_key ) {
        if( array_key_exists($server_key, $this->available_game_server) ) {
            $this->available_game_server[ $server_key ] = true;
            $this->servers[$server_key]->send( "/gatheroff" );
            $this->servers[$server_key]->disconnect();
            $this->servers[$server_key]->set_line_parser( null );

            echo "unset $server_key\n";
            unset( $this->sockets[$server_key] );
        }
    }

    public function free_all_servers() {
        foreach( $this->available_game_server as $key => $available ) {
            $this->release_game_server( $key );
        }
    }

    public function merge_account_users( $code, $auth ) {
        $this->database->connect();
        $result = $this->database->merge_account_users( $code, $auth );
        $this->database->disconnect();
        return $result;
    }       

    public function get_player_points( $user_id  ) 
    {
        $this->database->connect();
        $result = $this->database->get_points( $user_id );
        $this->database->disconnect();
        return $result;
    } 

    public function give_player_points( $user_id, $points, $type, $reason = null, $issuer = null )
    {
        $this->database->connect();
        $result = $this->database->give_points( $user_id, $points, $type, $reason, $issuer );
        $this->database->disconnect();   
        return $result;
    }

    public function erase_player_points( $uer_id, $type )
    {
        $this->database->connect();
        $this->database->erase_points( $user_id, $type );
        $this->database->disconnect();
    }

    public function get_max_gather_id()
    {
        $this->database->connect( false );
        $id = $this->database->get_max_gather_id();
        $this->database->disconnect();
        return $id;
    }

    public function create_gather() 
    {
        $this->database->connect( false );
        $id = $this->database->create_gather();
        $this->database->disconnect();
        return $id;
    }

    public function get_last_gather( $limit = 1, $id = null )
    {
        $this->database->connect( false );
        $result = $this->database->get_last_gather( $limit, $id );
        $this->database->disconnect();

        if( $result ) {
            $gathers = array();
            $gather = $result->fetch_array( MYSQLI_ASSOC );
            while( $gather ) {

                $gathers[$gather['id']] = $gather;
                $gather = $result->fetch_array( MYSQLI_ASSOC );
            }

            return $gathers;
        }

        return false;
    }
}


$pps = new mock_pps();
foreach( $GATHER_LIST as $server ) {

    if( $server['type'] == 'soldat' ) {

        $pps->add_game_server( $server['addr'], $server['port'], $server['pass'] );
    }

    if( $server['type'] == 'mysql' ) {

/*        $ut = new Tests( $server );
        $ut->run( 'test_mysql_point_functions' );
exit(0);*/

        $pps->add_mysql_server( $server['addr'], $server['user'], $server['pass'], $server['db'] );
    }
}

$ip = gethostbyname("irc.quakenet.org");
$pps->add_chat_server( $ip, "6667", "HenryVIII", "#soldat.na" );

//test_gather( $pps, "192.210.137.129" );
//test( $pps, $ip );
$pps->connect();
$pps->monitor();

?>
