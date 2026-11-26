# Employees Management System

## Introduction

**Employees Management System** is a complete web application designed to automate and simplify employee management and administrative tasks within organizations. The system provides a user-friendly interface for managing employee information, tracking attendance, managing assigned assets, handling leave requests, and performing other administrative operations.

## Key Features

- Employee data management (Add, Edit, Delete)
- Advanced attendance tracking system
- Employee assets and assigned property management
- Leave management system with electronic approvals
- Employee notes management
- Various reports and statistics
- Multi-level user permissions (Admin, Employee)
- Modern and responsive user interface

## Technical Requirements

- **PHP 7.4** or later
- **MySQL 5.7** or later
- **Apache** or **Nginx** web server
- A modern web browser with **HTML5** and **CSS3** support

## Installation and Setup

1. Clone the repository or copy the project files to your web server.
2. Create a new database in MySQL.
3. Import the `database.sql` file into the database.
4. Update the database connection settings in `config.php`.
5. Make sure the `uploads` directory is writable with permission `755`.
6. Log in using the default credentials:
   - **Username:** `admin`
   - **Password:** `password`

## Project Structure

```text
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
```

## Main Files Description

| File | Description |
| --- | --- |
| `config.php` | Main configuration file and database connection |
| `database.sql` | SQL file for creating the database structure |
| `index.php` | Main page and dashboard |
| `login.php` | Login page |
| `logout.php` | Handles user logout |
| `profile.php` | User profile page |
| `employees.php` | Employee data management |
| `departments.php` | Department management |
| `positions.php` | Position management |
| `attendance.php` | Attendance management |
| `assets.php` | Employee asset management |
| `leaves.php` | Leave management |
| `notes.php` | Employee notes management |
| `reports.php` | Reports and statistics |

## User Roles and Permissions

### Admin

The **Admin** has full access to the system, including:

- Full system access
- Managing all employee data
- Viewing and analyzing attendance records
- Managing and tracking employee assets
- Adding notes to any employee
- Generating various reports

### Employee

The **Employee** has access to the following features:

- View their personal information
- Record attendance and check-out
- View their own attendance records
- View assets assigned to them
- Add personal notes
- Submit leave requests

## Technical Support

For any inquiries, technical issues, or support requests, please contact the technical support team.

## License

This project is licensed under the **MIT License**.

## Updates

New updates and features will be added periodically based on user feedback and project requirements.
