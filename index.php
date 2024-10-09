<?php
// Inicializamos la semilla aleatoria con el tiempo actual
srand(time());

$instance_id = gethostname();

// Convertir el ID de la instancia en un número
$numeric_id = crc32($instance_id);

// Realizar el cálculo base para obtener un número entre 1 y 3
$base_result = ($numeric_id % 3) + 1;

// Generar un número aleatorio pequeño
$random_factor = rand(0, 10);

// Combinar el resultado base con el número aleatorio
$result = ($base_result + $random_factor) % 3 + 1;

echo "Hola mundo!! Servido desde la instancia: " . $instance_id . "\n";
echo "Servidor #: " . $result . "\n";
?>

