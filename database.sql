-- قاعدة بيانات نظام إدارة الموظفين
-- Employee Management System Database

-- إنشاء قاعدة البيانات
CREATE DATABASE IF NOT EXISTS employees_management;
USE employees_management;

-- جدول الأدوار (roles)
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    permissions JSON
);

-- جدول الأقسام (departments)
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    manager_id INT
);

-- جدول المناصب (positions)
CREATE TABLE IF NOT EXISTS positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    min_salary DECIMAL(10,2),
    max_salary DECIMAL(10,2)
);

-- جدول الموظفين (employees)
CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    national_id VARCHAR(20) UNIQUE,
    birth_date DATE,
    hire_date DATE,
    department_id INT,
    position_id INT,
    salary DECIMAL(10,2) DEFAULT 0.00,
    residency_number VARCHAR(50) NULL ,
    basic_salary DECIMAL(10,2) DEFAULT 0.00,
    housing_allowance DECIMAL(10,2) DEFAULT 0.00,
    transportation_allowance DECIMAL(10,2) DEFAULT 0.00,
    other_allowances DECIMAL(10,2) DEFAULT 0.00,
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    status ENUM('active', 'leave', 'terminated') DEFAULT 'active',
    profile_picture VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- جدول المستخدمين (users)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    employee_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- جدول الحضور والانصراف (attendance)
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    check_in TIME,
    check_out TIME,
    date DATE NOT NULL,
    status ENUM('present', 'absent', 'leave') DEFAULT 'present',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_employee_date (employee_id, date)
);

-- جدول الإجازات (leaves)
CREATE TABLE IF NOT EXISTS leaves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days INT NOT NULL,
    status ENUM('approved', 'rejected', 'pending') DEFAULT 'pending',
    approved_by INT,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- جدول العهد (assets)
CREATE TABLE IF NOT EXISTS assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    serial_number VARCHAR(100) UNIQUE,
    purchase_date DATE,
    status ENUM('available', 'in_use', 'maintenance', 'obsolete') DEFAULT 'available'
);

-- جدول تخصيص العهد (asset_assignments)
CREATE TABLE IF NOT EXISTS asset_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    employee_id INT NOT NULL,
    assigned_date DATE NOT NULL,
    return_date DATE,
    status ENUM('assigned', 'returned') DEFAULT 'assigned',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- جدول الملاحظات (notes)
CREATE TABLE IF NOT EXISTS notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    author_id INT NOT NULL,
    content TEXT NOT NULL,
    type ENUM('positive', 'negative', 'warning') DEFAULT 'positive',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- جدول الملفات المرفقة (attachments)
CREATE TABLE IF NOT EXISTS attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    type VARCHAR(100),
    path VARCHAR(255) NOT NULL,
    description TEXT,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- إضافة القيود بعد إنشاء الجداول
ALTER TABLE departments ADD CONSTRAINT fk_departments_manager FOREIGN KEY (manager_id) REFERENCES employees(id);

ALTER TABLE employees ADD CONSTRAINT fk_employees_department FOREIGN KEY (department_id) REFERENCES departments(id);
ALTER TABLE employees ADD CONSTRAINT fk_employees_position FOREIGN KEY (position_id) REFERENCES positions(id);

ALTER TABLE users ADD CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id);
ALTER TABLE users ADD CONSTRAINT fk_users_employee FOREIGN KEY (employee_id) REFERENCES employees(id);

ALTER TABLE attendance ADD CONSTRAINT fk_attendance_employee FOREIGN KEY (employee_id) REFERENCES employees(id);

ALTER TABLE leaves ADD CONSTRAINT fk_leaves_employee FOREIGN KEY (employee_id) REFERENCES employees(id);
ALTER TABLE leaves ADD CONSTRAINT fk_leaves_approved_by FOREIGN KEY (approved_by) REFERENCES employees(id);

ALTER TABLE asset_assignments ADD CONSTRAINT fk_asset_assignments_asset FOREIGN KEY (asset_id) REFERENCES assets(id);
ALTER TABLE asset_assignments ADD CONSTRAINT fk_asset_assignments_employee FOREIGN KEY (employee_id) REFERENCES employees(id);

ALTER TABLE notes ADD CONSTRAINT fk_notes_employee FOREIGN KEY (employee_id) REFERENCES employees(id);
ALTER TABLE notes ADD CONSTRAINT fk_notes_author FOREIGN KEY (author_id) REFERENCES employees(id);

ALTER TABLE attachments ADD CONSTRAINT fk_attachments_employee FOREIGN KEY (employee_id) REFERENCES employees(id);
ALTER TABLE attachments ADD CONSTRAINT fk_attachments_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES employees(id);

-- إدخال الأدوار الأساسية
INSERT INTO roles (id, name, permissions) VALUES
(1, 'admin', '{"all": true}'),
(2, 'employee', '{"profile": true, "attendance_own": true, "leaves_own": true, "assets_own": true, "notes_own": true}');

-- إدخال قسم مدير النظام
INSERT INTO departments (id, name) VALUES
(1, 'إدارة النظام');

-- إدخال منصب مدير النظام
INSERT INTO positions (id, title, description) VALUES
(1, 'مدير النظام', 'مدير نظام إدارة الموظفين');

-- إدخال موظف مدير النظام
INSERT INTO employees (id, first_name, last_name, national_id, hire_date, department_id, position_id, email, status) VALUES
(1, 'مدير', 'النظام', '1111111111', CURDATE(), 1, 1, 'admin@company.com', 'active');

-- إدخال حساب مدير النظام
INSERT INTO users (username, email, password, role_id, employee_id) VALUES
('admin', 'admin@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1);