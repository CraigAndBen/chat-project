<?php
// public/get_messages.php
header('Content-Type: application/json; charset=utf-8');

$dsn = 'mysql:host=127.0.0.1;dbname=chat_db;charset=utf8mb4';
$user = 'root';
$pass = '';
$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$limit = 50; // last 50 messages
$stmt = $pdo->prepare("SELECT id, username, message, created_at FROM messages ORDER BY id DESC LIMIT ?");
$stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// reverse so oldest first
$rows = array_reverse($rows);

echo json_encode($rows);
