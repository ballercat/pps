<?php

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
            $this->$cb( $user, null );
        }
    }

    function whois( $name, $callback )
    {   //Only a 'tiny' rabbit hole
        //This will send and AUTH command to Q;
        //read_line() will fire off a Q_response
        //while the function exits and other commands can finish

        //$this->auth_try = $name;
        $this->auth_cb = $callback;
        $this->send( "WHOIS $name", "Q" );
    }

    function store_auth( $user, $auth )
    {
        if( $user ) {
            $this->users[$user] = $auth;
        }

        if( count($this->auth_array) ) {

            $name = array_pop( $this->auth_array );
            $this->whois( $name, 'store_auth' );

        }

        if( !count($this->auth_array) && $this->init ) {
            $this->send( "Done", $this->chan );
            $this->init = false;
        }
    }
}
?>
