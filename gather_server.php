<?php
/*
This file 'gather_server.php' is part of the PPS project <http://code.google.com/p/fracsnetpps/>

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

/* Gather server is different from regular soldat server in that
 * it does not keep track of the stats other than the team scores 
 */
class gather_server extends ppsserver {
    public $m_adminlog;
    
    public $State;
    
    public $irc;    
    
    public $refresh;
    public $m_timeout;

    public $type = SERVER_TYPE_SOLDAT;

    public function __construct($irc, $ip, $port, $adminlog, $timeout = 10, $reconnect = true ){
        $this->irc = $irc;
        $this->ip = $ip;
        $this->port = $port;
        $this->m_adminlog = $adminlog;
        $this->m_timeout = $timeout;
        $this->m_retry = $reconnect;   
        
        $this->State = 0;
        
        $this->stats = null;
        $this->join_try = null;
        
        $this->sock = socket_create( AF_INET, SOCK_STREAM, SOL_TCP );
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */   
    public function connect($timeout = 0){
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */        
        $this->m_timeout = $timeout;
        
        if( @fsockopen($this->ip, $this->port, $errno, $errstr ) === false ) {
            echo $errstr . "\n";
            return;
        }        

        $this->sock = socket_create( AF_INET, SOCK_STREAM, SOL_TCP );

        socket_connect( $this->sock, $this->ip, $this->port);

        $logstr = $this->m_adminlog . "\r\n";
        socket_write( $this->sock, $logstr );
        $this->connected = true;
    }
    
    public function reconnect($timeout = 5) {
        //socket_shutdown( $this->sock ); //Shutting down non-connected socket gives a warning [107]. Weird
        $this->disconnect();

        sleep( $timeout );
        $this->connected = false;
        $this->sock = socket_create( AF_INET, SOCK_STREAM, SOL_TCP );
        $this->connect( $timeout );
    }
    
    public function disconnect() 
    {
        socket_close( $this->sock );
        unset( $this->sock );
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function get_info() 
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        $refresh = $this->get_refreshx();
        $info = "[$this->port] : " . $refresh['players'] . "/12  Map: ". $refresh['map'];
        return $info;
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
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
            echo "Zero length buffer read. Reconnect\n";
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
       $this->send( "/pm $id $message" ); 
    }

    public function set_tiebreaker( $tiebreaker )
    {
        $this->send( "/tiebreaker $tiebreaker" );
    }

    public function set_timer( $seconds, $data )
    {
        $this->send( "/timer $seconds $data" );
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function parse_buffer()
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {   if( !$this->buffer ) return;

        $lines = explode( "\r\n", $this->buffer );
        foreach( $lines as $line ) {
            if( $this->line_parser != null ) {
                $parser = $this->line_parser;
                $parser( $this, $line );
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
        $cmd = substr( $line, 0, 5 );
        if(method_exists( 'soldat_server', $cmd) ) {
            $this->$cmd( $line );
            return;
        }
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function PJOIN( $line ) 
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        list( $cmd, $hwid, $id, $team, $name ) = explode( " ", $line, 5 );

        if( $team == 2 || $team == 1 ) {
            $this->pc++;
            if( $team == 1 )
                $this->alpha[ $name ] = 1;
            if( $team == 2 )
                $this->bravo[ $name ] = 2;
        }
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function PLEFT( $line ) 
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        $this->irc->register_left( $line );
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function NXMAP( $line )
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        $this->irc->register_next_map( $line );
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function PCAPF( $line )
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        $this->irc->register_cap( $line );
    }

}
?>
