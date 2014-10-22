<?php
/*
This file 'irc_server.php' is part of the PPS project <http://code.google.com/p/fracsnetpps/>

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

//require 'server.php';
require 'gather.php';
require('gather_commands.php');
require('irc_commands.php');
require('irc_server_test.php');
require('qnet_users.php');
require('irc_utility.php');

define( 'SERVER_TYPE_IRC',  1 );

class irc_server extends ppsserver {
    Use qnet_users, gather_commands, irc_commands, irc_utility, irc_server_test;

    public $ip;
    public $port;
    public $sock;

    public $nick;
    public $chan;

    public $uptime;

    public $buffer;

    public $type = SERVER_TYPE_IRC;

    public $hooked = false;

    public $pps; //kind of an ugly hack but helps a lot with getting server info etc.,

    public $auth_try;
    public $auth_cb;
    public $auth = null;
    public $auth_cb_args = null;
    public $auth_array = null;

    public $gathers;
    public $gc;
    public $current_gather = null;
    
    public $init = false;

    public $users;

    public $admins;

    public function __construct($ip, $port, $nick, $channel) 
    {
        $this->ip   = $ip;
        $this->port = $port;
        $this->nick = $nick;
        $this->chan = $channel;
        
        $this->gathers = array();
        $this->gc = 0;

        $this->uptime = time();

        $this->users = array();
        $this->admins = array( "]{ing" => "astr" );
    }

    public function admin_access( $user ) 
    {
        if( !array_key_exists( $user, $this->admins ) ) return false;
        if( !array_key_exists( $user, $this->users ) ) return false;
        if( $this->users[$user] == $this->admins[$user] ) return true;

        return false;
    }

    public function user_access( $user )
    {
        if( !array_key_exists( $user, $this->users ) ) return false;
        if( $this->users[$user] === false ) return false;

        return true;
    }



    public function get_info() 
    {
        return "IRC server: $this->ip : $this->port ($this->chan)";
    }

    public function send( $data, $channel = null )
    {   
        $lines = explode( "\n", $data );
        if( !$this->sock ) {
            foreach( $lines as $line ) 
                if( strlen($line) ) echo "irc_debug: $line\n";
            return;
        }

        if( !$channel ) {
            foreach( $lines as $line )
                if( strlen($line) ) socket_write( $this->sock, "$line"."\r\n" );
        }
        else {
            foreach( $lines as $line )
                if( strlen($line) ) socket_write( $this->sock, ": PRIVMSG $channel :$line\r\n" );
        }
    }

    public function speak( $data, $channel = null ) 
    {
        if( $channel == null ) $channel = $this->chan;
        $this->send( MCOLOR ." ~ ". $data , $channel );
    }

    public function error( $data, $channel = null )
    {
        if( $channel == null ) $channel = $this->chan;
        $this->send( BOLD . RED . " <!> " . BOLD . MCOLOR .  $data, $channel );
    }

    public function warning( $data, $channel = null, $user = null ) 
    {
        $text = "";
        if( $user != null ) {

            $text .= BOLD . $user . BOLD;
        }

        $text .=  BOLD . BLUE . " [#] " . BOLD . MCOLOR . $data;

        if( !$channel ) $channel = $this->chan; 

        $this->send( $text, $channel );
    }

    public function readbuffer( $size = 512 ) 
    {   if( !$this->sock ) return ($this->buffer = null);
        $this->buffer = trim( socket_read($this->sock, 512, PHP_BINARY_READ) );
        $this->parse_buffer();
    }

    public function PONG( $ping )
    { if( !$this->sock ) return;
        $pong = substr_replace( $ping, 'O', 1, 1);
        $this->send( $pong );
    }

    public function connect( $timeout = 10, $reconnect = true ) 
    {
        $this->sock = socket_create( AF_INET, SOCK_STREAM, SOL_TCP );
        socket_connect( $this->sock, $this->ip, $this->port );

        if( $this->sock ) {
            $this->send( "NICK $this->nick" ); //TODO: the server can reject this nickname. Have to handle this!
            $this->send( "USER pps_mon pps_mon pps_mon :SOLDATMON\r\n" );
            $this->connected = true;
            return false;
        }
        return true;
    }

    public function parse_buffer() 
    {
        $lines = explode( "\r\n", $this->buffer );
        foreach( $lines as $line ) 
            $this->parse_line( $line );
    }

    public function parse_line( $line )
    {   if( !strlen($line) ) return;
        //echo "$line\n";      
        if( $this->connected && !$this->hooked ) {
            if( $line == "ERROR :Your host is trying to (re)connect too fast -- throttled" ) return;
            if( strpos($line, "End of /MOTD command.") !== false || strpos($line, "MOTD File is missing") ) {
                $this->send( "AUTH ppsbot yak1soba", "Q@CServe.quakenet.org" );
                socket_write( $this->sock, "JOIN $this->chan\r\n" );
                $this->hooked = true;
                return;
            }
        }

        if( substr($line, 0, 4) === "PING" ) {
            $this->PONG( $line );
            return;
        }

        if( $this->sock && !$this->hooked ) return;
        $tokens = array_filter( explode(' ', $line, 5) );

        $useragent  = (array_key_exists(0,$tokens)) ? $tokens[0] : null;
        $method     = (array_key_exists(1,$tokens)) ? $tokens[1] : null;
        $channel    = (array_key_exists(2,$tokens)) ? $tokens[2] : null;
        $cmd        = (array_key_exists(3,$tokens)) ? $tokens[3] : null;
        $args       = (array_key_exists(4,$tokens)) ? $tokens[4] : null;


        if( substr($line, 0, 3) === ":Q!" ) {
            $this->Q_respond( $cmd, $args );
            return;
        }

        //Check for nick changes
        if( $method == "NICK" ) {
            $ut = explode( "!", $useragent );
            $u = substr( $ut[0], 1 );
            $n_nick = substr($channel, 1);

            if( array_key_exists($u, $this->users) ) {
                $this->users[$n_nick] = $this->users[$u];
                unset( $this->users[$u] );
            }
            
            foreach( $this->gathers as $gather ) {

                $result = $gather->nickchange( $u, $n_nick );
                if( $result ) {

                    $this->send( $result, $this->chan );
                }
            }

            if( $this->current_gather ) {

                $result = $this->current_gather->nickchange( $u, $n_nick );

                if( $result ) {

                    $this->send( $result, $this->chan );
                    return;
                }
            }
        }
        //Check for people leaving channel
        else if( $method == "PART" || $method == "QUIT" ) {

            $ut = explode( "!", $useragent );
            $u = substr( $ut[0], 1 );

            $this->del( $u, null );

            return;
        }
        //A user joined the channel store their auth
        else if( $method == "JOIN" ) {

            $ut = explode( "!", $useragent );
            $u = substr( $ut[0], 1 );
            if( $u != $this->nick ) {

                if( !$this->init ) //Init has finished
                {
                    if( !count($this->auth_array) ) {

                        $this->auth_array[] = $u;
                        $this->store_auth( null, null );
                    }
                    else {

                        $this->auth_array[] = $u;
                    }
                }
            }

            return;
        }
        //Check for userlist
        else if( $cmd == "=" ) {
            //Quakenets names list line looks something like this
            //quakenet.org 353 HenryVIII = #soldat.na :User1 +VoicedUser1 @UserOP1 User2 @UserOP2 @Q

            $dt = explode( ':', $args ); //split line into two 2nd part being the names list

            $users  = array_filter( explode(' ', $dt[1]) );

            //$this->init = 0;
            $this->send( "Connected. Initializing...", $this->chan );

            foreach( $users as $key => $user ) {

                $user = ltrim( $user, '@' );
                $user = ltrim( $user, '+' );

                if( $user == $this->nick ) continue;
                if( $user == 'Q' || $user == 'S' || $user == 'D' ) continue;

                $this->auth_array[] = $user;
            }

            $this->init = true;
            $this->store_auth( null, null );
        
            return;
        }

        if( $this->init ) return; 
        if( strpos($line, ":!") === false ) return;
            
        //Get vals 
        $user_tokens = explode('!', $useragent );
        $user = substr( $user_tokens[0], 1 );
        $args = array_filter( explode(' ', $args), 'strlen' );
        $method = substr( $cmd, 2 );
        
        if( method_exists('irc_server', $method) ) {
            
            $this->$method( $user, $args, $channel );
        }
    }
    
    public function user_rank_letter_str( $user_id )
    {
        $rank = $this->pps->get_player_rank( null, $user_id );
        $total = $rank['total'];
        $prank = $rank['rank'];
    }

    //SOLDAT SERVER COMMANDS
    public function PJOIN( $caller, $line ) {
        $port = $caller->port;
        $ip = $caller->ip;
        if( !array_key_exists("$ip:$port", $this->gathers) ) return;
        
        if( $this->gathers["$ip:$port"]->gather_timeout ) {
            $this->timeout( array("key" => "$ip:$port") );
        }

        list( $cmd, $hwid, $id, $team, $name ) = explode( " ", $line, 5 );

        //We can hook in here to tie the HWID to the auth!
        //NOTE: Check for no-shows here! Awesome.
        //TODO: HWID stuff, compare with/any currently rated players added 

        //ALPHA: 1, BRAVO: 2 SPEC: 5
        if( $team == 1 || $team == 2 ) {
            $this->gathers["$ip:$port"]->game_pc++;
            $gm = $this->gathers["$ip:$port"]->game_number;
            $gpc = $this->gathers["$ip:$port"]->game_pc;

            if( $team == 1 )
                $this->send( "Gather #$gm [$ip:$port]". RED . " * Player joined($gpc/6): $name" . BLACK, $this->chan );
            if( $team == 2 )
                $this->send( "Gather #$gm [$ip:$port]". BLUE . " * Player joined($gpc/6): $name" . BLACK, $this->chan );
        }
    }

    public function PLEFT( $caller, $line ) {
        $port = $caller->port;
        $ip = $caller->ip;
        if( !array_key_exists("$ip:$port", $this->gathers) ) return;

        if( $this->gathers["$ip:$port"]->gather_timeout ) {
            $this->timeout( array("key" => "$ip:$port") );
        }

        list( $cmd, $id, $team, $name ) = explode( " ", $line, 4 );

        $leave_time = time();
        if( $team == 1 || $team == 2 ) {
            $this->gathers["$ip:$port"]->game_pc--;
        }
    }

    public function PCAPF( $caller, $line ) {
        $ip = $caller->ip;
        $port = $caller->port;
        if( !array_key_exists("$ip:$port", $this->gathers) ) return;

        $this->gathers["$ip:$port"]->cap(); 
    }

    public function NXMAP( $caller, $line ) {
        $ip = $caller->ip;
        $port = $caller->port;
        if( !array_key_exists("$ip:$port", $this->gathers) ) return;

        if( $this->gathers["$ip:$port"]->gather_timeout ) {
            $this->timeout( array("key" => "$ip:$port") );
        }

        $result = $this->gathers["$ip:$port"]->nextmap();
        if( $result != false ) {
            $this->send( $result, $this->chan );
        }
    }

    public function TIMER( $caller, $line ) {
        //I can't really do poll events in php very well.
        //But Soldat can... sending /timer <seconds> <string> to soldat server will make it fire off this 
        //command back to console after <seconds> with <string> suplied
        $ip = $caller->ip;
        $port = $caller->port;
        $this->timeout( "$ip:$port" );
    }
}

?>
