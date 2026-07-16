<?php
$servername = "localhost";
$username = "root";
$password="";


try{
    $db = new PDO("mysql:host=$servername;dbname=pharmacy_inventory" , $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    //echo "connection successful !";
}

catch(PDOException $e){
    echo "connection faild :". $e->getMessage();

}


?>