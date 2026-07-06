<?php
require 'db_connect.php';
require 'sports_config.php';

// Force re-import for testing (clear old lock file if it exists)
if (file_exists('last_game_import.txt')) {
    unlink('last_game_import.txt');
}

// Fetch games for the next 10 days
$dates_to_fetch = [];
for ($i = 0; $i < 10; $i++) {
    $dates_to_fetch[] = date('Ymd', strtotime("+$i day"));
}

echo "Importing League Schedules...\n";

// 1. Existing ESPN Imports (NBA, NFL, NCAAF, NCAAB)
if (isset($supported_leagues)) {
    foreach ($supported_leagues as $league_name => $info) {
        $sport = $info['sport'];
        $slug  = $info['league_slug'];
        
        echo "Checking $league_name via ESPN...\n";
        
        // DELETE old events for this league before reimporting
        $delete_stmt = $conn->prepare("DELETE FROM events WHERE league = ?");
        $delete_stmt->bind_param("s", $league_name);
        $delete_stmt->execute();
        echo " - Cleared old $league_name games.\n";
        
        foreach ($dates_to_fetch as $date) {
            $api_url = "http://site.api.espn.com/apis/site/v2/sports/$sport/$slug/scoreboard?dates=$date";
            $json = @file_get_contents($api_url);
            
            if ($json) {
                $data = json_decode($json, true);
                if (isset($data['events'])) {
                    foreach ($data['events'] as $event) {
                        $game_name = $event['name'];
                        try {
                            $dateTime = new DateTime($event['date']);
                            $dateTime->setTimezone(new DateTimeZone('America/New_York'));
                            $game_date = $dateTime->format('Y-m-d H:i:s');
                        } catch (Exception $e) {
                            $game_date = date('Y-m-d 12:00:00', time());
                        }
                        
                        $venue = "TBA";
                        if (isset($event['competitions'][0]['venue']['fullName'])) {
                            $venue = $event['competitions'][0]['venue']['fullName'];
                        }
                        
                        $stmt = $conn->prepare("INSERT IGNORE INTO events (event_name, event_date, location_name, league) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("ssss", $game_name, $game_date, $venue, $league_name);
                        if ($stmt->execute() && $stmt->affected_rows > 0) {
                            echo " + Added (ESPN): $game_name ($game_date)\n";
                        }
                    }
                }
            }
        }
    }
}

// 2. FIFA World Cup Import via football-data.org API
echo "Checking FIFA World Cup matches via football-data.org...\n";

// Clear old FIFA games
$delete_fifa = $conn->prepare("DELETE FROM events WHERE league = 'FIFA'");
$delete_fifa->execute();
$delete_fifa->close();

$opts = [
    "http" => [
        "method" => "GET",
        "header" => "X-Auth-Token: 377fedbb77354c3aa744fc879a9f676a\r\n"
    ]
];
$context = stream_context_create($opts);

// Fetch World Cup matches endpoint
$fifa_url = "https://api.football-data.org/v4/competitions/WC/matches";
$fifa_json = @file_get_contents($fifa_url, false, $context);

if ($fifa_json) {
    $fifa_data = json_decode($fifa_json, true);
    if (isset($fifa_data['matches'])) {
        foreach ($fifa_data['matches'] as $match) {
            $home = $match['homeTeam']['name'] ?? 'TBD';
            $away = $match['awayTeam']['name'] ?? 'TBD';
            $game_name = "$home vs $away";
            
            // Format time: Force the UTC string into Eastern Time (America/New_York)
            try {
                $dateTime = new DateTime($match['utcDate']);
                $dateTime->setTimezone(new DateTimeZone('America/New_York'));
                $game_date = $dateTime->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                $game_date = date('Y-m-d H:i:s', strtotime($match['utcDate'])); // fallback
            }
            
            // Smarter venue parsing: Combines Stadium Name + City
            $stadium = $match['venue']['name'] ?? '';
            $city = $match['venue']['location'] ?? '';
            $venue = (!empty($stadium) && !empty($city)) ? "$stadium, $city" : ($stadium ?: ($city ?: 'World Cup Stadium'));
            
            $league_name = 'FIFA';

            $stmt = $conn->prepare("INSERT IGNORE INTO events (event_name, event_date, location_name, league) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $game_name, $game_date, $venue, $league_name);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                echo " + Added (FIFA): $game_name at $venue ($game_date)\n";
            }
            $stmt->close();
        }
    }
}

echo "Done importing all sports schedules.\n";
?>
