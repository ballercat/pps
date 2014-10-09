<?php

Trait irc_commands {

   /* Bellow are commands called from IRC */
    public function quit( $user, $line )
    {
        if( !$this->admin_access( $user ) ) return;
        
        $sec = time() - $this->uptime;

        $this->send( "quit called: Leaving. Uptime: $sec seconds", $this->chan );
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

        $result = $this->pps->get_auth_stats_string( $this->users[$user] );

        if( $result ) {
            $this->send( $result, $this->chan );
        }
        else {
            $this->send( "Could not find $user", $this->chan );
        }
    } 

    public function rank( $user, $args = null ) {
        if( !$this->user_access($user) ) {

            $this->send( "No auth stored for user $user", $this->chan );
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
            $this->send( $name . "s rank: " . $result['rank'] . "/" . $result['total'], $this->chan );
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
                if( !is_numeric($rating) ) continue;
                $ratings[] = $rating;
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
        else if( $args[0] == 'empty' ) {
            $this->send( "Freeing everything", $this->chan );
            foreach( $this->gathers as $gather ) {
                $this->end_gather( $gather );
            }
        }

    }

}

?>
