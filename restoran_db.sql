-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 06, 2025 at 09:54 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `restoran_db`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdateInventoryAfterPreparation` (IN `menu_item_id` INT, IN `servings_prepared` INT)   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE ingredient_id INT;
    DECLARE quantity_needed DECIMAL(10,3);
    
    DECLARE ingredient_cursor CURSOR FOR
        SELECT IngredientID, QuantityRequired * servings_prepared
        FROM recipe_ingredients
        WHERE MenuItemID = menu_item_id;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    START TRANSACTION;
    
    OPEN ingredient_cursor;
    read_loop: LOOP
        FETCH ingredient_cursor INTO ingredient_id, quantity_needed;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        UPDATE inventory 
        SET QuantityInStock = QuantityInStock - quantity_needed,
            LastUpdated = CURRENT_TIMESTAMP
        WHERE IngredientID = ingredient_id
        AND QuantityInStock >= quantity_needed;
        
        -- Check if update was successful
        IF ROW_COUNT() = 0 THEN
            ROLLBACK;
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Insufficient inventory for preparation';
        END IF;
    END LOOP;
    
    CLOSE ingredient_cursor;
    COMMIT;
END$$

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `GetMenuItemCost` (`menu_item_id` INT) RETURNS DECIMAL(10,2) DETERMINISTIC READS SQL DATA BEGIN
    DECLARE total_cost DECIMAL(10,2) DEFAULT 0;
    
    SELECT SUM(ri.QuantityRequired * COALESCE(inv.CostPerUnit, 0))
    INTO total_cost
    FROM recipe_ingredients ri
    JOIN inventory inv ON ri.IngredientID = inv.IngredientID
    WHERE ri.MenuItemID = menu_item_id;
    
    RETURN COALESCE(total_cost, 0);
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `CustomerID` int(11) NOT NULL,
  `Name` varchar(100) DEFAULT NULL,
  `Email` varchar(100) DEFAULT NULL,
  `Phone` varchar(20) DEFAULT NULL,
  `JoinDate` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`CustomerID`, `Name`, `Email`, `Phone`, `JoinDate`) VALUES
(1, 'Jobson', 'job123@gmail.com', '012-3456789', '2025-06-04 23:05:33');

-- --------------------------------------------------------

--
-- Table structure for table `daily_checklist`
--

CREATE TABLE `daily_checklist` (
  `ChecklistID` int(11) NOT NULL,
  `IngredientID` int(11) NOT NULL,
  `Date` date NOT NULL,
  `StaffID` int(11) DEFAULT NULL,
  `IsChecked` tinyint(1) DEFAULT 0,
  `CheckedAt` timestamp NULL DEFAULT NULL,
  `Notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `daily_checklist`
--

INSERT INTO `daily_checklist` (`ChecklistID`, `IngredientID`, `Date`, `StaffID`, `IsChecked`, `CheckedAt`, `Notes`) VALUES
(1, 1, '2025-06-05', NULL, 1, '2025-06-05 09:51:13', NULL),
(2, 2, '2025-06-05', NULL, 1, '2025-06-05 09:51:10', NULL),
(3, 3, '2025-06-05', NULL, 1, '2025-06-05 09:51:11', NULL),
(4, 4, '2025-06-05', NULL, 1, '2025-06-05 09:51:38', NULL),
(5, 5, '2025-06-05', NULL, 1, '2025-06-05 09:58:36', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `expense`
--

CREATE TABLE `expense` (
  `ExpenseID` int(11) NOT NULL,
  `Category` enum('Ingredient Purchase','Salary','Utility','Other') NOT NULL,
  `Amount` decimal(10,2) NOT NULL,
  `ExpenseDate` date NOT NULL,
  `Description` text DEFAULT NULL,
  `SupplierID` int(11) DEFAULT NULL,
  `StaffID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expense`
--

INSERT INTO `expense` (`ExpenseID`, `Category`, `Amount`, `ExpenseDate`, `Description`, `SupplierID`, `StaffID`) VALUES
(1, 'Salary', 1000.00, '2025-05-25', 'May salary', NULL, 1),
(2, 'Salary', 1000.00, '2025-05-25', 'May salary', NULL, 2),
(3, 'Salary', 1000.00, '2025-06-02', 'June Salary', NULL, 2),
(4, 'Ingredient Purchase', 2000.00, '2025-06-02', 'Tomatoes', 3, 1);

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `FeedbackID` int(11) NOT NULL,
  `OrderID` int(11) DEFAULT NULL,
  `CustomerID` int(11) DEFAULT NULL,
  `Rating` int(11) DEFAULT NULL,
  `Comments` text DEFAULT NULL,
  `FeedbackDate` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`FeedbackID`, `OrderID`, `CustomerID`, `Rating`, `Comments`, `FeedbackDate`) VALUES
(1, 11, 1, 5, '', '2025-06-04 23:05:33');

-- --------------------------------------------------------

--
-- Stand-in structure for view `financialsummary`
-- (See below for the actual view)
--
CREATE TABLE `financialsummary` (
`Date` varchar(10)
,`TotalIncome` decimal(32,2)
,`TotalOutcome` decimal(32,2)
,`NetProfit` decimal(33,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `IngredientID` int(11) NOT NULL,
  `IngredientName` varchar(100) NOT NULL,
  `QuantityInStock` decimal(10,2) NOT NULL,
  `UnitOfMeasure` varchar(20) NOT NULL,
  `ReorderLevel` decimal(10,2) NOT NULL,
  `SupplierID` int(11) DEFAULT NULL,
  `ExpiryDate` date DEFAULT NULL,
  `LastUpdated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Category` varchar(50) DEFAULT NULL,
  `CostPerUnit` decimal(10,2) DEFAULT NULL,
  `Notes` text DEFAULT NULL,
  `DateAdded` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`IngredientID`, `IngredientName`, `QuantityInStock`, `UnitOfMeasure`, `ReorderLevel`, `SupplierID`, `ExpiryDate`, `LastUpdated`, `Category`, `CostPerUnit`, `Notes`, `DateAdded`) VALUES
(1, 'Tomato', 99.50, 'kg', 20.00, 1, NULL, '2025-06-05 16:22:26', NULL, NULL, NULL, '2025-06-05 06:37:31'),
(2, 'Cheese', 50.00, 'kg', 10.00, NULL, NULL, '2025-06-05 04:24:54', NULL, NULL, NULL, '2025-06-05 06:37:31'),
(3, 'Flour', 199.40, 'kg', 50.00, 1, NULL, '2025-06-05 16:22:26', NULL, NULL, NULL, '2025-06-05 06:37:31'),
(4, 'Mango', 49.40, 'kg', 10.00, 3, '2025-08-08', '2025-06-05 16:22:26', 'Fruits', 300.00, '', '2025-06-05 09:50:30'),
(5, 'Coffee Beans', 1.70, 'kg', 2.00, 1, '2025-08-07', '2025-06-05 16:22:26', 'Grains', 100.00, '', '2025-06-05 09:58:15'),
(6, 'Spinach', 20.00, 'kg', 4.00, 1, '2025-06-09', '2025-06-05 10:02:34', 'Vegetables', 50.00, '', '2025-06-05 10:02:34');

-- --------------------------------------------------------

--
-- Stand-in structure for view `menu_ingredient_usage`
-- (See below for the actual view)
--
CREATE TABLE `menu_ingredient_usage` (
`MenuItemID` int(11)
,`MenuItemName` varchar(100)
,`Category` enum('Food','Beverages','Food/Noodle','Main Course','Desserts','Appetizers','Snacks')
,`IngredientID` int(11)
,`IngredientName` varchar(100)
,`QuantityRequired` decimal(10,3)
,`RecipeUnit` varchar(20)
,`QuantityInStock` decimal(10,2)
,`StockUnit` varchar(20)
,`CostPerUnit` decimal(10,2)
,`CostPerDish` decimal(20,5)
,`PossibleServings` varchar(22)
,`StockStatus` varchar(18)
);

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `MenuItemID` int(11) NOT NULL,
  `Name` varchar(100) NOT NULL,
  `Description` text DEFAULT NULL,
  `Price` decimal(10,2) NOT NULL,
  `Category` enum('Food','Beverages','Food/Noodle','Main Course','Desserts','Appetizers','Snacks') NOT NULL,
  `IsAvailable` tinyint(1) DEFAULT 1,
  `image_url` varchar(255) DEFAULT NULL,
  `status` enum('available','unavailable') NOT NULL DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`MenuItemID`, `Name`, `Description`, `Price`, `Category`, `IsAvailable`, `image_url`, `status`) VALUES
(1, 'Margherita Pizza', 'Classic pizza with tomato and cheese', 11.99, 'Food', 1, 'https://images.unsplash.com/photo-1513104890138-7c749659a591?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=800&q=80', 'available'),
(3, 'Coffee', 'Freshly brewed coffee', 3.50, 'Beverages', 1, 'https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=800&q=80', 'available'),
(4, 'Palak Paneer Bhurji', 'Cottage cheese scrambled with spinach and spices', 18.90, 'Main Course', 1, 'https://images.unsplash.com/photo-1633945274309-2c16c9682a8c?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=800&q=80', 'available'),
(5, 'Fresh Mango Lassi', 'Refreshing yogurt drink with sweet mango', 8.90, 'Beverages', 1, 'https://minimalistbaker.com/wp-content/uploads/2020/05/Mango-Lassi-Smoothie-SQUARE.jpg', 'available'),
(6, 'Kadai Paneer Gravy', 'Cottage cheese in a rich tomato and bell pepper gravy', 16.50, 'Main Course', 1, 'https://images.unsplash.com/photo-1631452180519-c014fe946bc7?q=80&w=1974&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', 'available');

-- --------------------------------------------------------

--
-- Table structure for table `order`
--

CREATE TABLE `order` (
  `OrderID` int(11) NOT NULL,
  `StaffID` int(11) DEFAULT NULL,
  `OrderDateTime` datetime NOT NULL DEFAULT current_timestamp(),
  `TotalAmount` decimal(10,2) NOT NULL,
  `O_Status` enum('Pending','In Progress','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `OrderType` enum('DineIn','TakeAway') NOT NULL,
  `number` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order`
--

INSERT INTO `order` (`OrderID`, `StaffID`, `OrderDateTime`, `TotalAmount`, `O_Status`, `OrderType`, `number`) VALUES
(9, 6, '2025-06-02 15:55:46', 34.39, 'Completed', 'DineIn', 'T2'),
(10, 8, '2025-06-02 16:15:49', 34.39, 'Completed', 'DineIn', 'T2'),
(11, 8, '2025-06-04 22:49:31', 34.39, 'Completed', 'DineIn', 'T1'),
(12, 3, '2025-06-05 14:29:39', 34.39, 'Completed', 'DineIn', 'T1'),
(16, 3, '2025-06-05 16:10:40', 34.39, 'Completed', 'DineIn', 'T10'),
(17, 3, '2025-06-05 16:10:51', 68.78, 'Completed', 'DineIn', 'T8'),
(18, 3, '2025-06-05 23:25:50', 24.39, 'Completed', 'DineIn', 'T1'),
(19, 3, '2025-06-05 23:55:58', 24.39, 'Completed', 'DineIn', 'T1');

-- --------------------------------------------------------

--
-- Table structure for table `orderitem`
--

CREATE TABLE `orderitem` (
  `OrderItemID` int(11) NOT NULL,
  `OrderID` int(11) DEFAULT NULL,
  `MenuItemID` int(11) DEFAULT NULL,
  `Quantity` int(11) NOT NULL,
  `UnitPrice` decimal(10,2) NOT NULL,
  `Subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orderitem`
--

INSERT INTO `orderitem` (`OrderItemID`, `OrderID`, `MenuItemID`, `Quantity`, `UnitPrice`, `Subtotal`) VALUES
(17, 9, 1, 1, 11.99, 11.99),
(18, 9, 3, 1, 3.50, 3.50),
(19, 9, 4, 1, 18.90, 18.90),
(20, 10, 1, 1, 11.99, 11.99),
(21, 10, 3, 1, 3.50, 3.50),
(22, 10, 4, 1, 18.90, 18.90),
(23, 11, 1, 1, 11.99, 11.99),
(24, 11, 3, 1, 3.50, 3.50),
(25, 11, 4, 1, 18.90, 18.90),
(26, 12, 1, 1, 11.99, 11.99),
(27, 12, 3, 1, 3.50, 3.50),
(28, 12, 4, 1, 18.90, 18.90),
(36, 16, 1, 1, 11.99, 11.99),
(37, 16, 3, 1, 3.50, 3.50),
(38, 16, 4, 1, 18.90, 18.90),
(39, 17, 1, 2, 11.99, 23.98),
(40, 17, 3, 2, 3.50, 7.00),
(41, 17, 4, 2, 18.90, 37.80),
(42, 18, 3, 1, 3.50, 3.50),
(43, 18, 1, 1, 11.99, 11.99),
(44, 18, 5, 1, 8.90, 8.90),
(45, 19, 1, 1, 11.99, 11.99),
(46, 19, 3, 1, 3.50, 3.50),
(47, 19, 5, 1, 8.90, 8.90);

-- --------------------------------------------------------

--
-- Table structure for table `payment`
--

CREATE TABLE `payment` (
  `PaymentID` int(11) NOT NULL,
  `OrderID` int(11) DEFAULT NULL,
  `Amount` decimal(10,2) NOT NULL,
  `PaymentMethod` enum('Cash','Credit Card','Digital Wallet') NOT NULL,
  `PaymentDateTime` datetime NOT NULL DEFAULT current_timestamp(),
  `Status` enum('Completed','Pending','Refunded') NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment`
--

INSERT INTO `payment` (`PaymentID`, `OrderID`, `Amount`, `PaymentMethod`, `PaymentDateTime`, `Status`) VALUES
(3, 9, 34.39, '', '2025-06-02 16:00:51', 'Completed'),
(4, 10, 34.39, 'Cash', '2025-06-02 16:16:34', 'Completed'),
(5, 11, 34.39, 'Cash', '2025-06-04 22:49:52', 'Completed');

-- --------------------------------------------------------

--
-- Table structure for table `recipe_ingredients`
--

CREATE TABLE `recipe_ingredients` (
  `RecipeID` int(11) NOT NULL,
  `MenuItemID` int(11) NOT NULL,
  `IngredientID` int(11) NOT NULL,
  `QuantityRequired` decimal(10,3) NOT NULL,
  `UnitOfMeasure` varchar(20) NOT NULL,
  `Notes` text DEFAULT NULL,
  `CreatedDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `LastUpdated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recipe_ingredients`
--

INSERT INTO `recipe_ingredients` (`RecipeID`, `MenuItemID`, `IngredientID`, `QuantityRequired`, `UnitOfMeasure`, `Notes`, `CreatedDate`, `LastUpdated`) VALUES
(1, 1, 1, 0.250, 'kg', 'Pizza Sauce', '2025-06-05 15:14:20', '2025-06-05 15:22:45'),
(2, 1, 3, 0.300, 'kg', 'Main Ingredient Making Pizza Dough', '2025-06-05 15:14:20', '2025-06-05 15:14:20'),
(9, 3, 5, 0.250, 'kg', 'Main Ingredient for Coffee', '2025-06-05 15:25:07', '2025-06-05 15:25:07'),
(10, 5, 4, 0.300, 'kg', 'Main Ingredient for Mango Lassi', '2025-06-05 15:25:07', '2025-06-05 15:25:07');

-- --------------------------------------------------------

--
-- Table structure for table `refunds`
--

CREATE TABLE `refunds` (
  `RefundID` int(11) NOT NULL,
  `PaymentID` int(11) NOT NULL,
  `RefundAmount` decimal(10,2) NOT NULL,
  `Reason` text NOT NULL,
  `RefundDateTime` datetime DEFAULT current_timestamp(),
  `ProcessedBy` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report`
--

CREATE TABLE `report` (
  `ReportID` int(11) NOT NULL,
  `ReportMonth` year(4) NOT NULL,
  `ReportPeriod` varchar(7) NOT NULL,
  `TotalSales` decimal(10,2) DEFAULT NULL,
  `TotalExpenses` decimal(10,2) DEFAULT NULL,
  `TotalSalaries` decimal(10,2) DEFAULT NULL,
  `GeneratedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `report`
--

INSERT INTO `report` (`ReportID`, `ReportMonth`, `ReportPeriod`, `TotalSales`, `TotalExpenses`, `TotalSalaries`, `GeneratedAt`) VALUES
(1, '2025', '2025-06', NULL, NULL, NULL, '2025-06-01 10:12:12'),
(2, '2025', '2025-06', NULL, NULL, NULL, '2025-06-01 10:14:30'),
(3, '2025', '2025-06', NULL, NULL, NULL, '2025-06-01 10:20:17'),
(4, '2025', '2025-06', NULL, NULL, NULL, '2025-06-01 10:20:23'),
(5, '2025', '2025-06', NULL, NULL, NULL, '2025-06-01 10:20:48'),
(6, '2025', '2025-05', 12.99, 2000.00, 2000.00, '2025-06-01 10:20:53'),
(7, '2025', '2025-04', NULL, NULL, NULL, '2025-06-01 10:21:09'),
(8, '2025', '2025-06', NULL, NULL, NULL, '2025-06-01 13:28:32'),
(9, '2025', '2025-06', 68.78, NULL, NULL, '2025-06-02 08:21:38'),
(10, '2025', '2025-06', 68.78, NULL, NULL, '2025-06-02 08:34:37'),
(11, '2025', '2025-06', 68.78, 1000.00, 1000.00, '2025-06-02 09:10:14'),
(12, '2025', '2025-06', 103.17, 3000.00, 1000.00, '2025-06-04 15:31:44'),
(13, '2025', '2025-06', 103.17, 3000.00, 1000.00, '2025-06-04 15:56:20'),
(14, '2025', '2025-06', 103.17, 3000.00, 1000.00, '2025-06-04 15:56:25'),
(15, '2025', '2025-06', 103.17, 3000.00, 1000.00, '2025-06-04 15:56:26'),
(16, '2025', '2025-06', 103.17, 3000.00, 1000.00, '2025-06-04 15:56:28'),
(17, '2025', '2025-06', 103.17, 3000.00, 1000.00, '2025-06-04 15:56:29'),
(18, '2025', '2025-06', 103.17, 3000.00, 1000.00, '2025-06-04 16:15:59'),
(19, '2025', '2025-06', 103.17, 3000.00, 1000.00, '2025-06-04 16:17:44'),
(20, '2025', '2025-06', 103.17, 3000.00, 1000.00, '2025-06-05 07:04:15'),
(21, '2025', '2025-06', 103.17, 3000.00, 1000.00, '2025-06-05 07:40:57');

-- --------------------------------------------------------

--
-- Table structure for table `salary`
--

CREATE TABLE `salary` (
  `SalaryID` int(11) NOT NULL,
  `StaffID` int(11) NOT NULL,
  `Amount` decimal(10,2) NOT NULL,
  `PaymentDate` date NOT NULL,
  `PaymentType` enum('Monthly','Bonus','Overtime') NOT NULL,
  `Description` text DEFAULT NULL,
  `ShiftHour` decimal(5,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `salary`
--

INSERT INTO `salary` (`SalaryID`, `StaffID`, `Amount`, `PaymentDate`, `PaymentType`, `Description`, `ShiftHour`) VALUES
(1, 1, 1000.00, '2025-05-25', 'Monthly', 'May salary', 8.00),
(2, 2, 1000.00, '2025-05-25', 'Monthly', 'May salary', 8.00),
(3, 2, 1000.00, '2025-06-02', 'Monthly', 'June Salary', 8.00);

--
-- Triggers `salary`
--
DELIMITER $$
CREATE TRIGGER `AfterSalaryInsert` AFTER INSERT ON `salary` FOR EACH ROW BEGIN
    INSERT INTO Expense (Category, Amount, ExpenseDate, Description, StaffID)
    VALUES ('Salary', NEW.Amount, NEW.PaymentDate, NEW.Description, NEW.StaffID);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `StaffID` int(11) NOT NULL,
  `FirstName` varchar(50) NOT NULL,
  `LastName` varchar(50) NOT NULL,
  `Role` enum('Waiter','Chef','Manager','Cashier') NOT NULL,
  `Phone` varchar(15) DEFAULT NULL,
  `Email` varchar(100) DEFAULT NULL,
  `HireDate` date NOT NULL,
  `IsActive` tinyint(1) DEFAULT 1,
  `Address` text DEFAULT NULL,
  `ICPassportNo` varchar(20) DEFAULT NULL,
  `Username` varchar(50) DEFAULT NULL,
  `Password` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`StaffID`, `FirstName`, `LastName`, `Role`, `Phone`, `Email`, `HireDate`, `IsActive`, `Address`, `ICPassportNo`, `Username`, `Password`) VALUES
(1, 'Alice', 'Smith', 'Manager', '555-1111', 'alice@restoran.com', '2025-01-01', 1, '789 Pine St, Kuala Lumpur', '950101-14-1234', 'alice', '$2y$10$yK1Ae07cT782DLFi9Zk2eOmlpVlLf5rmOk4W.qi0LD5ymOVfV.pTq'),
(2, 'Bob', 'Johnson', 'Chef', '555-2222', 'bob@restoran.com', '2024-12-01', 1, '456 Oak Rd, Petaling Jaya', 'A12345678', NULL, '$2y$10$zKwTU/MnbkVu1LUMZ0ai4.1foJOxem1wV6.QmCWDZogH4LbXPoEA6'),
(3, 'Moses', 'Yii', 'Manager', '016-8700123', 'annsingyii@gmail.com', '2025-05-27', 1, '108, Lorong 2, Taman Bina, 94300 Kuching, Sarawak', '031118-13-0065', 'moses_yii', '$2y$10$4wrBThzvxBVUV6DtXaSLVuu1C5WPWJR5TceL0U3xOQeX73ZY5ruGu'),
(4, 'Jared', 'Lee', 'Waiter', '018-8700123', 'bb.anna93@gmail.com', '2025-05-27', 1, '21,Jalan Bina 21, 34000 Ipoh, Perak', '031010-01-5055', 'Jared', '$2y$10$PDf32ww4E5onihXLEfJgy.MCVZQm61Ms9d0MoKU1mZ0dSC7Zsbq.S'),
(5, 'YB', 'TAN', 'Manager', '018-7743729', 'yuebaotan123@gmail.com', '2025-05-28', 1, 'No17, Jalan Baidu 10', '030329010675', 'YB', '$2y$10$8bDGcAq3XzpZ87yus3otYOYFPcB8ZprU2BVaFmVOYJwFdNnl5AlYi'),
(6, 'lai', 'xx', 'Waiter', '012-3456789', 'laixx123@gmail.com', '2025-06-01', 1, '12,Jalan Bunga, Taman Parit Raja', '031110014555', 'laixx', '$2y$10$ODssVTWFeHbz4GnydZ97beimUcG/3eMRG..FX28zJOIm8TCg6fqLa'),
(7, 'Divakhar', 'Tan', 'Chef', '016-8700111', 'annsingyii2@gmail.com', '2025-06-01', 1, '18,Jalan UTHM,Taman Buang', '031110014444', 'Divakhar', '$2y$10$.mUhbo/7N/ZKBDVRSvLBPOcsa76cRMJBuRpm7viK2Zmbu4tYU4/IK'),
(8, 'TanB', 'Yii', 'Cashier', '016-8700124', 'annsingyii3@gmail.com', '2025-06-01', 1, '44,Jalan Buang, Taman Bunga', '031110014666', 'TanB', '$2y$10$XWVyK/44cAgnuU9G0QOunes2wDsiVjsavftqtfl3WUHIxQFcn8EE6');

-- --------------------------------------------------------

--
-- Table structure for table `supplier`
--

CREATE TABLE `supplier` (
  `SupplierID` int(11) NOT NULL,
  `Name` varchar(100) NOT NULL,
  `ContactInfo` varchar(100) DEFAULT NULL,
  `Address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier`
--

INSERT INTO `supplier` (`SupplierID`, `Name`, `ContactInfo`, `Address`) VALUES
(1, 'Fresh Farms', '555-5678', '123 Market St'),
(3, 'BSH Enterprise (M) Sdn Bhd', '03-5191-7888', '20, Jalan Anggerik Mokara 31/59, Kota Kemuning, 40460 Shah Alam, Selangor, Malaysia.');

-- --------------------------------------------------------

--
-- Table structure for table `tables`
--

CREATE TABLE `tables` (
  `id` int(11) NOT NULL,
  `number` varchar(10) NOT NULL,
  `capacity` int(11) NOT NULL,
  `status` enum('available','occupied') DEFAULT 'available',
  `waiter` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tables`
--

INSERT INTO `tables` (`id`, `number`, `capacity`, `status`, `waiter`) VALUES
(1, 'T1', 4, 'occupied', NULL),
(2, 'T2', 4, 'available', NULL),
(3, 'T3', 4, 'available', NULL),
(4, 'T4', 6, 'available', NULL),
(5, 'T5', 6, 'available', NULL),
(6, 'T6', 2, 'available', NULL),
(7, 'T7', 2, 'available', NULL),
(8, 'T8', 8, 'available', NULL),
(9, 'T9', 8, 'available', NULL),
(10, 'T10', 4, 'available', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `workshift`
--

CREATE TABLE `workshift` (
  `ShiftID` int(11) NOT NULL,
  `StaffID` int(11) NOT NULL,
  `StartDateTime` datetime NOT NULL,
  `EndDateTime` datetime NOT NULL,
  `ShiftType` enum('Morning','Afternoon','Evening') NOT NULL,
  `Notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workshift`
--

INSERT INTO `workshift` (`ShiftID`, `StaffID`, `StartDateTime`, `EndDateTime`, `ShiftType`, `Notes`) VALUES
(1, 1, '2025-05-25 08:00:00', '2025-05-25 16:00:00', 'Morning', 'Serving tables'),
(2, 2, '2025-05-25 07:00:00', '2025-05-25 15:00:00', 'Morning', 'Kitchen duty'),
(5, 3, '2025-05-28 18:02:39', '2025-05-28 18:02:50', 'Evening', NULL),
(6, 3, '2025-05-28 18:06:57', '2025-05-28 18:07:08', 'Evening', 'Serving Table\n2025-05-28 18:07: Cleaning'),
(7, 4, '2025-05-29 05:51:18', '2025-05-29 05:51:23', 'Morning', ''),
(8, 4, '2025-05-29 05:51:31', '2025-05-29 05:51:41', 'Morning', ''),
(9, 4, '2025-05-29 05:52:05', '2025-05-29 05:52:07', 'Morning', ''),
(10, 4, '2025-05-29 11:57:07', '2025-05-29 11:57:14', 'Morning', ''),
(21, 4, '2025-05-29 14:54:47', '2025-05-29 14:54:53', 'Afternoon', ''),
(22, 4, '2025-05-29 14:56:21', '2025-05-29 14:56:49', 'Afternoon', ''),
(23, 4, '2025-05-29 15:09:57', '2025-05-29 15:13:07', 'Afternoon', '');

--
-- Triggers `workshift`
--
DELIMITER $$
CREATE TRIGGER `AfterWorkShiftInsert` AFTER INSERT ON `workshift` FOR EACH ROW BEGIN
    DECLARE shift_hours DECIMAL(5,2);
    DECLARE overtime_rate DECIMAL(10,2) DEFAULT 20.00;
    SET shift_hours = TIMESTAMPDIFF(HOUR, NEW.StartDateTime, NEW.EndDateTime);
    IF shift_hours > 8 THEN
        INSERT INTO Salary (StaffID, Amount, PaymentDate, PaymentType, Description)
        VALUES (NEW.StaffID, (shift_hours - 8) * overtime_rate, DATE(NEW.EndDateTime), 'Overtime', 
                CONCAT('Overtime for shift on ', DATE_FORMAT(NEW.StartDateTime, '%Y-%m-%d')));
        INSERT INTO Expense (Category, Amount, ExpenseDate, StaffID, Description)
        VALUES ('Salary', (shift_hours - 8) * overtime_rate, DATE(NEW.EndDateTime), NEW.StaffID, 
                CONCAT('Overtime for shift on ', DATE_FORMAT(NEW.StartDateTime, '%Y-%m-%d')));
    END IF;
END
$$
DELIMITER ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`CustomerID`);

--
-- Indexes for table `daily_checklist`
--
ALTER TABLE `daily_checklist`
  ADD PRIMARY KEY (`ChecklistID`),
  ADD KEY `fk_checklist_inventory` (`IngredientID`),
  ADD KEY `fk_checklist_staff` (`StaffID`);

--
-- Indexes for table `expense`
--
ALTER TABLE `expense`
  ADD PRIMARY KEY (`ExpenseID`),
  ADD KEY `SupplierID` (`SupplierID`),
  ADD KEY `StaffID` (`StaffID`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`FeedbackID`),
  ADD KEY `OrderID` (`OrderID`),
  ADD KEY `CustomerID` (`CustomerID`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`IngredientID`),
  ADD KEY `SupplierID` (`SupplierID`);

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`MenuItemID`);

--
-- Indexes for table `order`
--
ALTER TABLE `order`
  ADD PRIMARY KEY (`OrderID`),
  ADD KEY `StaffID` (`StaffID`),
  ADD KEY `order_ibfk_table` (`number`);

--
-- Indexes for table `orderitem`
--
ALTER TABLE `orderitem`
  ADD PRIMARY KEY (`OrderItemID`),
  ADD KEY `OrderID` (`OrderID`),
  ADD KEY `MenuItemID` (`MenuItemID`);

--
-- Indexes for table `payment`
--
ALTER TABLE `payment`
  ADD PRIMARY KEY (`PaymentID`),
  ADD UNIQUE KEY `OrderID` (`OrderID`);

--
-- Indexes for table `recipe_ingredients`
--
ALTER TABLE `recipe_ingredients`
  ADD PRIMARY KEY (`RecipeID`),
  ADD UNIQUE KEY `unique_menu_ingredient` (`MenuItemID`,`IngredientID`),
  ADD KEY `MenuItemID` (`MenuItemID`),
  ADD KEY `IngredientID` (`IngredientID`);

--
-- Indexes for table `refunds`
--
ALTER TABLE `refunds`
  ADD PRIMARY KEY (`RefundID`),
  ADD KEY `PaymentID` (`PaymentID`);

--
-- Indexes for table `report`
--
ALTER TABLE `report`
  ADD PRIMARY KEY (`ReportID`);

--
-- Indexes for table `salary`
--
ALTER TABLE `salary`
  ADD PRIMARY KEY (`SalaryID`),
  ADD KEY `StaffID` (`StaffID`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`StaffID`),
  ADD UNIQUE KEY `Username` (`Username`);

--
-- Indexes for table `supplier`
--
ALTER TABLE `supplier`
  ADD PRIMARY KEY (`SupplierID`);

--
-- Indexes for table `tables`
--
ALTER TABLE `tables`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_table_number` (`number`);

--
-- Indexes for table `workshift`
--
ALTER TABLE `workshift`
  ADD PRIMARY KEY (`ShiftID`),
  ADD KEY `StaffID` (`StaffID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `CustomerID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `daily_checklist`
--
ALTER TABLE `daily_checklist`
  MODIFY `ChecklistID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `expense`
--
ALTER TABLE `expense`
  MODIFY `ExpenseID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `FeedbackID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `IngredientID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `MenuItemID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `order`
--
ALTER TABLE `order`
  MODIFY `OrderID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `orderitem`
--
ALTER TABLE `orderitem`
  MODIFY `OrderItemID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `payment`
--
ALTER TABLE `payment`
  MODIFY `PaymentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `recipe_ingredients`
--
ALTER TABLE `recipe_ingredients`
  MODIFY `RecipeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `refunds`
--
ALTER TABLE `refunds`
  MODIFY `RefundID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report`
--
ALTER TABLE `report`
  MODIFY `ReportID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `salary`
--
ALTER TABLE `salary`
  MODIFY `SalaryID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `StaffID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `supplier`
--
ALTER TABLE `supplier`
  MODIFY `SupplierID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tables`
--
ALTER TABLE `tables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `workshift`
--
ALTER TABLE `workshift`
  MODIFY `ShiftID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

-- --------------------------------------------------------

--
-- Structure for view `financialsummary`
--
DROP TABLE IF EXISTS `financialsummary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `financialsummary`  AS SELECT date_format(`p`.`PaymentDateTime`,'%Y-%m-%d') AS `Date`, coalesce(sum(`p`.`Amount`),0) AS `TotalIncome`, coalesce((select sum(`e`.`Amount`) from `expense` `e` where cast(`e`.`ExpenseDate` as date) = cast(`p`.`PaymentDateTime` as date)),0) AS `TotalOutcome`, coalesce(sum(`p`.`Amount`),0) - coalesce((select sum(`e`.`Amount`) from `expense` `e` where cast(`e`.`ExpenseDate` as date) = cast(`p`.`PaymentDateTime` as date)),0) AS `NetProfit` FROM `payment` AS `p` WHERE `p`.`Status` = 'Completed' GROUP BY cast(`p`.`PaymentDateTime` as date)union select date_format(`e`.`ExpenseDate`,'%Y-%m-%d') AS `Date`,coalesce((select sum(`p`.`Amount`) from `payment` `p` where `p`.`Status` = 'Completed' and cast(`p`.`PaymentDateTime` as date) = cast(`e`.`ExpenseDate` as date)),0) AS `TotalIncome`,coalesce(sum(`e`.`Amount`),0) AS `TotalOutcome`,coalesce((select sum(`p`.`Amount`) from `payment` `p` where `p`.`Status` = 'Completed' and cast(`p`.`PaymentDateTime` as date) = cast(`e`.`ExpenseDate` as date)),0) - coalesce(sum(`e`.`Amount`),0) AS `NetProfit` from `expense` `e` group by cast(`e`.`ExpenseDate` as date)  ;

-- --------------------------------------------------------

--
-- Structure for view `menu_ingredient_usage`
--
DROP TABLE IF EXISTS `menu_ingredient_usage`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `menu_ingredient_usage`  AS SELECT `mi`.`MenuItemID` AS `MenuItemID`, `mi`.`Name` AS `MenuItemName`, `mi`.`Category` AS `Category`, `ri`.`IngredientID` AS `IngredientID`, `inv`.`IngredientName` AS `IngredientName`, `ri`.`QuantityRequired` AS `QuantityRequired`, `ri`.`UnitOfMeasure` AS `RecipeUnit`, `inv`.`QuantityInStock` AS `QuantityInStock`, `inv`.`UnitOfMeasure` AS `StockUnit`, `inv`.`CostPerUnit` AS `CostPerUnit`, `ri`.`QuantityRequired`* `inv`.`CostPerUnit` AS `CostPerDish`, CASE WHEN `ri`.`UnitOfMeasure` = `inv`.`UnitOfMeasure` THEN floor(`inv`.`QuantityInStock` / `ri`.`QuantityRequired`) ELSE 'Unit conversion needed' END AS `PossibleServings`, CASE WHEN `inv`.`QuantityInStock` < `ri`.`QuantityRequired` THEN 'Insufficient Stock' WHEN `inv`.`QuantityInStock` < `inv`.`ReorderLevel` THEN 'Low Stock' ELSE 'Sufficient Stock' END AS `StockStatus` FROM ((`menu_items` `mi` join `recipe_ingredients` `ri` on(`mi`.`MenuItemID` = `ri`.`MenuItemID`)) join `inventory` `inv` on(`ri`.`IngredientID` = `inv`.`IngredientID`)) WHERE `mi`.`IsAvailable` = 1 AND `mi`.`status` = 'available' ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `daily_checklist`
--
ALTER TABLE `daily_checklist`
  ADD CONSTRAINT `fk_checklist_inventory` FOREIGN KEY (`IngredientID`) REFERENCES `inventory` (`IngredientID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_checklist_staff` FOREIGN KEY (`StaffID`) REFERENCES `staff` (`StaffID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `expense`
--
ALTER TABLE `expense`
  ADD CONSTRAINT `expense_ibfk_1` FOREIGN KEY (`SupplierID`) REFERENCES `supplier` (`SupplierID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `expense_ibfk_2` FOREIGN KEY (`StaffID`) REFERENCES `staff` (`StaffID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`OrderID`) REFERENCES `order` (`OrderID`),
  ADD CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`CustomerID`) REFERENCES `customers` (`CustomerID`);

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`SupplierID`) REFERENCES `supplier` (`SupplierID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `order`
--
ALTER TABLE `order`
  ADD CONSTRAINT `order_ibfk_1` FOREIGN KEY (`StaffID`) REFERENCES `staff` (`StaffID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `order_ibfk_table` FOREIGN KEY (`number`) REFERENCES `tables` (`number`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `orderitem`
--
ALTER TABLE `orderitem`
  ADD CONSTRAINT `orderitem_ibfk_1` FOREIGN KEY (`OrderID`) REFERENCES `order` (`OrderID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `orderitem_ibfk_2` FOREIGN KEY (`MenuItemID`) REFERENCES `menu_items` (`MenuItemID`) ON UPDATE CASCADE;

--
-- Constraints for table `payment`
--
ALTER TABLE `payment`
  ADD CONSTRAINT `payment_ibfk_1` FOREIGN KEY (`OrderID`) REFERENCES `order` (`OrderID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `recipe_ingredients`
--
ALTER TABLE `recipe_ingredients`
  ADD CONSTRAINT `recipe_ingredients_ibfk_1` FOREIGN KEY (`MenuItemID`) REFERENCES `menu_items` (`MenuItemID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `recipe_ingredients_ibfk_2` FOREIGN KEY (`IngredientID`) REFERENCES `inventory` (`IngredientID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `refunds`
--
ALTER TABLE `refunds`
  ADD CONSTRAINT `refunds_ibfk_1` FOREIGN KEY (`PaymentID`) REFERENCES `payment` (`PaymentID`);

--
-- Constraints for table `salary`
--
ALTER TABLE `salary`
  ADD CONSTRAINT `salary_ibfk_1` FOREIGN KEY (`StaffID`) REFERENCES `staff` (`StaffID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `workshift`
--
ALTER TABLE `workshift`
  ADD CONSTRAINT `workshift_ibfk_1` FOREIGN KEY (`StaffID`) REFERENCES `staff` (`StaffID`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
