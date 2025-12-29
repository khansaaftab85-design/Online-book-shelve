<?php
session_start();
include 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$book_id = $data['book_id'] ?? 0;

if ($book_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid book']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if already in cart (optional — duplicate avoid)
$stmt = $conn->prepare("SELECT id FROM cart WHERE user_id = ? AND book_id = ?");
$stmt->bind_param("ii", $user_id, $book_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Already in cart']);
    $stmt->close();
    exit;
}
$stmt->close();

// Add to cart
$stmt = $conn->prepare("INSERT INTO cart (user_id, book_id) VALUES (?, ?)");
$stmt->bind_param("ii", $user_id, $book_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Added to cart']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error adding to cart']);
}

$stmt->close();
$conn->close();
?>