Employee Management System

Introduction

The Employee Management System is a comprehensive web application designed to automate and simplify employee management and administrative operations within organizations. The system provides a user-friendly interface for managing employee information, tracking attendance, managing assigned assets, and handling other administrative tasks.

Key Features

- Employee information management (Add, Edit, Delete)
- Advanced attendance tracking system
- Employee assets and assigned property management
- Leave management system with electronic approvals
- Employee notes management
- Various reports and statistics
- Multi-level access control (Admin, Employee)
- Modern and responsive user interface

Technical Requirements

- PHP 7.4 or later
- MySQL 5.7 or later
- Apache or Nginx web server
- A modern web browser with HTML5 and CSS3 support

Installation and Setup

1. Clone the repository or copy the project files to your web server.
2. Create a new database in MySQL.
3. Import the "database.sql" file into the database.
4. Update the database connection settings in "config.php".
5. Make sure the "uploads" directory is writable (permission "755").
6. Log in using the following default credentials:
   - Username: "admin"
   - Password: "password"

Project Structure

EmployeesManagment/
├── assets/
│   └── css/
│       └── style.css
├── uploads/
├── config.php
├── database.sql
├── index.php
├── login.php
├── logout.php
├── profile.php
├── employees.php
├── departments.php
├── positions.php
├── attendance.php
├── assets.php
├── leaves.php
├── notes.php
├── reports.php
└── README.md

Main Files Description

- "config.php": Main configuration file and database connection.
- "database.sql": SQL file used to create the database structure.
- "index.php": Main page and dashboard.
- "login.php": Login page.
- "logout.php": Handles user logout.
- "profile.php": User profile page.
- "employees.php": Employee information management.
- "departments.php": Department management.
- "positions.php": Position management.
- "attendance.php": Attendance and check-in/check-out management.
- "assets.php": Employee asset management.
- "leaves.php": Leave management.
- "notes.php": Employee notes management.
- "reports.php": Displays system reports.

User Permissions

Administrator (Admin)

- Full access to the system
- Manage all employee information
- View and analyze attendance records
- Manage and track assigned assets
- Add notes to any employee
- Generate various reports

Employee

- View their own personal information
- Record check-in and check-out
- View their own attendance history
- View assets assigned to them
- Add personal notes
- Submit leave requests

Technical Support

For any inquiries or technical issues, please contact the technical support team.

License

This project is licensed under the MIT License.

Updates

New updates and features will be added regularly based on user feedback and requirements.