<?php

include "refresh.php";
include "server.php";
include "irc_server.php";
include "gather_server.php";
include "mysql_server.php";

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
        $this->database = new mysql_server( "localhost", "pps", "noodles", "pps" );
    }

    public function __destruct()
    {
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
        /*if( array_key_exists( $key, $this->servers) ) {
            $info = $this->servers[$key]->get_info();
        }*/

        return array("No server found with this info: $key");
    }

    public function bind_user_auth( $name, $auth, $code ) 
    {
        $this->database->connect();
        echo "Bind user auth: $name $auth $code\n";
        $result = $this->database->bind_user_auth( $name, $auth, $code );
        $this->database->disconnect();
        return $result; 
    }

    public function get_auth_stats_string( $auth ) {
        $this->database->connect();
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
        return "$name rating: $rating KD: $kd CG: $CG +/-:$pm played(minutes):$played";
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
}


function test( &$pps, $ip ) 
{
    //$pps->test( $ip, "6667", ":cat!~art@m974636d0.tmodns.net PRIVMSG #soldat.na :!test gather 1 2 3" );// 4 5 6" );
    $pps->test( $ip, "6667", ":]{ing!~art@m974636d0.tmodns.net PRIVMSG #soldat.na :!test add manate astr" );
    exit( 0 );
    $pps->test( $ip, "6667", ":dog!~art@m974636d0.tmodns.net PRIVMSG #soldat.na :!add" );
    //$pps->test( $ip, "6667", ":]{ing!~art@m974636d0.tmodns.net PRIVMSG #soldat.na :!del" );
    $pps->test( $ip, "6667", ":rat!~art@m974636d0.tmodns.net PRIVMSG #soldat.na :!add" );
    //$pps->test( $ip, "6667", ":]{ing!~art@m974636d0.tmodns.net PRIVMSG #soldat.na :!del" );
    $pps->test( $ip, "6667", ":duck!~art@m974636d0.tmodns.net PRIVMSG #soldat.na :!add" );
    $pps->test( $ip, "6667", ":goose!~art@m974636d0.tmodns.net PRIVMSG #soldat.na :!add" );
    $pps->test( $ip, "6667", ":kiwi!~art@m974636d0.tmodns.net PRIVMSG #soldat.na :!add" );
    exit( 0 );
    $pps->test( $ip, "6667", ":bluejay!~art@m974636d0.tmodns.net PRIVMSG #soldat.na :!add" );
    $pps->test( $ip, "6667", ":whale!~art@m974636d0.tmodns.net PRIVMSG #soldat.na :!add" );
    $pps->test( $ip, "6667", ":lion!~art@m974636d0.tmodns.net PRIVMSG #soldat.na :!add" );
    //$pps->test( $ip, "6667", ":bluejay!~art@m974636d0.tmodns.net PRIVMSG #soldat.na :!del" );
    //$pps->test( $ip, "6667", ":whale!~art@m974636d0.tmodns.net PRIVMSG #soldat.na :!del" );
    //$pps->test( $ip, "6667", ":lion!~art@m974636d0.tmodns.net PRIVMSG #soldat.na :!del" );
    //$pps->test( $ip, "6667", ":lion!~art@m974636d0.tmodns.net PRIVMSG #soldat.na :!del" );
    $pps->test( $ip, "6667", ":lion!~art@m974636d0.tmodns.net NICK :Lion" );
        
    exit(0);
}

function test_gather( &$pps, $ip ) 

{
    $pps->servers["$ip:1337"]->connect();
    $pps->servers["$ip:1337"]->send('/nextmap');
    exit( 0 );
    $ratings = array( 10, 9, 8, 7, 6, 5 );
    $pps->test_gather( $ip, "6667", $ratings );
    exit( 0 );
}

$pps = new mock_pps();
$pps->add_game_server( "192.210.137.129", "40001", "noodles" );
$ip = gethostbyname("irc.quakenet.org");
$pps->add_chat_server( $ip, "6667", "HenryVIII", "#soldat.na" );
//test_gather( $pps, "192.210.137.129" );
//test( $pps, $ip );
$pps->connect();
$pps->monitor();

?>
