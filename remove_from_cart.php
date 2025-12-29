<?php
session_start();
include 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$book_id = $data['book_id'] ?? 0;

if ($book_id <= 0) {
    echo json_encode(['success' => false]);
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND book_id = ?");
$stmt->bind_param("ii", $user_id, $book_id);
$stmt->execute();

echo json_encode(['success' => true]);
$stmt->close();
$conn->close();
?>