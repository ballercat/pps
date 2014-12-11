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

define( 'SERVER_TYPE_SOLDAT', 2 );

/* Gather server is different from regular soldat server in that
 * it does not keep track of the stats other than the team scores 
 */
class gather_server extends ppsserver {
    public $m_adminlog;
    
    public $State;
    
    public $gather = null;    
    
    public $refresh = null;
    public $m_timeout;

    public $type = SERVER_TYPE_SOLDAT;

    public $region = "??";
    public $region_str = "";
    public $refresh_timer = 0;

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */        
    public function __construct($gather, $ip, $port, $adminlog, $region = "??", $tag = "unknown", $timeout = 10, $reconnect = true ){
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */        
        $this->gather = $gather;
        $this->ip = $ip;
        $this->port = $port;
        $this->m_adminlog = $adminlog;
        $this->region = $region;
        if( $region == 'NA' ) {

            $this->region_str = BOLD . BLUE . 'N' . RED . 'A' . BOLD . MCOLOR;
        }
        else if( $region == 'EU' ) {

            $this->region_str = BOLD . WHITEBLUE . 'EU' . BOLD . MCOLOR;
        }

        $this->m_timeout = $timeout;
        $this->m_retry = $reconnect;   
        
        $this->State = 0;
        
        $this->stats = null;
        $this->join_try = null;

        $this->tag = $tag; 

        $this->sock = socket_create( AF_INET, SOCK_STREAM, SOL_TCP );
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */        
    public function tie_to_gather( $gather ) { $this->gather = $gather; }
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */        

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

        return true;
    }
    
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */        
    public function reconnect($timeout = 5) {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */        
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
    public function disconnect() 
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */        
    {
        socket_close( $this->sock );
        //set custom line parser to null
        $this->line_parser = null;

        $this->connected = false;
        unset( $this->sock );
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function get_region_string() { return $this->region_str; }
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function get_tag_string() { return "[" . $this->get_region_string() . "][$this->tag][$this->port]"; }
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function get_key() { return "$this->ip:$this->port"; }
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function get_status()
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        $refresh = $this->get_refreshx();
        $status = $this->get_tag_string() . " " ;//"[". $this->region_str . "][$this->tag][$this->port] ";
        if( !$refresh ) {

            $status .= RED . "Connection is down.";
        }
        else {

            $status .= sprintf("%-13s", $refresh['map']) . " " . sprintf("%-6s", $refresh['timeleft']);
            $status .= RED .  sprintf(" %02d", $refresh['team'][1]) . MCOLOR . " -" . BLUE . sprintf(" %02d", $refresh['team'][2]);
        }

        return $status;
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function get_info() 
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        $info = $this->get_status();
        $refresh = $this->get_refreshx();
        //$info = "[" . $this->region . "][$this->tag][$this->port] ";

        if( $refresh ) {

            $info .= " ~";

            foreach( $refresh['player'] as $player ) {

                if( $player['team'] == 1 ) 
                    $info .= MCOLOR . ",0 " . RED . $player['name'] . MCOLOR . "(" . $player['kills'] . ":" . $player['deaths'] . " " . $player['caps'] . ")";

                if( $player['team'] == 2 )
                    $info .= MCOLOR . ",0 " . BLUE . $player['name'] . MCOLOR . "(" . $player['kills'] . ":" . $player['deaths'] . " " . $player['caps'] . ")"; 
            }
        }

        return $info;
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function get_refreshx() {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
        //Refreshx has a timer to prevent accidental spamming
        if( time() - $this->refresh_timer < 2 ) 
        {
            
            return $this->refresh; //return a cached copy
        }

        $this->refresh_timer = time();

        $sock = fsockopen( $this->ip, $this->port );
        if( !$sock ) {

            return null;
        }
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

        $this->refresh = $info;
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
        if( !$this->sock ) return false;
        if( !$this->connected ) return false;

        $this->buffer = trim( socket_read($this->sock, 1024, PHP_BINARY_READ) );

        if( !$this->buffer ) {

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
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function PJOIN( $line ) 
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function PLEFT( $line ) 
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function NXMAP( $line )
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function PCAPF( $line )
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
    }

}
?>
