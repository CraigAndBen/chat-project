<?php

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

require __DIR__ . "/db.php";

class Chat implements MessageComponentInterface
{
    protected $clients;
    protected $pdo;

    public function __construct($pdo)
    {
        $this->clients = new \SplObjectStorage;
        $this->pdo = $pdo;
        echo "Chat server started\n";
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";

        // send last 10 messages
        $stmt = $this->pdo->query("SELECT username, message, created_at FROM messages ORDER BY id DESC LIMIT 10");
        $messages = array_reverse($stmt->fetchAll());
        foreach ($messages as $msg) {
            $conn->send(json_encode([
                "type" => "message",
                "username" => $msg["username"],
                "message" => $msg["message"],
                "created_at" => $msg["created_at"]
            ]));
        }
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
        if (!$data || $data["type"] !== "message") return;

        $username = htmlspecialchars($data["username"]);
        $message = htmlspecialchars($data["message"]);

        // save to DB
        $stmt = $this->pdo->prepare("INSERT INTO messages (username, message) VALUES (?, ?)");
        $stmt->execute([$username, $message]);

        $payload = json_encode([
            "type" => "message",
            "username" => $username,
            "message" => $message,
            "created_at" => date("Y-m-d H:i:s")
        ]);

        // broadcast
        foreach ($this->clients as $client) {
            $client->send($payload);
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} closed\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}
