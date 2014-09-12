<?php

include "refresh.php";
include "server.php";
include "irc_server.php";
include "soldat_server.php";

/* Just a wrapper to manage the irc_server */
class mock_pps {
    public $servers     = [];
    public $sockets     = [];
    public $database    = null;

    public function __construct() 
    {
    }

    public function __destruct()
    {
    }

    public function add_game_server( $ip, $port, $adminlog ) {
        $this->servers["$ip:$port"] = new soldat_server( $this, $ip, $port, $adminlog );
    }

    public function add_chat_server( $ip, $port, $nick, $chan ) {
        $this->servers["$ip:$port"] = new irc_server( $ip, $port, $nick, $chan );
        $this->servers["$ip:$port"]->pps = $this;
    }

    public function test( $ip, $port, $buffer ) 
    {
        $this->servers["$ip:$port"]->parse_line( $buffer );
    }

    public function connect()
    {
        $this->database = new mysqli( "localhost", "pps", "noodles", "pps" );
        if( !$this->database ) {
            echo "Could not connect to database\n";
            exit(-1);
        }

        foreach( $this->servers as $key => $server ) {
            if( $server->type === SERVER_TYPE_IRC ) {
                $server->connect();
                $this->sockets[] = $server->sock;
            }
        }
    }

    public function monitor()
    {
        $socks = $this->sockets;
        do{
            if( count($socks) != $this->sockets ) {
                $socks = $this->sockets;
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
        $data = mysqli_query($this->database, "SELECT name FROM players WHERE code=\"$code\"");
        $record = mysqli_fetch_array($data);
        if( !$record ) {
            return "Could not locate record for code:$secret_code";
        }
        else {
            mysqli_query($this->database, "UPDATE players SET auth=\"$auth\" WHERE code=\"$code\"");
            $name = $record['name'];
            return "Sucess! User $name, has been updated with auth:$auth";
        }
    }

    public function get_auth_stats( $auth ) {
        $data = mysqli_query($this->database, "SELECT * FROM players WHERE auth=\"$auth\"");
        if( $data ) {
            $record = mysqli_fetch_array( $data );
            if( $record ) {
                $name = $record['name'];
                $rating = $record['rating'];
                $kills = $record['kills'];
                $deaths = $record['deaths'];
                $caps = $record['caps'];
                $grabs = $record['grabs'];
                $played = $record['time_played'];
                $pm = $record['plusminus'];
                return "$name rating:$rating k/d:$kills/$deaths c/g:$caps/$grabs +/-:$pm played(minutes):$played";
            }
            else {
                return null;
            }
        } else {
            return null;
        }
    }
}


function test( &$pps, $ip ) 
{
    $pps->test( $ip, "6667", ":cat!~art@m974636d0.tmodns.net PRIVMSG #soldat.na :!add" );
    //$pps->test( $ip, "6667", ":]{ing!~art@m974636d0.tmodns.net PRIVMSG #soldat.na :!del" );
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

$pps = new mock_pps();
$pps->add_game_server( "192.210.137.129", "23073", "noodles" );
$ip = gethostbyname("irc.quakenet.org");
$pps->add_chat_server( $ip, "6667", "catladdy", "#soldat.na" );
test( $pps, $ip );
$pps->connect();
$pps->monitor();

?>
