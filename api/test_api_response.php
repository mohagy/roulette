<?php
$response = file_get_contents('http://localhost/slipp/api/upcoming_draws_stats.php?count=5');
$data = json_decode($response, true);

echo "Status: " . $data['status'] . "\n";
echo "Last Completed: " . $data['data']['last_completed_draw'] . "\n";
echo "Next Draw: " . $data['data']['next_draw'] . "\n\n";
echo "Upcoming Draws:\n";
foreach ($data['data']['upcoming_draws'] as $draw) {
    echo "  Draw #{$draw['draw_number']} at {$draw['estimated_time']}\n";
}
?>
