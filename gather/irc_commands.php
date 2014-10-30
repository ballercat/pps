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

Trait irc_commands {

    public function help( $user, $line = null, $channel = null )
    {
        $this->speak( "Ranked stats gather. use !cmds for the list of commands. Try !<cmd> --help" );
    }

    public function commands( $user, $line = null, $channel = null )
    {
        $irc_cmd_list = get_class_methods( 'irc_commands' );
        $gather_cmd_list = get_class_methods( 'gather_commands' );

        $result = "Commands: ";
        foreach( $irc_cmd_list as $cmd ) {

            $result .= "!" . $cmd . " ";
        } 

        foreach( $gather_cmd_list as $cmd ) {

            $result .= "!" . $cmd . " ";
        }

        $this->speak( $result );

        if( $this->admin_access( $user ) ) {

            $result = "Admin commands: ";

            $adm_cmd_list = get_class_methods( 'admin_commands' );
            foreach( $adm_cmd_list as $cmd ) {

                $result .= "!" . $cmd . " ";
            }

            $this->speak( $this->highlight($result) );
        }

    }

    public function cmds( $user, $line = null, $channel = null ) { $this->commands($user); }

    public function info ( $user, $args ) {
        $key = ( array_key_exists( 0, $args ) ) ? $key = $args[0] : null;
        $info = $this->pps->get_info( $key ); 
        foreach( $info as $info_string ) {

            
            $this->speak( $info_string );
        } 
    }

    public function auth ( $user, $args = null, $channel = null ) {
        if( $channel != $this->nick ) {

            $this->error( "!auth command should only be used as a PM to the bot." );
            return;
        }

        if( !$this->user_access($user) ) {

            $this->error( $this->get_error_string(), $user );
            return;
        }

        $code = $args[0];
        if( strlen($code) != 6 ) {

            $this->error( "Invalid code. Must be six characters long", $user);
            return;
        }

        $accounts = $this->pps->get_account_users( $this->users[$user], $code );
        
        if( count($accounts) > 1 ) {

            $this->warning( "Can't auth. Auth(" . $this->users[$user]. ") has more than one account associated with it!", $user);
            $this->warning( "You must \"!merge <your code>\" to combine accounts before you can !auth", $user );
            return;
        }

        $this->authenticate( $user, $this->users[$user], $code );
    }

    public function merge( $user, $args = null, $channel = null ) {

        if( $channel != $this->nick ) {
            
            $this->error( "merge command can only be used as a PM to the bot." );
            return;
        }

        if( !$this->user_access( $user ) ) {

            $this->error( "No auth stored for user $user", $user );
            return;
        }

        if( !array_key_exists(0, $args) ) {

            $this->error( "No code supplied", $user );
            return;
        }

        $code = $args[0];
        if( strlen($code) != 6 ) {
            $this->error( "Invalid code. Must be six characters long", $user );
            return;
        }

        $text = $this->pps->merge_account_users( $code, $this->users[$user] );

        $this->speak( $text, $user );
    }

    public function rating ( $user, $args = null ) {

        if( count($args) && $args[0] == '-u' ) {

            if( count($args) != 2 ) return;

            $user = $args[1];
        } 
        
        if( !$this->user_access($user) ) {

            $this->error( $this->get_error_string(), $this->chan );
            return;
        }          

        $result = $this->pps->get_auth_stats( $this->users[$user] );

        if( $result ) {

            $rank = $this->pps->get_player_rank( null, $result['user_id'] );
            
            $tp = $result['time_played'];
            
            $info = $result['name'];

            if( $result['maps'] > 19 ) {

                $info .= $this->rank2string( $rank['rank'], $rank['total'] ) . MCOLOR;
            }
            else {

                $info .= $this->rank_N_string() . MCOLOR;
            }

            $info .= " ~ KD : " . $result['kd'] . " ~ CG: " . $result['cg'];

            $maps = ( $result['maps'] > 0 ) ? $result['maps'] : 1;
            $info .= " ~ WIN% : " . intval($result['wins']/($maps/100)) . " ~";

            if( $tp > 59 ) {
                $info .= " Played : " . round($tp/60, 2) . "h";
            }
            else {
                $info .= " Played : $tp" . "min";
            }

            $this->speak( $info );
        }
        else {

            $this->speak( "Could not find $user", $this->chan );
        }
    } 

    public function kills( $user, $args = null, $channel = null ) {
        if( !$this->user_access( $user ) ) {

            $this->error( $this->get_error_string(), $this->chan );
            return;
        }

        $record = $this->pps->get_auth_stats( $this->users[$user] );

        $this->speak( $record['name'] . " kills: " . $record['kills'] );
    }

    public function rank( $user, $args = null ) {

        if( count($args) && $args[0] == '-u' ) {

            if( count($args) != 2 ) return;

            $user = $args[1];
        }

        if( !$this->user_access($user) ) {

            $this->error( $this->get_error_string(), $this->chan );
            return;
        }
        
        $auth_stats = $this->pps->get_auth_stats( $this->users[$user] );

        if( !$auth_stats ) {

            $this->send( "This auth(" . $this->users[$user] . ") has no stats tied to it", $this->chan );
            return;
        }

        $name = $auth_stats['name'];
        $result = $this->pps->get_player_rank( $name );
        if( $result ) {
            //$data = $this->rank2color( $result['rank'], $result['total'] );
            $data = $name; 
            $data .= "\t : " . $result['rank'] . " / " . $result['total'];

            if( $auth_stats['maps'] > 19 ) 
                $data .= "\t : " . $this->rank2string( $result['rank'], $result['total'] ); 
            else
                $data .= "\t : " . $this->rank_N_string();

            $this->speak( $data );
        }
    }

    function played( $user, $args = null, $channel = null )
    {
        if( !$this->user_access( $user ) ) {

            $this->speak( "No auth stored for user $user" );
        }

        $result = $this->pps->get_auth_stats( $this->users[$user] );

        $now = new DateTime( "now" );
        $last = new DateTime( $result['lastplayed'] );
        $diff = date_diff( $now, $last );

        $this->speak( "$user played ". $result['maps'] . " maps. Last map " . $diff->format('%a days ago') );
    }

    function points( $user, $args = null, $channel = null )
    {
        if( !$this->user_access( $user ) ) {

            $this->error( "No auth stored for user $user" );
            return;
        }

        $auth_stats = $this->pps->get_auth_stats( $this->users[$user] );

        if( !$auth_stats ) {

            $this->warning( "No stats stored for auth: " . $this->users[$user] );
            return;
        }

        $points_record = $this->pps->get_player_points( $auth_stats['user_id'] );

        if( count( $args ) && $args[0] == '--id' ) {
            if( count( $args ) != 2 ) return;

            foreach( $points_record as $point ) {

                if( $point['id'] == $args[1] ) {

                    $this->speak( "#" . $point['id'] . " " . $point['type'] . ". " . "Issued on " . $point['issued'] . " by " . $point['issuer'] . " reason: \"" . $point['reason'] . "\""  );
                    return;
                }
            }
        }

        $point_count = array();

        foreach( $points_record as $point ) {

            if( !array_key_exists( $point['type'], $point_count ) ) {

                $point_count[ $point['type'] ] = 0;
            }

            $point_count[ $point['type'] ]++; 
        }

        foreach( $point_count as $key => $count ) {

            $text = BOLD . "$count $key(s).ids: " . BOLD;
            foreach( $points_record as $point ) {

                if( $point['type'] == $key ) {

                   $text .= "#". $point['id'] . " " ; 
                }
            }

            $this->speak( $text );
        }
    }

    function whypb( $user, $args = null, $channel = null ) {
        if( !$args ) return;

        $this->points( $user, $args, null );
    }

    function gather( $user, $args = null, $channel = null ) {
        $limit = 1;
        $id = null;
        if( count($args) ){
            switch( $args[0] ) {
            case "--id":
                if( count($args) < 2 ) return;
                $id = $args[1];
                break;
            case "--limit":
                if( count($args) > 1 && $args[1] < 6 && $args[1] > 0 ) {

                    $limit = $args[1];
                    break;
                }
                else {

                    return;
                }
            };
        }

        $gathers = $this->pps->get_last_gather( $limit, $id );

        if( !$gathers ) {

            if( $id ) 
                $this->error( "No gathers with id:$id found" );
            else
                $this->error( "No gathers stored..." );

            return;
        }

        foreach( $gathers as $gather ) {

            $text = "Gather " . $this->highlight(sprintf("%04d", $gather['id'])) ;
            $text .= " | " . $gather['played'] . " |";

            if( $gather['winner'] < 0 )
                $text .= BOLD . BLACK . ' FAILED';
            else if( $gather['winner'] == 0 ) 
                $text .= " Winner: " . BOLD . "TIE";
            else if( $gather['winner'] == 1 )
                $text .= " Winner: " . BOLD . RED . "Alpha";
            else if( $gather['winner'] == 2 )
                $text .= " Winner: " . BOLD . BRAVO . "Bravo";

            $this->speak( $text );
        }
    }
}

?>
