<?php
include("config.php");
session_start();

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function is_strong_password($password) {
    // At least 8 chars, 1 uppercase, 1 lowercase, 1 number
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password);
}

$message = "";
if (isset($_POST['register'])) {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "<p class='message' style='color:red;'>Invalid CSRF token.</p>";
    } else {
        $username = trim($_POST['username']);
        $email    = trim($_POST['email']);
        $password = $_POST['password'];

        // Server-side validation
        if (strlen($username) < 3 || strlen($username) > 32) {
            $message = "<p class='message' style='color:red;'>Username must be 3-32 characters.</p>";
        } elseif (!is_valid_email($email)) {
            $message = "<p class='message' style='color:red;'>Invalid email address.</p>";
        } elseif (!is_strong_password($password)) {
            $message = "<p class='message' style='color:red;'>Password must be at least 8 characters, include upper and lower case letters, and a number.</p>";
        } else {
            $check = $conn->prepare("SELECT id FROM users WHERE username=? OR email=?");
            $check->bind_param("ss", $username, $email);
            $check->execute();
            $result_check = $check->get_result();

            if ($result_check->num_rows > 0) {
                $message = "<p class='message' style='color:red;'>&#10060; Username or Email already exists!</p>";
            } else {
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $username, $email, $password_hash);
                if ($stmt->execute()) {
                    $message = "<p class='message' style='color:green;'>&#9989; Registration successful! You can now log in.</p>";
                } else {
                    $message = "<p class='message' style='color:red;'>&#10060; Registration error. Please try again later.</p>";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="./assets/register2.css">
    <title>Register</title>
</head>
<body>
    <div class="container">
        <h2>Create Account</h2>
        <?php if (!empty($message)) echo $message; ?>
        <form action="" method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="text" name="username" placeholder="Username" required minlength="3" maxlength="32">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required minlength="8">
            <button type="submit" name="register">Register</button>
        </form>
        <a class="login-btn" href="login.php">Already registered? Login</a>
    </div>
</body>
</html>
