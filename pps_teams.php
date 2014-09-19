<?php
/*
This file 'pps_teams.php' is part of the PPS project <http://code.google.com/p/fracsnetpps/>

Copyright: (C) 2009 Arthur, B. aka ]{ing <whinemore@gmail.com>

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
define('ALPHA', 1 );
define('BRAVO', 2 );
define('SPEC', 5 );

/* dragons */

class team{
    public $score;
    public $count;
    public $p;
    public $fc;
    public $flag;
    public $sigma;
    public $mu;
    public $number;
    public $rating = 0;

    public function __construct( $team_number = null ){
        $this->clear(); 
        if( $team_number )
            $number = $team_number;
    }

    public function add( &$player ) 
    { if( !$player ) return;
        $player->team = &$this;

        $this->count++; 
        $this->p[$player->p_id] = $player->name;
        $this->sigma += $player->sigma;
        $this->mu += $player->mu;
        $this->rating += $player->rating;
    }

    public function remove( &$player )
    {
        if( $this->fc == $player->name )
        $this->sigma -= $player->sigma;
        $this->mu -= $player->mu;

        $this->count--;

        unset( $this->p[$player->p_id] );
        ksort( $this->p );
    }

    public function clear()
    {
        $this->count = 0;
        $this->score = 0;
        $this->flag = 1;
        $this->fc = null;
        $this->p = array();
    }

    public function number_players_rated() 
    {
        $rated_number = 0;
        foreach( $this->p as $player ) {
            if( $player->has_rating() ) $rated_number++;
        }
        return $rated_number;
    }

    public function is_rated()
    {
        return ($this->rating > 0 );
    }

    public function average_rating() {
        $total_rating = 0.0;
        $number_rated = count($this->p);
       
        if( $number_rated == 0 ) return -1.0; //Don't divide by zero

        return ($this->rating/$number_rated);
    }

    public function contains_player( $name )
    {
        if( !$this->count ) return false;
        return( array_key_exists($name, $this->p) );
    }
}

class teams_container{

    public $alpha;
    public $bravo;
    public $spec;

    public $nullplayer = null;
    private $ids;
    private $big_id;
    private $avid;
    private $sock;
    
    /* All players */
    public $ps;
    public $pc;
    
    private $balance_timeout;
    
    public function __construct(&$ssocket){
        $this->alpha = new team(); $this->alpha->number = ALPHA;
        $this->bravo = new team(); $this->bravo->number = BRAVO;
        $this->spec = new team();  $this->spec->number = SPEC;
        
        $this->sock = & $ssocket;
        
        $this->ps = array();
        $this->pc = 0;
        
        $this->big_id = 0;
        $this->avid = 1;
        for( $i = 0; $i < 32; $i++ ){
            $this->ids[$i] = 0;
            if( $i == 0 ) $this->ids[$i] = 1;
        }
        
        $this->can_balance = true;
    }

    public function shuffle(){/*
        $i = round($this->alpha->count/2, 0);
        
        $this->balance_timeout = $i * 2;
        $Ap = array_values($this->alpha->p);
        $Bp = array_values($this->bravo->p);
        fputs( $this->sock, "/say Shuffling teams in 8 seconds.\r\n" );
        sleep(8);
        for($k = 0; $k < $i; $k++){
            
            $shf_alpha = $this->ps[$Ap[$k*2]]->p_id;
            $shf_bravo = $this->ps[$Bp[$k*2]]->p_id;
            
            fputs( $this->sock, "/setteam2 $shf_alpha\r\n" );
            fputs( $this->sock, "/setteam1 $shf_bravo\r\n" );            
        } 
        fputs($this->sock, "/restart\r\n");
        fputs( $this->sock, "/say Teams shuffled, restarting...\r\n" ); */
    }

    public function player_count() { return $this->pc; }
    public function pc_add( $team_number ) 
    {
        if( $team_number === 1 || $team_number === 2 ) {
            $this->pc++;
        }
    }

    public function pc_minus( $team_number ) 
    {
        if( $team_number === 1 || $team_number === 2 ) {
            $this->pc--;
        }
    }

    public function is_playing( $name ) 
    {
        if( !$this->pc ) return false;
        return array_key_exists($name, $this->ps);
    }

    public function add($name, $team, $id = null, $player = null){
        
        if( !isset($name) || !isset($team) ) return;

        switch( $team ){
            case 1:
                $to = &$this->alpha;
                break;
            case 2:
                $to = &$this->bravo;
                break;
            case 5:
                $to = &$this->spec;
                break;
        }
        
        if( $this->is_playing($name) ) {
            $this->ps[$name]->team->remove( $this->ps[$name] ); 
            $this->pc_minus( $this->ps[$name]->team->number );
        }
 
        $to->add( $player );
        $this->pc_add( $to->number );
        $player->p_id = $id;
        $this->ps[ $name ] = $player;
    }
    
    public function remove($name, $team = null){
        
        if( !isset($name) ) return;
        
        $player = $this->get_player_with_name( $name );
        if( !$player ) return;

        $from = $player->team;

        foreach( $from->p as $key => $player )
        {
            if( $from->p[$key] == $name ){
                if( $key == $from->fc ) $from->fc = "";
                
                unset($from->p[$key]);
                ksort($from->p);
                
                unset($this->ps[$name]);
                ksort($this->ps);
                
                if( $team != 5 ) $this->pc--;
                
                $from->count--;
                
                break;
            }
        }
    }

    public function &get_player_with_id( $id ) 
    {
        foreach( $this->ps as $name => $player ) {
            if( $player->p_id == $id ) {
                return $this->ps[$name];
            }
        }

        return $this->nullplayer;
    }

    public function &get_player_with_name( $name )
    {
        foreach( $this->ps as $n => $player ) {
            if( $n == $name ) 
                return $this->ps[$name];
        }

        return $this->nullplayer;
    }
}

