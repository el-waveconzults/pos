<?php
require_once 'config/config.php';
$conn = getDB();

$currentUser = getCurrentUser();
$userId = $currentUser['id'];
$success = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = sanitize($_POST['name']);
        $phone = sanitize($_POST['phone'] ?? '');

        $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $phone, $userId);
        if ($stmt->execute()) {
            $_SESSION['user_name'] = $name;
            $success = "Profile updated successfully!";
        }
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (!password_verify($currentPassword, $user['password'])) {
            $error = "Current password is incorrect!";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "New passwords do not match!";
        } elseif (strlen($newPassword) < 6) {
            $error = "Password must be at least 6 characters!";
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $newHash, $userId);
            $stmt->execute();
            $success = "Password changed successfully!";
        }
    }
}

// Refresh user data
$currentUser = getCurrentUser();
?>

<div class="row">
    <div class="col-md-4">
        <!-- Profile Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user"></i> My Profile</h5>
            </div>
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="fas fa-user-circle fa-5x text-primary"></i>
                </div>
                <h5><?= $currentUser['name'] ?></h5>
                <p class="text-muted"><?= $currentUser['email'] ?></p>
                <span class="badge bg-<?= $currentUser['role'] == 'owner' ? 'dark' : ($currentUser['role'] == 'admin' ? 'danger' : ($currentUser['role'] == 'manager' ? 'warning' : 'info')) ?>">
                    <?= ucfirst($currentUser['role']) ?>
                </span>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <!-- Edit Profile -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-edit"></i> Edit Profile</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?= $currentUser['name'] ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" value="<?= $currentUser['email'] ?>" disabled>
                        <small class="text-muted">Email cannot be changed</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= $currentUser['phone'] ?? '' ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <input type="text" class="form-control" value="<?= ucfirst($currentUser['role']) ?>" disabled>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>

        <!-- Change Password -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-lock"></i> Change Password</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>