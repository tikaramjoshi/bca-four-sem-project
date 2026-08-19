 <?php
    session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
        header("Location: ../login.php");
        exit();
    }
    require_once "../db.php";

    $owner_id = (int)$_SESSION['user_id'];
    $message = "";
    $message_type = "";

    $stmt = $conn->prepare("SELECT user_id, name, email, phone, profile_image FROM users WHERE user_id=? AND role='owner' LIMIT 1");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $owner = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$owner) {
        session_destroy();
        header("Location: ../login.php");
        exit();
    }

    $profile_dir = "../uploads/profile/" . $owner_id . "/profile/";

    if (!is_dir($profile_dir)) {
        mkdir($profile_dir, 0777, true);
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($name === '' || $email === '' || $phone === '') {
            $message = "All fields are required.";
            $message_type = "error";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
            $message_type = "error";
        } else {
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email=? AND user_id!=? LIMIT 1");
            $stmt->bind_param("si", $email, $owner_id);
            $stmt->execute();
            $email_exists = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if ($email_exists) {
                $message = "Email address is already in use.";
                $message_type = "error";
            } else {
                $profile_image = $owner['profile_image'];

                if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    if ($_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                        $extension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));

                        if (!in_array($extension, $allowed)) {
                            $message = "Only JPG, JPEG, PNG and WEBP images are allowed.";
                            $message_type = "error";
                        } elseif ($_FILES['profile_image']['size'] > 2 * 1024 * 1024) {
                            $message = "Image size must be less than 2MB.";
                            $message_type = "error";
                        } else {
                            $new_name = "owner_" . $owner_id . "_" . time() . "." . $extension;
                            $new_path = $profile_dir . $new_name;

                            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $new_path)) {
                                $old_image = $owner['profile_image'];

                                if (!empty($old_image) && $old_image !== "default.png") {
                                    $old_path = $profile_dir . $old_image;

                                    if (file_exists($old_path)) {
                                        unlink($old_path);
                                    }
                                }

                                $profile_image = $new_name;
                            } else {
                                $message = "Failed to upload profile image.";
                                $message_type = "error";
                            }
                        }
                    } else {
                        $message = "Error uploading image.";
                        $message_type = "error";
                    }
                }

                if ($message === "") {
                    $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=?, profile_image=? WHERE user_id=? AND role='owner'");
                    $stmt->bind_param("ssssi", $name, $email, $phone, $profile_image, $owner_id);

                    if ($stmt->execute()) {
                        $owner['name'] = $name;
                        $owner['email'] = $email;
                        $owner['phone'] = $phone;
                        $owner['profile_image'] = $profile_image;

                        $message = "Profile updated successfully.";
                        $message_type = "success";
                    } else {
                        $message = "Failed to update profile.";
                        $message_type = "error";
                    }

                    $stmt->close();
                }
            }
        }
    }

    $profile_image = !empty($owner['profile_image']) ? $owner['profile_image'] : "default.png";
    ?>
 <!DOCTYPE html>
 <html lang="en">

 <head>
     <meta charset="UTF-8">
     <meta name="viewport" content="width=device-width,initial-scale=1.0">
     <title>Edit Owner Profile</title>
     <link rel="stylesheet" href="profile.css">
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
     <style>
         .edit-page {
             min-height: calc(100vh - 60px);
             padding: 40px 20px;
             background: #f5f7fa;
         }

         .edit-container {
             max-width: 600px;
             margin: auto;
             background: #fff;
             padding: 30px;
             border-radius: 12px;
         }

         .edit-title {
             text-align: center;
             margin-bottom: 25px;
         }

         .edit-title h1 {
             color: #1560bd;
             margin-bottom: 8px;
         }

         .profile-preview {
             text-align: center;
             margin-bottom: 25px;
         }

         .profile-preview img {
             width: 120px;
             height: 120px;
             border-radius: 50%;
             object-fit: cover;
             border: 4px solid #1560bd;
         }

         .form-group {
             margin-bottom: 18px;
         }

         .form-group label {
             display: block;
             font-weight: bold;
             margin-bottom: 7px;
         }

         .form-group input {
             width: 100%;
             padding: 12px;
             border: 1px solid #ccc;
             border-radius: 6px;
             font-size: 15px;
         }

         .save-btn {
             width: 100%;
             padding: 13px;
             border: none;
             border-radius: 6px;
             background: #1560bd;
             color: #fff;
             font-size: 16px;
             font-weight: bold;
             cursor: pointer;
         }

         .save-btn:hover {
             background: #0fa070;
         }

         .back-btn {
             display: block;
             text-align: center;
             margin-top: 15px;
             color: #1560bd;
             text-decoration: none;
             font-weight: bold;
         }

         .message {
             padding: 12px;
             border-radius: 6px;
             margin-bottom: 18px;
             text-align: center;
         }

         .success {
             background: #d4edda;
             color: #155724;
         }

         .error {
             background: #f8d7da;
             color: #721c24;
         }
     </style>
 </head>

 <body>
     <div class="main">
         <nav><a href="profile.php"> <i class="fa fa-home"> &nbsp;</i>Home</a></nav>
     </div>
     <div class="edit-page">
         <div class="edit-container">
             <div class="edit-title">
                 <h1>Edit Profile</h1>
                 <p>Update your owner account information</p>
             </div>

             <?php if ($message): ?>
                 <div class="message <?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div>
             <?php endif; ?>
             <form method="POST" enctype="multipart/form-data">
                 <div class="profile-preview">
                     <?php if (!empty($profile_image) && $profile_image !== "default.png"): ?>
                         <img src="../uploads/profile/<?= $owner_id ?>/profile/<?= htmlspecialchars($profile_image) ?>" alt="Profile">
                     <?php else: ?>
                         <img src="../uploads/default.png" alt="Profile">
                     <?php endif; ?>
                 </div>

                 <div class="form-group">
                     <label for="profile_image"><i class="fa fa-camera"></i> Profile Photo</label>
                     <input type="file" id="profile_image" name="profile_image" accept=".jpg,.jpeg,.png,.webp">
                 </div>
                 <div class="form-group">
                     <label for="name"><i class="fa fa-user"></i> Full Name</label>
                     <input type="text" id="name" name="name" value="<?= htmlspecialchars($owner['name']) ?>" required>
                 </div>
                 <div class="form-group">
                     <label for="email"><i class="fa fa-envelope"></i> Email Address</label>
                     <input type="email" id="email" name="email" value="<?= htmlspecialchars($owner['email']) ?>" required>
                 </div>
                 <div class="form-group">
                     <label for="phone"><i class="fa fa-phone"></i> Phone Number</label>
                     <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($owner['phone']) ?>" required>
                 </div>
                 <button type="submit" class="save-btn"><i class="fa fa-save"></i> Save Changes</button>
                 <a href="profile.php" class="back-btn">Cancel</a>
             </form>
         </div>
     </div>
     <footer class="footer">
         <p>&copy;2026 Online Bus Ticket Booking System || All rights reserved.</p>
     </footer>
 </body>

 </html>