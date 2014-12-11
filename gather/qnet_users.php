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

Trait qnet_users 
{
    function Q_respond( $qmsg, $args ) {
        $args = array_filter(explode(' ', $args));
        
        if( $qmsg == ":-Information" ) {
            //':-Information for user <USER> (using account <ACCOUNT>):'
             
            $auth = substr($args[5], 0, -2);
            $user = $args[2];
            $cb = $this->auth_cb;
            $this->$cb( $user, $auth );
        } 
        else if( $qmsg == ":User" && count($args) == 4 && $args[3] == "authed." ) {
            $user = $args[0];
            $cb = $this->auth_cb; 
            $this->$cb( $user, false );
        }
    }

    function whois( $name, $callback )
    {   //Only a 'tiny' rabbit hole
        //This will send and AUTH command to Q;
        //read_line() will fire off a Q_response
        //while the function exits and other commands can finish

        //$this->auth_try = $name;
        $this->auth_cb = $callback;
        $this->send( "WHOIS $name", "Q", 2000000 );
    }

    function store_op( $auth, $admin )
    {
        $this->admins[$auth] = $admin;
    }

    function store_auth( $user, $auth )
    {
        //Update the responce timer here.
        $this->init = time();

        if( $user ) {

            $this->users[$user] = $auth;

            $this->voice_user( $user );
        }

        if( count($this->auth_array) ) {

            $name = array_pop( $this->auth_array );
            echo "reading auth: $name\n";
            $this->whois( $name, 'store_auth' );
        }

        if( !count($this->auth_array) && $this->init ) {

            $this->send( "Done", $this->chan);
            $this->init = false;
        }
    }

    function authenticate( $user, $account, $code ) 
    {
        $result = $this->pps->bind_user_auth( $user, $account, $code );
        $this->send($result, $user);

        $this->voice_user( $user );
    }

    function voice_user ( $user )  {

        if( !$this->users[$user] ) return false;

        $auth = $this->users[$user];

        $auth_stats = $this->pps->get_auth_stats( $auth );
        if( $auth_stats ) {
            
            $rank = $this->pps->get_player_rank( $auth_stats['name'] );
            $perc = $this->rank_percentile( $rank['rank'], $rank['total'] );
            if( $perc < $this->top_voice ) {

                $this->send( "MODE " . $this->chan . " +v $user\n", null );
            }
            else {
                $this->send( "MODE " . $this->chan . " -v $user\n", null );
            }
        }

        return false;
    }

    function voice_adjust() {

        if( count($this->users) ) {

            $this->success( "Checking voice status. top $this->top_voice" );
        }

        foreach( $this->users as $user => $auth ) {
            
            $this->voice_user( $user );
        }
    }
}
?>
