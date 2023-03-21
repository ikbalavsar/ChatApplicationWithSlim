<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

// ...
require_once __DIR__ . '/../vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Http\Response as SlimResponse;
use Slim\Routing\RouteCollectorProxy;


// Yeni kullanıcı kaydı
$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$app->post('/register', function (Request $request, Response $response) {
    $db = new SQLite3('chat.db');
    $data = $request->getParsedBody();
    $username = $data['username'];

    $stmt = $db->prepare('INSERT INTO users (username) VALUES (:username)');
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);

    if ($stmt->execute()) {
        $response->getBody()->write(json_encode(['success' => true, 'username' => $username]));
    } else {
        $response->getBody()->write(json_encode(['success' => false, 'message' => 'Error registering user']));
    }

    return $response->withHeader('Content-Type', 'application/json');
});

// iki kullanıcı arasında yeni mesajlaşma
$app->post('/start-conversation', function (Request $request, Response $response, array $args) {
    $db = new SQLite3('chat.db');
    $data = $request->getParsedBody();

    // iki kullanıcının da var olduğunun kontrolü 
    $user1 = getUser($db, $data['user1']);
    $user2 = getUser($db, $data['user2']);

    if (!$user1 || !$user2) {
        $response->getBody()->write(json_encode(['success' => false, 'message' => 'One or both users do not exist.']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // Yeni konuşma
    $stmt = $db->prepare('INSERT INTO conversations (user1_id, user2_id) VALUES (:user1_id, :user2_id)');
    $stmt->bindValue(':user1_id', $user1['id'], SQLITE3_INTEGER);
    $stmt->bindValue(':user2_id', $user2['id'], SQLITE3_INTEGER);

    if (!$stmt->execute()) {
        $response->getBody()->write(json_encode(['success' => false, 'message' => 'Failed to create a new conversation.']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // iletişim ıd si 
    $conversationId = $db->lastInsertRowID();

    // Mesaj tablosuna ilk mesaj kaydet
    $stmt = $db->prepare('INSERT INTO messages (conversation_id, user_id, message) VALUES (:conversation_id, :user_id, :message)');
    $stmt->bindValue(':conversation_id', $conversationId, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $user1['id'], SQLITE3_INTEGER);
    $stmt->bindValue(':message', $data['message'], SQLITE3_TEXT);

    if (!$stmt->execute()) {
        $response->getBody()->write(json_encode(['success' => false, 'message' => 'Failed to add the first message to the conversation.']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode(['success' => true, 'conversation_id' => $conversationId]));
    return $response->withHeader('Content-Type', 'application/json');
});

// Mesaj gönder
$app->post('/send', function (Request $request, Response $response) {
    $db = new SQLite3('chat.db');
    $data = $request->getParsedBody();
    $conversationId = $data['conversation_id'];
    $username = $data['username'];
    $message = $data['message'];

    // Username kullanarak user ıd alma
    $user = getUser($db, $username);
    if (!$user) {
        $response->getBody()->write(json_encode(['success' => false, 'message' => 'User not found']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }
    $userId = $user['id'];

    $stmt = $db->prepare('INSERT INTO messages (conversation_id, user_id, message) VALUES (:conversation_id, :user_id, :message)');
    $stmt->bindValue(':conversation_id', $conversationId, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':message', $message, SQLITE3_TEXT);

    if ($stmt->execute()) {
        $response->getBody()->write(json_encode(['success' => true]));
    } else {
        $response->getBody()->write(json_encode(['success' => false, 'message' => 'Error sending message']));
    }
    return $response->withHeader('Content-Type', 'application/json');
});


// Mesaj alma
$app->get('/messages/{conversation_id}/{last_message_id}', function (Request $request, Response $response, array $args) {
    $db = new SQLite3('chat.db');
    $conversationId = $args['conversation_id'];
    $lastMessageId = $args['last_message_id'];

    $stmt = $db->prepare('SELECT messages.id, users.username, messages.message, messages.timestamp FROM messages JOIN users ON messages.user_id = users.id WHERE messages.conversation_id = :conversation_id AND messages.id > :last_message_id ORDER BY messages.timestamp ASC');
    $stmt->bindValue(':conversation_id', $conversationId, SQLITE3_INTEGER);
    $stmt->bindValue(':last_message_id', $lastMessageId, SQLITE3_INTEGER);

    $result = $stmt->execute();
    $messages = [];

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $messages[] = [
            'id' => $row['id'],
            'username' => $row['username'],
            'message' => $row['message'],
            'timestamp' => $row['timestamp'],
        ];
    }

    $response->getBody()->write(json_encode(['success' => true, 'messages' => $messages]));
    return $response->withHeader('Content-Type', 'application/json');
});
function getUser($db, $username) {
    $stmt = $db->prepare('SELECT * FROM users WHERE username = :username');
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute();

    return $result->fetchArray(SQLITE3_ASSOC);
}

// uygulamayı başlat
$app->run();


