
<?php
/*
 * Author: Sam Vossen 2026.11.10
 * Filename: adminDashboard.php
 *
 * This page is the administrator dashboard.
 *
 * Responsibilities:
 * - Restrict access to administrators.
 * - Provide an overview of administrative tools.
 * - Serve as the starting point for future admin functionality.
 */

require_once 'includes/auth/require_login.php';
require_once 'includes/lightboard/core/database.php';

$pdo = db();

$stmt = $pdo->prepare("
    SELECT roleID
    FROM users
    WHERE userID = ?
    LIMIT 1
");
$stmt->execute(array((int)$_SESSION['userID']));

$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Only administrators can access this page.
if (!$user || (int)$user['roleID'] !== 1) {
    header('Location: display.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | Lightboard</title>

    <link rel="stylesheet" href="includes/css/style.css?v=2">

    <style>
        .admin-page {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 24px;
        }

        .admin-header {
            margin-bottom: 35px;
        }

        .admin-header h1 {
            color: var(--color-primary);
            font-size: 2.3rem;
            margin-bottom: 8px;
        }

        .admin-header p {
            color: #666;
            font-size: 1.05rem;
        }

        .admin-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .admin-card {
            background: #fff;
            border: 1px solid #e7e7e7;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,.04);
        }

        .admin-card h2 {
            margin: 0 0 10px;
            color: var(--color-primary);
            font-size: 1.3rem;
        }

        .admin-card p {
            color: #666;
            margin: 0 0 18px;
            line-height: 1.5;
        }

        .admin-card a {
            display: inline-block;
            padding: 9px 16px;
            background: #333;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
        }

        .admin-card a:hover {
            background: #555;
        }

        .admin-placeholder {
            color: #999;
            font-size: .85rem;
            font-style: italic;
        }

        @media (max-width: 700px) {
            .admin-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<?php include 'includes/layout/navbar.php'; ?>

<main class="admin-page">

    <section class="admin-header">
        <h1>Admin Dashboard</h1>
        <p>Welcome to the Lightboard administration panel.</p>
    </section>

    <section class="admin-grid">

        <div class="admin-card">
            <h2>Preset Displays</h2>
            <p>Manage the preset images available to Lightboard users.</p>
            <a href="display.php">View Displays</a>
        </div>

        <div class="admin-card">
            <h2>Upload Presets</h2>
            <p>Add new preset images for users to access.</p>
            <a href="upload.php">Manage Uploads</a>
        </div>

        <div class="admin-card">
            <h2>User Management</h2>
            <p>Future area for viewing and managing registered users.</p>
            <span class="admin-placeholder">Coming soon</span>
        </div>

        <div class="admin-card">
            <h2>System Management</h2>
            <p>Future area for Lightboard system settings and monitoring.</p>
            <span class="admin-placeholder">Coming soon</span>
        </div>

    </section>

</main>

</body>
</html>
