<?php
// Set the address and port for the WebSocket server
$address = '0.0.0.0';  // All available network interfaces
$port = 8050;  // WebSocket port

// Create the WebSocket server socket
$server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($server, $address, $port);
socket_listen($server);

echo "WebSocket server started on ws://{$address}:{$port}/wpweb-blogs/php/\n";

// Accept incoming client connections
while (true) {
    // Accept a connection
    $client = socket_accept($server);

    // Perform the WebSocket handshake
    $request = socket_read($client, 5000);
    preg_match('#Sec-WebSocket-Key: (.*)\r\n#', $request, $matches);
    $key = base64_encode(sha1($matches[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
    $headers = "HTTP/1.1 101 Switching Protocols\r\n" .
               "Upgrade: websocket\r\n" .
               "Connection: Upgrade\r\n" .
               "Sec-WebSocket-Accept: $key\r\n\r\n";
    socket_write($client, $headers, strlen($headers));

    // Send the timer value to the client every second
    $counter = 0;
    while (true) {
        // Build the message with the current timer value
        $message = "Timer: " . $counter;
        $messageLength = strlen($message);

        // Prepare the WebSocket frame to send
        $frame = chr(0x81) . chr($messageLength) . $message;

        // Send the message to the client
        socket_write($client, $frame);

        // Increment the timer
        $counter++;

        // Sleep for 1 second before sending the next message
        sleep(1);
    }

    // Close the connection when done (this won't be reached in this case)
    socket_close($client);
}
