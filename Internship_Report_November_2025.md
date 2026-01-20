# Internship Monthly Progress Report
**Month:** November 2025  
**Project:** e-HRMS (Electronic Human Resource Management System)  
**Role:** Full Stack Web Developer Intern  
**Supervisor/Mentor:** [Mentor Name]  

---

## 1. Executive Summary
During the month of November, the primary phase of the e-HRMS project was initiated with the goal of establishing a robust and scalable architecture. This month allowed me to transition from initial requirement gathering to the practical implementation of the system's core. The main focus was on setting up the Model-View-Controller (MVC) development environment, creating a normalized relational database schema in MySQL, and implementing the fundamental modules for secure authentication and employee data management.

By the end of November, a functional prototype was successfully deployed locally. This prototype firmly handles user sessions, role-based login redirection, and complete CRUD (Create, Read, Update, Delete) operations for the employee directory. These foundational blocks are critical for supporting the advanced payroll and RBAC features scheduled for the next phase.

---

## 2. Key Accomplishments

### 2.1 System Architecture & Environment Setup
The initial week was dedicated to configuring the development environment and defining the project structure. I chose **XAMPP** as the local server solution due to its integrated Apache and MySQL stack.
*   **Environment Configuration:** Installed and configured XAMPP with PHP 8.2 and MySQL/MariaDB 10.4. Enabled necessary PHP extensions like `pdo_mysql` and `mbstring`.
*   **MVC Implementation:** I organized the codebase into a strict **Model-View-Controller (MVC)** pattern. This architectural decision ensures improved code maintainability and separation of concerns:
    *   **Models (`app/Models/`):** Handle direct database interactions and data logic.
    *   **Controllers (`app/Controllers/`):** Process user input and return the appropriate views.
    *   **Views (`public/`):** Client-facing HTML/PHP files presented to the user.

**Project Directory Structure:**
```text
payslip_generator/
├── app/
│   ├── Config/          # Database configuration (Connection classes)
│   ├── Controllers/     # Logic handlers (Login, Employee Management)
│   └── Models/          # Data interaction classes (User, Employee)
├── public/              # Client-facing files
│   ├── admin/           # Administrative interfaces
│   ├── employee/        # Employee self-service dashboard
│   ├── auth/            # Authentication pages (Login/Register)
│   └── assets/          # Static assets (CSS, JS, Images)
├── database/            # SQL setup scripts and migration files
└── index.php            # Application entry point
```

**Code Snippet: Database Connection (`app/Config/database.php`)**
The following function was implemented using **PDO (PHP Data Objects)** to ensure secure, injection-proof database connections:
```php
function getDBConnection() {
    $host = 'localhost';
    $db_name = 'payslip_generator';
    $username = 'root';
    $password = '';
    
    try {
        $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $conn;
    } catch(PDOException $e) {
        // Log error securely instead of exposing specifics to user
        error_log("Connection failed: " . $e->getMessage());
        die("Database connection error. Please contact admin.");
    }
}
```

### 2.2 Database Design & Implementation
A normalized database schema was designed to verify data integrity and reduce redundancy.
*   **Schema Creation:** I designed the Entity-Relationship (ER) diagram and implemented it using MySQL.
*   **Core Tables Implemented:**
    *   **`users`**: This table manages authentication. It stores the `username`, `email`, and the encrypted `password`. It links to the `employees` table via a foreign key `employee_id` to associate login credentials with HR records.
    *   **`employees`**: The central repository for staff data, including `first_name`, `last_name`, `designation`, `basic_salary`, and `employment_type`.
    *   **`roles` & `user_roles`**: Prepared the schema for future expansion into Role-Based Access Control (RBAC) by creating a many-to-many relationship between users and defined roles (Administrator, Accountant, Employee).

### 2.3 Secure Authentication Module
Security was a top priority during the implementation of the login system.
*   **Secure Login Mechanism:** Developed `login_api.php` to process form data securely. I replaced deprecated MD5 hashing with PHP's modern `password_hash()` and `password_verify()` functions using the BCrypt algorithm.
*   **Session Management:** Implemented PHP sessions to track logged-in users. This includes session regeneration to prevent session fixation attacks.
*   **Output Sanitization:** Used `htmlspecialchars()` for all user-generated content to prevent Cross-Site Scripting (XSS) attacks.

**Code Snippet: Secure Password Verification Logic**
```php
// Verifying user credentials securely
if ($user && password_verify($password, $user['password'])) {
    // Regenerate session ID to prevent fixation
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['role'] = $user['role_name']; // Helper to fetch primary role
    $_SESSION['logged_in'] = true;
    
    // Redirect logic based on assigned role
    $redirectPath = ($user['role_name'] === 'admin') ? '../admin/dashboard.php' : '../employee/dashboard.php';
    header("Location: " . $redirectPath);
    exit;
} else {
    $error = "Invalid username or password provided."; // Generic error message for security
}
```

### 2.4 Core Employee Management Module
The Administrator panel was populated with the first set of functional features.
*   **Employee Directory:** Created `employees.php`, a dynamic data table that fetches and lists all registered staff members.
*   **Onboarding System:** Implemented `add_employee.php`, a form interface that allows admins to create new employee records. This includes validation for required fields like Email and Phone Number.
*   **Profile Management:** Developed `edit_employee.php`, allowing for the modification of existing records, such as updating salary details or changing designations.

---

## 3. Technology Stack Used

| Component | Technology | Description |
| :--- | :--- | :--- |
| **Server Environment** | **XAMPP / Apache** | Local development server stack. |
| **Backend Language** | **PHP 8.2** | Server-side scripting, MVC logic, and Session handling. |
| **Database** | **MySQL / MariaDB** | Relational Database Management System (RDBMS). |
| **Database Driver** | **PDO** | PHP Data Objects for secure SQL interactions. |
| **Frontend** | **HTML5 & CSS3** | Structure and Design (using Vanilla CSS for custom styling). |
| **Scripting** | **JavaScript (ES6)** | Client-side validation and dynamic DOM manipulation. |
| **Version Control** | **Git & GitHub** | Source code management and tracking changes. |
| **IDE** | **Visual Studio Code** | Primary code editor with PHP extensions. |

---

## 4. Challenges Faced & Solutions

### Challenge 1: Modular Database Design
**Issue:** Designing a database that allows a single user to perform multiple roles (e.g., an "Accountant" who is also an "Employee" needing to view their own payslip) was complex.
**Solution:** I decoupled the `users` table from the `roles` table. Instead of a simple `role` column in the user table, I implemented a `user_roles` pivot table. This allows for a flexible Many-to-Many relationship, ensuring the system can scale if one user assumes multiple responsibilities in the future.

### Challenge 2: Secure Session Handling
**Issue:** Initial tests showed that session data persisted incorrectly when navigating back in the browser after logout.
**Solution:** I implemented a strict `logout.php` script that not only destroys the session (`session_destroy()`) but also unsets the session cookie (`setcookie()`) and invalidates the session array (`$_SESSION = []`). Additionally, I added cache-control headers to prevent browsers from caching sensitive internal pages.

---

## 5. Plan for Next Month (December)
Building on this month's foundation, the plan for December focuses on advanced functionality and reporting.
*   **Advanced RBAC:** Implement fine-grained permission checks (Middleware) to ensure non-admins cannot access sensitive URLs.
*   **Payroll Processing:** Develop the logic to auto-calculate Gross and Net Salary based on basic pay and defined allowances (HRA, DA).
*   **PDF Generation:** Integrate a library (like PHPMailer or TCPDF) to generate downloadable PDF payslips for employees.
*   **UI Overhaul:** Transition from the current basic layout to a modern "Glassmorphism" design system using advanced CSS3.

---

## 6. Conclusion
By the end of November, the e-HRMS project has successfully transitioned from a conceptual design to a functional prototype. The robust database schema works as expected, and the secure authentication system establishes a safe environment for future feature development. The Core Employee Management module is fully operational, providing a solid foundation for the complex payroll and RBAC features planned for December. This month's progress ensures we are on track to deliver a comprehensive HR solution.
