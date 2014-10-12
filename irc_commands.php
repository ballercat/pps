<?php

Trait irc_commands {

   /* Bellow are commands called from IRC */
    public function quit( $user, $line )
    {
        if( !$this->admin_access( $user ) ) return;
        
        $sec = time() - $this->uptime;

        $tm = ( $sec > 59 ) ? round($sec/60, 2) . "min" : $sec . "sec";

        $this->speak( "Quit called: Leaving. Uptime: $tm" );

        exit(0);
    }

    public function sinfo ( $user, $args ) {
        $key = $args[0];
        $info = $this->pps->get_info( $key ); 
        foreach( $info as $info_string ) {
            $this->send( $info_string, $this->chan );
        } 
    }

    public function auth ( $user, $args = null ) {
        if( !$this->user_access($user) ) {

            $this->send( "No auth stored for user $user", $this->chan );
            return;
        }

        $this->authenticate( $user, $this->users[$user], $args[0] );
    }

    public function rating ( $user, $args = null ) {

        if( !array_key_exists($user, $this->users) || !$this->users[$user] ) {

            $this->send( "No auth stored for user $user", $this->chan );
            return;
        }          

        $result = $this->pps->get_auth_stats( $this->users[$user] );

        if( $result ) {

            $rank = $this->pps->get_player_rank( null, $result['user_id'] );
            
            $tp = $result['time_played'];
            
            $info = $result['name'];
            $info .= $this->rank2string( $rank['rank'], $rank['total'] ) . MCOLOR;
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

    public function kills( $user, $args = null ) {
        if( !$this->user_access( $user ) ) {

            $this->speak( "No auth stored for $user" );
        }

        $record = $this->pps->get_auth_stats( $this->users[$user] );

        $this->speak( $record['name'] . " kills: " . $record['kills'] );
    }

    public function rank( $user, $args = null ) {
        if( !$this->user_access($user) ) {

            $this->speak( "No auth stored for user $user", $this->chan );
            return;
        }
        
        $result = $this->pps->get_auth_stats( $this->users[$user] );

        if( !$result ) {
            $this->send( "Auth `" . $this->users[$user] . "` is not recognized\n", $this->chan );
            return;
        }

        $name = $result['name'];
        $result = $this->pps->get_player_rank( $name );
        if( $result ) {
            //$data = $this->rank2color( $result['rank'], $result['total'] );
            $data = $name; 
            $data .= "\t : " . $result['rank'] . " / " . $result['total'];
            $data .= "\t : " . $this->rank2string( $result['rank'], $result['total'] ); 

            $this->speak( $data );
        }
    }

    public function ls ( $user, $args = null ) {
        exec( "ps aux | grep php", $output );
        foreach( $output as $line ) {
            if( preg_match("/php\s+(?P<script>\w+)\.php$/", $line, $matches) ) {
                $script = $matches['script'];
                $this->send( "$script running...", $this->chan );
            }
        }
    }

    function authenticate( $user, $account, $code ) 
    {
        $result = $this->pps->bind_user_auth( $user, $account, $code );
        $this->send($result, $user);
    }

    public function test ( $user, $args = null ) {
        if( !array_key_exists(0, $args) ) return; 

        if( $args[0] == 'gather' ) {
            $ratings = array();
            foreach( $args as $rating ) {
                $ratings[] = $rating;
            }
            var_dump( $ratings );
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

}

?>
