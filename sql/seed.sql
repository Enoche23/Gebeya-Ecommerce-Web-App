USE gebeya_db;

INSERT INTO roles (role_name) VALUES
('buyer'), ('seller'), ('admin');

INSERT INTO categories (category_name) VALUES
('Electronics'), ('Clothing'), ('Home'), ('Books'), ('Vehicles');

-- Create an admin user (password is: Admin1234!)
-- NOTE: This hash was generated with PHP password_hash().
INSERT INTO users (full_name, email, password_hash, status)
VALUES (
  'Gebeya Admin',
  'admin@gebeya.local',
  '$2y$10$Q0o8lqv5q3j7jvXKxQy0OeN5fKp9Zc2Cz7qYjV5p2yQH6Qb0gkM1S',
  'active'
);

-- assign admin role
INSERT INTO user_roles (user_id, role_id)
SELECT u.user_id, r.role_id
FROM users u, roles r
WHERE u.email='admin@gebeya.local' AND r.role_name='admin';
