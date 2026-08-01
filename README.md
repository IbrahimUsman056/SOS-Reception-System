<div align="center">

# 📦 SOS Reception

A modern **Reception Management System** built with **PHP, MySQL, PDO, JavaScript, Bootstrap, and Chart.js** to digitize package reception, dispatch, and visitor/package tracking for organizations.

Designed to replace traditional paper logbooks with a secure, role-based, and analytics-driven solution.

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3?style=for-the-badge&logo=bootstrap)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6-F7DF1E?style=for-the-badge&logo=javascript)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

</div>

---

# 📸 Screenshots

## Dashboard

![Dashboard](screenshots/dashboard.png)

---

## Login

![Login](screenshots/login.png)

---

## Reception Records

![Records](screenshots/records.png)

---

## Add New Package

![Add Package](screenshots/add-package.png)

---

## Package Details

![Details](screenshots/details.png)

---

## Reports & Analytics

![Reports](screenshots/reports.png)

---

## QR / Barcode Scanner

![Scanner](screenshots/scanner.png)

---

## Notifications

![Notifications](screenshots/notifications.png)

---

# ✨ Features

### 📦 Package Management

- Add incoming & outgoing packages
- Track package status
- Receiving & Dispatch workflow
- Tracking numbers
- Weight & dimensions
- Priority levels
- Attachments
- Digital signature

---

### 👥 Role-Based Access Control

Three user roles:

- 👑 Admin
- 🧑‍💼 Manager
- 🧑‍💻 Receptionist

Each role has its own permissions for viewing, editing, exporting, and administration.

---

### 📊 Dashboard

- Total Packages
- Pending Packages
- Delivered Packages
- Returned Packages
- Recent Activity
- Quick Statistics

---

### 📈 Reports

- Daily Reports
- Weekly Reports
- Monthly Reports
- Building Statistics
- Delivery Time Analytics
- Chart.js Visualizations

---

### 🔍 Advanced Search

Filter packages by:

- Date
- Employee
- Building
- Package Type
- Status
- Priority

---

### 🔔 Notifications

- Package Arrival
- Pending Pickup Reminder
- In-app Notifications

---

### 📱 QR / Barcode

- Barcode Generation
- Camera Scanner
- Instant Package Lookup

---

### 👤 User Management

Admin can:

- Create Users
- Edit Users
- Disable Accounts
- Change Roles
- View Activity Logs

---

### 🔒 Security

- PDO Prepared Statements
- Password Hashing
- Session Protection
- CSRF Protection
- XSS Prevention
- Secure Authentication
- Activity Logging

---

# 🛠 Tech Stack

| Technology | Usage |
|------------|-------|
| PHP | Backend |
| MySQL | Database |
| PDO | Database Layer |
| Bootstrap 5 | UI |
| JavaScript | Frontend |
| Chart.js | Analytics |
| HTML5/CSS3 | Interface |
| PHPMailer | Email Notifications |
| html5-qrcode | QR Scanner |

---

# 📂 Project Structure

```
project/
│
├── assets/
├── config/
├── database/
├── includes/
├── uploads/
├── views/
├── auth/
├── reports/
├── notifications/
├── dashboard/
├── index.php
└── README.md
```

---

# 🚀 Installation

```bash
git clone https://github.com/yourusername/sos-reception.git
```

Import the SQL database.

Update:

```
config/database.php
```

Start your local server:

```
http://localhost/sos-reception
```

---

# 🔑 Demo Accounts

| Role | Email |
|-------|--------|
| Admin | demo.admin@example.com |
| Manager | demo.manager@example.com |
| Receptionist | demo.reception@example.com |

Password

```
Demo@1234
```

---

# 🔄 Workflow

```
Package Arrives
        │
        ▼
Receptionist Creates Record
        │
        ▼
Barcode Generated
        │
        ▼
Notification Sent
        │
        ▼
Manager Monitors
        │
        ▼
Employee Picks Up
        │
        ▼
Signature Captured
        │
        ▼
Status Updated
        │
        ▼
Analytics & Reports
```

---

# 🎯 Future Improvements

- SMS Notifications
- Cloud Storage
- Visitor Management
- Multi-Branch Support
- Mobile App
- API Integration
- OCR Package Detection
- AI Analytics

---

# 🤝 Contributing

Contributions, issues, and feature requests are welcome.

Feel free to fork the repository and submit a Pull Request.

---

# 📄 License

This project is licensed under the MIT License.

---

<div align="center">

Made with ❤️ during Internship

</div>
