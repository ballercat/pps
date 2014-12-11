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

Trait irc_utility {

    function rank_percentile( $rank, $total ) 
    {
        return intval($rank/($total/100));
    }

    function rank2color( $rank, $total )
    {
        if( $rank < 6 ) {

            return (BOLD . PURPLE);
        }

        $colors = array_fill (  0,10,                       TEAL);
        $colors = array_merge( $colors, array_fill(11,20,   TEAL) );
        $colors = array_merge( $colors, array_fill(21,30,   TEAL) );
        $colors = array_merge( $colors, array_fill(31,40,   TEAL) );
        $colors = array_merge( $colors, array_fill(41,100,  TEAL) );

        $p = intval($rank/($total/100));

        return $colors[$p];
    }

    function region2string( $region )
    {
        switch( $region )
        {
        case 'NA' :
            return RED . "N" . BLUE . "A";
        case 'EU':
            return BLUE . "EU";
        };
    }

    function rank2string( $rank, $total ) 
    {
        if( $rank < 6 ) {

            return BOLD . PURPLE . "[X]";
        }

        $strings = array_fill(0,10, RED . "[A]");
        $strings = array_merge( $strings, array_fill(11,10, BOLD . ORANGE  . "[B]" . BOLD) );
        $strings = array_merge( $strings, array_fill(21,10, BOLD . LIME    . "[C]" . BOLD) );
        $strings = array_merge( $strings, array_fill(31,10, BOLD . LBLUE   . "[D]" . BOLD) );
        $strings = array_merge( $strings, array_fill(41,61, BOLD . BLACK   . "[F]" . BOLD) );

        $p = intval($rank/($total/100));

        return $strings[$p];
    }

    function rank_N_color( ) { return BLACK; }
    function rank_N_string() { return BOLD . BLACK . "[N]" . BOLD; }

    function check_help_args( $args ) 
    {

        if( $args == null ) return false;
        if( count($args) == 1 && $args[0] == '--help' ) return true;

        return false;
    }
}

?>
