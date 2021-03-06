<?php
/*
This file 'soldat_server.php' is part of the PPS project <http://code.google.com/p/fracsnetpps/>

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

//include 'server.php';

define( 'SERVER_TYPE_SOLDAT', 2 );

class soldat_server extends ppsserver {
    public $m_adminlog;
    
    public $State;
    
    public $stats = null; /* Note: Initialize outside the class! */
    public $pps;    
    
    public $refresh;
    public $m_timeout;

    public $gather = null;

    public $type = SERVER_TYPE_SOLDAT;

    public function __construct($pps, $ip, $port, $adminlog, $timeout = 10, $reconnect = true ){
        $this->pps = $pps;
        $this->ip = $ip;
        $this->port = $port;
        $this->m_adminlog = $adminlog;
        $this->m_timeout = $timeout;
        $this->m_retry = $reconnect;   
        
        $this->join_try = null;
        
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */        
    public function ping() { //maybe should be called... check connection?
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */        
        $_s = fsockopen( $this->ip, $this->port );
        if( !$_s ) {

            return false; 
        }

        fclose($_s);
        return true;
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */   
    public function connect($timeout = 0){
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */        
        $this->m_timeout = $timeout;
        
        if( @fsockopen($this->ip, $this->port, $errno, $errstr ) === false ) {
            error_log( "Gather Server connect: $errstr" );
            return false;
        }        

        $this->sock = socket_create( AF_INET, SOCK_STREAM, SOL_TCP );

        socket_connect( $this->sock, $this->ip, $this->port);

        $logstr = $this->m_adminlog . "\r\n";
        socket_write( $this->sock, $logstr );
        $this->connected = true;
    
        //??
        if( $this->stats ) {
            $refresh = $this->get_refreshx();
        }

        return true;
    }
    
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */        
    public function disconnect() {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */        
        socket_close( $this->sock );
        $this->connected = false;
        unset( $this->sock );
    }

    public function reconnect($timeout = 5) {
        //socket_shutdown( $this->sock ); //Shutting down non-connected socket gives a warning [107]. Weird
        $this->disconnect();

        sleep( $timeout );
        $this->connected = false;
        $this->sock = socket_create( AF_INET, SOCK_STREAM, SOL_TCP );
        $this->connect( $timeout );
    }
    
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */        
    public function kill() 
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */        
    {
        $this->disconnect();
        if( $this->gather ) $this->gather->game_server_dc( "$this->ip:$this->port" );
    }

    /* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function get_key() { return "$this->ip:$this->port"; }
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function get_info() 
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        $refresh = $this->get_refreshx();
        $info = "[$this->ip:$this->port] : " . $refresh['players'] . "/12  Map: ". $refresh['map'];
        return $info;
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    // This function uses a separate socket to get game info
    public function get_refreshx() {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
        $sock = fsockopen( $this->ip, $this->port );
        fputs( $sock, "$this->m_adminlog\r\n" );
        fputs( $sock, "REFRESHX\r\n" );
        $version = null;
        $info = null;
        while( $data = trim(fgets($sock, 1024)) ) {
            if( preg_match('/^Server Version: (.+)$/', $data, $match) ) {
                $version = $match[1];
            }
            else if( $data == "REFRESHX" ) {
                $packet = fread( $sock, RefreshXSize($version) );
                $info = ParseRefresh($packet, $version);
                break;
            }
        }

        fclose($sock);
        return $info;
    }  

    public function set_stats() {
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function send( $data ) 
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        socket_write( $this->sock, "$data\r\n" );
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function readbuffer() 
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        if( !$this->sock ) return;
        $this->buffer = trim( socket_read($this->sock, 1024, PHP_BINARY_READ) );
        if( !$this->buffer ) {
            $this->reconnect();
            return false;
        }
        $this->parse_buffer();
        return true;
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function private_message($id, $message)
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        //NOTE: Sending $id zero messages all players
        //NOTE: /pvm is a custom command. $id has to be two digits
        $this->send( "/pvm ". sprintf("%02d", $id) . " $message" ); 
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function parse_buffer()
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {   if( !$this->buffer ) return;

        $lines = explode( "\r\n", $this->buffer );
        foreach( $lines as $line ) {
            //use a custom line parser
            if( $this->line_parser != null ) {
                $this->line_parser( $this, $line );
            }
            else {
                $this->parse_line( $line );
            }
        }
    }

    //Parse a line from the Soldat server
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function parse_line( $line ) {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
        //This server type does not parse its own lines
        return;

        $cmd = substr( $line, 0, 5 );
        if(method_exists( 'soldat_server', $cmd) ) {
            $this->$cmd( $line );
            return;
        }

        //Thanks to soldat script core total failure i have to do this
        if( strpos($line, "killed (") ) {
            $this->stats->ch_kill( $line );
        }
    }

	/*
    public function PKILL( $line ) 
    {
        if( $this->stats )
            $this->stats->ch_kill( $line );
    }*/

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function PJOIN( $line ) {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
        if( $this->stats ) {

            $this->stats->ch_join( $line );
        }

        //we still want to update the player count
        $this->pps->m_TPC++;
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function PLEFT( $line ) 
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        if( $this->stats ) 
            $this->stats->ch_left( $line );

        //we still want to update the player count
        $this->pps->m_TPC--;
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function NXMAP( $line )
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        if( $this->stats )
            $this->stats->ch_nextmap( $line );
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function PGRAB( $line )
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        if( $this->stats && $this->stats->T->pc > 1 )
            $this->stats->ch_grab( $line );
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function PCAPF( $line )
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        if( $this->stats && $this->stats->T->pc > 1 )
            $this->stats->ch_cap( $line );
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function PRETF( $line )
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        if( $this->stats && $this->stats->T->pc > 1 )
            $this->stats->ch_return( $line );
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function PRATE( $line )
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        if( !$this->stats ) return;

        $name = substr( $line, 5 );
        if( $this->stats->T->is_playing($name) ) { 
            $P = &$this->stats->T->ps[$name];
            if( $P->sigma != 8.3 && $P->mu != 25 )
                $this->private_message( $P->p_id, "Rating: " . round($P->rating,2) );
            else
                $this->private_message( $P->p_id, "Not yet rated. No full games played(" . "full map=" . PPS_FULL_MAP_TIME ."s)" );
        }
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function RCODE( $line )
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        if( !$this->stats ) return;

        $name = substr( $line, 5);
        if( $this->stats->T->is_playing($name) ) {
            $P = &$this->stats->T->ps[$name];
            $this->private_message( $P->p_id, "Secret Code: $P->code" );
        }
    }

    public function PRANK( $line )
    {
        if( !$this->stats ) return;

        $name = substr( $line, 5 );
        if( $this->stats->T->is_playing($name) ) {
            $P = &$this->stats->T->ps[$name];
            $result = $this->pps->get_player_rank( null, null, null, null, $P->hwid ); 
            if( $result != false ) {
                $this->private_message( 0, "$P->acc_name is Ranked: " . $result['rank'] . "/" . $result['total'] );
            }
            else {
                $this->private_message( 0, "$P->acc_name is not Ranked!" );
            }
        }
    }

}
?>
