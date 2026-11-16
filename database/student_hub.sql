CREATE DATABASE IF NOT EXISTS student_hub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE student_hub;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  course VARCHAR(100),
  role ENUM('student','admin') NOT NULL DEFAULT 'student',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS resources (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  subject VARCHAR(100) NOT NULL,
  description TEXT,
  file_name VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  downloads INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_subject (subject),
  INDEX idx_status (status)
);

CREATE USER IF NOT EXISTS 'hub_user'@'localhost' IDENTIFIED BY 'hub_pass_123';
CREATE USER IF NOT EXISTS 'hub_user'@'127.0.0.1' IDENTIFIED BY 'hub_pass_123';
GRANT ALL PRIVILEGES ON student_hub.* TO 'hub_user'@'localhost';
GRANT ALL PRIVILEGES ON student_hub.* TO 'hub_user'@'127.0.0.1';
FLUSH PRIVILEGES;
