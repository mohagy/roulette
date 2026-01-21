<?php
require_once '../includes/db_connection.php';
$result = $conn->query('SHOW COLUMNS FROM detailed_draw_results');
while ($col = $result->fetch_assoc()) {
    echo $col['Field'] . ' (' . $col['Type'] . ')' . "\n";
}
?>
