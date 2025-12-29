<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($data === null || empty($input)) {
    echo json_encode(['message' => 'Invalid data']);
    exit;
}

$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(['message' => 'Please fill both fields']);
    exit;
}

// User find karo
$stmt = $conn->prepare("SELECT id, name, password FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['message' => 'Email not registered']);
    $stmt->close();
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

if (password_verify($password, $user['password'])) {
    // Session start
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];

    echo json_encode([
        'message' => 'Login successful',
        'user' => ['name' => $user['name']]
    ]);
} else {
    echo json_encode(['message' => 'Invalid password']);
}

$conn->close();
?>