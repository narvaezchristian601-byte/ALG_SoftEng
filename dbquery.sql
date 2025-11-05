-- phpMyAdmin SQL Dump
--
-- Database: `algdb`
--

Create DATABASE IF NOT EXISTS `algdb` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `algdb`;

--
-- Table structure for table `category`
--

CREATE TABLE `category` (
  `Category_id` int(11) NOT NULL,
  `Name` varchar(50) NOT NULL,
  `Description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `category`
--

INSERT INTO `category` (`Category_id`, `Name`, `Description`) VALUES
(1, 'Hardware', 'Physical computer components and networking gear.'),
(2, 'Software Licenses', 'Purchased licenses for operating systems and applications.'),
(3, 'Cables & Accessories', 'Networking cables, adapters, and small peripherals.');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `Customer_id` int(11) NOT NULL,
  `Name` varchar(50) NOT NULL,
  `Email` varchar(50) DEFAULT NULL,
  `PhoneNumber` varchar(20) DEFAULT NULL,
  `Address` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`Customer_id`, `Name`, `Email`, `PhoneNumber`, `Address`) VALUES
(1, 'Tech Solutions Inc.', 'tech@sol.com', '555-1001', '10 Industrial Way'),
(2, 'Green Energy Co.', 'green@en.com', '555-1002', '20 Park Blvd'),
(3, 'Local Cafe', 'cafe@local.net', '555-1003', '30 Downtown St'),
(4, 'Rohan gwapo', 'gwapo@gmail.com', '09694328345', 'Sa la salle dapit duol sa 7/11');

-- --------------------------------------------------------

--
-- Table structure for table `orderitems`
--

CREATE TABLE `orderitems` (
  `Items_id` int(11) NOT NULL,
  `Orders_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `services_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orderitems`
--

INSERT INTO `orderitems` (`Items_id`, `Orders_id`, `product_id`, `services_id`, `quantity`, `price`) VALUES
(301, 201, 100, NULL, 2, 120.00),
(302, 201, 102, NULL, 10, 5.00),
(303, 201, NULL, 10, 1, 1500.00),
(304, 202, 101, NULL, 1, 150.00),
(305, 202, NULL, 12, 1, 300.00);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `Orders_id` int(11) NOT NULL,
  `Services_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('Pending','Ongoing','Completed','Dismissed') NOT NULL,
  `stock_adjusted` tinyint(1) DEFAULT 0,
  `Payment_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`Orders_id`, `Services_id`, `customer_id`, `total_amount`, `order_date`, `status`, `stock_adjusted`, `Payment_id`) VALUES
(201, 10, 1, 1850.00, '2025-10-28 05:27:36', 'Completed', 0, 9001),
(202, 12, 3, 505.00, '2025-10-28 05:23:04', 'Ongoing', 0, 9002),
(203, 11, 2, 850.00, '2025-10-05 03:00:00', 'Pending', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payment`
--

CREATE TABLE `payment` (
  `Payment_id` int(11) NOT NULL,
  `Orders_id` int(11) DEFAULT NULL,
  `Total_value` decimal(10,2) DEFAULT NULL,
  `payment_method` enum('Credit Card','Cash','Bank Transfer') DEFAULT NULL,
  `transaction_id` varchar(50) DEFAULT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment`
--

INSERT INTO `payment` (`Payment_id`, `Orders_id`, `Total_value`, `payment_method`, `transaction_id`, `payment_date`) VALUES
(9001, 201, 1850.00, 'Bank Transfer', 'TXN789012', '2025-10-19 06:45:52'),
(9002, 202, 505.00, 'Credit Card', 'TXN789013', '2025-10-19 06:45:52'),
(9003, NULL, 300.00, 'Cash', 'TXN789014', '2025-10-10 02:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `product`
--

CREATE TABLE `product` (
  `Product_id` int(11) NOT NULL,
  `Category_id` int(11) DEFAULT NULL,
  `Supplier_id` int(11) DEFAULT NULL,
  `Name` varchar(50) NOT NULL,
  `Description` text DEFAULT NULL,
  `Price` decimal(10,2) NOT NULL,
  `Stock` int(11) NOT NULL,
  `Reorder_Level` int(11) NOT NULL DEFAULT 10
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product`
--

INSERT INTO `product` (`Product_id`, `Category_id`, `Supplier_id`, `Name`, `Description`, `Price`, `Stock`, `Reorder_Level`) VALUES
(100, 1, 501, 'Network Switch 24-Port', 'Managed gigabit switch.', 120.00, 50, 5),
(101, 2, 502, 'Windows Pro License', 'Single-user license for Windows 11 Pro.', 150.00, 200, 20),
(102, 3, 501, 'CAT6 Ethernet Cable (10ft)', 'Standard network cable.', 5.00, 1000, 50);

-- --------------------------------------------------------

--
-- Table structure for table `productimports`
--

CREATE TABLE `productimports` (
  `Import_id` int(11) NOT NULL,
  `PO_id` int(11) DEFAULT NULL,
  `Supplier_id` int(11) DEFAULT NULL,
  `Product_id` int(11) NOT NULL,
  `Quantity` int(11) NOT NULL,
  `Price` decimal(10,2) NOT NULL,
  `Total` decimal(12,2) GENERATED ALWAYS AS (`Quantity` * `Price`) STORED,
  `ImportDate` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `productimports`
--

INSERT INTO `productimports` (`Import_id`, `PO_id`, `Supplier_id`, `Product_id`, `Quantity`, `Price`, `ImportDate`) VALUES
(1, NULL, 501, 100, 20, 100.00, '2025-08-14'),
(2, NULL, 501, 102, 500, 4.00, '2025-08-14'),
(3, NULL, 502, 101, 10, 150.00, '2025-09-09');

-- --------------------------------------------------------

--
-- Table structure for table `projectlogs`
--

CREATE TABLE `projectlogs` (
  `Log_id` int(11) NOT NULL,
  `Project_id` int(11) NOT NULL,
  `LogDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `Description` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projectlogs`
--

INSERT INTO `projectlogs` (`Log_id`, `Project_id`, `LogDate`, `Description`) VALUES
(1, 1, '2025-09-17 06:00:00', 'All hardware installed and configured.'),
(2, 2, '2025-10-06 01:30:00', 'OS installation started.');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `Project_id` int(11) NOT NULL,
  `Orders_id` int(11) NOT NULL,
  `StartDate` date DEFAULT NULL,
  `EndDate` date DEFAULT NULL,
  `Status` enum('Pending','Ongoing','Completed') DEFAULT 'Pending',
  `Notes` text DEFAULT NULL,
  `Total_Cost` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`Project_id`, `Orders_id`, `StartDate`, `EndDate`, `Status`, `Notes`, `Total_Cost`) VALUES
(1, 201, '2025-09-15', '2025-09-18', 'Completed', 'Initial network setup for new office building.', 1850.00),
(2, 202, '2025-10-05', NULL, 'Ongoing', 'Piste yawa patya ko daan ayha nako humanon, bahalag akong kalag nalay mo himo sa code', 505.00);

-- --------------------------------------------------------

--
-- Table structure for table `projectstaff`
--

CREATE TABLE `projectstaff` (
  `ProjectStaff_id` int(11) NOT NULL,
  `Project_id` int(11) NOT NULL,
  `Staff_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projectstaff`
--

INSERT INTO `projectstaff` (`ProjectStaff_id`, `Project_id`, `Staff_id`) VALUES
(5, 1, 102),
(6, 1, 103),
(4, 2, 103);

-- --------------------------------------------------------

--
-- Table structure for table `purchaseorders`
--

CREATE TABLE `purchaseorders` (
  `PO_id` int(11) NOT NULL,
  `PO_Number` varchar(50) NOT NULL,
  `Supplier_id` int(11) NOT NULL,
  `Date_Created` date NOT NULL,
  `Est_Ship_Date` date DEFAULT NULL,
  `Total_Amount` decimal(12,2) NOT NULL,
  `Discount` decimal(12,2) DEFAULT 0.00,
  `Final_Amount` decimal(12,2) NOT NULL,
  `Status` enum('Pending','Sent','Approved','Received','Cancelled') NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchaseorders`
--

INSERT INTO `purchaseorders` (`PO_id`, `PO_Number`, `Supplier_id`, `Date_Created`, `Est_Ship_Date`, `Total_Amount`, `Discount`, `Final_Amount`, `Status`) VALUES
(1, '4001', 501, '2025-08-01', '2025-08-15', 3000.00, 100.00, 2900.00, 'Received'),
(2, '4002', 502, '2025-09-01', '2025-09-10', 500.00, 0.00, 500.00, 'Approved');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `Services_id` int(11) NOT NULL,
  `Name` varchar(50) NOT NULL,
  `Description` text DEFAULT NULL,
  `Price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`Services_id`, `Name`, `Description`, `Price`) VALUES
(10, 'Network Setup', 'Full installation and configuration of a local area network.', 1500.00),
(11, 'Security Audit', 'Comprehensive review of system vulnerabilities and security policies.', 850.00),
(12, 'Custom PC Build', 'Building a computer based on client specifications.', 300.00);

-- --------------------------------------------------------

--
-- Table structure for table `service_sched`
--

CREATE TABLE `service_sched` (
  `sched_id` int(11) NOT NULL,
  `Services_id` int(11) DEFAULT NULL,
  `ScheduleDate` datetime DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `appointment_type` enum('Service','Consultation','Purchase Materials','N/A') NOT NULL DEFAULT 'N/A',
  `appointment_description` text DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_sched`
--

INSERT INTO `service_sched` (`sched_id`, `Services_id`, `ScheduleDate`, `customer_id`, `appointment_type`, `appointment_description`, `project_id`) VALUES
(1, 10, '2025-10-25 09:00:00', NULL, '', NULL, NULL),
(2, 11, '2025-10-25 14:00:00', NULL, '', NULL, NULL),
(3, 10, '2025-11-03 10:30:00', 4, '', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `Staff_id` int(11) NOT NULL,
  `Name` varchar(50) NOT NULL,
  `address` varchar(50) DEFAULT NULL,
  `PhoneNum` varchar(20) DEFAULT NULL,
  `Email` varchar(50) DEFAULT NULL,
  `Password` varchar(255) NOT NULL,
  `Role` enum('Admin','Staff') NOT NULL,
  `Position` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`Staff_id`, `Name`, `address`, `PhoneNum`, `Email`, `Password`, `Role`, `Position`) VALUES
(101, 'Alice Smith', '123 Main St', '555-0101', 'admin@alg.com', 'admin', 'Admin', 'General Manager'),
(102, 'Bob Johnson', '456 Oak Ave', '555-0102', 'staff@alg.com', 'staff', 'Staff', 'Project Lead'),
(103, 'Charlie Brown', '789 Pine Ln', '555-0103', 'charlie.b@algdb.com', 'hashedpass3', 'Staff', 'Technician');

-- --------------------------------------------------------

--
-- Table structure for table `supplier`
--

CREATE TABLE `supplier` (
  `Supplier_id` int(11) NOT NULL,
  `Company_Name` varchar(50) NOT NULL,
  `Contact_Person` varchar(50) DEFAULT NULL,
  `Email` varchar(50) DEFAULT NULL,
  `Address` varchar(50) DEFAULT NULL,
  `Contact_Num` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier`
--

INSERT INTO `supplier` (`Supplier_id`, `Company_Name`, `Contact_Person`, `Email`, `Address`, `Contact_Num`) VALUES
(501, 'Global Tech Dist.', 'David Lee', 'david@global.com', '100 Supply Road', '555-2001'),
(502, 'Software Keys Co.', 'Emma White', 'emma@softkeys.com', '200 License Center', '555-2002');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `category`
--
ALTER TABLE `category`
  ADD PRIMARY KEY (`Category_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`Customer_id`),
  ADD UNIQUE KEY `Email` (`Email`);

--
-- Indexes for table `orderitems`
--
ALTER TABLE `orderitems`
  ADD PRIMARY KEY (`Items_id`),
  ADD KEY `Orders_id` (`Orders_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `services_id` (`services_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`Orders_id`),
  ADD UNIQUE KEY `Payment_id` (`Payment_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `Services_id` (`Services_id`);

--
-- Indexes for table `payment`
--
ALTER TABLE `payment`
  ADD PRIMARY KEY (`Payment_id`),
  ADD UNIQUE KEY `Orders_id` (`Orders_id`);

--
-- Indexes for table `product`
--
ALTER TABLE `product`
  ADD PRIMARY KEY (`Product_id`),
  ADD KEY `Category_id` (`Category_id`),
  ADD KEY `Supplier_id` (`Supplier_id`);

--
-- Indexes for table `productimports`
--
ALTER TABLE `productimports`
  ADD PRIMARY KEY (`Import_id`),
  ADD KEY `Supplier_id` (`Supplier_id`),
  ADD KEY `Product_id` (`Product_id`),
  ADD KEY `PO_id` (`PO_id`);

--
-- Indexes for table `projectlogs`
--
ALTER TABLE `projectlogs`
  ADD PRIMARY KEY (`Log_id`),
  ADD KEY `Project_id` (`Project_id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`Project_id`),
  ADD KEY `Orders_id` (`Orders_id`);

--
-- Indexes for table `projectstaff`
--
ALTER TABLE `projectstaff`
  ADD PRIMARY KEY (`ProjectStaff_id`),
  ADD UNIQUE KEY `uk_project_staff` (`Project_id`,`Staff_id`),
  ADD KEY `Staff_id` (`Staff_id`);

--
-- Indexes for table `purchaseorders`
--
ALTER TABLE `purchaseorders`
  ADD PRIMARY KEY (`PO_id`),
  ADD UNIQUE KEY `PO_Number` (`PO_Number`),
  ADD KEY `Supplier_id` (`Supplier_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`Services_id`);

--
-- Indexes for table `service_sched`
--
ALTER TABLE `service_sched`
  ADD PRIMARY KEY (`sched_id`),
  ADD KEY `Services_id` (`Services_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`Staff_id`),
  ADD UNIQUE KEY `Email` (`Email`);

--
-- Indexes for table `supplier`
--
ALTER TABLE `supplier`
  ADD PRIMARY KEY (`Supplier_id`),
  ADD UNIQUE KEY `Email` (`Email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `category`
--
ALTER TABLE `category`
  MODIFY `Category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `Customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `orderitems`
--
ALTER TABLE `orderitems`
  MODIFY `Items_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=306;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `Orders_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=204;

--
-- AUTO_INCREMENT for table `payment`
--
ALTER TABLE `payment`
  MODIFY `Payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9004;

--
-- AUTO_INCREMENT for table `product`
--
ALTER TABLE `product`
  MODIFY `Product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT for table `productimports`
--
ALTER TABLE `productimports`
  MODIFY `Import_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `projectlogs`
--
ALTER TABLE `projectlogs`
  MODIFY `Log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `Project_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `projectstaff`
--
ALTER TABLE `projectstaff`
  MODIFY `ProjectStaff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `purchaseorders`
--
ALTER TABLE `purchaseorders`
  MODIFY `PO_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `Services_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `service_sched`
--
ALTER TABLE `service_sched`
  MODIFY `sched_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `Staff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT for table `supplier`
--
ALTER TABLE `supplier`
  MODIFY `Supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=503;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `orderitems`
--
ALTER TABLE `orderitems`
  ADD CONSTRAINT `orderitems_ibfk_1` FOREIGN KEY (`Orders_id`) REFERENCES `orders` (`Orders_id`),
  ADD CONSTRAINT `orderitems_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`Product_id`),
  ADD CONSTRAINT `orderitems_ibfk_3` FOREIGN KEY (`services_id`) REFERENCES `services` (`Services_id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`Services_id`) REFERENCES `services` (`Services_id`),
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`Payment_id`) REFERENCES `payment` (`Payment_id`);

--
-- Constraints for table `product`
--
ALTER TABLE `product`
  ADD CONSTRAINT `product_ibfk_1` FOREIGN KEY (`Category_id`) REFERENCES `category` (`Category_id`),
  ADD CONSTRAINT `product_ibfk_2` FOREIGN KEY (`Supplier_id`) REFERENCES `supplier` (`Supplier_id`);

--
-- Constraints for table `productimports`
--
ALTER TABLE `productimports`
  ADD CONSTRAINT `productimports_ibfk_1` FOREIGN KEY (`Supplier_id`) REFERENCES `supplier` (`Supplier_id`),
  ADD CONSTRAINT `productimports_ibfk_2` FOREIGN KEY (`Product_id`) REFERENCES `product` (`Product_id`),
  ADD CONSTRAINT `productimports_ibfk_3` FOREIGN KEY (`PO_id`) REFERENCES `purchaseorders` (`PO_id`);

--
-- Constraints for table `projectlogs`
--
ALTER TABLE `projectlogs`
  ADD CONSTRAINT `projectlogs_ibfk_1` FOREIGN KEY (`Project_id`) REFERENCES `projects` (`Project_id`) ON DELETE CASCADE;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`Orders_id`) REFERENCES `orders` (`Orders_id`);

--
-- Constraints for table `projectstaff`
--
ALTER TABLE `projectstaff`
  ADD CONSTRAINT `projectstaff_ibfk_1` FOREIGN KEY (`Project_id`) REFERENCES `projects` (`Project_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `projectstaff_ibfk_2` FOREIGN KEY (`Staff_id`) REFERENCES `staff` (`Staff_id`);

--
-- Constraints for table `purchaseorders`
--
ALTER TABLE `purchaseorders`
  ADD CONSTRAINT `purchaseorders_ibfk_1` FOREIGN KEY (`Supplier_id`) REFERENCES `supplier` (`Supplier_id`);

--
-- Constraints for table `service_sched`
--
ALTER TABLE `service_sched`
  ADD CONSTRAINT `service_sched_ibfk_1` FOREIGN KEY (`Services_id`) REFERENCES `services` (`Services_id`),
  ADD CONSTRAINT `service_sched_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`Customer_id`),
  ADD CONSTRAINT `service_sched_ibfk_3` FOREIGN KEY (`project_id`) REFERENCES `projects` (`Project_id`);
COMMIT;
