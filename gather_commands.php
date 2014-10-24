<?php

Trait gather_commands{
    
    function add ( $user, $args = null ) {

        if( count($args) ) {
            
            switch( $args[0] ) {

            case "-u":
                if( count($args) > 1 && $this->admin_access($user) ) {

                    $user = $args[1];
                    break;
                }
                return;
            case "-h":
            case "-help":
                $this->speak( "Add to current gather, or start a new one." );
                return;
            }
        }

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
            
            //$this->gc++;

            $this->current_gather = new gather_man( $this->gc, $game_server );
        }

        $result = false;
        if( $auth_record ) {

            $rank = $this->pps->get_player_rank( null, $auth_record['user_id'] );
            //Also send maps played
            $result = $this->current_gather->add_rated( $user, $auth_record['rating'], $rank, $auth_record['maps'], $auth_record['hwid'] );
        }
        else {

            $result = $this->current_gather->add( $user );
        }

        if( $result )
            $this->send( $result, $this->chan );

        if( $this->current_gather->is_full() ) {
            
            //Start gather
            $this->start_gather( $this->current_gather, 1 );
            $this->current_gather = null;
        }
    }

    function del ( $user, $args = null ) {

        if( count($args) ) {

            switch( $args[0] ) {

            case "-u":
                if( count($args) > 1 && $this->admin_access($user) ) {
                    $user = $args[1];
                    break;
                }
                return;
            case "-h":
            case "--help":
                $this->speak( "Delete from current gather." );
                return;
            };
        }

        if( $this->current_gather != null ) {

            $result = $this->current_gather->del( $user );

            //Remove the gather its empty
            if( $this->current_gather->is_empty() ) {

                $this->speak( 'Empty. Deleting gather ' . $this->current_gather->game_number );

                $this->end_gather( $this->current_gather );
                $this->current_gather = null;

                return;
            }

            if( $result )
                $this->send( $result, $this->chan );
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
                $this->speak( $gather->id_string() . " " . $gather->game_server->get_info() );
            }
        }

        if( !$i ) {

            $this->speak( "No gathers being played" );
        }
    }
    

}

?>
