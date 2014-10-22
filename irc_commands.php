<?php

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
    }

    public function cmds( $user, $line = null, $channel = null ) { $this->commands($user); }

    public function info ( $user, $args ) {
        $key = $args[0];
        $info = $this->pps->get_info( $key ); 
        foreach( $info as $info_string ) {

            
            $this->speak( $info_string );
        } 
    }

    public function auth ( $user, $args = null, $channel = null ) {
        if( $channel != $this->nick ) $this->error( "auth command should only be used as a PM to the bot." );

        if( !$this->user_access($user) ) {

            $this->error( "No auth stored for user $user", $user );
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
        
        if( !array_key_exists($user, $this->users) || !$this->users[$user] ) {

            $this->error( "No auth stored for user $user", $this->chan );
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
        

    }

}

?>
