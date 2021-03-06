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

Trait gather_control{

    //Callback for automated gather timeout
    function timeout( $key ) {
        ///debug_print_backtrace( 0, 1 );

        $refresh = $this->gathers[$key]->game_server->get_refreshx();
        if( !$refresh ) return false;

        $result = $this->gathers[$key]->timeout( $refresh['players'] );

        if( $result ) {
            $this->speak( $result );

            //Iussue banpoints for no shows
            /*foreach( $this->gathers[$key]->player_hwid as $name=>$hwid ) {

                $authr = $this->pps->get_auth_stats( $this->users[$name] );
                $this->pps->give_player_points( $authr['user_id'], 1, "banpoint", "no show to gather #". $this->gathers[$key]->game_number , "Gatherbot" );
                $this->warning( "$name did not show up to gather. Banpoint issued\n" );
            }*/

            $this->end_gather( $this->gathers[$key] );
            return true;
        }

        return false;
    }

    function init_gather( $region )
    {
        if( $this->current_gather === null ) {

            $game_server = $this->pps->request_game_server( $region );
            if( !$game_server ) {

                $this->error( "No available $region game servers" );
                return false;
            }

            $game_server->gather = $this;
            $this->current_gather = new gather_man( $this->pps->get_max_gather_id()+1, $game_server );

            return true;
        } 
        else {

            $this->speak( $this->current_gather->get_info() );
        }

        return false;
    }

    function start_gather( $gather, $tm_min = 0, $tm_sec = 0 ) {
        $gather->game_number = $this->pps->create_gather();
        //Custom line parser for getting live soldat updates 
        $irc_copy = $this;

        $line_parser = function ( $caller, $line  ) use ($irc_copy) {

            $cmd = substr( $line, 0, 5 );
            $key = "$caller->ip:$caller->port";

            if( !array_key_exists($key, $irc_copy->gathers) ) return;

            if( strpos($line, "!") ) {
                $tokens = array_filter( explode(' !', $line, 3) );
                print_r($tokens);
                $user = (array_key_exists(0,$tokens)) ? $tokens[0] : null;
                //$cmd = (array_key_exists(1,$tokens)) ? "_" . $tokens[1] : null; 
                $txt = (array_key_exists(1,$tokens)) ? $tokens[1] : null;
                if( $txt ) 
                {                
                    $tokens = array_filter( explode(' ', $txt, 2) );
                    $cmd = (array_key_exists(0,$tokens)) ? "_" . $tokens[0] : null;
                    $txt = (array_key_exists(1,$tokens)) ? $tokens[1] : null;
                    $line = $user . " " . $txt;
                }
            }
            
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

        $this->send( $gather->start($tm_min, $tm_sec), $this->chan );
        $gather->game_server->set_tiebreaker( $gather->game_tiebreaker );

        if( $tm_min || $tm_sec ) {
            $gather->game_server->set_timer( $tm_min * 60 + $tm_sec, "$ip:$port" );
            $gather->set_timeout( $tm_min * 60 + $tm_sec );
        }

        //PM all player to show up to gather
        foreach( $gather->alpha->p as $player ) {
            
            $this->speak( "$player gather #" . $gather->game_number . " has started you are on Alpha. Server: " . 
                            $gather->get_server_info(), $player );
        }
        foreach( $gather->bravo->p as $player ) {
            
            $this->speak( "$player gather #" . $gather->game_number . " has started you are on Bravo. Server: " . 
                            $gather->get_server_info(), $player );
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
