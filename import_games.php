<?php
// Run this file once by visiting your-url.com/import_games.php in your browser!
set_time_limit(0); // Allow time for looping through days
require 'db_connect.php';

// Force re-import for testing
if (file_exists('last_game_import.txt')) {
    unlink('last_game_import.txt');
}

echo "<h1>Importing League Schedules...</h1>\n";

// We MUST query ESPN day-by-day. If we ask for a 14-day range, their API returns empty for MLB!
$dates_to_fetch = [];
for ($i = 0; $i < 14; $i++) {
    $dates_to_fetch[] = date('Ymd', strtotime("+$i day"));
}

$leagues = [
    'MLB'   => ['sport' => 'baseball', 'slug' => 'mlb'],
    'NFL'   => ['sport' => 'football', 'slug' => 'nfl'],
    'NBA'   => ['sport' => 'basketball', 'slug' => 'nba'],
    'NCAAF' => ['sport' => 'football', 'slug' => 'college-football'],
    'NCAAB' => ['sport' => 'basketball', 'slug' => 'mens-college-basketball']
];

$inserted_count = 0;

// Prepare statements to check for duplicates (Prevents deleting fan data!)
$check_stmt = $conn->prepare("SELECT event_id FROM events WHERE event_name = ? AND DATE(event_date) = DATE(?)");
$insert_stmt = $conn->prepare("INSERT INTO events (event_name, location_name, event_date, league) VALUES (?, ?, ?, ?)");

// 1. Import ESPN Leagues Day-by-Day
foreach ($leagues as $league_name => $info) {
    echo "<p>Checking $league_name via ESPN...</p>";
    
    foreach ($dates_to_fetch as $date) {
        $api_url = "https://site.api.espn.com/apis/site/v2/sports/{$info['sport']}/{$info['slug']}/scoreboard?dates=$date";
        $json = @file_get_contents($api_url);
        
        if ($json) {
            $data = json_decode($json, true);
            if (!empty($data['events'])) {
                foreach ($data['events'] as $event) {
                    $name = $event['name'];
                    
                    try {
                        $dateTime = new DateTime($event['date']);
                        $dateTime->setTimezone(new DateTimeZone('America/New_York'));
                        $game_date = $dateTime->format('Y-m-d H:i:s');
                    } catch (Exception $e) {
                        $game_date = date('Y-m-d 12:00:00', time());
                    }
                    
                    $venue = $event['competitions'][0]['venue']['fullName'] ?? 'TBA';
                    
                    if (strpos($name, 'TBD') !== false || strpos($name, '/') !== false) continue;

                    // Check if game already exists
                    $check_stmt->bind_param("ss", $name, $game_date);
                    $check_stmt->execute();
                    
                    // Only insert if it doesn't exist
                    if ($check_stmt->get_result()->num_rows == 0) {
                        $insert_stmt->bind_param("ssss", $name, $venue, $game_date, $league_name);
                        $insert_stmt->execute();
                        $inserted_count++;
                    }
                }
            }
        }
    }
}

// 2. FIFA World Cup Import via football-data.org API
echo "<p>Checking FIFA World Cup matches via football-data.org...</p>";

$opts = [
    "http" => [
        "method" => "GET",
        "header" => "X-Auth-Token: 377fedbb77354c3aa744fc879a9f676a\r\n"
    ]
];
$context = stream_context_create($opts);
$fifa_url = "https://api.football-data.org/v4/competitions/WC/matches";
$fifa_json = @file_get_contents($fifa_url, false, $context);

if ($fifa_json) {
    $fifa_data = json_decode($fifa_json, true);
    if (isset($fifa_data['matches'])) {
        foreach ($fifa_data['matches'] as $match) {
            $home = $match['homeTeam']['name'] ?? 'TBD';
            $away = $match['awayTeam']['name'] ?? 'TBD';
            $game_name = "$home vs $away";
            
            try {
                $dateTime = new DateTime($match['utcDate']);
                $dateTime->setTimezone(new DateTimeZone('America/New_York'));
                $game_date = $dateTime->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                $game_date = date('Y-m-d H:i:s', strtotime($match['utcDate'])); 
            }
            
            $stadium = $match['venue']['name'] ?? '';
            $city = $match['venue']['location'] ?? '';
            $venue = (!empty($stadium) && !empty($city)) ? "$stadium, $city" : ($stadium ?: ($city ?: 'World Cup Stadium'));
            $league_name = 'FIFA';

            // Check if game already exists
            $check_stmt->bind_param("ss", $game_name, $game_date);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows == 0) {
                $insert_stmt->bind_param("ssss", $game_name, $venue, $game_date, $league_name);
                $insert_stmt->execute();
                $inserted_count++;
            }
        }
    }
}

echo "<h3>Import Complete!</h3>";
echo "<p>Successfully imported <strong>$inserted_count</strong> new upcoming games without deleting existing fan data.</p>";
echo "<a href='checkin.php'>Return to Dashboard</a>";
?>
```eof

