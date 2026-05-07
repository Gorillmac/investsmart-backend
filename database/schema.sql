CREATE DATABASE IF NOT EXISTS investsmart CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE investsmart;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  surname VARCHAR(120) NOT NULL,
  id_number VARCHAR(20) NOT NULL UNIQUE,
  email VARCHAR(180) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
  status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  contact_info VARCHAR(180) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS financial_profiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  gross_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
  deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
  monthly_expenses DECIMAL(12,2) NOT NULL DEFAULT 0,
  current_savings DECIMAL(12,2) NOT NULL DEFAULT 0,
  net_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_financial_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS banks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  contact_info VARCHAR(220) NOT NULL,
  website VARCHAR(220) NULL,
  plan_type VARCHAR(80) NOT NULL,
  expected_return DECIMAL(5,2) NOT NULL,
  risk ENUM('Low', 'Medium', 'High') NOT NULL,
  liquidity ENUM('High', 'Medium', 'Low') NOT NULL,
  horizon ENUM('Short', 'Medium', 'Long') NOT NULL,
  allows_monthly TINYINT(1) NOT NULL DEFAULT 0,
  details TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS investment_plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  bank_id INT NULL,
  user_plan_name VARCHAR(160) NOT NULL,
  investment_amount DECIMAL(12,2) NOT NULL,
  monthly_contribution TINYINT(1) NOT NULL DEFAULT 0,
  monthly_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  investment_goal VARCHAR(255) NOT NULL,
  risk ENUM('Low', 'Medium', 'High') NOT NULL,
  liquidity ENUM('High', 'Medium', 'Low') NOT NULL,
  horizon ENUM('Short', 'Medium', 'Long') NOT NULL,
  expected_return DECIMAL(5,2) NOT NULL,
  score INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_plan_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_plan_bank FOREIGN KEY (bank_id) REFERENCES banks(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id INT NULL,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO banks (name, contact_info, website, plan_type, expected_return, risk, liquidity, horizon, allows_monthly, details) VALUES
('Standard Bank', 'investments@standardbank.co.za | 0860 123 000', 'https://www.standardbank.co.za', 'Fixed Plan', 7.20, 'Low', 'Low', 'Short', 0, 'Fixed-term investment option for users who prefer capital stability and predictable returns.'),
('FNB', 'invest@fnb.co.za | 087 575 9404', 'https://www.fnb.co.za', 'Flexi Plan', 8.35, 'Low', 'High', 'Short', 1, 'Flexible savings and investment access with recurring contribution support.'),
('Nedbank', 'wealth@nedbank.co.za | 0800 555 111', 'https://www.nedbank.co.za', 'Retirement/Income Plan', 9.40, 'Medium', 'Low', 'Long', 1, 'Long-term retirement and income-focused solution for balanced investors.'),
('Absa', 'investment@absa.co.za | 0860 111 515', 'https://www.absa.co.za', 'Growth Plan', 12.60, 'Medium', 'Medium', 'Medium', 1, 'Balanced growth investment for users seeking moderate risk and medium-term returns.'),
('Capitec', 'invest@capitecbank.co.za | 0860 102 043', 'https://www.capitecbank.co.za', 'Flexi Plan', 8.75, 'Medium', 'High', 'Short', 1, 'Simple flexible investment structure with accessible monthly contribution options for users who can accept moderate risk.'),
('Investec', 'wealth@investec.co.za | 0860 443 443', 'https://www.investec.com', 'Equity Plan', 15.80, 'High', 'High', 'Long', 0, 'Higher-growth equity-linked investment option for users with long horizons and higher risk tolerance.');

INSERT INTO users (full_name, surname, id_number, email, password_hash, role, status)
VALUES ('System', 'Admin', '8001015009087', 'admin@investsmart.local', '$2y$10$Slg7oTxQIhPf5ltaabyL7.LeVLwxfh6s1WHavQwvwthhhO959nQG.', 'admin', 'active')
ON DUPLICATE KEY UPDATE email = email;
