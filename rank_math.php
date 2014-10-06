<?php
/*
This file 'rank_math.php' is part of the PPS project(gather branch) <http://code.google.com/p/fracsnetpps/>

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

Trait rank_math 
{
    /* --------------------------------------------------------------------------------------------------------------------------- */    
    function v($val, $e = 0){ /* 'v' function a simple knock off the one shown in TrueSkill(tm) documentation
    /* --------------------------------------------------------------------------------------------------------------------------- */
        if( $val > 0 )
            return (pow(1.2, -($val) * 6));
        else
            return (pow(2, -($val) * 0.25));
    }

    /* ---------------------------------------------------------------------------------------------------------------------------- */
    function w($val, $e = 0){ /* 'w' function knock off */
    /* ---------------------------------------------------------------------------------------------------------------------------- */
        if( $val == 0 )
            return 0.8;
        if( $val < 0 )
            return -( 1 / pow(1.5, -$val) );    
        
        return ( 1 / pow(1.5, $val * 2) );
    }

    /* ---------------------------------------------------------------------------------------------------------------------------- */
    function c_squared($sigma_w, $sigma_l){ /* Return the value of c squared */
    /* ---------------------------------------------------------------------------------------------------------------------------- */
        return 2 + ($sigma_w * $sigma_w) + ($sigma_l * $sigma_l);
    }

    /* ---------------------------------------------------------------------------------------------------------------------------- */
    function newSigma($sigma, $c, $Mu_w, $Mu_l){ /* Get the new sigma for a player */
    /* ---------------------------------------------------------------------------------------------------------------------------- */
        return sqrt(($sigma * $sigma) * (1 - ((($sigma*$sigma)/$c) * $this->w( ($Mu_w - $Mu_l)/sqrt($c) )) ));
    }

    /* -------------------------------------------------------------------------------------------------------------------- */
    function newMu_w($sigma, $c, $Mu_w, $Mu_l){ /* Get the new Mu for a player(Winner) */
    /* -------------------------------------------------------------------------------------------------------------------- */
        return $Mu_w + ( (($sigma*$sigma)/sqrt($c)) * $this->v( ($Mu_w - $Mu_l)/sqrt($c) ) );
    }

    /* ---------------------------------------------------------------------------------------------------------------------- */
    function newMu_l($sigma, $c, $Mu_w, $Mu_l){ /* Get the new Mu for a player(Looser) */
    /* ----------------------------------------------------------------------------------------------------------------------- */
        return $Mu_l - ( (($sigma*$sigma)/sqrt($c)) * $this->v( ($Mu_w - $Mu_l)/sqrt($c) ) );
    }
}

?>
