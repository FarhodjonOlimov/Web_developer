<?php
session_start();
$conn = new mysqli("localhost", "root", "", "kutubxona");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

function isAdmin() {
    return isset($_SESSION['user']) && $_SESSION['user']['is_admin'];
}

function currentUser() {
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

function getReviews($book_id) {
    global $conn;
    $result = $conn->query("SELECT r.*, u.username FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.book_id = $book_id");
    $reviews = [];
    while ($row = $result->fetch_assoc()) {
        $reviews[] = $row;
    }
    return $reviews;
}

if (isset($_POST['register'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (username, email, password) VALUES ('$username', '$email', '$password')");
    echo "<script>alert('Registered successfully');</script>";
}

if (isset($_POST['login'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    $res = $conn->query("SELECT * FROM users WHERE email='$email'");
    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user;
        } else {
            echo "<script>alert('Wrong password');</script>";
        }
    } else echo "<script>alert('User not found');</script>";
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

if (isset($_POST['add_book']) && isAdmin()) {
    $title = $conn->real_escape_string($_POST['title']);
    $author = $conn->real_escape_string($_POST['author']);
    $genre = $conn->real_escape_string($_POST['genre']);
    $year = intval($_POST['year']);
    $desc = $conn->real_escape_string($_POST['desc']);
    $conn->query("INSERT INTO books (title, author, genre, published_year, description, available) VALUES ('$title', '$author', '$genre', $year, '$desc', 1)");
}

if (isset($_POST['borrow']) && currentUser()) {
    $book_id = intval($_POST['book_id']);
    $user_id = $_SESSION['user']['id'];
    $conn->query("INSERT INTO borrows (user_id, book_id) VALUES ($user_id, $book_id)");
    $conn->query("UPDATE books SET available=0 WHERE id=$book_id");
}

if (isset($_POST['review']) && currentUser()) {
    $book_id = intval($_POST['book_id']);
    $user_id = $_SESSION['user']['id'];
    $text = $conn->real_escape_string($_POST['text']);
    $rating = intval($_POST['rating']);
    $conn->query("INSERT INTO reviews (user_id, book_id, text, rating) VALUES ($user_id, $book_id, '$text', $rating)");
}

if (isset($_POST['delete_book']) && isAdmin()) {
    $book_id = intval($_POST['book_id']);
    $conn->query("DELETE FROM books WHERE id = $book_id");
    $conn->query("DELETE FROM reviews WHERE book_id = $book_id");
    $conn->query("DELETE FROM borrows WHERE book_id = $book_id");
}

$searchQuery = "SELECT * FROM books";
if (isset($_GET['q'])) {
    $search = $conn->real_escape_string($_GET['q']);
    $searchQuery = "SELECT * FROM books WHERE title LIKE '%$search%' OR author LIKE '%$search%'";
}
$books = $conn->query($searchQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Kutubxona</title>
</head>
<body class="bg-gray-100 text-gray-900">
<div class="max-w-6xl mx-auto p-4">
    <h1 class="text-3xl font-bold mb-4">Kutubxona</h1>

    <?php if (!currentUser()): ?>
        <!-- Register and Login Forms -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <form method="post" class="bg-white p-4 rounded shadow">
                <h2 class="text-xl font-semibold">Ro'yxatdan o'tish</h2>
                <input type="text" name="username" placeholder="Username" class="w-full border p-2 my-1">
                <input type="email" name="email" placeholder="Email" class="w-full border p-2 my-1">
                <input type="password" name="password" placeholder="Parol" class="w-full border p-2 my-1">
                <button name="register" class="bg-green-500 text-white px-4 py-2 mt-2">Ro'yxatdan o'tish</button>
            </form>

            <form method="post" class="bg-white p-4 rounded shadow">
                <h2 class="text-xl font-semibold">Kirish</h2>
                <input type="email" name="email" placeholder="Email" class="w-full border p-2 my-1">
                <input type="password" name="password" placeholder="Parol" class="w-full border p-2 my-1">
                <button name="login" class="bg-blue-500 text-white px-4 py-2 mt-2">Kirish</button>
            </form>
        </div>
    <?php else: ?>
        <!-- Logged in user -->
        <div class="flex justify-between items-center">
            <p>Salom, <strong><?= $_SESSION['user']['username'] ?></strong>!</p>
            <a href="?logout=1" class="text-red-500">Chiqish</a>
        </div>

        <?php if (isAdmin()): ?>
        <!-- Admin: Add Book Form -->
        <form method="post" class="bg-white p-4 my-4 rounded shadow">
            <h2 class="text-xl font-semibold">Yangi kitob qo'shish</h2>
            <input type="text" name="title" placeholder="Kitob nomi" class="w-full border p-2 my-1">
            <input type="text" name="author" placeholder="Muallif" class="w-full border p-2 my-1">
            <input type="text" name="genre" placeholder="Janr" class="w-full border p-2 my-1">
            <input type="number" name="year" placeholder="Yil" class="w-full border p-2 my-1">
            <textarea name="desc" placeholder="Tavsif" class="w-full border p-2 my-1"></textarea>
            <button name="add_book" class="bg-purple-500 text-white px-4 py-2 mt-2">Qo'shish</button>
        </form>
        <?php endif; ?>

        <!-- Search -->
        <form method="get" class="my-4">
            <input type="text" name="q" placeholder="Kitob yoki muallif qidirish..." class="w-full p-2 border rounded">
            <button class="bg-blue-600 text-white px-4 py-2 mt-2">Qidirish</button>
        </form>

        <!-- Books Listing -->
        <h2 class="text-2xl font-semibold mt-6 mb-4">Kitoblar</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php while($book = $books->fetch_assoc()): ?>
                <div class="bg-white p-4 rounded shadow">
                    <h3 class="text-xl font-bold"><?= $book['title'] ?></h3>
                    <p class="text-sm text-gray-600">Muallif: <?= $book['author'] ?> | Janr: <?= $book['genre'] ?> | Yil: <?= $book['published_year'] ?></p>
                    <p class="my-2 text-gray-700"><?= $book['description'] ?></p>
                    <p class="text-sm mb-2">Holati: <?= $book['available'] ? 'Mavjud' : 'Band' ?></p>
                    <?php if ($book['available']): ?>
                        <form method="post">
                            <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                            <button name="borrow" class="bg-blue-500 text-white px-3 py-1 mt-2">Olish</button>
                        </form>
                    <?php endif; ?>
                    <!-- Reviews -->
                    <?php $reviews = getReviews($book['id']); ?>
                    <?php if ($reviews): ?>
                        <div class="mt-3 border-t pt-2">
                            <h4 class="font-semibold text-sm text-gray-700">Fikrlar:</h4>
                            <?php foreach ($reviews as $review): ?>
                                <div class="border p-2 my-1 rounded text-sm bg-gray-50">
                                    <p><strong><?= $review['username'] ?>:</strong> <?= $review['text'] ?></p>
                                    <p class="text-yellow-600">Baho: <?= $review['rating'] ?>/5</p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <!-- Add Review -->
                    <form method="post" class="mt-2">
                        <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                        <textarea name="text" placeholder="Fikr" class="w-full border p-1"></textarea>
                        <input type="number" name="rating" placeholder="Baho (1-5)" class="w-full border p-1 mt-1">
                        <button name="review" class="bg-green-500 text-white px-3 py-1 mt-1">Yuborish</button>
                    </form>
                    <?php if (isAdmin()): ?>
                        <form method="post" class="mt-2">
                            <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                            <button name="delete_book" class="bg-red-500 text-white px-3 py-1 mt-2">O‘chirish</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- User Profile -->
        <div class="bg-white p-4 my-4 rounded shadow">
            <h2 class="text-xl font-semibold">Profil</h2>
            <p><strong>Username:</strong> <?= $_SESSION['user']['username'] ?></p>
            <p><strong>Email:</strong> <?= $_SESSION['user']['email'] ?></p>
        </div>

        <!-- Stats -->
        <?php
        $total = $conn->query("SELECT COUNT(*) as count FROM books")->fetch_assoc()['count'];
        $available = $conn->query("SELECT COUNT(*) as count FROM books WHERE available = 1")->fetch_assoc()['count'];
        ?>
        <div class="bg-white p-4 my-4 rounded shadow">
            <h2 class="text-xl font-semibold">Statistika</h2>
            <p>Jami kitoblar: <strong><?= $total ?></strong></p>
            <p>Mavjud kitoblar: <strong><?= $available ?></strong></p>
        </div>

    <?php endif; ?>
</div>
</body>
</html>