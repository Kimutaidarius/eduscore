<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session_timeout.php';

// Detect AJAX
$isAjax = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']));

// Auth check
if (empty($_SESSION['authenticated']) || empty($_SESSION['teacher_id'])) {
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    header("Location: login.php");
    exit;
}

$teacher_id = (int) $_SESSION['teacher_id'];

// ================= FETCH USER =================
$sql = "SELECT firstname, secondname, lastname, email, password, profile_photo, role 
        FROM tblteachers WHERE id = :id";

$query = $dbh->prepare($sql);
$query->bindParam(':id', $teacher_id, PDO::PARAM_INT);
$query->execute();
$user = $query->fetch(PDO::FETCH_ASSOC);

$role = $user['role'] ?? '';

// Build name
$nameParts = array_filter([
    $user['firstname'] ?? '',
    $user['secondname'] ?? '',
    $user['lastname'] ?? ''
]);

$fullName = implode(' ', $nameParts);
$email = $user['email'] ?? '';
$profilePhoto = $user['profile_photo'] ?? '';

// Initials
$initials = strtoupper(substr($nameParts[0] ?? 'U', 0, 1) . substr(end($nameParts) ?? '', 0, 1));

// ================= AJAX HANDLERS =================
if ($isAjax) {
    header('Content-Type: application/json');

    try {

        // UPDATE PROFILE
        if ($_POST['action'] === 'update_profile') {
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);

            $parts = explode(' ', $name);

            $sql = "UPDATE tblteachers 
                    SET firstname=?, secondname=?, lastname=?, email=? 
                    WHERE id=?";

            $dbh->prepare($sql)->execute([
                $parts[0] ?? '',
                $parts[1] ?? '',
                $parts[2] ?? '',
                $email,
                $teacher_id
            ]);

            echo json_encode(['success' => true, 'message' => 'Profile updated']);
            exit;
        }

        // CHANGE PASSWORD
        if ($_POST['action'] === 'change_password') {

            $current = $_POST['current_password'];
            $new = password_hash($_POST['new_password'], PASSWORD_DEFAULT);

            if (!password_verify($current, $user['password'])) {
                echo json_encode(['success' => false, 'message' => 'Wrong password']);
                exit;
            }

            $dbh->prepare("UPDATE tblteachers SET password=? WHERE id=?")
                ->execute([$new, $teacher_id]);

            echo json_encode(['success' => true, 'message' => 'Password updated']);
            exit;
        }

        // UPLOAD PHOTO
        if ($_POST['action'] === 'upload_photo') {

            if (!isset($_FILES['photo'])) {
                throw new Exception("No file uploaded");
            }

            $file = $_FILES['photo'];
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

            $newName = "profile_" . $teacher_id . "_" . time() . "." . $ext;

            $uploadDir = __DIR__ . "/uploads/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            move_uploaded_file($file['tmp_name'], $uploadDir . $newName);

            $dbh->prepare("UPDATE tblteachers SET profile_photo=? WHERE id=?")
                ->execute([$newName, $teacher_id]);

            echo json_encode([
                'success' => true,
                'file' => $newName
            ]);
            exit;
        }

// DELETE ACCOUNT
if ($_POST['action'] === 'delete_account') {

    // Protect Super Admin
    if (strtolower($user['role']) === 'super admin') {
        echo json_encode(['success' => false, 'message' => 'This account is protected']);
        exit;
    }

    $dbh->prepare("DELETE FROM tblteachers WHERE id=?")
        ->execute([$teacher_id]);

    session_destroy();

    echo json_encode(['success' => true]);
    exit;
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Profile | EduScore</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --primary: #4f46e5;
    --primary-light: #6366f1;
    --primary-soft: #eef2ff;

    --danger: #ef4444;

    --bg: #f1f5f9;
    --card: #ffffff;

    --text: #0f172a;
    --muted: #64748b;

    --border: #e2e8f0;

    --radius: 14px;
}

/* RESET */
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

/* BODY */
body {
    font-family: 'Inter', sans-serif;
    background: var(--bg);
    color: var(--text);
}

/* MAIN LAYOUT */
.main-content {
    margin-left: 260px; /* matches sidebar */
    padding: 2rem;
}

.container {
    max-width: 1100px;
    margin: auto;
}

/* HEADER TITLE */
h2 {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 2rem;
}

/* GRID */
.profile-grid {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 2rem;
}

@media (max-width: 900px) {
    .main-content { margin-left: 0; }
    .profile-grid {
        grid-template-columns: 1fr;
    }
}

/* CARD */
.card {
    background: var(--card);
    border-radius: var(--radius);
    padding: 2rem;
    border: 1px solid var(--border);
    box-shadow: 0 10px 25px rgba(0,0,0,0.05);
    transition: 0.3s;
}

.card:hover {
    transform: translateY(-3px);
}

/* LEFT PROFILE CARD */
.text-center {
    text-align: center;
}

/* AVATAR */
.avatar-img {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    object-fit: cover;
    border: 5px solid var(--primary-soft);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.avatar-initials {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    font-size: 2.8rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: auto;
}

/* FILE INPUT */
#photoInput {
    margin-top: 1rem;
    font-size: 0.9rem;
}

/* NAME */
h3 {
    margin-top: 1rem;
    font-size: 1.2rem;
}

p {
    color: var(--muted);
    font-size: 0.9rem;
}

/* FORM */
form {
    margin-top: 1rem;
}

form input {
    width: 100%;
    padding: 0.8rem 1rem;
    border-radius: 10px;
    border: 1px solid var(--border);
    margin-bottom: 1rem;
    font-size: 0.95rem;
    transition: 0.2s;
}

form input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-soft);
}

/* BUTTONS */
button {
    display: inline-flex;        /* shrink to content */
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;       /* smaller height and width */
    border-radius: 10px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.95rem;
    white-space: nowrap;        /* prevent breaking text */
}

/* PRIMARY */
button[type="submit"] {
    background: var(--primary);
    color: white;
}

button[type="submit"]:hover {
    background: var(--primary-light);
}

/* DELETE / DANGER BUTTON */
.danger {
    background: transparent;
    border: 1px solid var(--danger);
    color: var(--danger);
    margin-top: 1rem;
}

.danger:hover {
    background: var(--danger);
    color: white;
}

/* RESPONSIVE FIXES */
@media (max-width: 600px) {
    button {
        width: 100%;        /* full width on small screens */
    }
}

/* DIVIDER */
hr {
    border: none;
    border-top: 1px solid var(--border);
    margin: 2rem 0;
}

/* SECTION HEADINGS */
.card h3 {
    font-size: 1.1rem;
    margin-bottom: 1rem;
}

/* TOAST (optional upgrade) */
.toast {
    position: fixed;
    top: 20px;
    right: 20px;
    background: var(--primary);
    color: white;
    padding: 1rem 1.5rem;
    border-radius: 10px;
    display: none;
}

/* SCROLLBAR (nice touch) */
::-webkit-scrollbar {
    width: 8px;
}
::-webkit-scrollbar-thumb {
    background: #cbd5f5;
    border-radius: 10px;
}

/* ANIMATION */
.card, button {
    transition: all 0.25s ease;
}
</style>
</head>
<body>

<!-- ✅ SIDEBAR -->
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<!-- ✅ MAIN CONTENT -->
<div class="main-content">

    <!-- ✅ HEADER -->
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="container">

        <h2>Profile Settings</h2>

        <div class="profile-grid">

            <!-- LEFT -->
            <div class="card text-center">
                <div id="avatar">
                    <?php if ($profilePhoto): ?>
                        <img src="uploads/<?= $profilePhoto ?>" class="avatar-img">
                    <?php else: ?>
                        <div class="avatar-initials"><?= $initials ?></div>
                    <?php endif; ?>
                </div>

                <input type="file" id="photoInput">

                <h3><?= htmlspecialchars($fullName) ?></h3>
                <p><?= htmlspecialchars($email) ?></p>
            </div>

            <!-- RIGHT -->
            <div class="card">

                <h3>Update Profile</h3>

                <form id="profileForm">
                    <input type="hidden" name="action" value="update_profile">

                    <input type="text" name="name" value="<?= htmlspecialchars($fullName) ?>" required>
                    <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>

                    <button type="submit">Save</button>
                </form>

                <hr>

                <h3>Change Password</h3>

                <form id="passwordForm">
                    <input type="hidden" name="action" value="change_password">

                    <input type="password" name="current_password" placeholder="Current Password" required>
                    <input type="password" name="new_password" placeholder="New Password" required>

                    <button type="submit">Update Password</button>
                </form>

<?php if (strtolower($role) === 'super admin'): ?>
    <button class="danger" disabled>Protected</button>
<?php else: ?>
    <button onclick="deleteAccount()" class="danger">Delete Account</button>
<?php endif; ?>

            </div>
        </div>

    </div>
</div>
<script>
async function submitForm(form) {
    const res = await fetch("", {
        method: "POST",
        body: new FormData(form)
    });

    const data = await res.json();
    alert(data.message || "Done");
}

document.getElementById("profileForm").onsubmit = e => {
    e.preventDefault();
    submitForm(e.target);
};

document.getElementById("passwordForm").onsubmit = e => {
    e.preventDefault();
    submitForm(e.target);
};

document.getElementById("photoInput").onchange = async function () {
    const fd = new FormData();
    fd.append("action", "upload_photo");
    fd.append("photo", this.files[0]);

    const res = await fetch("", { method: "POST", body: fd });
    const data = await res.json();

    if (data.success) location.reload();
};

async function deleteAccount() {
    if (!confirm("Delete account permanently?")) return;

    const fd = new FormData();
    fd.append("action", "delete_account");

    const res = await fetch("", { method: "POST", body: fd });
    const data = await res.json();

    if (data.success) location = "login.php";
}
</script>
</body>
</html>