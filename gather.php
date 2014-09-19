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

define( 'BLACK', "\x031" );
define( 'RED',  "\x034" );
define( 'BLUE' , "\x032" );
define( 'GREEN' , "\x033" );
define( 'BOLD' , "\x02" );

class gather_man {
    public $pc;
    public $players;
    public $bravo;
    public $alpha;
    public $game_number;
    public $game_server;

    //Rating related data
    public $rated_players;
    public $rated_player_count = 0;
    public $low_rating = 0;
    public $top_rating = 0;
    public $rating_player_average = 0;
    public $rating_team_average = 0;
    

    public function __construct( $p_game_number ) 
    {
        $this->game_number = $p_game_number;
        $this->pc = 0;
        $this->rated_players = array();
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
                //Rated players are a key value pair
                $this->rated_players[ $n_nick ] = $this->rated_player[ $nick ];
                unset( $this->rated_players[ $nick ] );

                //Players array is indexed
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
                if( array_key_exists( $this->players[$i], $this->rated_players) ) {
                    $result .= "(". $this->rated_players[ $this->players[$i] ] . ")";
                }
            }
            else {
                $result .= "x";
            }

            if( $i != 5 )   $result .= " - ";
            else            $result .= " |"; 
        }

        return $result;
    }

    //Takes two numeric arrays of ratings and one array of available ratings.
    //Makes best possible, balanced pick based on data provided
    //$pick is a string reperesenting the player name
    //Returns the number of the team that made the pick: 1 for alpha, 2 for bravo
    private function rating_balanced_pick( &$alpha, &$bravo, $pick )
    {
        $alpha_total = array_sum( $alpha );
        $bravo_total = array_sum( $bravo );
        $alpha_size = count( $alpha );
        $bravo_size = count( $bravo );

        //Pick who gets next pick, based on :
        // Team Size) Team with fewer players should get priority
        // Totals) Team with the lowest total picks next 
        $team = 0;

        if( $bravo_total < $alpha_total ) {
            if( $bravo_size != 3 ) //Magic number 3 is team size(s)
            {
                $team = 2;
            }
            else {
                $team = 1; //Bravos total is less, but no more players can be added
            }
            
        }
        else if( $alpha_total < $bravo_total ) {
            if( $alpha_size != 3 ) {
                $team = 1;
            }
            else {
                $team = 2; //Alphas total is less, but no more players can be added
            }
        }

        if( $team == 0 ) { //No team chosen, ratings are equal, choose alpha
            $team = 1;
        }

        if( $team == 1 ) {
            $alpha[] = $pick;
        }

        if( $team == 2 ) {
            $bravo[] = $pick;
        } 

        return $team;
    }

    private function pick_player_name( $team, $name )
    {
        $key = array_search( $name, $this->players );
        $player = new base_player( null, $this->players[$key] );
        $player->p_id = count( $this->players );

        unset( $this->players[$key] );
        $this->players = array_values( $this->players );
        if( array_key_exists( $name, $this->rated_players ) ) {
            $player->rating = $this->rated_players[ $name ] ;
        }

        if( $team == 1 ) {
            $this->alpha->add( $player );
        }

        if( $team == 2 ) {
            $this->bravo->add( $player );
        }
    }

    private function shuffle_teams( $team ) 
    {
        $max = count( $this->players ) - 1;
        if( $max < 0 ) return;     

        $i = mt_rand(0,$max);
        
        $this->pick_player_name( $team, $this->players[$i] );

        if( $team == 1 ) {
            $this->shuffle_teams( 2 );
        }
        else {
            $this->shuffle_teams( 1 );
        }
    }

    //Start a new gather game
    //Return formated string with the teams etc.,
    public function start()
    {
        $result = BOLD. "Gather starting!";
        if( $this->rated_player_count > 1 )
        { //Rated players are available so distribute them before shuffling the rest    
            $result .= " Two or more rated players. Balancing";
            $alpha = array();
            $bravo = array();
            $rated = $this->rated_players;
            arsort( $rated );

            foreach( $rated as $name => $rating ) {
                //Make the picks
                $team = $this->rating_balanced_pick( $alpha, $bravo, $rating );
                $this->pick_player_name( $team, $name );
            }
        } else {
            $result .= " Not enough rated players. Shuffling";
        }

        $result .= BOLD . "\n";
        //Do the leftover shuffling
        mt_srand( crc32(microtime()) );
        $this->shuffle_teams( 1 );


        //Format result
        $result .= RED . "Alpha Team(";
        $result .= round($this->alpha->average_rating(),2) . "):";
        foreach( $this->alpha->p as $p ) {
            $result .= " " . $p;
        }
        $result .= "\n";

        $result .= BLUE . "Bravo Team(";
        $result .= round($this->bravo->average_rating(),2) . "):";
        foreach( $this->bravo->p as $p ) {
            $result .= " " . $p;
        }
        $result .= BLACK . "\n";

        return $result;
    }

    //Extra step is done here to update rating values BEFORE the actual add
    public function add_rated( $name, $rating )
    {
        //This makes the check in 'add' redundant but its still necessary
        if( $this->pc != 6 && !$this->is_added( $name ) ) {
            $this->rated_players[ $name ] = $rating; 
            
            if( $this->low_rating > $rating ) {
                $this->low_rating = $rating;
            }

            if( $this->top_rating < $rating ) {
                $this->top_rating = $rating;
            }

            $this->rated_player_count++;
        }

        return $this->add( $name ); 
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
