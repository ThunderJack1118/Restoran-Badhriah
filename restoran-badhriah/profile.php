<?php
session_start();
require 'db.php';

if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['Username'];

// Fetch staff info by username
$stmt = $conn->prepare("SELECT * FROM staff WHERE Username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$staff = $result->fetch_assoc();

if (!$staff) {
    die("Profile not found.");
}

// Format StaffID as RB00X
$staffId = 'RB' . str_pad($staff['StaffID'], 3, '0', STR_PAD_LEFT);

// Note: In a real application, you should store the plain password separately or use a different approach
// This is for demonstration purposes only - showing hashed password is not secure practice
// You would need to store plain passwords (not recommended) or implement a different system
$plainPassword = "Cannot retrieve - Password is hashed for security";
?>

<!DOCTYPE html>
<html>
<head>
  <title>My Profile</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 100%);
      color: #fff;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      min-height: 100vh;
      padding: 2rem;
      position: relative;
    }

    body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: 
        radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
      pointer-events: none;
      z-index: -1;
    }
    
    .profile-container {
      max-width: 800px;
      margin: 0 auto;
    }

    .profile-header {
      text-align: center;
      margin-bottom: 3rem;
    }
    
    .profile-avatar {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;
      font-size: 3rem;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
      position: relative;
    }

    .profile-avatar::after {
      content: '';
      position: absolute;
      inset: -3px;
      border-radius: 50%;
      background: linear-gradient(135deg, #667eea, #764ba2);
      z-index: -1;
      filter: blur(10px);
      opacity: 0.6;
    }

    .profile-title {
      font-size: 2.5rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .profile-subtitle {
      color: #888;
      font-size: 1.1rem;
      font-weight: 400;
    }
    
    .profile-box {
      background: rgba(26, 26, 26, 0.8);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      padding: 3rem;
      border-radius: 20px;
      box-shadow: 
        0 20px 40px rgba(0, 0, 0, 0.4),
        inset 0 1px 0 rgba(255, 255, 255, 0.1);
      position: relative;
      overflow: hidden;
    }

    .profile-box::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 1px;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    }
    
    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 2rem;
    }

    .info-section {
      background: rgba(255, 255, 255, 0.02);
      padding: 1.5rem;
      border-radius: 12px;
      border: 1px solid rgba(255, 255, 255, 0.05);
      transition: all 0.3s ease;
    }

    .info-section:hover {
      background: rgba(255, 255, 255, 0.05);
      border-color: rgba(255, 255, 255, 0.1);
      transform: translateY(-2px);
    }

    .section-title {
      font-size: 1.1rem;
      font-weight: 600;
      color: #667eea;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .info-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0.75rem 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
      transition: all 0.2s ease;
    }

    .info-row:last-child {
      border-bottom: none;
    }

    .info-row:hover {
      background: rgba(255, 255, 255, 0.02);
      margin: 0 -0.5rem;
      padding: 0.75rem 0.5rem;
      border-radius: 6px;
    }
    
    .label {
      font-weight: 500;
      color: #bbb;
      font-size: 0.9rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .value {
      color: #fff;
      font-weight: 500;
      text-align: right;
      max-width: 60%;
    }

    .status-active {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      background: rgba(76, 175, 80, 0.1);
      color: #4caf50;
      padding: 0.25rem 0.75rem;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
      border: 1px solid rgba(76, 175, 80, 0.2);
    }

    .status-inactive {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      background: rgba(244, 67, 54, 0.1);
      color: #f44336;
      padding: 0.25rem 0.75rem;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
      border: 1px solid rgba(244, 67, 54, 0.2);
    }
    
    .password-container {
      position: relative;
      display: inline-block;
      cursor: pointer;
      padding: 0.25rem 0.75rem;
      background: rgba(255, 255, 255, 0.05);
      border-radius: 6px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      transition: all 0.3s ease;
    }

    .password-container:hover {
      background: rgba(255, 255, 255, 0.1);
      border-color: rgba(255, 255, 255, 0.2);
    }
    
    .password-hidden {
      color: #fff;
      font-family: monospace;
      letter-spacing: 2px;
    }
    
    .password-visible {
      display: none;
      color: #ff6b6b;
      font-family: monospace;
      font-size: 0.9rem;
    }
    
    .password-container:hover .password-hidden {
      display: none;
    }
    
    .password-container:hover .password-visible {
      display: inline;
    }

    .back-button {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      margin-top: 3rem;
      padding: 0.75rem 1.5rem;
      background: rgba(255, 255, 255, 0.05);
      color: #fff;
      text-decoration: none;
      border-radius: 10px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      transition: all 0.3s ease;
      font-weight: 500;
    }

    .back-button:hover {
      background: rgba(255, 255, 255, 0.1);
      border-color: rgba(255, 255, 255, 0.2);
      transform: translateX(-5px);
    }

    @media (max-width: 768px) {
      .profile-box {
        padding: 2rem;
      }
      
      .info-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
      }

      .profile-title {
        font-size: 2rem;
      }
    }
  </style>
</head>
<body>

<div class="profile-container">
  <div class="profile-header">
    <div class="profile-avatar">👤</div>
    <h1 class="profile-title">My Profile</h1>
    <p class="profile-subtitle">Personal Information & Account Details</p>
  </div>

  <div class="profile-box">
    <div class="info-grid">
      <div class="info-section">
        <h3 class="section-title">🆔 Basic Information</h3>
        <div class="info-row">
          <span class="label">Staff ID</span>
          <span class="value"><?= htmlspecialchars($staffId) ?></span>
        </div>
        <div class="info-row">
          <span class="label">Full Name</span>
          <span class="value"><?= htmlspecialchars($staff['FirstName'] . ' ' . $staff['LastName']) ?></span>
        </div>
        <div class="info-row">
          <span class="label">Role</span>
          <span class="value"><?= htmlspecialchars($staff['Role']) ?></span>
        </div>
        <div class="info-row">
          <span class="label">Status</span>
          <span class="value">
            <span class="<?= $staff['IsActive'] ? 'status-active' : 'status-inactive' ?>">
              <?= $staff['IsActive'] ? '✅ Active' : '❌ Inactive' ?>
            </span>
          </span>
        </div>
      </div>

      <div class="info-section">
        <h3 class="section-title">📞 Contact Information</h3>
        <div class="info-row">
          <span class="label">Phone</span>
          <span class="value"><?= htmlspecialchars($staff['Phone']) ?></span>
        </div>
        <div class="info-row">
          <span class="label">Email</span>
          <span class="value"><?= htmlspecialchars($staff['Email']) ?></span>
        </div>
        <div class="info-row">
          <span class="label">Address</span>
          <span class="value"><?= nl2br(htmlspecialchars($staff['Address'])) ?></span>
        </div>
        <div class="info-row">
          <span class="label">IC/Passport</span>
          <span class="value"><?= htmlspecialchars($staff['ICPassportNo']) ?></span>
        </div>
      </div>

      <div class="info-section">
        <h3 class="section-title">🔐 Account Details</h3>
        <div class="info-row">
          <span class="label">Username</span>
          <span class="value"><?= htmlspecialchars($staff['Username']) ?></span>
        </div>
        <div class="info-row">
          <span class="label">Password</span>
          <span class="value">
            <div class="password-container">
              <span class="password-hidden">••••••••••</span>
              <span class="password-visible"><?= htmlspecialchars($plainPassword) ?></span>
            </div>
          </span>
        </div>
        <div class="info-row">
          <span class="label">Hire Date</span>
          <span class="value"><?= date('F j, Y', strtotime($staff['HireDate'])) ?></span>
        </div>
      </div>
    </div>

<a href="javascript:history.back()" class="back-button">&larr; Back to Dashboard</a>
  </div>
</div>

</body>
</html>