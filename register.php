<?php
session_start();

if (isset($_SESSION['student_id'])) {
    header('Location: student_dashboard.php');
    exit;
}

require_once 'db.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = trim($_POST['student_id']);
    $name       = trim($_POST['name']);
    $email      = trim($_POST['email']);
    $password   = $_POST['password'];
    $confirm    = $_POST['confirm_password'];
    $department = trim($_POST['department']);
    $gender     = trim($_POST['gender'] ?? '');

    if (empty($name)) {
        $error = 'Please enter your full name.';

    } elseif (empty($email)) {
        $error = 'Please enter your university email.';

    } elseif (!str_ends_with($email, '@stu.jazanu.edu.sa')) {
        $error = 'Email must be a university email (@stu.jazanu.edu.sa).';

    } elseif (empty($student_id)) {
        $error = 'Please enter your Student ID.';

    } elseif (!preg_match('/^[0-9]{9}$/', $student_id)) {
        $error = 'Student ID must be exactly 9 digits.';

    } elseif (empty($department)) {
        $error = 'Please select your department.';

    } elseif (empty($gender) || !in_array($gender, ['Male', 'Female'])) {
        $error = 'Please select your gender.';

    } elseif (empty($password)) {
        $error = 'Please enter a password.';

    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';

    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter.';

    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one number.';

    } elseif (empty($confirm)) {
        $error = 'Please confirm your password.';

    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';

    } else {
        

        $stmt = mysqli_prepare($conn, "SELECT studentID FROM students WHERE studentID = ?");
        mysqli_stmt_bind_param($stmt, 's', $student_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error = 'This Student ID is already registered.';
        }
        mysqli_stmt_close($stmt);

        if (!$error) {
            $stmt = mysqli_prepare($conn, "SELECT studentID FROM students WHERE email = ?");
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $error = 'This email is already registered.';
            }
            mysqli_stmt_close($stmt);
        }

        if (!$error) {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $sql    = "INSERT INTO students (studentID, name, email, password, department, gender) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt   = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'ssssss', $student_id, $name, $email, $hashed, $department, $gender);
            if (mysqli_stmt_execute($stmt)) {
                $success = 'Account created successfully! You can now log in.';
            } else {
                $error = 'Something went wrong. Please try again.';
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - DUEMS</title>
    <style>
        body {
            margin: 0;
            background-color: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 30px 0;
        }

        .card {
            background: #fff;
            width: 460px;
            padding: 36px 32px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .card h2 {
            text-align: center;
            color: #2c3e50;
            font-size: 22px;
            margin: 0 0 4px;
        }

        .card .sub {
            text-align: center;
            font-size: 13px;
            color: #888;
            margin-bottom: 24px;
        }

        .alert-error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
            border-radius: 6px;
            padding: 9px 12px;
            font-size: 13px;
            margin-bottom: 16px;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #86efac;
            border-radius: 6px;
            padding: 9px 12px;
            font-size: 13px;
            margin-bottom: 16px;
        }

        .alert-success a {
            color: #166534;
            font-weight: 700;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 5px;
        }

        input, select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            color: #2c3e50;
            background: #f9fafb;
            box-sizing: border-box;
            margin-bottom: 16px;
            outline: none;
        }

        input:focus, select:focus {
            border-color: #2c3e50;
            background: #fff;
        }

        .hint {
            font-size: 11.5px;
            color: #94a3b8;
            margin-top: -12px;
            margin-bottom: 14px;
        }

        button {
            width: 100%;
            padding: 11px;
            background-color: #2c3e50;
            color: #f0c040;
            font-size: 15px;
            font-weight: 700;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 4px;
        }

        button:hover {
            background-color: #3d5166;
        }

        hr {
            border: none;
            border-top: 1px solid #e5e7eb;
            margin: 20px 0 16px;
        }

        .login-link {
            text-align: center;
            font-size: 13px;
            color: #888;
        }

        .login-link a {
            color: #2c3e50;
            font-weight: 600;
            text-decoration: none;
        }

        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="card">
    <h2>Create Account</h2>
    <p class="sub">Jazan University &mdash; DUEMS</p>

    <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert-success">
            <?= htmlspecialchars($success) ?> &nbsp;<a href="login.php">Login now &rarr;</a>
        </div>
    <?php endif; ?>

    <!-- novalidate disables the browser's built-in popup -->
    <form method="POST" action="register.php" novalidate>

        <label>Full Name</label>
        <input type="text" name="name" placeholder="Your full name"
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">

        <label>University Email</label>
        <input type="email" name="email" placeholder="202000000@stu.jazanu.edu.sa"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

        <label>Student ID</label>
        <input type="text" name="student_id" placeholder="Student ID" maxlength="9"
               value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>">

        <label>Department</label>
        <select name="department">
            <option value=""> Select Department </option>
            <?php
            $depts = ['Computer Science','Information Technology','Software Engineering',
                      'Electrical Engineering','Mechanical Engineering','Civil Engineering',
                      'Business Administration','Medicine','Pharmacy','Other'];
            foreach ($depts as $d):
                $sel = (isset($_POST['department']) && $_POST['department'] == $d) ? 'selected' : '';
            ?>
                <option value="<?= $d ?>" <?= $sel ?>><?= $d ?></option>
            <?php endforeach; ?>
        </select>

        <label>Gender</label>
        <select name="gender">
            <option value=""> Select Gender </option>
            <option value="Male"   <?= (isset($_POST['gender']) && $_POST['gender'] == 'Male')   ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : '' ?>>Female</option>
        </select>

        <label>Password</label>
        <input type="password" name="password" placeholder="At least 8 characters">
        <p class="hint">At least 8 characters &bull; One uppercase letter &bull; One number</p>

        <label>Confirm Password</label>
        <input type="password" name="confirm_password" placeholder="Repeat your password">

        <button type="submit">Register</button>
    </form>

    <hr>
    <p class="login-link">Already have an account? <a href="login.php">Login here</a></p>
</div>

</body>
</html>
