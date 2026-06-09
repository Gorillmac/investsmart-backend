USE investsmart;

-- Deployment-ready admin accounts.
-- Both accounts use the password: password

UPDATE users
SET email = 'developer3214@outlook.com',
    password_hash = '$2y$10$Slg7oTxQIhPf5ltaabyL7.LeVLwxfh6s1WHavQwvwthhhO959nQG.',
    role = 'admin',
    status = 'active'
WHERE email = 'admin@investsmart.local';

INSERT INTO users (email, password_hash, role, status)
VALUES
('developer3214@outlook.com', '$2y$10$Slg7oTxQIhPf5ltaabyL7.LeVLwxfh6s1WHavQwvwthhhO959nQG.', 'admin', 'active'),
('paukomeni@gmail.com', '$2y$10$Slg7oTxQIhPf5ltaabyL7.LeVLwxfh6s1WHavQwvwthhhO959nQG.', 'admin', 'active')
ON DUPLICATE KEY UPDATE
  password_hash = VALUES(password_hash),
  role = VALUES(role),
  status = VALUES(status);

INSERT INTO admins (user_id, full_name, surname, employee_code, contact_info)
SELECT u.id, 'Developer', 'Admin', 'ADM-001', 'developer3214@outlook.com'
FROM users u
WHERE u.email = 'developer3214@outlook.com'
  AND NOT EXISTS (SELECT 1 FROM admins a WHERE a.user_id = u.id);

INSERT INTO admins (user_id, full_name, surname, employee_code, contact_info)
SELECT u.id, 'Pau', 'Komeni', 'ADM-002', 'paukomeni@gmail.com'
FROM users u
WHERE u.email = 'paukomeni@gmail.com'
  AND NOT EXISTS (SELECT 1 FROM admins a WHERE a.user_id = u.id);

UPDATE admins a
JOIN users u ON u.id = a.user_id
SET a.full_name = 'Developer',
    a.surname = 'Admin',
    a.employee_code = 'ADM-001',
    a.contact_info = 'developer3214@outlook.com'
WHERE u.email = 'developer3214@outlook.com';

UPDATE admins a
JOIN users u ON u.id = a.user_id
SET a.full_name = 'Pau',
    a.surname = 'Komeni',
    a.employee_code = 'ADM-002',
    a.contact_info = 'paukomeni@gmail.com'
WHERE u.email = 'paukomeni@gmail.com';
