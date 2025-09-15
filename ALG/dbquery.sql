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
    `Password` VARCHAR(20) NOT NULL,
    `Role` ENUM('Admin', 'Sales', 'Technician', 'Manager') NOT NULL
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
    `Price` DECIMAL(10, 2) NOT NULL,
);

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

CREATE TABLE `Orders` (
    `Orders_id` INT PRIMARY KEY,
    `Services_id` INT NOT NULL,
    `customer_id` INT NOT NULL,
    `total_amount` DECIMAL(10, 2),
    `order_date` TIMESTAMP NOT NULL,
    `status` ENUM('Pending', 'Ongoing', 'Completed', 'Canceled'),
    FOREIGN KEY (`customer_id`) REFERENCES `Customers`(`customer_id`),
    FOREIGN KEY (`Services_id`) REFERENCES `Services`(`Services_id`)
);

CREATE TABLE `Payment` (
    `Payment_id` INT PRIMARY KEY,
    `Orders_id` INT UNIQUE,
    `Total_value` DECIMAL(10, 2),
    `payment_method` ENUM('Credit Card', 'Cash', 'Bank Transfer'),
    `transaction_id` VARCHAR(50),
    `payment_date` TIMESTAMP NOT NULL,
    FOREIGN KEY (`Orders_id`) REFERENCES `Orders`(`Orders_id`)
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

CREATE TABLE `service_sched` (
    `sched_id` INT PRIMARY KEY,
    `Services_id` INT NOT NULL,
    `ScheduleDate` TIMESTAMP,
    FOREIGN KEY (`Services_id`) REFERENCES `Services`(`Services_id`)
);

CREATE TABLE ProductImports (
    Import_id INT PRIMARY KEY AUTO_INCREMENT,
    Supplier_id INT NOT NULL,
    Product_id INT NOT NULL,
    Quantity INT NOT NULL,
    Price DECIMAL(10,2) NOT NULL,
    Total DECIMAL(12,2) GENERATED ALWAYS AS (Quantity * Price) STORED,
    ImportDate DATE NOT NULL,
    Payment_id INT NOT NULL,
    FOREIGN KEY (Supplier_id) REFERENCES Supplier(Supplier_id),
    FOREIGN KEY (Product_id) REFERENCES Product(Product_id),
    FOREIGN KEY (Payment_id) REFERENCES Payment(Payment_id)
);



-- Set AUTO_INCREMENT for primary keys
ALTER TABLE Customers 
MODIFY Customer_id INT NOT NULL AUTO_INCREMENT;

ALTER TABLE Orders 
MODIFY Orders_id INT NOT NULL AUTO_INCREMENT;

ALTER TABLE OrderItems 
MODIFY Items_id INT NOT NULL AUTO_INCREMENT;

ALTER TABLE Product 
MODIFY Product_id INT NOT NULL AUTO_INCREMENT;

ALTER TABLE Services 
MODIFY Services_id INT NOT NULL AUTO_INCREMENT;

ALTER TABLE Staff 
MODIFY Staff_id INT NOT NULL AUTO_INCREMENT;

ALTER TABLE Supplier 
MODIFY Supplier_id INT NOT NULL AUTO_INCREMENT;

ALTER TABLE Category 
MODIFY Category_id INT NOT NULL AUTO_INCREMENT;

ALTER TABLE Payment 
MODIFY Payment_id INT NOT NULL AUTO_INCREMENT;


-- After tables are created, you can add the remaining foreign key to the Orders table
-- This is necessary if the `Payment` table is created after the `Orders` table
ALTER TABLE `Orders`
ADD COLUMN `Payment_id` INT,
ADD FOREIGN KEY (`Payment_id`) REFERENCES `Payment`(`Payment_id`);

-- Section 1: Insert data into the 'Category' table
-- Based on roofing services and products
INSERT INTO `Category` (`Category_id`, `Name`, `Description`) VALUES
(1, 'Shingles & Tiles', 'A wide range of asphalt shingles, clay tiles, and concrete tiles for various roofing styles.'),
(2, 'Metal Roofing', 'Durable and long-lasting metal roofing sheets and panels.'),
(3, 'Roofing Accessories', 'Essential accessories like underlayment, vents, flashing, and fasteners.'),
(4, 'Sealants & Coatings', 'Protective sealants and coatings for waterproofing and maintenance.'),
(5, 'Specialty Materials', 'Unique or custom materials like slate, wood shakes, and green roofing systems.');

-- Section 2: Insert data into the 'Supplier' table
-- Suppliers of roofing materials
INSERT INTO `Supplier` (`Supplier_id`, `Company Name`, `Contact_Person`, `Email`, `Address`, `Contact_Num`) VALUES
(1, 'Prime Roofing Solutions', 'Jane Doe', 'jane.doe@primerfs.com', '123 Roofing Way, Metropolis', '555-123-4567'),
(2, 'Apex Building Materials', 'John Smith', 'john.smith@apexmaterials.com', '456 Metal Rd, Central City', '555-987-6543'),
(3, 'Global Coatings Inc.', 'David Lee', 'david.lee@globalcoatings.com', '789 Sealant Dr, Star City', '555-555-1234'),
(4, 'Quality Shingle Co.', 'Sarah Chen', 'sarah.chen@qualityshingles.com', '101 Shingle Lane, Townsville', '555-222-3333'),
(5, 'Durable Roof Supplies', 'Michael Davis', 'michael.davis@durable.com', '202 Tiling Ave, Riverdale', '555-888-9999');

-- Section 3: Insert data into the 'Services' table
-- Various roofing services offered
INSERT INTO `Services` (`Services_id`, `Name`, `Description`, `Price`, `Stock`) VALUES
(1, 'Roof Inspection', 'Comprehensive roof inspection service to assess damage and wear.', 150.00, 1),
(2, 'Shingle Installation', 'Professional installation of new asphalt or composite shingles.', 3500.00, 1),
(3, 'Metal Roof Repair', 'Repair service for damaged metal roofing panels and fasteners.', 750.00, 1),
(4, 'Gutter Cleaning', 'Routine gutter cleaning to prevent water damage and clogs.', 120.00, 1);

-- Section 4: Insert 25 products into the 'Product' table
-- Each product is linked to a category and a supplier
INSERT INTO `Product` (`Product_id`, `Category_id`, `Supplier_id`, `Name`, `Description`, `Price`, `Stock`) VALUES
(1, 1, 1, 'Asphalt Architectural Shingles', 'High-quality layered shingles for a dimensional look.', 85.50, 62),
(2, 1, 4, '3-Tab Fiberglass Shingles', 'Standard, economical shingles for a flat, classic look.', 65.00, 41),
(3, 1, 4, 'Clay Spanish Tile', 'Classic, red clay tiles for a Mediterranean style roof.', 120.75, 12),
(4, 1, 2, 'Concrete Roof Tile', 'Durable and fire-resistant concrete tiles, various colors available.', 105.25, 33),
(5, 2, 2, 'Standing Seam Metal Panels', 'Premium metal panels with hidden fasteners for a sleek finish.', 250.00, 8),
(6, 2, 2, 'Corrugated Galvanized Sheets', 'Wave-patterned galvanized sheets, ideal for commercial roofs.', 180.50, 56),
(7, 2, 1, 'Metal Shake Panels', 'Metal panels designed to mimic the look of traditional wood shakes.', 220.00, 19),
(8, 3, 3, 'Synthetic Roof Underlayment', 'Water-resistant underlayment to protect your roof deck.', 45.00, 71),
(9, 3, 3, 'Ridge Vent', 'Allows hot air to escape from the attic, improving ventilation.', 35.00, 24),
(10, 3, 5, 'Drip Edge Flashing', 'Protective flashing installed at the roof’s edge.', 15.00, 58),
(11, 4, 3, 'Acrylic Elastomeric Coating', 'A highly reflective roof coating for added weather protection.', 150.00, 17),
(12, 4, 3, 'Roofing Cement & Sealant', 'Heavy-duty cement for sealing cracks and leaks.', 25.00, 69),
(13, 5, 1, 'Genuine Slate Shingles', 'Natural stone shingles, extremely durable and long-lasting.', 350.00, 5),
(14, 5, 1, 'Treated Wood Shakes', 'Pressure-treated wood shakes for a rustic, natural roof.', 180.00, 15),
(15, 1, 4, 'Cool Roof Shingles', 'Energy-efficient shingles that reflect sunlight.', 95.00, 48),
(16, 1, 1, 'Laminated Shingles', 'A modern take on traditional shingles, with a thicker profile.', 90.00, 31),
(17, 2, 2, 'Aluminum Standing Seam', 'Lightweight and corrosion-resistant aluminum panels.', 275.00, 22),
(18, 3, 5, 'Ice and Water Shield', 'A self-adhering membrane for roof eaves and valleys.', 60.00, 65),
(19, 3, 4, 'Roofing Nails', 'Galvanized nails for securing shingles and underlayment.', 10.00, 75),
(20, 4, 3, 'Silicone Roof Coating', 'Moisture-curing sealant for long-term waterproofing.', 170.00, 29),
(21, 5, 1, 'Synthetic Slate Tiles', 'A composite tile that mimics the look of slate without the weight.', 190.00, 11),
(22, 1, 4, 'Dimensional Shingles', 'Multi-layered shingles that add depth and texture to a roof.', 78.00, 53),
(23, 2, 2, 'Metal Roof Screws', 'Corrosion-resistant screws with a sealing washer.', 18.50, 70),
(24, 3, 5, 'PVC Roof Vents', 'Durable plastic vents for proper attic ventilation.', 42.00, 39),
(25, 5, 1, 'Green Roof Drainage Mat', 'A mat that helps drain water and aerate soil for a green roof.', 95.00, 26);