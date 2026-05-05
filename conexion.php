<?php

$sever = "localhost";
$user = "root";
$pass = "";
=======
$db = "sistema";
>>>>>>> 6437cdfe83b7100765bb5d6a82355bc79986ee40

$conexion = new mysqli($server; $user, $pass, $db);

if ($conexion->connect_errno) {
    die("Conexion fallida" . $conexion->connect_errno );

} else {
    echo"conectado";
}

?>