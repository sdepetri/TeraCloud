<?php
$instance_id = gethostname();
// Convertir el ID de la instancia en un número
$numeric_id = crc32($instance_id);
// Realizar el cálculo para obtener un número entre 1 y 3
$result = ($numeric_id % 3) + 1;

echo "Hola mundo!! Servido desde la instancia: " . $instance_id . "\n";
echo "Server # " . $result;
?>




