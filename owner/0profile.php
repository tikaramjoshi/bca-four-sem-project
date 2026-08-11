<?php
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="owner"){
    header("Location: ../login.php");
    exit();
}

require_once "../db.php";

$owner_id = $_SESSION['user_id'];


$stmt = $pdo->prepare("
SELECT 
u.name,
u.email,
u.phone,
ov.owner_photo,
ov.status

FROM users u

LEFT JOIN owner_verification ov

ON u.user_id = ov.owner_id

WHERE u.user_id=?
");


$stmt->execute([$owner_id]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

?>


<!DOCTYPE html>
<html>
<head>

<title>Owner Profile</title>

<style>

.profile-box{
    width:350px;
    margin:50px auto;
    text-align:center;
    padding:30px;
    border-radius:15px;
    box-shadow:0 0 10px gray;
}


.profile-img{

width:120px;
height:120px;
border-radius:50%;
object-fit:cover;
border:3px solid #1769c2;

}


.verified{

background:#28a745;
color:white;
padding:8px 15px;
border-radius:20px;

}


.unverified{

background:red;
color:white;
padding:8px 15px;
border-radius:20px;

}

</style>

</head>


<body>


<div class="profile-box">


<?php if(!empty($user['owner_photo'])){ ?>

<img src="../uploads/<?php echo $user['owner_photo']; ?>"
class="profile-img">


<?php }else{ ?>


<img src="../uploads/default.png"
class="profile-img">


<?php } ?>


<h2>
<?php echo $user['name']; ?>
</h2>


<p>
Email:
<?php echo $user['email']; ?>
</p>


<p>
Phone:
<?php echo $user['phone']; ?>
</p>



<?php

if($user['status']=="verified"){

?>

<span class="verified">
✔ Verified
</span>


<?php

}else{

?>


<span class="unverified">
✖ Unverified
</span>


<?php

}

?>


</div>


</body>
</html>