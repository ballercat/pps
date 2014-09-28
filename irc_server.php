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

define( 'SERVER_TYPE_IRC',  1 );

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
    public $auth_cb_args = null;

    public $gathers;
    public $gc;
    public $current_gather = null;

    public function __construct($ip, $port, $nick, $channel) 
    {
        $this->ip   = $ip;
        $this->port = $port;
        $this->nick = $nick;
        $this->chan = $channel;
        
        $this->gathers = array();
        $this->gc = 0;
    }

    public function test_gather( $players ) {
        $game_server = $this->pps->request_game_server();
        if( !$game_server ) {
            $this->send( "No available game servers.", $this->chan );
            return;
        }

        $tg = new gather_man( 0, $game_server );

        $game_server->set_line_parser( $line_parser );

        $names = array( "cat", "dog", "mouse", "duck", "sheep", "wolf" );
        $i = 0;
        foreach( $players as  $rating ) {
            $result = false;
            $name = $names[$i];
            if( $rating ) {
                $result = $tg->add_rated( $name, $rating );
            }
            else {
                $result = $tg->add( $name );
            }

            $this->send( $result, $this->chan );
            $i++;
        }
        if( $i < 5 ) {
            for( $i ; $i < 6; $i++ ) {
                $result = $tg->add( $names[$i] );
                $this->send( $result, $this->chan );
            }
        }

        $this->start_gather( $tg, 0, 20 );
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
        echo "$line\n";      
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
            $this->Q_respond( $cmd, explode(' ', $args) );
            return;
        }

        //Check for nick changes
        if( strpos($line, "NICK") ) {
            $ut = explode( "!", $useragent );
            $u = substr( $ut[0], 1 );

            echo "Old nick:$u New nick:" . substr($channel, 1) . "\n";

            foreach( $this->gathers as $gather ) {
                $result = $gather->nickchange( $u, substr($channel, 1) );
                if( $result ) {
                    $this->send( $result, $this->chan );
                }
            }
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
            $this->auth = substr($args[5], 0, -2);
            $cb = $this->auth_cb;
            $this->$cb( $args[2], $this->auth_cb_args );
            $this->auth = null;
            $this->auth_cb = null;
        } 
    }

    private function whois( $name, $callback )
    {   //Only a 'tiny' rabbit hole
        //This will send and AUTH command to Q;
        //read_line() will fire off a Q_response
        //while the function exits and other commands can finish

        $this->auth_try = $name;
        $this->auth_cb = $callback;
        $this->send( "WHOIS $name", "Q" );
    }

    private function authenticate( $user, $account, $code ) 
    {
        $result = $this->pps->bind_user_auth( $user, $account, $code );
        $this->send($result, $user);
    }

    /* Bellow are commands called from IRC */
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
        if( $this->auth === null ) {
            $this->auth_cb_args = $args; 
            $this->whois( $user, "auth" );
            return;
        }
        
        $this->authenticate( $user, $this->auth, $args[0] );
    }

    public function rating ( $user, $args = null ) {
        if( $this->auth === null ) {
            $this->whois( $user, "rating" );
            return;
        }
            
        $result = $this->pps->get_auth_stats_string($this->auth);
        if( $result ) {
            $this->send( $result, $this->chan );
        }
        else {
            $this->send( "Could not find $user", $this->chan );
        }
    } 

    public function rank( $user, $args = null ) {
        if( $this->auth === null ) {
            $this->whois( $user, "rank" );
            return;
        }

        if( $this->auth === false ) return;

        $result = $this->pps->get_auth_stats( $this->auth );
        if( !$result ) {
            $this->send( "Auth `$this->auth` is not recognized\n", $this->chan );
            return;
        }
        $name = $result['name'];
        $result = $this->pps->get_player_rank( $name );
        if( $result ) {
            $this->send( $name . "s rank: " . $result['rank'] . "/" . $result['total'], $this->chan );
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

    public function add ( $user, $args = null ) {
        if( $this->auth === null ) {
            $this->whois( $user,  "add" );
            return;
        } 
        $auth_record = $this->pps->get_auth_stats( $this->auth );

        if( $this->current_gather === null ) {
            $game_server = $this->pps->request_game_server();

            if( $game_server == null ) { //No available game servers 
                $this->send( 'No available game servers.', $this->chan );
                return;
            }
            
            $this->gc++;

            $this->current_gather = new gather_man( $this->gc, $game_server );
        }

        $result = false;
        if( $auth_record ) 
            $result = $this->current_gather->add_rated( $user, $auth_record['rating'] );
        else
            $result = $this->current_gather->add( $user );
        
        if( !$result ) return;
        $this->send( $result, $this->chan );

        if( $this->current_gather->is_full() ) {
            //Start gather
            $this->start_gather( $this->current_gather, 2 );
        }
    }

    public function del ( $user, $args = null ) {
        if( $this->current_gather != null ) {
            $result = $this->current_gather->del( $user );
            if( $result )
                $this->send( $result, $this->chan );
            
            //Remove the gather its empty
            if( $this->current_gather->is_empty() ) {
                $this->send( 'Empty. Deleting gather ' . $this->current_gather->game_number, $this->chan );

                $this->end_gather( $this->current_gather );
                $this->current_gather = null;
                
            }
        }
    }

    public function test ( $user, $args = null ) {
        if( $args[0] && $args[0] == 'gather' ) {
            $ratings = array();
            foreach( $args as $rating ) {
                if( !is_numeric($rating) ) continue;
                $ratings[] = $rating;
            }
            $this->test_gather( $ratings );
        }
        else if( $args[0] && $args[0] == 'add' ) {
            if( $args[1] ) {
                $this->auth = 'nooober';
                if( $args[2] ) {
                    $this->auth = $args[2];
                }
                $this->add( $args[1] );
                $this->auth = null;
            }
        }
        else if( $args[0] && $args[0] == 'free' ) {
            $this->send( "Freeing all servers", $this->chan );
        }
        else if( $args[0] && $args[0] == 'empty' ) {
            $this->send( "Freeing everything", $this->chan );
            unset( $this->gathers );
            $this->gathers = [];
        }

    }

    //Callback for automated gather timeout
    public function timeout( $args ) {
        $refresh = $this->gathers[$args["key"]]->game_server->get_refreshx();
        if( !$refresh ) return;

        $result = $this->gathers[$args["key"]]->timeout( $refresh['players'] );

        if( $result ) {
            $this->send( $result, $this->chan );
            $this->end_gather( $this->gathers[$args["key"]] );
        }
    }

    public function start_gather( $gather, $tm_min = 0, $tm_sec = 0 ) {
        //Custom line parser for getting live soldat updates 
        $irc_copy = $this;
        $line_parser = function ( $caller, $line  ) use ($irc_copy) {
            $cmd = substr( $line, 0, 5 );
            //echo "$line\n";
            
            if( method_exists('irc_server', $cmd) ) {
                $key = "$caller->ip:$caller->port";
                if( $irc_copy->gathers[$key]->gather_timeout ) {
                    $irc_copy->timeout( array( "key" => $key ) );
                }

                $irc_copy->$cmd( $caller, $line );
            } 
        };

        $gather->game_server->set_line_parser( $line_parser );

        $ip = $gather->game_server->ip;
        $port = $gather->game_server->port;

        $this->send( $gather->start(), $this->chan );
        $gather->game_server->set_tiebreaker( $gather->game_tiebreaker );

        if( $tm_min || $tm_sec ) {
            $gather->game_server->set_timer( $tm_min * 60 + $tm_sec, "$ip:$port" );
            $gather->set_timeout( $tm_min * 60 + $tm_sec );
            $this->send( "This gather has a $tm_min minute $tm_sec second timeout.", $this->chan );
        }

        $this->gathers["$ip:$port"] = $gather;
    }

    public function end_gather( $gather ) {
        $ip = $gather->game_server->ip;
        $port = $gather->game_server->port;

        echo "Release $ip:$port server\n";
        $this->pps->release_game_server( "$ip:$port" );

        unset( $this->gathers["$ip:$port"] );
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
        list( $cmd, $key ) = explode( " ", $line, 2 );
        $this->timeout( array("key" => $key) );
    }
}

?>
