<?php
session_start();
require_once 'db.php';

// Check if user came from payment page
if (!isset($_SESSION['feedback_order']) || !isset($_SESSION['Username'])) {
    header("Location: cashier_payment.php");
    exit();
}

// Display payment success message if it exists
$payment_success = '';
if (isset($_SESSION['payment_success'])) {
    $payment_success = $_SESSION['payment_success'];
    unset($_SESSION['payment_success']); // Clear the message after displaying
}

$order_id = $_SESSION['feedback_order'];
$error_message = '';
$success_message = '';

// Get order details (updated for combined orders)
$order_query = $conn->prepare("SELECT 
                    o.OrderID,
                    o.number as table_number,
                    o.TotalAmount,
                    (
                        SELECT GROUP_CONCAT(mi.Name SEPARATOR ', ') 
                        FROM orderitem oi 
                        JOIN menu_items mi ON oi.MenuItemID = mi.MenuItemID 
                        WHERE oi.OrderID = o.OrderID
                    ) as items,
                    (
                        SELECT GROUP_CONCAT(DISTINCT o2.OrderID) 
                        FROM `order` o2 
                        WHERE o2.number = o.number 
                        AND o2.O_Status = 'Completed'
                        AND NOT EXISTS (
                            SELECT 1 FROM payment p 
                            WHERE p.OrderID = o2.OrderID 
                            AND p.Status = 'Completed'
                        )
                    ) as combined_order_ids,
                    (
                        SELECT COUNT(DISTINCT o3.OrderID)
                        FROM `order` o3
                        WHERE o3.number = o.number
                        AND o3.O_Status = 'Completed'
                        AND NOT EXISTS (
                            SELECT 1 FROM payment p 
                            WHERE p.OrderID = o3.OrderID 
                            AND p.Status = 'Completed'
                        )
                    ) as order_count
                FROM `order` o
                WHERE o.OrderID = ?");
$order_query->bind_param("i", $order_id);
$order_query->execute();
$order_result = $order_query->get_result();
$order = $order_result->fetch_assoc();

// Get combined order numbers if this is a combined order
if ($order['order_count'] > 1) {
    $combined_ids = explode(',', $order['combined_order_ids']);
    $order_numbers = array_map(function($id) {
        return '#' . str_pad($id, 4, '0', STR_PAD_LEFT);
    }, $combined_ids);
    $order['order_numbers'] = implode(', ', $order_numbers);
    $order['combined_total'] = $order['TotalAmount']; // You might want to calculate the sum of all combined orders
}

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $rating = intval($_POST['rating']);
    $comments = $conn->real_escape_string($_POST['comments']);
    
    // Basic validation
    if (empty($name)) {
        $error_message = "Name is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($email)) {
        $error_message = "Invalid email format";
    } elseif ($rating < 1 || $rating > 5) {
        $error_message = "Please select a rating between 1-5";
    } else {
        $conn->begin_transaction();
        
        try {
            // Check if customer exists (by email or phone)
            $customer_id = null;
            if (!empty($email)) {
                $check_customer = "SELECT CustomerID FROM customers WHERE Email = '$email'";
                $result = $conn->query($check_customer);
                if ($result->num_rows > 0) {
                    $customer_id = $result->fetch_assoc()['CustomerID'];
                }
            }
            
            // If new customer, insert
            if (!$customer_id) {
                $insert_customer = "INSERT INTO customers (Name, Email, Phone) 
                                    VALUES ('$name', '$email', '$phone')";
                $conn->query($insert_customer);
                $customer_id = $conn->insert_id;
            }
            
            // Insert feedback
            $insert_feedback = "INSERT INTO feedback (OrderID, CustomerID, Rating, Comments)
                                VALUES ($order_id, $customer_id, $rating, '$comments')";
            $conn->query($insert_feedback);
            
            $conn->commit();
            $success_message = "Thank you for your feedback!";
            
            // Clear session and redirect after 3 seconds
            unset($_SESSION['feedback_order']);
            header("Refresh: 3; url=cashier_payment.php");
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error submitting feedback: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Feedback | Restoran Badhriah</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #000;
            color: #fff;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Main Content */
        .main {
            min-height: 100vh;
            background: radial-gradient(ellipse at top, #111 0%, #000 100%);
        }

        .header {
            padding: 40px 40px 20px;
            text-align: center;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, transparent 100%);
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #fff 0%, #ccc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        /* Alert Styles */
        .alert {
            max-width: 450px;
            margin: 0 auto 2rem;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            font-weight: 500;
            text-align: center;
            backdrop-filter: blur(10px);
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #22c55e;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }

        /* Feedback Container */
        .feedback-container {
            max-width: 400px; /* Reduced from 450px */
            margin: 0 auto 3rem; /* Reduced bottom margin */
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px; /* Slightly smaller radius */
            padding: 1.5rem; /* Reduced from 2rem */
            backdrop-filter: blur(20px);
            position: relative;
            overflow: hidden;
            transform: scale(0.95); /* Slightly scales down the whole form */
        }

        .feedback-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
        }

        /* Order Summary */
        .order-summary {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            position: relative;
        }

        .order-summary h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: #fff;
        }

        .order-summary p {
            color: #ccc;
            margin-bottom: 0.3rem;
            font-weight: 400;
            font-size: 0.85rem;
        }

        .order-summary p:last-child {
            margin-bottom: 0;
            font-weight: 600;
            color: #fff;
            font-size: 1rem;
        }

        /* Form Styles */
        form h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            text-align: center;
            color: #fff;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #e5e5e5;
            font-size: 0.9rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem 0.875rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.4);
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
        }

        .form-group input::placeholder {
            color: #888;
        }

        /* Rating Section */
        form h4 {
            font-size: 1.1rem;
            font-weight: 500;
            text-align: center;
            margin-bottom: 0.75rem;
            color: #e5e5e5;
        }

        .rating-stars {
            display: flex;
            justify-content: center;
            gap: 0.3rem;
            margin: 1.5rem 0 2rem;
            direction: rtl;
        }

        .rating-stars input {
            display: none;
        }

        .rating-stars label {
            font-size: 2rem;
            color: rgba(255, 255, 255, 0.2);
            cursor: pointer;
            transition: all 0.3s ease;
            line-height: 1;
            position: relative;
        }

        .rating-stars label:hover,
        .rating-stars label:hover ~ label {
            color: #fff;
            transform: scale(1.1);
        }

        .rating-stars input:checked ~ label {
            color: #fff;
        }

        .rating-stars label:active {
            transform: scale(0.95);
        }

        /* Textarea */
        textarea {
            width: 100%;
            min-height: 80px;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            resize: vertical;
            transition: all 0.3s ease;
        }

        textarea:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.4);
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
        }

        textarea::placeholder {
            color: #888;
        }

        /* Submit Button */
        .submit-btn {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, #fff 0%, #e5e5e5 100%);
            color: #000;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.5s;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 255, 255, 0.2);
        }

        .submit-btn:hover::before {
            left: 100%;
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        /* Skip Button */
        .skip-btn {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            font-weight: 400;
            transition: all 0.3s ease;
            padding: 0.5rem;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .skip-btn:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.05);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .feedback-container {
                margin: 0 1rem 1.5rem;
                padding: 1.25rem;
                transform: scale(1);
            }

            .header {
                padding: 30px 20px 15px;
            }

            .header h1 {
                font-size: 1.75rem;
            }

            .rating-stars label {
                font-size: 1.8rem;
            }
        }

        /* Loading Animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .feedback-container {
            animation: fadeIn 0.6s ease-out;
        }

        /* Smooth Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #111;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        .alert-payment-success {
            background: rgba(34, 197, 94, 0.2);
            border: 1px solid rgba(34, 197, 94, 0.4);
            color: #22c55e;
            font-size: 1.1rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            animation: slideDown 0.5s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="main" id="main">
        <div class="header">
            <h1>Customer Feedback</h1>
        </div>

        <?php if (!empty($payment_success)): ?>
            <div class="alert alert-payment-success">
                ✅ <?= htmlspecialchars($payment_success) ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                ✅ <?= htmlspecialchars($success_message) ?>
            </div>
        <?php elseif ($error_message): ?>
            <div class="alert alert-error">
                ❌ <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <div class="feedback-container">
            <div class="order-summary">
                <h3>
                    <?php if ($order['order_count'] > 1): ?>
                        Combined Orders <?= htmlspecialchars($order['order_numbers']) ?>
                    <?php else: ?>
                        Order #<?= str_pad($order_id, 4, '0', STR_PAD_LEFT) ?>
                    <?php endif; ?>
                </h3>
                <p>Table: <?= htmlspecialchars($order['table_number']) ?></p>
                <p>Items: <?= htmlspecialchars($order['items']) ?></p>
                <p>Total: RM <?= number_format($order['order_count'] > 1 ? $order['combined_total'] : $order['TotalAmount'], 2) ?></p>
            </div>

            <form method="POST" id="feedbackForm">
                <h3>Help us improve our service</h3>
                
                <div class="form-group">
                    <label for="name">Name *</label>
                    <input type="text" id="name" name="name" required placeholder="Enter your name">
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="your@email.com">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" placeholder="+60 12-345 6789">
                </div>
                
                <h4>How would you rate your experience?</h4>
                <div class="rating-stars">
                    <input type="radio" id="star5" name="rating" value="5" required>
                    <label for="star5">★</label>
                    <input type="radio" id="star4" name="rating" value="4">
                    <label for="star4">★</label>
                    <input type="radio" id="star3" name="rating" value="3">
                    <label for="star3">★</label>
                    <input type="radio" id="star2" name="rating" value="2">
                    <label for="star2">★</label>
                    <input type="radio" id="star1" name="rating" value="1">
                    <label for="star1">★</label>
                </div>
                
                <div class="form-group">
                    <label for="comments">Comments or Suggestions</label>
                    <textarea id="comments" name="comments" placeholder="What did you like or how can we improve?"></textarea>
                </div>
                
                <button type="submit" name="submit_feedback" class="submit-btn">
                    Submit Feedback
                </button>
                
                <a href="cashier_payment.php" class="skip-btn">Skip Feedback</a>
            </form>
        </div>
    </div>

    <script>
        // Enhanced star rating interaction
        document.querySelectorAll('.rating-stars input').forEach(star => {
            star.addEventListener('change', function() {
                const rating = this.value;
                console.log(`Rating selected: ${rating}`);
            });
        });

        // Form submission animation
        document.getElementById('feedbackForm').addEventListener('submit', function(e) {
            const submitBtn = document.querySelector('.submit-btn');
            submitBtn.style.transform = 'scale(0.98)';
            submitBtn.innerHTML = 'Submitting...';
        });

        // Add hover effects to form inputs
        document.querySelectorAll('input, textarea').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>