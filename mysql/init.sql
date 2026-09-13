-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS lamp_db;
USE lamp_db;

-- Create a sample table
CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    author VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert sample data
INSERT INTO posts (title, content, author) VALUES
('Welcome to LAMP Stack', 'This is your first post in the LAMP stack setup.', 'Admin'),
('Docker Compose Benefits', 'Using Docker Compose makes development environment setup incredibly easy.', 'Developer'),
('Database Connection Test', 'If you can see this, your MySQL connection is working perfectly!', 'System');

-- Create additional sample tables
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO categories (name, description) VALUES
('Technology', 'Posts about technology and programming'),
('Tutorial', 'Step-by-step guides and tutorials'),
('News', 'Latest news and updates');