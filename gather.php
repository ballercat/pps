<?php
/*
This file 'irc_server.php' is part of the PPS project <http://code.google.com/p/fracsnetpps/>

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
require './pps_player.php';
require './pps_teams.php';

class gather_man {
    public $pc;
    public $players;
    public $bravo;
    public $alpha;
    public $game_number;
    public $game_server;

    public function __construct( $p_game_number ) 
    {
        $this->game_number = $p_game_number;
        $this->pc = 0;
        $this->players = array();
        $this->alpha = new team( 1 );
        $this->bravo = new team( 2 );
    }

    public function is_full() 
    {
        return ($this->pc === 6);
    }

    public function is_added( $name ) 
    {
        foreach( $this->players as $key => $p ) {
            if( $p === $name ) {
                return true;
            }
        }

        return false;
    }

    public function nickchange( $nick, $n_nick )
    {
        foreach( $this->players as $k => $player ) {
            if( $player == $nick ) {
                $this->players[$k] = $n_nick;
                return $this->get_info();
            }
        }

        return null;
    }

    public function get_info() {
        $result = "Gather ($this->game_number) | ";

        for( $i = 0; $i < 6; $i++ ) {
            if( array_key_exists($i, $this->players) ) {
                $result .= $this->players[$i];
            }
            else {
                $result .= "x";
            }

            if( $i != 5 )   $result .= " - ";
            else            $result .= " |"; 
        }

        return $result;
    }

    private function shuffle_teams( $team ) 
    {
        $max = count( $this->players ) - 1;
        if( $max < 0 ) return;     

        $i = mt_rand(0,$max);

        $player = new base_player( null, $this->players[$i] ); 
        $player->p_id = count( $this->players ); 

        unset( $this->players[$i] );
        $this->players = array_values( $this->players );
        
        if( $team == 1 ) {
            $this->alpha->add( $player );
            $this->shuffle_teams( 2 );
        }
        else {
            $this->bravo->add( $player );
            $this->shuffle_teams( 1 );
        }
    }

    public function start()
    {
        mt_srand( crc32(microtime()) );
        $this->shuffle_teams( 1 );

        $result = "Gather starting!\n";
        $result .= "Alpha Team:";
        foreach( $this->alpha->p as $p ) {
            $result .= " " . $p;
        }
        $result .= "\n";

        $result .= "Bravo Team:";
        foreach( $this->bravo->p as $p ) {
            $result .= " " . $p;
        }
        $result .= "\n";

        return $result;
    }

    public function add( $name ) 
    { 
        if( $this->pc == 6 ) return "Gather full"; 
        if( $this->is_added( $name ) ) return null;

        $this->players[] = $name;
        $this->pc++;

        return $this->get_info();
    }

    public function del( $name )
    { 
        if( $this->is_full() ) return null;
        if( !$this->is_added( $name ) ) return null;
         
        foreach( $this->players as $key => $p ) {
            if( $p === $name ) {
                unset( $this->players[$key] );
                $this->players = array_values( $this->players );
                return $this->get_info();
            }
        }

        return null;
    }

}

?>
