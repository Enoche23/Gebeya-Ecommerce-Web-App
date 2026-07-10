# Gebeya E-Commerce Platform

Gebeya is a secure, full-stack, multi-role e-commerce application built with native PHP and MySQL. It features a complete shopping cart system, seller inventory management, and an advanced security layer including Email-based Multi-Factor Authentication (MFA) and secure Password Resets.

## Features
- **Multi-Role System**: Buyers, Sellers, and Administrators.
- **Secure Authentication**: Bcrypt password hashing, session regeneration, and Email OTP MFA.
- **Password Recovery**: Cryptographically secure token-based password reset flow.
- **Store Capabilities**: Product listings, shopping cart, and order fulfillment.
- **Security Protections**: CSRF tokens, XSS sanitization, HTTP Security Headers (HSTS), and brute-force lockouts.

## Setup Instructions

### 1. Requirements
- PHP 8.0+
- MySQL / MariaDB
- Composer (for installing dependencies)

### 2. Installation
1. Clone this repository into your web server directory (e.g., `htdocs` for XAMPP).
2. Open your terminal in the project folder and run:
   ```bash
   composer install
   ```
   *This installs the PHPMailer dependency required for the MFA system.*

### 3. Database Setup
1. Create a new MySQL database named `gebeya_db`.
2. Import the database schema and sample data located at:
   `sql/schema.sql`

### 4. Environment Configuration
1. Rename the `.env.example` file to `.env`
2. Update your database credentials if they differ from the defaults.
3. To enable the Multi-Factor Authentication and Password Reset systems, you must provide real SMTP credentials (e.g., from Brevo, SendGrid, or Gmail) in the `.env` file:
   ```env
   SMTP_HOST=smtp-relay.brevo.com
   SMTP_PORT=587
   SMTP_USER=your_email@example.com
   SMTP_PASS=your_smtp_key
   ```

## Usage
Navigate to `http://localhost/gebeya/public/index.php` in your browser to start using the platform!
