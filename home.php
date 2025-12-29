<?php
session_start();
include 'db.php'; // DB connection

// Fetch featured books from database
$sql = "SELECT id, title, author, price, cover_image FROM books LIMIT 8";
$result = $conn->query($sql);

$books = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BookShelf — Home</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

<style>
:root{
  --accent:#FF6B6B;
  --accent2:#FFD166;
  --muted:rgba(224,224,224,0.7);
  --glass: rgba(255,255,255,0.15);
  --radius:18px;
  --shadow:0 15px 35px rgba(0,0,0,0.5);
}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif;}
body{color:rgba(255,255,255,0.85); min-height:100vh; display:flex; flex-direction:column; position:relative;}
body::before{content:''; position:absolute; inset:0; background:url('images/image12.jpeg') center/cover no-repeat fixed; z-index:-2; filter:brightness(0.7);}
body::after{content:''; position:absolute; inset:0; background:rgba(0,0,0,0.35); z-index:-1;}

.nav{display:flex; justify-content:space-between; align-items:center; padding:20px 40px; backdrop-filter:blur(8px);}
.brand{display:flex; gap:12px; align-items:center;}
.logo{width:50px;height:50px;border-radius:12px;background:linear-gradient(135deg,#FF6B6B,#FFD166);display:flex;align-items:center;justify-content:center;color:#1b1b1b;font-weight:700;font-size:24px;}
.nav-links{display:flex; gap:20px;}
.nav-links a{color:#fff;text-decoration:none;font-weight:600;}

.hero{text-align:center;padding:90px 20px;}
.hero h1{font-family:'Playfair Display',serif;font-size:48px;}
.hero span{color:var(--accent);}
.hero p{color:var(--muted);font-size:20px;max-width:700px;margin:auto;}

.section{width:90%;max-width:1200px;margin:50px auto;text-align:center;}
.section h2{color:var(--accent2);font-size:36px;}

.featured-container{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
  gap:25px;
  margin-top:30px;
}

.book-box{
  background:var(--glass);
  backdrop-filter:blur(10px);
  border-radius:var(--radius);
  padding:15px;
  cursor:pointer;
  transition:0.3s;
}
.book-box:hover{transform:translateY(-8px);}
.book-img{width:140px;height:200px;object-fit:cover;border-radius:12px;}
.book-title{margin-top:10px;font-weight:600;color:var(--accent2);}

footer{margin-top:auto;text-align:center;padding:20px;color:rgba(255,255,255,0.7);}
</style>
</head>

<body>

<nav class="nav">
  <div class="brand">
    <div class="logo">B</div>
    <h1>BookShelf</h1>
  </div>
  <div class="nav-links">
    <a href="about.html">About</a>
    <a href="contact.html">Contact</a>
    <a href="books_category.html">Books</a>
    <a href="cart.php">Cart</a>
  </div>
</nav>

<header class="hero">
  <h1>Welcome to <span>BookShelf</span></h1>
  <p>Discover, collect, and enjoy your favorite books.</p>
</header>

<div class="section">
  <h2>Featured Books</h2>

  <div class="featured-container">
    <?php foreach ($books as $b): ?>
      <div class="book-box" onclick="openBook(<?= $b['id'] ?>)">
        <img src="<?= $b['cover_image'] ?>" class="book-img">
        <div class="book-title"><?= htmlspecialchars($b['title']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<footer>
  © BookShelf — Made with ♥
</footer>

<script>
function openBook(id){
  window.location.href = "books_detail.php?id=" + id;
}
</script>

</body>
</html>