<?php

Trait mysql_tests {

    function test_mysql_point_functions()
    {
        echo "MySQL point functions...";

        if( !$this->mysql ) {

            echo " (no Mysql connection)";
            return null;
        }

        if( !$this->mysql->connected ) 
           $this->mysql->connect(); 

        $i = -10;
        $types = array( "ban", "innactive" );

        echo "read/write";

        for( $i; $i < 0; $i++ ) {

            $this->mysql->give_points( $i, 1, $types[0] );
            $this->mysql->give_points( $i, 1, $types[1] );
        }

        for( $i=-1; $i > -11; $i-- ) {

            $points = $this->mysql->get_points( $i );
            if( count($points) != 2 ) {

                echo $this->mysql->mysqli->error;
                var_dump( $points );
                return $this->x(); 
            }

            if( $points[0]['type'] != "ban" ) {

                echo $this->mysql->mysqli->error;
                var_dump( $points );
                return $this->x();
            }
        
            if( $points[1]['type'] != "innactive" ) {

                echo $this->mysql->mysqli->error;
                var_dump( $points );
                return $this->x();
            }
        }

        $this->ok();

        echo "...erase";

        for( $i; $i < 0; $i++ ) {

            $this->mysql->erase_points( $i, "ban" );
            $this->mysql->erase_points( $i, "innactive" );
        }

        for( $i=-1; $i > -11; $i-- ) {

            if( count($this->mysql->get_points($i)) ) {
                return $this->x();
            }
        }

        $this->ok();

        $this->mysql->disconnect();
        return true;
    }
}

?>
