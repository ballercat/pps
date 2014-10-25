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

Trait admin_commands {

    public function dbg ( $user, $args = null, $channel = null ) {

        if( !array_key_exists(0, $args) ) return; 
        if( !$this->admin_access( $user ) ) {

            $this->error( $this->get_error_string(), $this->chan );
            return;
        }

        if( $args[0] == 'gather' ) {
            $ratings = array();
            foreach( $args as $rating ) {
                if( !is_numeric( $rating ) ) continue;
                $ratings[] = floatval( $rating );
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
        else if( $args[0] == 'fill' ) {

            $this->fill_gather();
        }
    }

    public function quit( $user, $line, $channel = null )
    {
        if( !$this->admin_access( $user ) ) {

            $this->speak( "Don't..." );
            return;
        } 

        $sec = time() - $this->uptime;

        $tm = round($sec/3600, 2) . " hours";

        $this->speak( "Quit called: Leaving. Uptime: $tm" );

        exit(0); //note exit nicer
    }

    public function ls ( $user, $args = null, $channel ) {
        
        if( $channel != $this->nick ) return;

        exec( "ps aux | grep php", $output );

        foreach( $output as $line ) {

            if( preg_match("/php\s+(?P<script>\w+)\.php$/", $line, $matches) ) {

                $script = $matches['script'];
                $this->send( "$script running...", $channel );
            }
        }
    }

    function set_top ( $user, $args = null, $channel = null ) {
        if( !$this->admin_access( $user ) ) return;
        if( !count($args) ) return;
        if( !is_numeric($args[0]) ) return;

        $this->top_voice = $args[0]; 
        $this->voice_adjust();
    }
}

?>
