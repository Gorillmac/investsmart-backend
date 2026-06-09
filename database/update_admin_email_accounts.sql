USE investsmart;

-- Deployment-ready admin accounts.
-- Both accounts use the password: password

DELETE old_user
FROM users old_user
JOIN users final_user ON final_user.email = 'developer3214@outlook.com'
WHERE old_user.email = 'admin@investsmart.local';

UPDATE users old_user
LEFT JOIN users final_user ON final_user.email = 'developer3214@outlook.com'
SET old_user.email = 'developer3214@outlook.com',
    old_user.password_hash = '$2y$10$Slg7oTxQIhPf5ltaabyL7.LeVLwxfh6s1WHavQwvwthhhO959nQG.',
    old_user.role = 'admin',
    old_user.status = 'active'
WHERE old_user.email = 'admin@investsmart.local'
  AND final_user.id IS NULL;

DELETE old_user
FROM users old_user
JOIN users final_user ON final_user.email = 'paulkomeni@gmail.com'
WHERE old_user.email = 'paukomeni@gmail.com';

UPDATE users old_user
LEFT JOIN users final_user ON final_user.email = 'paulkomeni@gmail.com'
SET old_user.email = 'paulkomeni@gmail.com',
    old_user.password_hash = '$2y$10$Slg7oTxQIhPf5ltaabyL7.LeVLwxfh6s1WHavQwvwthhhO959nQG.',
    old_user.role = 'admin',
    old_user.status = 'active'
WHERE old_user.email = 'paukomeni@gmail.com'
  AND final_user.id IS NULL;

INSERT INTO users (email, password_hash, role, status)
VALUES
('developer3214@outlook.com', '$2y$10$Slg7oTxQIhPf5ltaabyL7.LeVLwxfh6s1WHavQwvwthhhO959nQG.', 'admin', 'active'),
('paulkomeni@gmail.com', '$2y$10$Slg7oTxQIhPf5ltaabyL7.LeVLwxfh6s1WHavQwvwthhhO959nQG.', 'admin', 'active')
ON DUPLICATE KEY UPDATE
  password_hash = VALUES(password_hash),
  role = VALUES(role),
  status = VALUES(status);

INSERT INTO admins (user_id, full_name, surname, employee_code, contact_info)
SELECT u.id, 'Developer', 'Admin', 'ADM-001', 'developer3214@outlook.com'
FROM users u
WHERE u.email = 'developer3214@outlook.com'
  AND NOT EXISTS (SELECT 1 FROM admins a WHERE a.user_id = u.id OR a.employee_code = 'ADM-001');

INSERT INTO admins (user_id, full_name, surname, employee_code, contact_info)
SELECT u.id, 'Paul', 'Komeni', 'ADM-002', 'paulkomeni@gmail.com'
FROM users u
WHERE u.email = 'paulkomeni@gmail.com'
  AND NOT EXISTS (SELECT 1 FROM admins a WHERE a.user_id = u.id OR a.employee_code = 'ADM-002');

UPDATE admins a
JOIN users u ON u.id = a.user_id
SET a.full_name = 'Developer',
    a.surname = 'Admin',
    a.employee_code = 'ADM-001',
    a.contact_info = 'developer3214@outlook.com'
WHERE u.email = 'developer3214@outlook.com';

UPDATE admins a
JOIN users u ON u.id = a.user_id
SET a.full_name = 'Paul',
    a.surname = 'Komeni',
    a.employee_code = 'ADM-002',
    a.contact_info = 'paulkomeni@gmail.com'
WHERE u.email = 'paulkomeni@gmail.com';
