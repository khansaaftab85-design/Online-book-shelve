<?php
include 'db.php';

header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($data === null || empty($input)) {
    echo json_encode(['message' => 'Invalid data']);
    exit;
}

$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if (empty($name) || empty($email) || empty($password)) {
    echo json_encode(['message' => 'All fields are required']);
    exit;
}

// Email already exists check
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo json_encode(['message' => 'Email already registered']);
    $stmt->close();
    exit;
}
$stmt->close();

// Insert new user
$hashed = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $name, $email, $hashed);

if ($stmt->execute()) {
    echo json_encode(['message' => 'Signup successful']);
} else {
    echo json_encode(['message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>