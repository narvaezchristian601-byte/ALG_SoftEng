-- Create the database
CREATE DATABASE IF NOT EXISTS `algdb`;
USE `algdb`;

-- Create tables without foreign key dependencies first
CREATE TABLE `Staff` (
    `Staff_id` INT PRIMARY KEY,
    `Name` VARCHAR(50) NOT NULL,
    `address` VARCHAR(50),
    `PhoneNum` VARCHAR(20),
    `Email` VARCHAR(50) UNIQUE,
    `Password` VARCHAR(255) NOT NULL,
    `Role` ENUM('Admin', 'Staff') NOT NULL,
    `Position` VARCHAR(50) NOT NULL
);

CREATE TABLE `Customers` (
    `customer_id` INT PRIMARY KEY,
    `Name` VARCHAR(50) NOT NULL,
    `Email` VARCHAR(50) UNIQUE,
    `PhoneNumber` VARCHAR(20),
    `Address` VARCHAR(50)
);

CREATE TABLE `Category` (
    `Category_id` INT PRIMARY KEY,
    `Name` VARCHAR(50) NOT NULL,
    `Description` TEXT
);

CREATE TABLE `Supplier` (
    `Supplier_id` INT PRIMARY KEY,
    `Company_Name` VARCHAR(50) NOT NULL,
    `Contact_Person` VARCHAR(50),
    `Email` VARCHAR(50) UNIQUE,
    `Address` VARCHAR(50),
    `Contact_Num` VARCHAR(50)
);

CREATE TABLE `Services` (
    `Services_id` INT PRIMARY KEY,
    `Name` VARCHAR(50) NOT NULL,
    `Description` TEXT,
    `Price` DECIMAL(10, 2) NOT NULL
);

-- Product must come before Orders/OrderItems
CREATE TABLE `Product` (
    `Product_id` INT PRIMARY KEY,
    `Category_id` INT,
    `Supplier_id` INT,
    `Name` VARCHAR(50) NOT NULL,
    `Description` TEXT,
    `Price` DECIMAL(10, 2) NOT NULL,
    `Stock` INT NOT NULL,
    FOREIGN KEY (`Category_id`) REFERENCES `Category`(`Category_id`),
    FOREIGN KEY (`Supplier_id`) REFERENCES `Supplier`(`Supplier_id`)
);

-- Payment
CREATE TABLE `Payment` (
    `Payment_id` INT PRIMARY KEY,
    `Orders_id` INT UNIQUE,
    `Total_value` DECIMAL(10, 2),
    `payment_method` ENUM('Credit Card', 'Cash', 'Bank Transfer'),
    `transaction_id` VARCHAR(50),
    `payment_date` TIMESTAMP NOT NULL
);

-- Orders
CREATE TABLE `Orders` (
    `Orders_id` INT PRIMARY KEY,
    `Services_id` INT NOT NULL,
    `customer_id` INT NOT NULL,
    `total_amount` DECIMAL(10, 2),
    `order_date` TIMESTAMP NOT NULL,
    `status` ENUM('Pending', 'Ongoing', 'Completed', 'Dismissed') NOT NULL,
    `schedule_date` DATETIME,
    `stock_adjusted` TINYINT(1) DEFAULT 0,
    `Payment_id` INT UNIQUE,
    FOREIGN KEY (`customer_id`) REFERENCES `Customers`(`customer_id`),
    FOREIGN KEY (`Services_id`) REFERENCES `Services`(`Services_id`),
    FOREIGN KEY (`Payment_id`) REFERENCES `Payment`(`Payment_id`)
);

-- Projects table
CREATE TABLE `Projects` (
    `Project_id` INT PRIMARY KEY AUTO_INCREMENT,
    `Orders_id` INT NOT NULL,
    `Assigned_Staff` INT,
    `StartDate` DATE,
    `EndDate` DATE,
    `Progress` ENUM('Not Started', 'In Progress', 'Completed') DEFAULT 'Not Started',
    `Notes` TEXT,
    FOREIGN KEY (`Orders_id`) REFERENCES `Orders`(`Orders_id`),
    FOREIGN KEY (`Assigned_Staff`) REFERENCES `Staff`(`Staff_id`)
);

-- Project Logs table
CREATE TABLE `ProjectLogs` (
    `Log_id` INT PRIMARY KEY AUTO_INCREMENT,
    `Project_id` INT NOT NULL,
    `LogDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `Description` TEXT NOT NULL,
    FOREIGN KEY (`Project_id`) REFERENCES `Projects`(`Project_id`) ON DELETE CASCADE
);

CREATE TABLE `service_sched` (
    `sched_id` INT PRIMARY KEY,
    `Services_id` INT NOT NULL,
    `ScheduleDate` DATETIME,
    FOREIGN KEY (`Services_id`) REFERENCES `Services`(`Services_id`)
);

CREATE TABLE `orderitems` (
    `Items_id` INT PRIMARY KEY,
    `Orders_id` INT,
    `product_id` INT,
    `services_id` INT,
    `quantity` INT NOT NULL,
    `price` DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (`Orders_id`) REFERENCES `Orders`(`Orders_id`),
    FOREIGN KEY (`product_id`) REFERENCES `Product`(`Product_id`),
    FOREIGN KEY (`services_id`) REFERENCES `Services`(`Services_id`)
);

CREATE TABLE ProductImports (
    Import_id INT PRIMARY KEY AUTO_INCREMENT,
    Supplier_id INT,
    Product_id INT NOT NULL,
    Quantity INT NOT NULL,
    Price DECIMAL(10,2) NOT NULL,
    Total DECIMAL(12,2) GENERATED ALWAYS AS (Quantity * Price) STORED,
    ImportDate DATE NOT NULL,
    FOREIGN KEY (Supplier_id) REFERENCES Supplier(Supplier_id),
    FOREIGN KEY (Product_id) REFERENCES Product(Product_id)
);

-- AUTO INCREMENT SETTINGS
ALTER TABLE Customers MODIFY Customer_id INT NOT NULL AUTO_INCREMENT;
ALTER TABLE Orders MODIFY Orders_id INT NOT NULL AUTO_INCREMENT;
ALTER TABLE OrderItems MODIFY Items_id INT NOT NULL AUTO_INCREMENT;
ALTER TABLE Product MODIFY Product_id INT NOT NULL AUTO_INCREMENT;
ALTER TABLE Services MODIFY Services_id INT NOT NULL AUTO_INCREMENT;
ALTER TABLE Staff MODIFY Staff_id INT NOT NULL AUTO_INCREMENT;
ALTER TABLE Supplier MODIFY Supplier_id INT NOT NULL AUTO_INCREMENT;
ALTER TABLE Category MODIFY Category_id INT NOT NULL AUTO_INCREMENT;
ALTER TABLE Payment MODIFY Payment_id INT NOT NULL AUTO_INCREMENT;
ALTER TABLE service_sched MODIFY sched_id INT NOT NULL AUTO_INCREMENT;

-- ====== SAMPLE DATA ======

-- Categories
INSERT INTO `Category` VALUES
(1, 'Shingles & Tiles', 'Roofing materials like shingles and tiles.'),
(2, 'Metal Roofing', 'Durable metal sheets and panels.'),
(3, 'Roofing Accessories', 'Accessories like vents and underlayment.');

-- Supplier
INSERT INTO `Supplier` VALUES
(1, 'Prime Roofing Solutions', 'Jane Doe', 'jane@primerfs.com', '123 Roofing Way', '555-1234');

-- Services
INSERT INTO `Services` VALUES
(1, 'Roof Inspection', 'Full roof inspection.', 150.00),
(2, 'Shingle Installation', 'Installation of shingles.', 3500.00);

-- Product
INSERT INTO `Product` VALUES
(1, 1, 1, 'Asphalt Shingles', 'High-quality asphalt shingles.', 85.50, 62),
(2, 2, 1, 'Metal Panels', 'Durable metal roofing.', 250.00, 25);

-- Customers
INSERT INTO `Customers` VALUES
(1, 'Alice Johnson', 'alice@example.com', '09123456789', '123 Maple St'),
(2, 'Bob Smith', 'bob@example.com', '09998887777', '456 Oak Ave');

-- Staff
INSERT INTO `Staff` VALUES
(1, 'Admin User', '123 Main St', '09123456789', 'admin@alg.com', 'admin', 'Admin', 'Administrator'),
(2, 'John Doe', '456 Roof Lane', '09998887777', 'staff@alg.com', 'staff', 'Staff', 'Technician');

-- Orders
INSERT INTO `Orders` (`Orders_id`, `Services_id`, `customer_id`, `total_amount`, `order_date`, `status`, `schedule_date`) VALUES
(1, 1, 1, 150.00, NOW(), 'Pending', '2025-10-09 10:00:00'),
(2, 2, 2, 3500.00, NOW(), 'Ongoing', '2025-10-10 09:00:00');

-- Projects
INSERT INTO `Projects` (`Orders_id`, `Assigned_Staff`, `StartDate`, `EndDate`, `Progress`, `Notes`) VALUES
(1, 2, '2025-10-09', '2025-10-10', 'In Progress', 'Roof inspection scheduled.'),
(2, 2, '2025-10-10', '2025-10-12', 'Not Started', 'Shingle installation upcoming.');

-- Project Logs
INSERT INTO `ProjectLogs` (`Project_id`, `Description`) VALUES
(1, 'Arrived at site, began inspection.'),
(1, 'Identified 3 areas of minor damage.'),
(2, 'Materials prepared for installation.');

-- Order Items
INSERT INTO `OrderItems` (`Orders_id`, `product_id`, `services_id`, `quantity`, `price`) VALUES
(1, 1, 1, 2, 171.00),
(2, 2, 2, 1, 3500.00);

-- Product Imports
INSERT INTO ProductImports (Product_id, Supplier_id, Quantity, Price, ImportDate)
VALUES (1, 1, 100, 85.50, '2025-10-01');
