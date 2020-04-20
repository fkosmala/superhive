<?php
require __DIR__ . '/config.php';

// Link to the JSON file who contains all posts
$file = __DIR__ . '/blog.json';
// Set data from config file to create query
$query = '{"jsonrpc":"2.0","method":"condenser_api.get_discussions_by_blog","params":[{"tag":"'.$settings['author'].'","limit":10}],"id":0}';

// Go take articles from Hive api
file_put_contents($file, '');
$ch = curl_init($settings['api']);
curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
curl_close($ch);
file_put_contents($file, $result);
?>
