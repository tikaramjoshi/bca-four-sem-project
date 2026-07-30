# Online Bus Ticket Booking System (Western Nepal)

An **Online Bus Ticket Booking System** developed using **PHP, MySQL, JavaScript, HTML, CSS, and Bootstrap**. This system allows passengers to search routes, book bus tickets online, and enables administrators to manage buses, routes, schedules, bookings, and users.

---

## Features

### User Features
- User registration and login
- Search buses by route and date
- View available seats
- Book bus tickets online
- View booking history
- Download or print tickets
- Update user profile
- Change password

### Admin Features
- Secure admin login
- Dashboard with statistics
- Manage buses
- Manage routes
- Manage schedules
- Manage bus operators
- Manage users
- Manage bookings
- View reports
- Update booking status

---

## Technologies Used

| Technology | Purpose |
|------------|---------|
| PHP | Backend Development |
| MySQL | Database Management |
| JavaScript | Client-side Functionality |
| HTML5 | Page Structure |
| CSS3 | Styling |
| Bootstrap 5 | Responsive Design |
| AJAX | Dynamic Requests |


---

## Installation

### 1. Clone the Repository

```bash
git clone git@github.com:tikaramjoshi/bca-four-sem-project.git
```

### 2. Move the Project

Copy the project folder to:

**XAMPP**
```text
xampp/htdocs/
```

**WAMP**
```text
wamp/www/
```

### 3. Create the Database

Create a new MySQL database named:

```text
bus_ticket_booking
```

### 4. Import the Database

Import the provided SQL file using **phpMyAdmin**.

### 5. Configure the Database

Update your database connection settings.

```php
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "bus_ticket_booking";
```

### 6. Start the Server

Start **Apache** and **MySQL** from XAMPP or WAMP.

### 7. Run the Project

Open your browser and visit:

```text
http://localhost/sl_project_final/
```

---

## Project Modules

- Authentication
- User Management
- Bus Management
- Route Management
- Schedule Management
- Seat Management
- Ticket Booking
- Booking History
- Reports
- Admin Dashboard

---

## Future Improvements

- Online payment integration (eSewa, Khalti, Fonepay)
- QR code ticket generation
- Email notifications
- SMS notifications
- Mobile application
- Live bus tracking
- Ticket cancellation and refund system
- Multi-language support (English & Nepali)

---

## Contributing

Contributions are welcome!

1. Fork the repository.
2. Create a new branch.
3. Commit your changes.
4. Push your branch.
5. Open a Pull Request.

---

## License

This project is developed for educational purposes.

---

## Author

**Tikaram Joshi**

- GitHub: https://github.com/tikaramjoshi
- Email: tikaramjoshi883@gmail.com

---

## Project Goal

The goal of this project is to digitize the bus ticket booking process for the **Western Region of Nepal**. It enables passengers to search routes, check seat availability, and book tickets online while helping transport operators efficiently manage buses, routes, schedules, and bookings.