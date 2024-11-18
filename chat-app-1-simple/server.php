<?php
$address = '0.0.0.0';
$port = 8120;

// Create a TCP/IP stream socket
$server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($server, $address, $port);
socket_listen($server);

echo "WebSocket server started on ws://$address:$port\n";

function decodeFrame($data) {
    $payloadLength = ord($data[1]) & 127;
    $maskStart = 2;

    if ($payloadLength === 126) {
        $maskStart = 4;
    } elseif ($payloadLength === 127) {
        $maskStart = 10;
    }

    $masks = substr($data, $maskStart, 4);
    $payload = substr($data, $maskStart + 4);

    $decoded = '';
    for ($i = 0; $i < strlen($payload); $i++) {
        $decoded .= $payload[$i] ^ $masks[$i % 4];
    }

    return $decoded;
}

function frameData($payload) {
    $frameHead = [];
    $frame = '';
    $payloadLength = strlen($payload);

    // Set FIN and opcode for text frame
    $frameHead[0] = 129;

    if ($payloadLength <= 125) {
        $frameHead[1] = $payloadLength;
    } elseif ($payloadLength > 125 && $payloadLength < 65536) {
        $frameHead[1] = 126;
        $frameHead[2] = ($payloadLength >> 8) & 255;
        $frameHead[3] = $payloadLength & 255;
    } else {
        $frameHead[1] = 127;
        for ($i = 7; $i >= 0; $i--) {
            $frameHead[$i + 2] = ($payloadLength >> ($i * 8)) & 255;
        }
    }

    foreach ($frameHead as $char) {
        $frame .= chr($char);
    }

    $frame .= $payload;

    return $frame;
}

while (true) {
    $client = @socket_accept($server);
    if ($client === false) {
        continue;
    }

    // Read the client's request headers
    $request = @socket_read($client, 1024);
    if (!$request) {
        socket_close($client);
        continue;
    }

    // Match the WebSocket key
    if (preg_match('/Sec-WebSocket-Key: (.*)\r\n/', $request, $matches)) {
        $key = trim($matches[1]);
        $acceptKey = base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

        // Create and send the handshake headers
        $headers = "HTTP/1.1 101 Switching Protocols\r\n";
        $headers .= "Upgrade: websocket\r\n";
        $headers .= "Connection: Upgrade\r\n";
        $headers .= "Sec-WebSocket-Accept: $acceptKey\r\n\r\n";
        socket_write($client, $headers, strlen($headers));
    } else {
        socket_close($client);
        continue;
    }

    // Main communication loop
    while (true) {
        $data = @socket_read($client, 2048, PHP_BINARY_READ);
        if (!$data) {
            // Connection closed by client
            socket_close($client);
            break;
        }

        $decodedMessage = decodeFrame($data);
        echo "Received: $decodedMessage\n";

        // Respond to the client with framed data
        $response = frameData("You said: $decodedMessage");
        socket_write($client, $response, strlen($response));
    }
}

socket_close($server);
