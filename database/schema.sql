CREATE DATABASE IF NOT EXISTS investsmart CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE investsmart;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(180) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('client', 'admin') NOT NULL DEFAULT 'client',
  status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  full_name VARCHAR(120) NOT NULL,
  surname VARCHAR(120) NOT NULL,
  id_number VARCHAR(20) NOT NULL UNIQUE,
  contact_info VARCHAR(180) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_client_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  full_name VARCHAR(120) NOT NULL,
  surname VARCHAR(120) NOT NULL,
  employee_code VARCHAR(40) NULL UNIQUE,
  contact_info VARCHAR(180) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_admin_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS financial_profiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL UNIQUE,
  gross_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
  deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
  monthly_expenses DECIMAL(12,2) NOT NULL DEFAULT 0,
  current_savings DECIMAL(12,2) NOT NULL DEFAULT 0,
  net_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_financial_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS banks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  contact_info VARCHAR(220) NOT NULL,
  website VARCHAR(255) NOT NULL,
  plan_type VARCHAR(80) NOT NULL,
  expected_return DECIMAL(5,2) NOT NULL,
  risk ENUM('Low', 'Medium', 'High') NOT NULL,
  liquidity ENUM('High', 'Medium', 'Low') NOT NULL,
  horizon ENUM('Short', 'Medium', 'Long') NOT NULL,
  allows_monthly TINYINT(1) NOT NULL DEFAULT 0,
  details TEXT NULL,
  created_by_admin_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bank_admin FOREIGN KEY (created_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS investment_plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
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
  bank_investment_url VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_plan_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_plan_bank FOREIGN KEY (bank_id) REFERENCES banks(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  client_id INT NULL,
  admin_id INT NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id INT NULL,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_activity_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
  CONSTRAINT fk_activity_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
);

INSERT INTO users (email, password_hash, role, status)
VALUES ('admin@investsmart.local', '$2y$10$Slg7oTxQIhPf5ltaabyL7.LeVLwxfh6s1WHavQwvwthhhO959nQG.', 'admin', 'active')
ON DUPLICATE KEY UPDATE email = email;

INSERT INTO admins (user_id, full_name, surname, employee_code, contact_info)
SELECT u.id, 'System', 'Admin', 'ADM-001', 'admin@investsmart.local'
FROM users u
WHERE u.email = 'admin@investsmart.local'
  AND NOT EXISTS (SELECT 1 FROM admins a WHERE a.user_id = u.id);

INSERT INTO banks (name, contact_info, website, plan_type, expected_return, risk, liquidity, horizon, allows_monthly, details, created_by_admin_id) VALUES
('Standard Bank', 'investments@standardbank.co.za | 0860 123 000', 'https://www.standardbank.co.za/southafrica/personal/products-and-services/grow-your-money/investment-solutions/digital-save-and-invest', 'Fixed Plan', 7.20, 'Low', 'Low', 'Short', 0, 'Fixed-term investment option for users who prefer capital stability and predictable returns.', 1),
('FNB', 'invest@fnb.co.za | 087 575 9404', 'https://www.fnb.co.za/for-me/save-and-invest.html', 'Flexi Plan', 8.35, 'Low', 'High', 'Short', 1, 'Flexible savings and investment access with recurring contribution support.', 1),
('Nedbank', 'wealth@nedbank.co.za | 0800 555 111', 'https://personal.nedbank.co.za/private-clients/invest/invest-with-us.html', 'Retirement/Income Plan', 9.40, 'Medium', 'Low', 'Long', 1, 'Long-term retirement and income-focused solution for balanced investors.', 1),
('Absa', 'investment@absa.co.za | 0860 111 515', 'https://www.absa.co.za/personal/investment-management/investment-accounts/', 'Growth Plan', 12.60, 'Medium', 'Medium', 'Medium', 1, 'Balanced growth investment for users seeking moderate risk and medium-term returns.', 1),
('Capitec', 'invest@capitecbank.co.za | 0860 102 043', 'https://www.capitecbank.co.za/personal/save/', 'Flexi Plan', 8.75, 'Medium', 'High', 'Short', 1, 'Simple flexible investment structure with accessible monthly contribution options for users who can accept moderate risk.', 1),
('Investec', 'wealth@investec.co.za | 0860 443 443', 'https://www.investec.com/en_za/focus/investing/how-to-save-and-invest.html', 'Equity Plan', 15.80, 'High', 'High', 'Long', 0, 'Higher-growth equity-linked investment option for users with long horizons and higher risk tolerance.', 1)
ON DUPLICATE KEY UPDATE website = VALUES(website), contact_info = VALUES(contact_info), details = VALUES(details), expected_return = VALUES(expected_return);
