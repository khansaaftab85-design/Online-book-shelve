<?php
session_start();
include 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Query ko simple rakha hai taake error na aaye
$stmt = $conn->prepare("SELECT b.id, b.title, b.author, b.cover, b.price 
                        FROM cart c JOIN books b ON c.book_id = b.id 
                        WHERE c.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$cart = [];
while ($row = $result->fetch_assoc()) {
    $cart[] = $row;
}

echo json_encode($cart);
$stmt->close();
$conn->close();
?>
