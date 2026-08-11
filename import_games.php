<?php

require 'db_connect.php';


if (file_exists('last_game_import.txt')) {
    unlink('last_game_import.txt');
}

echo "<h1>Importing League Schedules...</h1>\n";


$start_date = date('Ymd');
$end_date = date('Ymd', strtotime('+14 days'));


$endpoints = [
    'MLB'   => "https://site.api.espn.com/apis/site/v2/sports/baseball/mlb/scoreboard?dates={$start_date}-{$end_date}",
    'NFL'   => "https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard?dates={$start_date}-{$end_date}",
    'NBA'   => "https://site.api.espn.com/apis/site/v2/sports/basketball/nba/scoreboard?dates={$start_date}-{$end_date}",
    'NCAAF' => "https://site.api.espn.com/apis/site/v2/sports/football/college-football/scoreboard?dates={$start_date}-{$end_date}",
    'NCAAB' => "https://site.api.espn.com/apis/site/v2/sports/basketball/mens-college-basketball/scoreboard?dates={$start_date}-{$end_date}"
];

$inserted_count = 0;

!
$check_stmt = $conn->prepare("SELECT event_id FROM events WHERE event_name = ? AND DATE(event_date) = DATE(?)");
$insert_stmt = $conn->prepare("INSERT INTO events (event_name, location_name, event_date, league) VALUES (?, ?, ?, ?)");


foreach ($endpoints as $league => $url) {
    echo "<p>Checking $league via ESPN...</p>";
    $json = @file_get_contents($url);
    if (!$json) continue;
    
    $data = json_decode($json, true);
    if (empty($data['events'])) continue;

    foreach ($data['events'] as $event) {
        $name = $event['name'];
        $date = date('Y-m-d H:i:s', strtotime($event['date']));
        $venue = $event['competitions'][0]['venue']['fullName'] ?? 'TBA';
        
        if (strpos($name, 'TBD') !== false || strpos($name, '/') !== false) continue;


        $check_stmt->bind_param("ss", $name, $date);
        $check_stmt->execute();
        
  
        if ($check_stmt->get_result()->num_rows == 0) {
            $insert_stmt->bind_param("ssss", $name, $venue, $date, $league);
            $insert_stmt->execute();
            $inserted_count++;
        }
    }
}


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
