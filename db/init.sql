-- Create database with utf8mb4 for emoji support
CREATE DATABASE IF NOT EXISTS login_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE login_system;

-- Users table
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Income categories
CREATE TABLE IF NOT EXISTS income_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  icon VARCHAR(10) NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO income_categories (id, name, icon) VALUES
(1,'Salary','💼'),
(2,'Additional Work / Freelance','🧰'),
(3,'Business Income','🧾'),
(4,'Bonus / Commission','🎁'),
(5,'Rental Income','🏠'),
(6,'Investment Returns','📈'),
(7,'Other','💸');

-- Expense categories
CREATE TABLE IF NOT EXISTS expense_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  icon VARCHAR(10) NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO expense_categories (id, name, icon) VALUES
(1,'Rent / Mortgage','🏠'),
(2,'Utilities','💡'),
(3,'Groceries','🛒'),
(4,'Fuel / Transport','⛽'),
(5,'Education','🎓'),
(6,'Subscriptions','📺'),
(7,'Entertainment','🎬'),
(8,'Insurance','📄'),
(9,'Medical','🏥'),
(10,'Other','❓');

-- Incomes
CREATE TABLE IF NOT EXISTS incomes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  amount DECIMAL(10,2) NOT NULL,
  category_id INT NULL,
  note VARCHAR(255) NULL,
  created_at DATE DEFAULT (CURRENT_DATE),
  created_ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_income_category FOREIGN KEY (category_id) 
    REFERENCES income_categories(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Expenses
CREATE TABLE IF NOT EXISTS expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  amount DECIMAL(10,2) NOT NULL,
  category_id INT NULL,
  note VARCHAR(255) NULL,
  created_at DATE DEFAULT (CURRENT_DATE),
  created_ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_expense_category FOREIGN KEY (category_id) 
    REFERENCES expense_categories(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
