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

Trait server_tools{

    public $servers = [];
    public $database = null;
    public $chat = null;
    public $sockets = [];

    public $connected_servers = [];

    public $parser = [];

    public $gather_mode = false;

    public $nullserver = null;

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    function _validate($info, $keys) {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    

        $_data = [];
        foreach( $keys as $key ) {

            if( !array_key_exists($key, $info) ) {

                return null;
            }
            else {

                $_data[] = $info[$key];
            }
        }

        return $_data;
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    function soldat_gather_parser($srv) {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    

        $_valid = $this->_validate($srv, array('addr', 'port', 'pass', 'region', 'tag'));
        if( !$_valid ) {

            return null;
        }
        list( $a, $p, $k, $r, $t ) = $_valid;
        return new  gather_server($this, $a, $p, $k, $r, $t);
    }
    
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    function mysql_parser($srv) {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    

        $_valid = $this->_validate($srv, array('addr', 'user', 'pass', 'db', 'table'));
        if( !$_valid ) {

            echo "Invalid\n";
            return null;
        }
        list($a, $u, $p, $d, $t) = $_valid;
        return new mysql_server($a, $u, $p, $d, $t);
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    function irc_parser($srv) {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    

        $_valid = $this->_validate($srv, array('addr', 'port', 'nick', 'chan'));
        if( !$_valid ) {

            return null;
        }
        list($a, $p, $n, $c) = $_valid;
        $irc =  new irc_server( $a, $p, $n, $c );
        $irc->pps = $this;
        return $irc;
    }
    
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    function init_servers($info)
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        if( !count($info) ) return;

        foreach( $info as $si ) {

            if( !array_key_exists('type', $si) ) continue;
            $type = $si['type'];
            $parser = $type . '_parser';
            if( method_exists('server_tools', $parser) ) {
                $server = $this->$parser( $si );

                if( $server ) {

                    if( $si['type'] == 'mysql' ) {

                        $this->database = $this->$parser( $si );
                        continue;
                    }

                    //Store the server
                    $server = $this->$parser( $si );
                    $key = "$server->ip:$server->port";
                    $this->servers[$key] = $server; 
                }
            }
        }
    } 

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    function close_all_connections()
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        foreach( $this->connected_servers as $key => $status ) {

            $this->servers[$key]->disconnect();
        }

        $this->connected_servers = array();
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    function irc_connect() {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
        foreach( $this->servers as $key => $server ) {
            if( $server->type === SERVER_TYPE_IRC ) {
                $server->connect();
                $this->connected_servers[$key] = true;
            }
        }
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    function database_connect($params = null) {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
        
        if( $params ) {
            
            $this->database->connect( $params );
        }
        else {

            $this->database->connect();
        }
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    function game_server_connect($key) {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
        $server = $this->servers[$key];
        $sucess = $server->connect();
        if( $sucess == true ) {

            $this->connected_servers[$key] = true;
        }
        return $sucess;
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    function game_server_disconnect($key) {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
        $this->servers[$key]->disconnect();
        //$this->servers[$key]->set_line_parser( null );
        $this->connected_servers[$key] = null;
        unset( $this->connected_servers[$key] );
    }


	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    function game_server_kill($key) {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
        $this->available_game_server[$key] = true;

        $this->servers[$key]->kill();
        $this->connected_servers[$key] = null;
        unset( $this->connected_servers[$key] );
    }

    //This function will attemt to reconnect to any game socket not connected
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    function get_sockets( $tag = null )
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        $sockets = [];

        //We might edit connected_servers during the process

        reset($this->connected_servers);
        while( list($key, $connected) = each($this->connected_servers) ) {
        //foreach( $this->connected_servers as $key => $connected ) {

            if( $this->servers[$key]->type === SERVER_TYPE_SOLDAT )
            {
                if( $this->servers[$key]->ping() === false ) {

                    //release_game_server() -> server_disconnect() is the 'nice' behavior
                    //bypass it here to kill() the server instead
                    $this->game_server_kill( $key ); 

                    continue; //server dc'ed probably
                } 
                //Could store ping here...
            } 
            
            $sockets[] = $this->servers[$key]->sock;
        }

        return array_values( $sockets );
    }

    //Main monitoring loop function
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    function monitor()
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        $socks = $this->get_sockets();
        do {

            //check for socket update
            if( !count($socks) || count($socks) != count($this->connected_servers) ) {

                $socks = $this->get_sockets();

                if( !count($socks) ) {

                    continue; //Try again
                }
            }

            $r = $socks;
            $w = null;
            $e = null;
            $tv_sec = null;

            if( false === socket_select($r, $w, $e, $tv_sec) ) {
                
                //socket select failed 
                break;
            }
            else {

                foreach( $r as $socket ) {

                    socket_getpeername( $socket, $IP, $PORT );
                    //check for a zero length buffer read
                    if( !$this->servers["$IP:$PORT"]->readbuffer() ) { 
    
                        /*if( $this->servers["$IP:$PORT"]->connected ) {

                            $this->servers["$IP:$PORT"]->disconnect();
                        }*/

                        $socks = $this->get_sockets( "$IP:$PORT" );                 
                    }
                }
            }

        } while( true );
    }

    //Return a server reference or null
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function &request_game_server( $region = null ) {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    

        foreach( $this->available_game_server as $key => $available ) {

            if( $available && $this->servers[$key]->region == $region ) {

                if( $this->game_server_connect($key) == false ) {
                    
                    //There was a connection problem try another server
                    continue;
                } else {
                
                    $this->available_game_server[$key] = false;
                    return $this->servers[$key];
                }
            }
        }

        return $this->nullserver;
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function release_game_server( $server_key ) {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
        
        if( $this->available_game_server[$server_key] == false ) {
            $this->available_game_server[ $server_key ] = true;
            
            //the server keys don't change and the server array is always the same
            //$this->servers[$server_key]->send( "/gatheroff" );
            
            $this->game_server_disconnect( $server_key );
        }
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function free_all_servers() {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
        foreach( $this->available_game_server as $key => $available ) {
            $this->release_game_server( $key );
        }
    }

    /* This function will attempt to get any info for server $ip:$port.*/
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    function get_info( )
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        
        $info = [];
        foreach( $this->servers as $key => $server ) {

            if( $server->type == SERVER_TYPE_SOLDAT ) {

                $info[] = $server->get_info();
            }
        }

        if( count($info) ) return $info;

        return array("No servers found");
    }

    function get_status()
    {
        $status = [];
        foreach( $this->servers as $key => $server ) {

            if( $server->type == SERVER_TYPE_SOLDAT ) {

                if( $this->available_game_server[$key] ) 
                    $status[] = "o".$server->get_status();
                else
                    $status[] = "x".$server->get_status();
            }
        }

        if( count($status) ) return $status;
        return array("No servers found!");
    }



}

?>
