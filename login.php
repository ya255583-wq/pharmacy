<?php
session_start();

// If already logged in, skip the form entirely.
if (isset($_SESSION['userName'])) {
    header("Location: dashboard.php");
    exit();
}

$current_page = 'login';

$servername = "localhost";
$dbUser = "root";
$dbPass = "";

try {
    $db = new PDO("mysql:host=$servername;dbname=pharmacy_inventory", $dbUser, $dbPass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username !== "" && $password !== "") {

        $stmt = $db->prepare("SELECT * FROM users WHERE userName = :username AND password = :password");
        $stmt->execute([
            'username' => $username,
            'password' => $password
        ]);
        $rep = $stmt->fetch();

        if ($rep !== false) {
            $_SESSION['userName'] = $rep['userName'];
            $_SESSION['fullName'] = $rep['fullName'];
            $_SESSION['role']     = $rep['role'];
            $_SESSION['userId']   = $rep['userId'];

            header("Location: dashboard.php");
            exit();
        } else {
            $error_msg = "Username or password incorrect !";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="includes/style.css">
</head>

<body class="login-page" style="background-color: #1e303d;">

    <div class="main-container" style="background-color: #1e303d;">
        <div class="logo-container">
            <img src="includes/logo.png" alt="Logo">
        </div>

        <div class="login-card">
            <h1>Login</h1>
            <p class="subtitle">Enter your credentials to access</p>
            <form action="" method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" name="username" placeholder="e.g admin" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" placeholder="e.g admin123" required>
                </div>

                <button type="submit" class="login-btn">Login</button>
            </form>
            <?php if (isset($error_msg)): ?>
                <p><?php echo htmlspecialchars($error_msg); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <br>
    <?php require_once 'includes/footer.php'; ?>

</body>

</html>