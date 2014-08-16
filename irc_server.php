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
//include 'server.php';

class irc_server extends ppsserver {
    public $ip;
    public $port;
    public $sock;

    public $nick;
    public $chan;

    public $buffer;

    public $type = SERVER_TYPE_IRC;

    public $hooked = false;

    public $pps; //kind of an ugly hack but helps a lot with getting server info etc.,

    public $auth_try;
    public $auth_cb;
    public $auth = null;

    public function __construct($ip, $port, $nick, $channel) 
    {
        $this->ip   = $ip;
        $this->port = $port;
        $this->nick = $nick;
        $this->chan = $channel;

        $this->sock = socket_create( AF_INET, SOCK_STREAM, SOL_TCP );
    }

    public function get_info() 
    {
        return "IRC server: $this->ip : $this->port ($this->chan)";
    }

    public function send( $data, $channel = null )
    {   if( !$this->sock ) {
            echo "irc_debug: $data\n";
            return;
        }

        if( !$channel ) {
            socket_write( $this->sock, "$data"."\r\n" );
        }
        else {
            socket_write( $this->sock, ": PRIVMSG $channel :$data\r\n" );
        }
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
        
        if( $this->connected && !$this->hooked ) {
            if( $line == "ERROR :Your host is trying to (re)connect too fast -- throttled" ) return;
            if( strpos($line, "End of /MOTD command.") !== false ) {
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

        if( !$this->hooked ) return;
        $tokens = array_filter( explode(' ', $line, 5) );

        $useragent  = (array_key_exists(0,$tokens)) ? $tokens[0] : null;
        $method     = (array_key_exists(1,$tokens)) ? $tokens[1] : null;
        $channel    = (array_key_exists(2,$tokens)) ? $tokens[2] : null;
        $cmd        = (array_key_exists(3,$tokens)) ? $tokens[3] : null;
        $args       = (array_key_exists(4,$tokens)) ? $tokens[4] : null;


        if( substr($line, 0, 3) === ":Q!" ) {
            $this->Q_respond( $cmd, explode(' ', $args) );
            return;
        }

        if( strpos($line, ":!") === false ) return;
            
        //Get vals 
        $user_tokens = explode('!', $useragent );
        $user = substr( $user_tokens[0], 1 );
        $args = array_filter( explode(' ', $args) );
        $method = substr( $cmd, 2 );
        
        if( method_exists('irc_server', $method) ) {
            
            $this->$method( $user, $args );
        }
    }

    private function Q_respond( $qmsg, $args  ) {
        if( $qmsg == ":-Information" ) {
            //':-Information for user <USER> (using account <ACCOUNT>):'
            //$args = explode(' ', $args );
            var_dump( $args );
            $this->auth = substr($args[5], 0, -2);
            $cb = $this->auth_cb;
            $this->$cb( $args[2] );
            $this->auth = null;
            $this->auth_cb = null;
        } 
    }

    private function authenticate( $user, $account ) 
    {
        $result = $this->pps->bind_user_auth( $user, $account, $this->auth_try );
        $this->send($result, $user);
    }

    /* Bellow are commands called from IRC */
    public function test( $user, $line )
    {
        $this->send( "test command called!", $this->chan );
    }

    public function quit( $user, $line )
    {
        $this->send( "quit called: Leaving", $this->chan );
        exit(0);
    }

    public function sinfo ( $user, $args ) {
        $key = $args[0];
        $info = $this->pps->get_info( $key ); 
        foreach( $info as $info_string ) {
            $this->send( $info_string, $this->chan );
        } 
    }

    public function auth ( $user, $args = null ) {
        if( !$this->auth ) {
            $this->auth_try = $args[0];
            $this->auth_cb = "auth";
            $this->send( "WHOIS $user", "Q" );
            return;
        }
        $this->authenticate( $user, $this->auth );
    }

    public function rating ( $user, $args = null ) {
        echo $user, "\n";
        if( !$this->auth ) {
            $this->auth_cb = "rating";
            $this->send( "WHOIS $user", "Q" );
            return;
        }
        $result = $this->pps->get_auth_stats($this->auth);
        if( $result ) {
            $this->send( $result, $this->chan );
        }
        else {
            $this->send( "Could not find $user", $this->chan );
        }
    }

    public function ls ( $user, $args = null ) {
        exec( "ps aux | grep php", $output );
        foreach( $output as $line ) {
            if( preg_match("/php\s+(?P<script>\w+)\.php$/", $line, $matches) ) {
                $script = $matches['script'];
                $this->send( "$script running...", $this->chan );
            }
        }
    }
}

?>
