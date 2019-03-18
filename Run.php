<?php
require 'vendor/autoload.php';
use App\Server\WebSocket\WebSocketServer;
$server = new WebSocketServer();
$server->run();