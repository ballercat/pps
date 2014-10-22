<?php

Trait irc_utility {

    function rank2color( $rank, $total )
    {
        if( $rank < 6 ) {

            return (BOLD . PURPLE);
        }

        $colors = array_fill (  0,10,                       TEAL);
        $colors = array_merge( $colors, array_fill(11,20,   TEAL) );
        $colors = array_merge( $colors, array_fill(21,30,   TEAL) );
        $colors = array_merge( $colors, array_fill(31,40,   TEAL) );
        $colors = array_merge( $colors, array_fill(41,100,  TEAL) );

        $p = intval($rank/($total/100));

        return $colors[$p];
    }

    function rank2string( $rank, $total ) 
    {
        if( $rank < 6 ) {

            return BOLD . PURPLE . "[SS]";
        }

        $strings = array_fill(0,10, RED . "[A]");
        $strings = array_merge( $strings, array_fill(11,10, BOLD . ORANGE  . "[B]" . BOLD) );
        $strings = array_merge( $strings, array_fill(21,10, BOLD . LIME    . "[C]" . BOLD) );
        $strings = array_merge( $strings, array_fill(31,10, BOLD . LBLUE   . "[D]" . BOLD) );
        $strings = array_merge( $strings, array_fill(41,61, BOLD . BLACK   . "[F]" . BOLD) );

        $p = intval($rank/($total/100));

        return $strings[$p];
    }

    function rank_N_color( ) { return BLACK; }
    function rank_N_string() { return BOLD . BLACK . "[N]" . BOLD; }

    function authenticate( $user, $account, $code ) 
    {
        $result = $this->pps->bind_user_auth( $user, $account, $code );
        $this->send($result, $user);
    }

    public function test ( $user, $args = null, $channel = null ) {
        if( !array_key_exists(0, $args) ) return; 

        if( $channel != $this->nick ) return;

        if( $args[0] == 'gather' ) {
            $ratings = array();
            foreach( $args as $rating ) {
                if( !is_numeric( $rating ) ) continue;
                $ratings[] = floatval( $rating );
            }
            $this->test_gather( $ratings );
        }
        else if( $args[0] == 'add' ) {
            if( array_key_exists(1, $args) ) {
                $this->add( $args[1] );
            }
        }
        else if( $args[0] == 'del' ) {
            if( array_key_exists(1, $args) ) {
                $this->del( $args[1] );
            }
        }
        else if( $args[0] == 'del_all' ) {
            $this->speak( "Delete all" );
        }
        else if( $args[0] == 'empty' ) {
            $this->speak( "Freeing everything" );
            while( list($key, $gather) = each($this->gathers) ) {
                $this->speak( "End gather #" . $gather->game_number );
                $this->end_gather( $gather ); 
            }
        }

    }

    public function quit( $user, $line, $channel = null )
    {
        if( $channel != $this->nick ) {

            $this->speak( "Don't..." );
            return;
        } 

        if( !$this->admin_access( $user ) ) return;
        
        $sec = time() - $this->uptime;

        $tm = round($sec/3600, 2) . " hours";

        $this->speak( "Quit called: Leaving. Uptime: $tm" );

        exit(0); //note exit nicer
    }

    public function ls ( $user, $args = null, $channel ) {
        
        if( $channel != $this->nick ) return;

        exec( "ps aux | grep php", $output );

        foreach( $output as $line ) {

            if( preg_match("/php\s+(?P<script>\w+)\.php$/", $line, $matches) ) {

                $script = $matches['script'];
                $this->send( "$script running...", $channel );
            }
        }
    }
    
    //Callback for automated gather timeout
    function timeout( $key ) {
        //debug_print_backtrace( 0, 1 );

        $refresh = $this->gathers[$key]->game_server->get_refreshx();
        if( !$refresh ) return;

        $result = $this->gathers[$key]->timeout( $refresh['players'] );

        if( $result ) {
            $this->speak( $result );
            $this->end_gather( $this->gathers[$key] );
        }
    }

    function start_gather( $gather, $tm_min = 0, $tm_sec = 0 ) {
        //Custom line parser for getting live soldat updates 
        $irc_copy = $this;

        $line_parser = function ( $caller, $line  ) use ($irc_copy) {
            $cmd = substr( $line, 0, 5 );
            $key = "$caller->ip:$caller->port";
            
            if( !array_key_exists($key, $irc_copy->gathers) ) return;

            if( method_exists('irc_server', $cmd) ) {

                $irc_copy->$cmd( $caller, $line );
            }
            else if( !strpos($line, "connected") && !strpos($line, "disconnected")) {

                if( $irc_copy->gathers[$key]->gather_timeout ) {

                    //echo "$line\n";
                    $irc_copy->timeout( $key );
                }
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
        }

        $this->gathers["$ip:$port"] = $gather;
    }

    function end_gather( $gather ) {
        $ip = $gather->game_server->ip;
        $port = $gather->game_server->port;

        $this->pps->release_game_server( "$ip:$port" );

        unset( $this->gathers["$ip:$port"] );
    }
}

?>
