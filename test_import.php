<?php
// Test DateTime parsing without needing database

$test_dates = [
    "2026-06-16T23:15Z",
    "2026-06-17T16:40Z",
    "2026-06-17T17:05Z"
];

foreach ($test_dates as $date_string) {
    echo "Testing: $date_string\n";
    
    $dateTime = DateTime::createFromFormat('Y-m-d\TH:i\Z', $date_string, new DateTimeZone('UTC'));
    
    if ($dateTime) {
        $dateTime->setTimezone(new DateTimeZone('America/New_York'));
        $game_date = $dateTime->format('Y-m-d H:i:s');
        echo "  SUCCESS: $game_date\n";
    } else {
        echo "  FAILED to parse\n";
    }
}
?>
