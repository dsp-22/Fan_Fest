-- 1. DATABASE TEARDOWN
-- Disable foreign keys to allow dropping tables in any order
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS checkins;
DROP TABLE IF EXISTS stand_inventory;
DROP TABLE IF EXISTS menu_items;
DROP TABLE IF EXISTS concession_stands;
DROP TABLE IF EXISTS friends;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================
-- 2. USER MANAGEMENT
-- ==========================================

-- Table: users
-- Stores student authentication and profile information
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed Data: Initial Test Users
-- Note: Passwords are hashed versions of 'password123'
INSERT INTO users (id, first_name, last_name, email, password) VALUES 
(1, 'Caleb', 'Willits', 'willitsc@iu.edu', '$2y$10$YourHashedPasswordHere'),
(2, 'Dan', 'Sproat', 'dsproat@iu.edu', '$2y$10$YourHashedPasswordHere');

-- ==========================================
-- 3. EVENT TRACKING
-- ==========================================

-- Table: events
-- Stores upcoming games and official tailgate events
CREATE TABLE events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(100) NOT NULL,
    event_date DATE NOT NULL,
    location_name VARCHAR(100) NOT NULL
);

INSERT INTO events (event_name, event_date, location_name) VALUES 
('Home Opener: IU vs Purdue', '2025-09-12', 'Memorial Stadium'),
('Friday Night Lights', '2025-09-19', 'Memorial Stadium'),
('Championship Tailgate', '2025-10-04', 'Tailgate Fields');

-- Table: checkins
-- logs user attendance at specific events using geolocation data
CREATE TABLE checkins (
    checkin_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    checkin_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE
);

-- Seed Data: Test Check-in
INSERT INTO checkins (user_id, event_id, latitude, longitude) VALUES (1, 1, 39.1670, -86.5264);

-- ==========================================
-- 4. CONCESSIONS MODULE
-- ==========================================

-- Table: concession_stands
-- Represents physical locations within the stadium
CREATE TABLE concession_stands (
    stand_id INT PRIMARY KEY,
    stand_name VARCHAR(100),
    location VARCHAR(100),
    image_url VARCHAR(255) DEFAULT 'images/default_stand.jpg'
);

INSERT INTO concession_stands (stand_id, stand_name, location) VALUES
(1, 'North Concourse (Grill)', 'Section 101'),
(2, 'South Concourse (Pizza)', 'Section 115'),
(3, 'East Concourse (Sweets)', 'Section 205'),
(4, 'West Concourse (Tacos)',  'Section 220');

-- Table: menu_items
-- Master list of all available food and drink items
CREATE TABLE menu_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    price DECIMAL(4, 2) NOT NULL,
    category VARCHAR(50), 
    image_url VARCHAR(255) DEFAULT 'default_food.png'
);

INSERT INTO menu_items (item_id, name, description, price, category) VALUES 
-- Grill Items
(1, 'Stadium Hot Dog', 'Classic beef frank on a bun', 6.50, 'Food'),
(2, 'Classic Cheeseburger', 'Quarter pounder with cheddar', 8.50, 'Food'),
-- Mexican Cuisine
(3, 'Nachos Supreme', 'Chips, cheese, jalapeños, and beef', 9.00, 'Food'),
(4, 'Street Tacos', '3 beef tacos with cilantro lime', 8.50, 'Food'),
(5, 'Churro', 'Cinnamon sugar pastry', 4.00, 'Food'),
-- Pizza
(6, 'Pepperoni Slice', 'NY Style pepperoni pizza', 5.50, 'Food'),
(7, 'Cheese Slice', 'Classic cheese pizza', 5.00, 'Food'),
-- Beverages
(8, 'Large Soda', '32oz fountain drink', 4.50, 'Drink'),
(9, 'Craft Beer', 'Local IPA on tap', 11.00, 'Drink'),
(10, 'Domestic Draft', 'Light american lager', 9.00, 'Drink'),
(11, 'Soft Pretzel', 'Warm pretzel with cheese dip', 5.00, 'Snack');

-- Table: stand_inventory
-- Junction table linking menu items to specific stands (Many-to-Many relationship)
CREATE TABLE stand_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stand_id INT,
    item_id INT,
    FOREIGN KEY (stand_id) REFERENCES concession_stands(stand_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES menu_items(item_id) ON DELETE CASCADE
);

-- Distribution Logic: Assigns specific menus to stands
-- 1. Global Items: Drinks available at all locations
INSERT INTO stand_inventory (stand_id, item_id) VALUES 
(1, 8), (1, 9), (1, 10),
(2, 8), (2, 9), (2, 10),
(3, 8), (3, 9), (3, 10),
(4, 8), (4, 9), (4, 10);

-- 2. Stand-Specific Assignments
-- North: Grill items
INSERT INTO stand_inventory (stand_id, item_id) VALUES (1, 1), (1, 2), (1, 11);
-- South: Pizza items
INSERT INTO stand_inventory (stand_id, item_id) VALUES (2, 6), (2, 7);
-- West: Tacos and Nachos
INSERT INTO stand_inventory (stand_id, item_id) VALUES (4, 3), (4, 4), (4, 5);
-- East: Sweets and overflowing snacks
INSERT INTO stand_inventory (stand_id, item_id) VALUES (3, 5), (3, 11);

-- ==========================================
-- 5. SOCIAL FEATURES
-- ==========================================

-- Table: friends
-- Manages friendship status between users (pending/accepted)
CREATE TABLE friends (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id_1 INT NOT NULL,
    user_id_2 INT NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id_1) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id_2) REFERENCES users(id) ON DELETE CASCADE
);

-- Seed Data: Initial Friendships
INSERT INTO friends (user_id_1, user_id_2, status) VALUES (1, 2, 'accepted');