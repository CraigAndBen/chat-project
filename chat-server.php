<?php
// chat-server.php
require __DIR__ . '/vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\App;

class Chat implements MessageComponentInterface {
    protected $clients;
    protected $pdo;

    public function __construct() {
        $this->clients = new \SplObjectStorage;

        // Configure PDO once (persistent connection to avoid reconnect overhead)
        $dsn = 'mysql:host=127.0.0.1;dbname=chat_db;charset=utf8mb4';
        $user = 'root';
        $pass = '';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_PERSISTENT => true
        ];
        $this->pdo = new PDO($dsn, $user, $pass, $options);
    }

    public function onOpen(ConnectionInterface $conn) {
        // Store the new connection
        $this->clients->attach($conn);
        echo "New connection: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        // We expect incoming messages as JSON { type: 'message', username: 'Alice', message: 'Hi' }
        $data = json_decode($msg, true);
        if (!is_array($data) || !isset($data['type'])) {
            return;
        }

        if ($data['type'] === 'message') {
            $username = mb_substr($data['username'] ?? 'Guest', 0, 100);
            $message  = mb_substr($data['message'] ?? '', 0, 2000); // limit length

            // Store message in DB
            $stmt = $this->pdo->prepare("INSERT INTO messages (username, message) VALUES (?, ?)");
            $stmt->execute([$username, $message]);
            $id = $this->pdo->lastInsertId();
            $created_at = (new DateTime())->format('Y-m-d H:i:s');

            // Prepare payload to broadcast
            $payload = json_encode([
                'type' => 'message',
                'id' => (int)$id,
                'username' => $username,
                'message' => $message,
                'created_at' => $created_at
            ]);

            // Broadcast to all connected clients
            foreach ($this->clients as $client) {
                $client->send($payload);
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} closed\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Run the ratchet app on localhost:8080
$host = '0.0.0.0'; // or 'localhost'
$port = 8080;
$app = new App($host, $port);
$app->route('/chat', new Chat, ['*']); // route '/chat'
echo "WebSocket server started on ws://{$host}:{$port}/chat\n";
$app->run();
