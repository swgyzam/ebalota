<?php
session_start();
if (!isset($_SESSION['pending_admin_auth'])) {
    header('Location: login.html');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Admin Verification Sent</title>
<style>
  body {
    margin: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f5f5f5;
  }

  .modal {
    display: flex;
    align-items: center;
    justify-content: center;
    position: fixed;
    z-index: 1000;
    left: 0; top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.6);
  }

  .modal-content {
    background-color: #fff;
    padding: 40px;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    text-align: center;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    animation: popUp 0.3s ease;
  }

  @keyframes popUp {
    from { transform: scale(0.9); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
  }

  .check-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    border-radius: 50%;
    background-color: #0a5f2d;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .check-icon svg {
    width: 40px;
    height: 40px;
    fill: white;
  }

  h2 {
    color: #0a5f2d;
    margin-bottom: 10px;
  }

  p {
    font-size: 16px;
    color: #333;
  }

  button {
    background-color: #0a5f2d;
    border: none;
    color: white;
    padding: 12px 28px;
    margin-top: 30px;
    cursor: pointer;
    font-weight: bold;
    border-radius: 6px;
    font-size: 16px;
  }

  button:hover {
    background-color: #08491c;
  }
</style>
</head>
<body>

<div class="modal" id="verificationModal">
  <div class="modal-content">
    <div class="check-icon">
      <svg viewBox="0 0 24 24">
        <path d="M9 16.2l-4.2-4.2-1.4 1.4L9 19 21 7l-1.4-1.4z"/>
      </svg>
    </div>
    <h2>Verification Email Sent</h2>
    <p>A secure login link has been sent to your admin email address. Please check your inbox and follow the link to complete your login.</p>
    <button id="okButton">OK</button>
  </div>
</div>

<script>
  document.getElementById('okButton').addEventListener('click', function() {
    window.location.href = 'login.html';
  });
</script>

</body>
</html>
