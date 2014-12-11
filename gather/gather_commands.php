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


Trait gather_commands{
    
    function on ( $user, $args = null, $channel = null ) {

        $this->warning( $this->highlight("!on") . " is not a command. Use " . $this->highlight("!eu") . " or " . $this->highlight("!na") . " to start a gather" );
    }

    function eu( $user, $args = null, $channel = null ) {

        if( $this->current_gather ) {

            $this->warning( "Only one gather at a time is supported(so far)" );
            return;
        }
        if( $this->init_gather( "EU" ) ) {

            $this->add( $user, $args, $channel );
        }
    }

    function na( $user, $args = null, $channel = null ) {

        if( $this->current_gather ) {

            $this->warning( "Only one gather at a time is supported(so far)" );
            return;
        }
        if( $this->init_gather( "NA" ) ) {

            $this->add( $user, $args, $channel );
        }
    }

    function add ( $user, $args = null ) {

        if( $this->current_gather === null ) {

            $this->warning( "No gathers running at the moment. Use " . $this->highlight('!na') . " or " . $this->highlight('!eu') . " to start one" );
            return;
        }

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

        /*if( $this->current_gather === null ) {

            $game_server = $this->pps->request_game_server();

            if( $game_server == null ) { //No available game servers 

                $this->send( 'No available game servers.', $this->chan );
                return;
            }
            
            $this->current_gather = new gather_man( $this->pps->get_max_gather_id()+1, $game_server );
        }*/

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
            $this->start_gather( $this->current_gather, 0, $this->gather_to_sec );
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

            $this->speak( "No gather pending..." );
        }

        $server_status = $this->pps->get_status();
        foreach( $server_status as $status ) {

            //This is a hack where get_status() adds a 1 character token indicating availability of the server
            $this->speak( substr($status, 1), $this->chan, substr($status,0,1) );
        }
    }
    
    function players ( $user, $args = null ) {

        $i = 0;
        $info = $this->pps->get_info();
        foreach( $info as $players ) {

            $this->speak( $players );
        }
    }
    
    function sub ( $user, $args = null ) {

        if( !count($args) ) return;
        $tag = $args[0]; 
        foreach( $this->gathers as $gather ) {

            if( $gather->game_server->tag == $tag ) {

                $this->speak( "You have added as a sub to gather " . $tag . ". Server info: $gather->server_info", $user );
                $gather->game_server->private_message(0, "Sub found, $user is coming" );
            }
        }
    }
}

?>
