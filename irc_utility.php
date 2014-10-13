<?php

Trait irc_utility {

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

    function rank2string( $rank, $total ) 
    {
        if( $rank < 6 ) {

            return BOLD . PURPLE . "[SS]";
        }

        $strings = array_fill(0,10, RED . "[A]");
        $strings = array_merge( $strings, array_fill(11,10, BOLD . ORANGE  . "[B]" . BOLD) );
        $strings = array_merge( $strings, array_fill(21,10, BOLD . LIME    . "[C]" . BOLD) );
        $strings = array_merge( $strings, array_fill(31,10, BOLD . LBLUE   . "[D]" . BOLD) );
        $strings = array_merge( $strings, array_fill(41,60, BOLD . BLACK   . "[F]" . BOLD) );

        $p = intval($rank/($total/100));

        return $strings[$p];
    }
}

?>
