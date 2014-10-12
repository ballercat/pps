<?php

Trait gather_commands{
    
    function add ( $user, $args = null ) {

        if( !$this->user_access( $user ) ) {

            $this->speak( "$user is not authed. Only authed users can add..." );
            return;
        }

        $auth_record = $this->pps->get_auth_stats( $this->users[$user] );

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
        if( $auth_record ) {
            $rank = $this->pps->get_player_rank( null, $auth_record['user_id'] );
            $result = $this->current_gather->add_rated( $user, $auth_record['rating'], $rank );
        }
        else {

            $result = $this->current_gather->add( $user );
        }

        if( !$result ) return;

        $this->send( $result, $this->chan );

        if( $this->current_gather->is_full() ) {
            
            //Start gather
            $this->start_gather( $this->current_gather, 3 );
        }
    }

    function del ( $user, $args = null ) {

        if( $this->current_gather != null ) {

            $result = $this->current_gather->del( $user );

            if( $result )
                $this->send( $result, $this->chan );
            
            //Remove the gather its empty
            if( $this->current_gather->is_empty() ) {

                $this->speak( 'Empty. Deleting gather ' . $this->current_gather->game_number );

                $this->end_gather( $this->current_gather );
                $this->current_gather = null;
                
            }
        }
    }

    function status ( $user, $args = null ) {
        

        if( $this->current_gather ) {

            $this->speak( $this->current_gather->get_info(), $this->chan );
        }
        else {

            $this->speak( "No gather pending...", $this->chan );
        }
    }
    
    function playing ( $user, $args = null ) {

        $i = 0;
        foreach( $this->gathers as $gather ) {

            if( $gather->is_full() ) {
                $i++;
                $this->speak( $gather->get_info() );
            }
        }

        if( !$i ) {
            $this->speak( "No gathers being played" );
        }
    }
    
    //Callback for automated gather timeout
    function timeout( $key ) {
        debug_print_backtrace( 0, 1 );

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

                    echo "$line\n";
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
