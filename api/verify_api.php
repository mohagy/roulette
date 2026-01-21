<?php
$url = 'http://localhost/slipp/api/draw_info.php';
$response = file_get_contents($url);
echo $response;
?>
