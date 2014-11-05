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

require './pps_player.php';
require './pps_teams.php';

//Colors
    define( 'BLACK', "\x031" );
    define( 'BROWN', "\x035" );
    define( 'RED',  "\x034" );
    define( 'BLUE' , "\x032" );
    define( 'GREEN' , "\x033" );
    define( 'CYAN', "\x0311" );
    define( 'LBLUE', "\x0312" );
    define( 'ORANGE', "\x037" );
    define( 'GREY', "\x0314" );
    define( 'LGREY', "\x0315" );
    define( 'PURPLE', "\x036" );
    define( 'PINK', "\x0313" );
    define( 'TEAL', "\x0310" );
    define( 'LIME', "\x039" );
    define( 'YELLOW', "\x038" );

    define( 'BOLD' , "\x02" );
    define( 'UNDERLINE', "\x1F" );
    define( 'NORMAL' , "\x0F" );

    //define( 'MCOLOR', ORANGE );
    define( 'MCOLOR', GREY );

    define( 'UNRATED', ORANGE );

class gather_man {
    Use irc_utility;

    //Data
        public $pc;
        public $players;
        public $bravo;
        public $alpha;
        public $game_number;
        public $game_server = null;

        public $game_password;

        public $gather_timer;
        public $gather_timeout = false;
        public $gather_started = false;

        public $region = "??";
        
        public $server_info = null;

        //Rating related data
            public $rated_players;
            public $rated_player_count = 0;
            public $player_hwid;
            public $low_rating = 0;
            public $top_rating = 0;
            public $rating_player_average = 0;
            public $rating_team_average = 0;
            public $player_color;
            public $player_rank;

        //Game data
            public $game_active = false;
            public $game_map = "";
            public $game_pc = 0;
            public $game_timer = 0;
            public $game_alpha_score = 0;
            public $game_bravo_score = 0;
            public $game_map_timer = 0;
            public $game_tiebreaker = "ctf_Laos";


    public function __construct( $p_game_number, $game_server ) 
    {
        $this->game_server = $game_server;
        $this->region = $game_server->region;
        $this->game_number = $p_game_number;
        $this->pc = 0;
        $this->rated_players = array();
        $this->player_rank = array();
        $this->player_color = array();
        $this->player_hwid = array();
        $this->players = array();
        $this->alpha = new team( 1 );
        $this->bravo = new team( 2 );
        $this->game_timer = time();
        $this->game_map = "lobby";
        $this->game_map_timer = time();
        $this->gather_timer = time();
    }

    public function is_full() 
    {
        return ($this->pc === 6);
    }

    public function is_empty()
    {
        return ( $this->pc === 0 );
    }

    public function is_active() { return $this->game_active; }

    public function set_timeout( $timeout ) {
        $this->gather_timeout = time() + $timeout; 
    }

    public function timeout( $player_count ) {
        if( $player_count < 6 && (time() >= $this->gather_timeout) ) {
            $result = $this->id_string() . " ";
            $result .= " has timed out after " . round((time() - $this->gather_timer)/60, 2) . "min. Deleting";

            $this->gather_timeout = false;
            return $result;
        }
        if( $player_count > 5 && (time() >= $this->gather_timeout) ) {
            $this->gather_timeout = false;
        }

        return false;
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
                $this->rated_players[ $n_nick ] = $this->rated_players[ $nick ];
                unset( $this->rated_players[ $nick ] );

                //Players array is indexed
                $this->players[$k] = $n_nick;

                return $this->get_info();
            }
        }

        return null;
    }

    public function id_string() {
        return (TEAL . BOLD . "[" . sprintf( "%04d", $this->game_number ) . "][$this->region]");
    }

    public function get_server_info() {
        if( $this->server_info ) {

            return $this->server_info;
        }
        else {

            return "No server info available!";
        }
    }

    public function get_info() {

        $result =  $this->id_string() . " ";

        $result .= BOLD . MCOLOR. "~ ";

        if( $this->gather_started ) {
            $result .= TEAL. BOLD . sprintf("CTF:\t%02d/06", $this->game_pc ) . BOLD ;
            $result .= RED . "Alpha: $this->game_alpha_score" . MCOLOR . " - ";
            $result .= BLUE . "Bravo: $this->game_bravo_score " . MCOLOR;
        }
        else {

            for( $i = 0; $i < 6; $i++ ) {
                if( array_key_exists($i, $this->players) ) {

                    //Check if the player is rated. Add fancy colors
                    if( array_key_exists( $this->players[$i], $this->rated_players) ) {
                        
                        $result .= $this->player_color[ $this->players[$i] ];
                        $result .= $this->players[$i];

                        $result .= BOLD ;
                        $result .= $this->player_rank[ $this->players[$i] ];
                        $result .= BOLD;
                        $result .= MCOLOR;
                    }
                    else {
                        //Not rated/Not found in db 
                        $result .= $this->player_color[ $this->players[$i] ];
                        $result .= $this->players[$i];
                        $result .= BOLD ;
                        $result .= $this->player_rank[ $this->players[$i] ];
                        $result .= MCOLOR . BOLD;
                    }
                }
                else {
                    $result .= "x";
                }

                if( $i != 5 )   $result .= " - ";
                else            $result .= " ~" ; 
            }
        }

        if( $this->gather_timeout ) {

            $to = $this->gather_timeout - time();
            $result .= "~  TO: ";
            if( $to < 0 ) {
                
                $result .= "0 +" . $to*-1 . "(extra time)";
            }
            else {
                $result .= $to;
            }
            
        }

        return $result;
    }

    //Takes two numeric arrays of ratings and one array of available ratings.
    //Makes best possible, balanced pick based on data provided
    //$pick is a string reperesenting the player name
    //Returns the number of the team that made the pick: 1 for alpha, 2 for bravo
    public function rating_balanced_pick( &$alpha, &$bravo, $pick )
    {
        $alpha_total = array_sum( $alpha );
        $bravo_total = array_sum( $bravo );
        $alpha_size = count( $alpha );
        $bravo_size = count( $bravo );

        //echo "asz: $alpha_size at: " . $alpha_total . " | bsz: $bravo_size bt: " . $bravo_total . "\n";
        
        if( intval($alpha_size) === 3 ) {

            $bravo[] = $pick;
            return 2;
        }
        else if( intval($bravo_size) === 3 ) {

            $alpha[] = $pick;
            return 1;
        }

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

        if( $team == 0 ) { //No team chosen, ratings are equal, choose random team
            $team = mt_rand(1,2);  //mt_rand is great, should be around 50/50 results
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

            if( $this->alpha->count == 3 )
                $this->bravo->add( $player );
            else
                $this->alpha->add( $player );
        }

        if( $team == 2 ) {

            if( $this->bravo->count == 3 ) $this->alpha->add( $player );
            else
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
    public function start( )
    {
        mt_srand( crc32(microtime()) );

        $result = BOLD. "Gather starting!";
        if( $this->rated_player_count > 1 )
        { //Rated players are available so distribute them before shuffling the rest    
            $result .= " Two or more rated players. Balancing";
            $alpha = array();
            $bravo = array();
            $rated = $this->rated_players;
            arsort( $rated );

            //var_dump( $rated );

            foreach( $rated as $name => $rating ) {
                //Make the picks
                $team = $this->rating_balanced_pick( $alpha, $bravo, $rating );
                echo "Pick $name $team\n";
                $this->pick_player_name( $team, $name );
            }
        } else {
            $result .= " Not enough rated players. Shuffling";
        }

        $result .= BOLD . "\n";
        //Do the leftover shuffling
        $this->shuffle_teams( 1 );

        //Format result
        $result .= RED . "Alpha [";
        $result .= sprintf("%05.2f", $this->alpha->average_rating() ) . "] = ";
        foreach( $this->alpha->p as $p ) {

            $result .= sprintf("%-9s ", $p);
        }
        $result .= "\n";

        $result .= BLUE . "Bravo [";
        $result .= sprintf("%05.2f", $this->bravo->average_rating() ) . "] = ";
        foreach( $this->bravo->p as $p ) {

            $result .= sprintf("%-9s ", $p);
        }
        $result .= BOLD . " \n";
        //Generate password
        $this->game_password = sprintf( "%03d", mt_rand(1, 999) );
        $this->game_server->send("/password $this->game_password");
        $this->game_server->send("/gatheron");
        $result .= BOLD;
        $result .= MCOLOR ." ~ Gather #$this->game_number: " . BOLD . " Tiebreaker is $this->game_tiebreaker ";
        $this->server_info = UNDERLINE . "server: soldat://". $this->game_server->ip . ":". $this->game_server->port . "/$this->game_password";
        $result .= ":: " . $this->server_info;
        $result .= NORMAL . "\n";

        $this->gather_timer = time();

        $this->gather_started = true;

        return $result;
    }

    //Extra step is done here to update rating values BEFORE the actual add
    public function add_rated( $name, $rating, $rank, $maps, $hwid = null)
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

            if( $hwid )
                $this->player_hwid[$name] = $hwid;

            $this->rated_player_count++;
        }

        //Get the players rank and morph it into a Letter:
        //'S' == top 5
        //'A' == top 10%
        //'B' == 80-89%
        //'C' == 70-79%
        //'D' == 60-69%
        //'F' == < 60%
        //'N' == has not played enough
        $total = $rank['total'];
        $prank = $rank['rank'];
        $this->player_color[$name] = $this->rank2color( $prank, $total );
        $this->player_rank[$name] = $this->rank2string( $prank, $total );
        /*if( $maps < 20 ) {
            
            $this->player_color[$name] = BLACK;
            $this->player_rank[$name] = BLACK . "[N]";
        }
        else if( $prank < 6 ) {

            $this->player_color[$name] = BOLD . PURPLE;
            $this->player_rank[$name] = PURPLE . "[SS]";
        }
        else if( $prank/$total < 0.1001 ) {

            $this->player_color[$name] = TEAL;
            $this->player_rank[$name] = RED . "[A]";
        }
        else if( $prank/$total < 0.2001 ) {
            $this->player_color[$name] = TEAL; 
            $this->player_rank[$name] = ORANGE . "[B]";
        }
        else if( $prank/$total < 0.3001 ) {
            $this->player_color[$name] = TEAL;
            $this->player_rank[$name] = LIME . "[C]";
        }
        else if( $prank/$total < 0.4001 ) {
            $this->player_color[$name] = TEAL;
            $this->player_rank[$name] = LBLUE . "[D]";
        }
        else if( $prank/$total > 0.3999 ) {
            $this->player_color[$name] = TEAL;
            $this->player_rank[$name] = BLACK . "[F]";
        }*/

        return $this->add( $name ); 
    }

    public function add( $name ) 
    { 
        if( $this->pc == 6 ) return "Gather full"; 
        if( $this->is_added( $name ) ) return null;

        if( !array_key_exists( $name, $this->player_rank ) ) {
            $this->player_color[$name] = UNRATED;
            $this->player_rank[$name] = UNRATED . "[E]";
        }

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
                $this->pc--;
                unset( $this->players[$key] );
                $this->players = array_values( $this->players );
                return $this->get_info();
            }
        }

        return null;
    }

    public function cap()
    {
        $refresh = $this->game_server->get_refreshx();

        $this->game_alpha_score = $refresh['team'][1];
        $this->game_bravo_score = $refresh['team'][2];
    }

    public function nextmap() 
    {
  
        $timer = time() - $this->game_map_timer;

        $result = false;

        if( !$this->game_active && $this->is_full() ) {

            $this->game_active = true;
        }
        else if( $this->game_active ){ //this becomes true after 6 ppl are playing & nextmap is called

            if( $timer > 120 ) {

                $timer = sprintf( "%02.2f", $timer/60 );
                $result = $this->game_map . " has finished after " . $timer . "min.";
                $result .= " With score:". BOLD . RED . " " . $this->game_alpha_score . MCOLOR . " -" . BLUE . " " . $this->game_bravo_score;   
            }
        }

        $refresh = $this->game_server->get_refreshx();
        $this->game_alpha_score = 0;
        $this->game_bravo_score = 0;
        $this->game_map_timer = time();
        $this->game_map = $refresh['map'];

        return $result;
    }

    public function player_joined( $hwid = null) {
        $this->game_pc++;

        if( $hwid == null ) return;

        while( list( $name, $key ) = each( $this->player_hwid ) ) {
            
            if( $key == $hwid ) {

                unset( $this->player_hwid[$name] );
                return;
            } 
        }
    }

    public function player_left( $name ) {
        $this->game_pc--;

        if( $this->game_active && $this->is_empty() ) {

            $this->game_active = false;
            return $this->id_string() . " ~ Gather finished";
        }
        if( $this->game_pc == 5 || $this->game_pc == 4 ) {
            return $this->id_string() . " ~ Sub may be needed";
        }

        return false;
    }

}

?>
