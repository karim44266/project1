<?php
include("config.php");
session_start();
// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$error = "";
if (isset($_POST['login'])) {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "<p class='message' style='color:#ff4c4c;'>Invalid CSRF token.</p>";
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username=?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                session_regenerate_id(true); // Prevent session fixation
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user'] = $user['username'];
                header("Location: profile.php");
                exit();
            } else {
                $error = "<p class='message' style='color:#ff4c4c;'>❌ Wrong password!</p>";
            }
        } else {
            $error = "<p class='message' style='color:#ff4c4c;'>❌ User not found!</p>";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="./assets/style.css">
</head>
<body>
    <div class="login-page">
        <div class="form">
            <h2>Login</h2>
            <?php if(!empty($error)) echo $error; ?>
            <form class="login-form" action="" method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="login">Sign In</button>
            </form>
            <p class="message">Not registered? <a href="register.php">Create an account</a></p>
        </div>
    </div>
</body>
</html>
