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

require( ABS_PATH . '/gather/irc_utility.php');
require( ABS_PATH . '/gather/gather.php' );
require( ABS_PATH . '/gather/gather_control.php' );
require( ABS_PATH . '/gather/gather_commands.php');
require( ABS_PATH . '/gather/irc_commands.php');
require( ABS_PATH . '/gather/admin_commands.php');
require( 'irc_server_test.php' );
require( ABS_PATH . '/gather/qnet_users.php');

require( ABS_PATH . '/utility/help.php' );

define( 'SERVER_TYPE_IRC',  1 );

class irc_server extends ppsserver {
    Use qnet_users, irc_utility, irc_server_test;
    Use irc_commands, gather_commands, admin_commands;
    Use gather_control;
    Use help_commands;

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

    public $error_string = "";

    public $gathers;
    public $gc;
    public $current_gather = null;

    public $gather_to_sec = 320;

    public $init = false;

    public $users;

    public $admins;

    public $top_voice = 15;

    public $flood_check = 0;

    public $bad_line = null;

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function __construct($ip, $port, $nick, $channel) 
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        $this->ip   = gethostbyname($ip);
        $this->port = $port;
        $this->nick = $nick;
        $this->chan = $channel;
        
        $this->gathers = array();
        $this->gc = 0;

        $this->uptime = time();

        $this->users = array();
        $this->admins = array( );

        $this->flood_check = microtime(true);
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function admin_access( $user ) 
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        if( !array_key_exists( $user, $this->users ) ) {

            $this->set_error( "$user is not a user in system. Admins must be authed" );
            return false;
        }
        if( !array_key_exists( $user, $this->admins ) ) {

            $this->set_error( "$user is not an admin" );
            return false;
        }

        if( $this->admins[$user] ) return true;

        $this->set_error( "Admin access for $user is denied" );

        return false;
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function user_access( $user )
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        if( !array_key_exists( $user, $this->users ) ) {
            
            $this->set_error( "$user is not a user in system" );
            return false;
        }
        if( $this->users[$user] === false ) {

            $this->set_error( "No auth stored for $user" );
            return false;
        }

        return true;
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function get_info() 
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        return "IRC server: $this->ip : $this->port ($this->chan)";
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function send( $data, $channel = null, $wait = 1000000 )
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {   
        $lines = explode( "\n", $data );
        if( !$this->sock ) {
            foreach( $lines as $line ) 
                if( strlen($line) ) echo "irc_debug: $line\n";
            return;
        }

        if( !$channel ) {

            foreach( $lines as $line ) {

                if( strlen($line) ) socket_write( $this->sock, "$line"."\r\n" );
                $this->flood_check = microtime(true);
            }
        }
        else {

            //if( $channel == $this->chan ) $channel = "]{ing"; 

            foreach( $lines as $line ) {

                //$interval = microtime(true) - $this->flood_check;
                //if( $this->connected && $this->hooked && $interval < 1.0 ) {

                usleep($wait);
                //}
                if( strlen($line) ) socket_write( $this->sock, ": PRIVMSG $channel :$line\r\n" );
                $this->flood_check = microtime(true);
            }
        }
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function speak( $data, $channel = null, $token = '~' ) 
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        if( $channel == null ) $channel = $this->chan;

        if( $token != '~' ) {

            switch( $token ) {
            case 'x':
                $data = RED . BOLD . " $token " . BOLD . MCOLOR . $data;
                break;
            case 'o':
                $data = GREEN . BOLD . " $token " . BOLD . MCOLOR . $data;
                break;
            };
        }
        else {

            $data = BLACK . BOLD . " $token " . BOLD . MCOLOR . $data;
        }
        $this->send( $data, $channel );
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function highlight( $text ) { return TEAL . BOLD . $text . BOLD . MCOLOR; }
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function set_error( $text ) { $this->error_string = $text; }
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function get_error_string() { return $this->error_string; }
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function error( $data, $channel = null )
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        if( $channel == null ) $channel = $this->chan;
        $this->send( " " . BOLD . RED . UNDERLINE . "/!\\" . NORMAL .  " " . MCOLOR .  $data, $channel );
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function success( $data, $channel = null )
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        if( $channel == null ) $channel = $this->chan;
        $this->send( " " . BOLD . GREEN . UNDERLINE . "/!\\" . NORMAL .  " " . MCOLOR .  $data, $channel );
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function warning( $data, $channel = null, $user = null ) 
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        $text = "";
        if( $user != null ) {

            $text .= BOLD . $user . BOLD;
        };

        $text .=  " " . BOLD . YELLOW . UNDERLINE . "/!\\" . NORMAL . " " . MCOLOR . $data;

        if( !$channel ) $channel = $this->chan; 

        $this->send( $text, $channel );
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function readbuffer( $size = 512 ) 
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {   if( $this->sock == null ) return false;

        //PHP_BINARY_READ is great but it causes events where the data read does 
        //NOT get terminated by a \r\n. Causing parsing problems. Specificaly on 
        //large userlist in irc channel
        while( $this->buffer = trim( socket_read($this->sock, 512, PHP_NORMAL_READ) ) )
            $this->parse_buffer();

        return true;
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function PONG( $ping )
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    { if( !$this->sock ) return;
        $pong = substr_replace( $ping, 'O', 1, 1);
        $this->send( $pong );
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function MODE( $mode, $name ) 
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        switch( $mode ) {
        case "+o":
            $this->admins[$name] = true;
            return;
        case "-o":
            $this->admins[$name] = false;
            return;
        };
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function connect( $timeout = 10, $reconnect = true ) 
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        $this->sock = socket_create( AF_INET, SOCK_STREAM, SOL_TCP );
        socket_connect( $this->sock, $this->ip, $this->port );

        if( $this->sock ) {
            $this->send( "NICK $this->nick" ); //TODO: the server can reject this nickname. Have to handle this!
            $this->send( "USER pps_mon pps_mon pps_mon :SOLDATMON\r\n" );
            $this->connected = true;
            return false;
        }

        error_log( "IRC server connect failed: " . $this->ip . ":" . $this->port );
        return true;
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function disconnect()
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        socket_close( $this->sock );
        $this->connected = false;
        $this->sock = null;
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function parse_buffer() 
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        $lines = explode( "\r\n", $this->buffer );
        for( $i = 0; $i < count($lines); $i++)  {
            $line = $lines[$i];
            /*echo "-->" . substr($line, -1) ."|". bin2hex(substr($line,-1) ) ."\n";
            if( substr($line, 0, 4) != "PING" && substr($line,-1) != '\r' ) {
                //bad line break
                if( $i+1 >= count($lines) ) {

                    //bad line
                    $this->bad_line = $line;
                    return;
                }
                else {
                    $i++;
                    $line .= $lines[$i];
                }
            }

            if( $this->bad_line != null && $i == 0 ) {

                $line = $this->bad_line . $line;
                $this->bad_line = null;
            }*/
            $this->parse_line( $line );
        }
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function parse_line( $line )
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {   if( !strlen($line) ) return;
    //        echo "IRC:$line\n";
        if( $this->connected && !$this->hooked ) {

            if( $line == "ERROR :Your host is trying to (re)connect too fast -- throttled" ) return;
            if( strpos($line, "End of /MOTD command.") !== false || strpos($line, "422 " . $this->nick) !== false ) {

                $this->send( "AUTH ppsbot yak1soba", "Q@CServe.quakenet.org" );
                socket_write( $this->sock, "JOIN $this->chan\r\n" );
                $this->hooked = true;
                //Sleeping here. Otherwise userlist might get segmented
                sleep(2); 
                return;
            }
        }

        if( substr($line, 0, 4) == "PING" ) {
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

        //Check for nick changes
        if( $method == "NICK" ) {

            $ut = explode( "!", $useragent );
            $u = substr( $ut[0], 1 );
            $n_nick = substr($channel, 1);

            if( array_key_exists($u, $this->users) ) {

                $this->users[$n_nick] = $this->users[$u];
                unset( $this->users[$u] );
            }
            
            if( array_key_exists($u, $this->admins) && $this->admins[$u] ) {

                $this->admins[$n_nick] = true;
                unset( $this->admins[$u] );
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

            unset( $this->admins[$u] );

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
        else if( $method == "MODE" ) {

            if( $cmd == '+o' ) {

                $this->admins[$args] = true;
            }
            else if( $cmd == '-o' ) {

                $this->admins[$args] = false;
            }

            return;
        }


        if( substr($line, 0, 3) === ":Q!" ) {
            $this->Q_respond( $cmd, $args );
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

                $user = ltrim( $user, '+' );

                if( $user[0] == '@' ) {

                    $this->admins[ ltrim($user,'@') ] = true;
                }

                $user = ltrim( $user, '@' );

                if( $user == $this->nick ) continue;
                if( $user == 'Q' || $user == 'S' || $user == 'D' ) continue;

                $this->auth_array[] = $user;
            }

            //Initialize with a timer, to avoid hangs on failed irc server responce. This happens 3/100 times
            $this->store_auth( null, null );

            return;
        }

        if( $this->init ){
            if( time() - $this->init > 5 ) {
                //Give irc server 5 seconds to respond(PER USER)
                //if not just skip to next one
                //Skipping users causes them to be unable to add/use commands, but can be fixed with them
                //rejoining the server.
                $this->store_auth( null, null );
            } 
            return; 
        }
        if( strpos($line, ":!") === false ) return;

        //Get vals 
        $user_tokens = explode('!', $useragent );
        $user = substr( $user_tokens[0], 1 );
        $args = array_filter( explode(' ', $args), 'strlen' );
        $method = substr( $cmd, 2 );

        if( method_exists('gather_commands', $method) ||
            method_exists('irc_commands', $method) ||
            method_exists('admin_commands', $method) ) 
        {
            
            if( $this->check_help_args($args) ) {

                if( method_exists('help_commands', 'help_' . $method) ) {

                    $method = 'help_' . $method;
                }
                else {

                    $this->speak( "No help available for " . $this->highlight($method) );
                    return;
                }
            }
            $this->$method( $user, $args, $channel );
        }
    }
    
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function user_rank_letter_str( $user_id )
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {

        $rank = $this->pps->get_player_rank( null, $user_id );
        $total = $rank['total'];
        $prank = $rank['rank'];
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function game_server_dc( $server_key ) 
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    {
        //debug_print_backtrace(0,3);
        if( array_key_exists($server_key, $this->gathers) ) {

            $this->warning( "$server_key has died! Ending gather #" . $this->gathers[$server_key]->game_number );
            $this->end_gather( $this->gathers[$server_key] );
            return;
        } 
        if( $this->current_gather ) {

            $c = $this->current_gather->game_server->ip . ":" . $this->current_gather->game_server->port;
            if( $c == $server_key ) {

                $this->warning( "$server_key has died! Ending gather #" . $this->current_gather->game_number );
                $this->end_gather( $this->current_gather );
                $this->current_gather = null;
            }
        }
    }

    //SOLDAT SERVER COMMANDS
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function PJOIN( $caller, $line ) {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    

        $port = $caller->port;
        $ip = $caller->ip;

        if( !array_key_exists("$ip:$port", $this->gathers) ) return;
        
        if( $this->gathers["$ip:$port"]->gather_timeout ) {
            $this->timeout( "$ip:$port" );
        }

        list( $cmd, $hwid, $id, $team, $name ) = explode( " ", $line, 5 );

        //We can hook in here to tie the HWID to the auth!
        //NOTE: Check for no-shows here! Awesome.
        //TODO: HWID stuff, compare with/any currently rated players added 

        //ALPHA: 1, BRAVO: 2 SPEC: 5
        if( $team == 1 || $team == 2 ) {

            $this->gathers["$ip:$port"]->player_joined( $name, $hwid );
        }
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function PLEFT( $caller, $line ) {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    

        $port = $caller->port;
        $ip = $caller->ip;
        $key = "$ip:$port";
        //if( !array_key_exists($key, $this->gathers) ) return;

        if( $this->gathers[$key]->gather_timeout ) {

            $this->timeout($key );
        }

        list( $cmd, $id, $team, $name ) = explode( " ", $line, 4 );

        $leave_time = time();
        if( $team == 1 || $team == 2 ) {
            $result = $this->gathers[$key]->player_left( $name );

            if( $result ) {

                $this->send( $result );

                if( $this->gathers[$key]->is_game_empty() )
                    $this->end_gather( $this->gathers[$key] );
            }
        }

    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function PCAPF( $caller, $line ) {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    

        $key = $caller->get_key();
        if( !array_key_exists($key, $this->gathers) ) return;

        $this->gathers[$key]->cap(); 
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function NXMAP( $caller, $line ) {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    

        $ip = $caller->ip;
        $port = $caller->port;
        $key = "$ip:$port";

        /*$this->pps->write_refresh(  $this->gathers[$key]->game_number,
                                    $this->gathers[$key]->game_server->region,
                                    serialize($this->gathers[$key]->refresh) ); */
        
        if( !array_key_exists($key , $this->gathers) ) return;

        if( $this->gathers[$key]->gather_timeout ) {
            if( $this->timeout( $key ) )
                return;
        }

        $result = $this->gathers[$key]->nextmap( );
        if( $result != false ) {

            $this->send( $result, $this->chan );
        }
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function TIMER( $caller, $line ) {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
        //I can't really do poll events in php very well.
        //But Soldat can... sending /timer <seconds> <string> to soldat server will make it fire off this 
        //command back to console after <seconds> with <string> suplied
        $ip = $caller->ip;
        $port = $caller->port;
        $this->timeout( "$ip:$port" );
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function GSTRT( $caller, $line ) {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function GDONE( $caller, $line ) {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function _irc( $caller, $line ) {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    

        $text = $caller->get_tag_string() . " " . $line;
        $this->speak( $text );     
    }

	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    
    public function _sub( $caller, $line ) {
	/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */    

        $text = $caller->get_tag_string() . " Sub needed! To sub type: !sub " . $caller->tag;
        $this->speak( $text );
        $this->speak( $caller->get_info() );
    }
}

?>
