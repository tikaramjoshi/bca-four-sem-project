<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashbord</title>
    <style>
        * {
            padding: 0;
            margin: 0;
            box-sizing: border-box;
        }
        body{
            font-family: Arial, Helvetica, sans-serif;
            background-color: #f4f7f6;
            min-height: 100vh;
            flex-direction: column;
        }
        .main {
            background-color: #1560bd;
            padding: 15px 25px;
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            color: #fff;
        }

        nav a {
            text-decoration: none;
            color: black;
            background-color: rgb(165, 154, 239);
            padding: 8px 15px;
            font-weight: bold;
            border-radius: 4px;
            margin: 0 8px;
        }

        nav a:hover,
        nav a.active:hover,
        button:hover,
        .first button:hover {
            color: white;
            background-color: rgb(15, 160, 112);
        }

        button {
            background-color: #ffc107;
            border: none;
            padding: 8px 15px;
            border-radius: 15px;
            font-weight: bold;
            margin-left: 10px;
        }

        nav a.active {
            background-color: rgb(17, 127, 134);
            color: white;
        }

        .datetime{
            width: 130px;
            height: 130px;
            margin: 20px 0 0 40px;
            color: #1560BD;
            background-color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            border-radius: 50%;
            border: 5px solid #1560BD;
            text-align: center;
            box-shadow: 0 0 10px rgba(0, 0, 0, .2);
        }

        .datetime h4{
            font-size: 14px;
            margin-top: 8px;
        }

        .datetime p{
            margin: 0;
            font-size: 22px;
        }
        .first {
            background-color: #f2f2f2;
            width: 350px;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgb(0, 0, 0, .2);
            margin: -10px auto 0;
        }

        .first input {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid#ccc;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }

        .first button {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 5px;
            background: #1560bd;
            color: white;
            font-size: 16px;
        }

        .second {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            margin: 100px auto 50px;
            width: 95%;
            background-color: #ebebe6;
            padding: 20px;
            border-radius: 10px;
        }

        .second h1 {
            width: 100%;
            text-align: center;
            margin-bottom: 20px;
        }

        .route {
            height: 320px;
            width: 220px;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            text-align: center;
            box-shadow: 0 0 10px rgb(0, 0, 0, .2);
        }

        .route img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }

        .route h4 {
            margin: 10px 0;
            font-size: 18px;
        }

        .route p {
            margin: 8px 0 15px;
            font-size: 15px;
        }

        .route button {
            width: 80%;
            margin: 10px 0;
            padding: 10px;
            background-color: #1560BD;
            color: white;
            border-radius: 5px;
            font-weight: bold;
        }

        .route button:hover {
            background-color: rgb(15, 160, 112);
        }

        .last {
            background-color: #1560BD;
            color: #fff;
            padding: 30px 40px 10px;
        }

        .last-main {
            display: flex;
            justify-content: space-around;
            align-items: flex-start;
            gap: 60px;
            width: 100%;
        }

        .last-link,
        .last-contact,
        .link-about {
            width: 280px;
        }

        .link h3 {
            font-size: 24px;
            margin-bottom: 15px;
            border-bottom: 2px solid #ffc107;
            padding-bottom: 8px;
        }

        .last a {
            color: #fff;
            text-decoration:underline ;
            display: block;
            margin: 8px 0;
            transition: .3s;
        }

        .last a:hover {
            color: #ffc107;
            padding-left: 5px;
        }

        .last ul li {
            margin: 8px 0;
        }

        .last-mission {
            width: 90%;
            margin-top: 25px;
            text-align: center;
        }

        .last-mission h3 {
            margin-bottom: 10px;
        }

        .last hr {
            width: 100%;
            margin: 10px auto 20px;
            border: none;
            border: 1px solid rgb(255, 255, 255, .4);
        }

        .copy {
            width: 100%;
            text-align: center;
            font-size: 16px;
            color: #eee;
        }

        html {
            scroll-behavior: smooth;
        }
    </style>
</head>

<body>
    <div class="main">
        <nav>
            <div>
                <a href="#" id="home" class="active">Home</a>
                <a href="#contactSection" id="contact"> Contact</a>
                <a href="#aboutSection" id="about">About</a>
            </div>
            <div>
                <button type="button" id="login">Login</button>
                <button type="button" id="register" onclick="location.href='register.php'">Register</button>
            </div>
        </nav>
    </div>

     <div class="datetime">
        <p id="time"> </p>
        <h4 id="today"> </h4>
    </div> 

    <div class="first">
        <input type="text" id="form" list="formList" placeholder="Form" required> <datalist id="formList"></datalist>
        <input type="text" id="to" list="toList" placeholder="To" required>
        <datalist id="toList"></datalist>
        <input type="date" id="date" min="<?php echo date('Y-m-d'); ?>" 
        max="<?php echo date('Y-m-d', strtotime('+7 days'));?>" required>
        <button type="button" id="search">Search Bus</button>
    </div>
    <div class="second" id="box">
        <h1>Popular Route</h1>
        <div class="route">
            <img src="Bus Image/b1.jpg" alt="">
            <h4><span>To:</span><span>From:</span></h4>
            <p>Price: Rs.</p>
            <button> Booking </button>
        </div>
    </div>
    <footer class="last">
        <div class="last-main">
            <div class="last-link">
                <h3>Quick Link</h3>
                <a href="#">Home</a>
                <a href="#">Gallery</a>
                <a href="#">Policy</a>
                <a href="#">Login</a>
                <a href="register.php?role=passenger">Register Passenger</a>
                <a href="register.php?role=owner">Register Owner</a>
                <a href="register.php?role=driver">Register Driver</a>
            </div>
            <div class="last-contact" id="contactSection">
                <h3>Contact</h3>
                <p>Email:<a href="#" id="mail">tikaramj519@gmail.com</a></p>
                <p>Phone:<a href="#" id="call">+9779840792553</a></p>
                <p>Whatsapp:<a href="#" id="what">+9779840792553</a></p>
            </div>
            <div class="last-about" id="aboutSection">
                <h3>About</h3>
                <ul>
                    <li>Online Bus Ticket Booking System</li>
                    <li>Easy Bus Search</li>
                    <li>24/7 Customer Support</li>
                    <li>Safe Online Booking</li>
                    <li>No Cancel Ticket</li>
                </ul>
            </div>
        </div>
        <div class="last-mission">
            <h3>Our Mission</h3>
            <p>Our mission is to make bus ticket booking quick, safe and convenient for every passenger by providing reliable online services.</p>
        </div>
        <hr>
        <div class="copy">
            <p>&copy;2026 Online Bus Ticket Booking System | All rights reserved.</p>
        </div>
    </footer>
</body>
<script>
let menu = document.querySelectorAll("nav a");

// let home = document.getElementById("home");
// let contact = document.getElementById("contact");
// let about = document.getElementById("about");

// window.addEventListener("scroll", function(){
//     let scroll = window.scrollY;
//     menu.forEach(function(item){
//         item.classList.remove("active");
//     });
//     if(scroll < 300){
//         home.classList.add("active");
//     }
//     else if(scroll < 700){
//         contact.classList.add("active");
//     }
//     else{
//         about.classList.add("active");
//     }
// });


menu.forEach(link =>{
    link.onclick =() => {
        menu.forEach(item => item.classList.remove("active"));
        link.classList.add("active");
    };
});

let time = document.getElementById("time");
let today = document.getElementById("today");

function clock(){
    let now = new Date();
    time.innerHTML = now.toLocaleTimeString();
    today.innerHTML = now.toDateString();
}
clock();
setInterval(clock,1000);
</script>
</html>