<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}

require_once "../db.php";

/* Get all owners */
$sql = "
    SELECT 
        user_id,
        name,
        email,
        phone,
        verification_status,
        created_at
    FROM users
    WHERE role = 'owner'
    ORDER BY user_id DESC
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Database Error: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>View Owners</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 0;
        }

        .box {
            width: 95%;
            max-width: 1200px;
            margin: 40px auto;
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12);
        }

        h2 {
            text-align: center;
            margin-bottom: 25px;
            color: #333;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #1560BD;
            color: white;
            padding: 12px;
            text-align: left;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .verified {
            color: #198754;
            font-weight: bold;
        }

        .unverified {
            color: #dc3545;
            font-weight: bold;
        }

        .view-btn {
            display: inline-block;
            padding: 8px 14px;
            background: #1560BD;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .view-btn:hover {
            background: #0d4f9c;
        }

        .back {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #555;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .back:hover {
            background: #333;
        }
    </style>

</head>

<body>

    <div class="box">

        <h2>All Owners</h2>

        <div class="table-container">

            <table>

                <thead>

                    <tr>
                        <th>Owner ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Verification</th>
                        <th>Registered Date</th>
                        <th>Action</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if (mysqli_num_rows($result) > 0): ?>

                        <?php while ($owner = mysqli_fetch_assoc($result)): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($owner['user_id']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($owner['name']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($owner['email']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($owner['phone']) ?>
                                </td>

                                <td>

                                    <?php if ($owner['verification_status'] == "verified"): ?>

                                        <span class="verified">
                                            Verified
                                        </span>

                                    <?php else: ?>

                                        <span class="unverified">
                                            Unverified
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>
                                    <?= htmlspecialchars($owner['created_at']) ?>
                                </td>

                                <td>
                                    <a
                                        href="view_owner.php?id=<?= $owner['user_id'] ?>"
                                        class="view-btn">
                                        View Details
                                    </a>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="7" style="text-align:center;">
                                No owners found.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <a href="dashboard.php" class="back">
            Back
        </a>

    </div>

</body>

</html>