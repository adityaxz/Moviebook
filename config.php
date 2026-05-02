<?php
$dbPath = __DIR__ . '/moviebook.db';
$conn = new SQLite3($dbPath);

$conn->exec("
    CREATE TABLE IF NOT EXISTS movies (
        movie_id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT, genre TEXT, release_year INTEGER, poster TEXT, link TEXT
    );
    CREATE TABLE IF NOT EXISTS ratings (
        rating_id INTEGER PRIMARY KEY AUTOINCREMENT,
        movie_id INTEGER, rating INTEGER
    );
");
?>