-- Stock Management App Database Schema
-- Create the database first: CREATE DATABASE stock_management;

CREATE TABLE IF NOT EXISTS users (
  id VARCHAR(255) PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255),
  role ENUM('admin', 'outlet', 'staff') DEFAULT 'staff',
  outletId VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
  id VARCHAR(255) PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  category VARCHAR(255),
  stockPusat INT DEFAULT 0,
  price INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS outlets (
  id VARCHAR(255) PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  address TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS outlet_stock (
  outlet_id VARCHAR(255) NOT NULL,
  product_id VARCHAR(255) NOT NULL,
  quantity INT DEFAULT 0,
  PRIMARY KEY (outlet_id, product_id),
  FOREIGN KEY (outlet_id) REFERENCES outlets(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS invoices (
  id VARCHAR(255) PRIMARY KEY,
  outlet_id VARCHAR(255) NOT NULL,
  total_amount INT DEFAULT 0,
  is_paid BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (outlet_id) REFERENCES outlets(id)
);

CREATE TABLE IF NOT EXISTS invoice_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id VARCHAR(255) NOT NULL,
  product_id VARCHAR(255) NOT NULL,
  product_name VARCHAR(255),
  quantity INT DEFAULT 0,
  price INT DEFAULT 0,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE IF NOT EXISTS payments (
  id VARCHAR(255) PRIMARY KEY,
  invoice_id VARCHAR(255),
  amount INT DEFAULT 0,
  payment_method VARCHAR(50) DEFAULT 'Tunai',
  proof_image VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id)
);

CREATE TABLE IF NOT EXISTS consignments (
  id VARCHAR(255) PRIMARY KEY,
  outlet_id VARCHAR(255) NOT NULL,
  product_id VARCHAR(255) NOT NULL,
  quantity INT DEFAULT 0,
  status ENUM('sent', 'received', 'returned') DEFAULT 'sent',
  proof_image VARCHAR(255) NULL,
  latitude DECIMAL(10, 8) NULL,
  longitude DECIMAL(11, 8) NULL,
  received_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (outlet_id) REFERENCES outlets(id),
  FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE IF NOT EXISTS expenses (
  id VARCHAR(255) PRIMARY KEY,
  outlet_id VARCHAR(255) NULL,
  amount INT DEFAULT 0,
  category VARCHAR(255),
  description TEXT,
  date DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (outlet_id) REFERENCES outlets(id)
);

-- Insert sample data
INSERT INTO users (id, name, email, role, outletId) VALUES
('1', 'Admin User', 'admin@admin.com', 'admin', NULL),
('2', 'Outlet User', 'outlet@outlet.com', 'outlet', '1');

INSERT INTO outlets (id, name, address) VALUES
('1', 'Outlet Jakarta', 'Jl. Sudirman'),
('2', 'Outlet Bandung', 'Jl. Dago');

INSERT INTO products (id, name, category, stockPusat, price) VALUES
('1', 'Item A', 'Kategori 1', 100, 50000),
('2', 'Item B', 'Kategori 2', 50, 75000),
('3', 'Item C', 'Kategori 1', 200, 20000);

INSERT INTO outlet_stock (outlet_id, product_id, quantity) VALUES
('1', '1', 10),
('1', '2', 5),
('2', '1', 20),
('2', '3', 50);
