<?php
/*
This file 'rating_update.php' is part of the PPS project <http://code.google.com/p/fracsnetpps/>

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

Trait rating_update {
	/* ------------------------------------------------------------------------------------------------------------------------------------------------------------------- */
   	function update_ratings($win_number){
	/* ------------------------------------------------------------------------------------------------------------------------------------------------------------------- */       
        if( $win_number < 3 ){

            if( $win_number == 1 ){
                
                $win = &$this->T->alpha;
                $loose = &$this->T->bravo;
                $win_leavers = &$this->T->alpha_leavers;
                $loose_leavers = &$this->T->bravo_leavers; 
            }
            elseif( $win_number == 2){

                $win = &$this->T->bravo;
                $loose = &$this->T->alpha;
                $win_leavers = &$this->T->bravo_leavers;
                $loose_leavers = &$this->T->alpha_leavers;
            }
            elseif( !$win_number ){

                return;
            }
        }
        else{

            return;
        }
        if( (!$win->mu || !$loose->mu) ){

            return null;
        }
        
        $L = array();
        $l_sigma = $loose->sigma + $loose_leavers->sigma;
        $l_mu = $loose->mu + $loose_leavers->mu;
        
        $W = array();
        $w_sigma = $win->sigma + $win_leavers->sigma;
        $w_mu = $win->mu + $win_leavers->mu;
        
        $l_pc = $loose->count + $loose_leavers->count;
        $w_pc = $win->count + $win_leavers->count;

        if( $w_pc === 0 || $l_pc === 0 ) return;

        /* Generate temporary teams */
        foreach( $this->T->ps as $name => $player ){

            if( $this->T->ps[$name]->is_rated ){

                if( $this->T->ps[$name]->team->number == $win->number){

                    $W[$name] = $this->T->ps[$name];
                }elseif( $this->T->ps[$name]->team->number == $loose->number ){

                    $L[$name] = $this->T->ps[$name];
                }
            }
        }

        foreach( $W as $name => $value ){
            
            $c = $this->c_squared( $W[$name]->sigma, ($l_sigma/$l_pc) );
            $sigma = 0;
            
            $sigma = $this->newSigma( $W[$name]->sigma,
                                      $c,
                                      $W[$name]->mu,
                                      ($l_mu/$l_pc) );
                                                          
            $W[$name]->mu = $this->newMu_w( $sigma,
                                            $c,
                                            $W[$name]->mu,
                                            ($l_mu/$l_pc) );
            
            $W[$name]->sigma = $sigma;
            $W[$name]->old_rating = $W[$name]->rating;
            $W[$name]->rating = $W[$name]->mu - ( $W[$name]->sigma * 2.5);
            
            $this->T->ps[$name] = $W[$name];
        }
        foreach( $L as $name => $value ){

            $c = $this->c_squared( ($w_sigma/$w_pc), $L[$name]->sigma );
            $sigma = 0;

            $sigma = $this->newSigma( $L[$name]->sigma, 
                                      $c, 
                                      ($w_mu/$w_pc), 
                                      $L[$name]->mu );
                                      
            $L[$name]->mu = $this->newMu_l( $sigma, 
                                            $c, 
                                            ($w_mu/$w_pc), 
                                            $L[$name]->mu );
                                            
            $L[$name]->sigma = $sigma;
            $L[$name]->old_rating = $L[$name]->rating;
            $L[$name]->rating = $L[$name]->mu - ($L[$name]->sigma * 2.5);
            
            $this->T->ps[$name] = $L[$name];
        }           
    }

	/* ---------------------------------------------------------------------------------------------------------------------- */
    function update_player_stats( &$player )
	/* ---------------------------------------------------------------------------------------------------------------------- */
    {
        /* First Part Update Player specific stats */
        $player->update_kdr();
        $player->update_cgr();
        $player->update_ckr();
        $player->update_time_played();

        $this->db->write_player_stats( $player ); 

        foreach( $player->w as $key => $kills ) {
            $this->db->update_weapon_stats( $key, $kills );
            $player->w[$key] = 0;
        }
    }
}

?>
