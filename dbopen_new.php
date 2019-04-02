<?php
$db_user="tu"; $db_password="tu2016"; $db_database="tu";
$db = new mysqli("localhost", $db_user, $db_password, $db_database);
if($db->connect_errno > 0){
    die('Unable to connect to database [' . $db->connect_error . ']');
}
$db->autocommit(true);
?>