<?php
$response = file_get_contents('http://localhost/slipp/api/draw_info.php');
$data = json_decode($response, true);

if ($data['status'] === 'success') {
    echo "Status: SUCCESS\n";
    echo "Current Draw: " . $data['data']['current_draw'] . "\n";
    echo "Expected Draw: " . $data['data']['expected_draw'] . "\n";
    echo "Match: " . ($data['data']['draw_number_match'] ? 'YES' : 'NO') . "\n";
    echo "Last Draw: " . $data['data']['last_draw'] . "\n";
    echo "Next Draw: " . $data['data']['next_draw'] . "\n";
    echo "Countdown: " . $data['data']['countdown'] . " seconds\n";
} else {
    echo "ERROR: " . $data['message'] . "\n";
}
?>
