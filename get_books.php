<?php
include 'db.php';
header('Content-Type: application/json');

$stmt = $conn->prepare("SELECT id, title, author, cover, price FROM books");
$stmt->execute();
$result = $stmt->get_result();

$books = [];
while ($row = $result->fetch_assoc()) {
    $books[] = $row; // id automatically include ho jayega
}

echo json_encode($books);
$stmt->close();
$conn->close();
?>