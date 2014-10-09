<?php

Trait qnet_users 
{
    function Q_respond( $qmsg, $args  ) {
        if( $qmsg == ":-Information" ) {
            //':-Information for user <USER> (using account <ACCOUNT>):'
            //$args = explode(' ', $args );

            //echo $this->buffer;

            if( !array_key_exists(5, $args) ) {
                echo $this->buffer . "\n";
                return;
            }
            $this->auth = substr($args[5], 0, -2);
            $cb = $this->auth_cb;
            $this->$cb( $args[2], $this->auth );//$this->auth_cb_args );
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
        echo "AUTH: $user\n";
        $this->users[$user] = $auth;
        $this->init--;
        if( $this->init === 0 ) {
            $this->init = false;
            $this->send( "Done", $this->chan );
        }
    }
}
?>
