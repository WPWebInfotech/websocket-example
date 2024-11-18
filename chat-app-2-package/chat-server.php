<?php
error_reporting(E_ALL & ~E_DEPRECATED);
// chat-server.php

// Ensure the autoload is included properly
require 'vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;         // Import IoServer
use Ratchet\Http\HttpServer;         // Import HttpServer
use Ratchet\WebSocket\WsServer;      // Import WsServer

class Chat implements MessageComponentInterface {
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        // Store the new connection to $this->clients
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        echo "New message! ({$msg})\n";
        // Broadcast the message to all clients except the sender
        foreach ($this->clients as $client) {
            if ($from !== $client) {
                $client->send($msg);
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        // Remove the connection when it closes
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        // Handle any errors
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Set up the WebSocket server on port 8080
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new Chat()
        )
    ),
    8045
);

// Run the server
$server->run();
