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

Trait help_commands {

    function help_spec()
    {
        $this->speak( "Usage: " . $this->highlight("!spec") . " <server #>" );
    }

    function help_auth()
    {
        $this->speak( $this->highlight("!auth") .  " takes a code from soldat and binds it to your qnet auth" );
        $this->speak( "Usage: " . $this->highlight("!auth")  . " <CODE> (As a Private Message)" );
    }
}

?>
