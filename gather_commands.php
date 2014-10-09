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
            $this->start_gather( $this->current_gather, 2 );
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
        
        foreach( $this->gathers as $gather ) {

            $this->send( $gather->get_info() , $this->chan ); 
        }

        if( !count($this->gathers) ) {

            $this->send( "No gathers being played...", $this->chan );
        }

        if( $this->current_gather ) {

            $this->send( $this->current_gather->get_info(), $this->chan );
        }
        else {

            $this->send( "No gather pending...", $this->chan );
        }
    }
    
    //Callback for automated gather timeout
    function timeout( $args ) {
        $refresh = $this->gathers[$args["key"]]->game_server->get_refreshx();
        if( !$refresh ) return;

        $result = $this->gathers[$args["key"]]->timeout( $refresh['players'] );

        if( $result ) {
            $this->send( $result, $this->chan );
            $this->end_gather( $this->gathers[$args["key"]] );
        }
    }

    function start_gather( $gather, $tm_min = 0, $tm_sec = 0 ) {
        //Custom line parser for getting live soldat updates 
        $irc_copy = $this;

        $line_parser = function ( $caller, $line  ) use ($irc_copy) {
            $cmd = substr( $line, 0, 5 );
            
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
