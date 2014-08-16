<?php

/*
This file 'server.php' is part of the PPS project <http://code.google.com/p/fracsnetpps/>

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

define( 'SERVER_TYPE_SOLDAT', 2 );
define( 'SERVER_TYPE_IRC',  1 );
define( 'SERVER_TYPE_MYSQL', 3 );

class ppsserver {
    public $ip;
    public $port;
    public $sock;
    public $buffer;

    public $connected = false;

    public function get_info() { return "Info function not supported on server"; }
    public function clear_buffer() { $this->buffer = null; }
    public function send( $data ) {}
    public function command( $data ) {}
    public function readbuffer() {}
    public function parse_buffer() {}
    public function parse_line( $line ) {}

}

?>

