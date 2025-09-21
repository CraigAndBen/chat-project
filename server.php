<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/db.php';
require __DIR__ . '/Chat.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

$chat = new Chat($pdo);

$server = IoServer::factory(
    new HttpServer(
        new WsServer($chat)
    ),
    8080
);

echo "Server running at ws://localhost:8080\n";
$server->run();
