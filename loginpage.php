<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$admin_email = 'admin123@gmail.com';
$admin_password = 'password123'; 
$t_email = 'abc123@gmail.com';
$t_pass = 'bmgk123';

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "project1";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$response = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax'])) {
    $email = $_POST['email'];
    $input_password = $_POST['password'];

    // Admin login
    if ($email === $admin_email && $input_password === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
        $response = ['redirect' => 'residency_details.php'];
        echo json_encode($response);
        exit();
    }
    // Treasurer login
    if ($email === $t_email && $input_password === $t_pass) {
        $_SESSION['treasure_logged_in'] = true;
        $response = ['redirect' => 't_profile.php'];
        echo json_encode($response);
        exit();
    }

    // Regular user login
    $stmt = $conn->prepare("SELECT * FROM userlogin1 WHERE email = ?");
    if ($stmt === false) {
        die(json_encode(['error' => "Prepare failed: " . $conn->error]));
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        
        // Check password
        if ($input_password !== $users[0]['password']) {
            $response = ['error' => 'Incorrect password. Please try again.'];
        } else {
            $_SESSION['user_logged_in'] = true;

            if ($users[0]['who'] === 'Rental') {
                $response = ['redirect' => 'rental_profile.php?email=' . urlencode($email)];
            } elseif ($users[0]['who'] === 'Owner') {
                if (count($users) == 1) {
                    $flat = $users[0]['flat'];
                    $response = ['redirect' => 'u_profile.php?email=' . urlencode($email) . '&flat=' . urlencode($flat)];
                } else {
                    $flats = array_map(function($u) { return $u['flat']; }, $users);
                    $response = ['multiple_flats' => true, 'flats' => $flats, 'email' => $email];
                }
            }
        }
    } else {
        $response = ['error' => 'Invalid email or password.'];
    }

    echo json_encode($response);
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            background: url('./image1.jpg') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: flex-start; 
            align-items: center;
            height: 100vh;
            padding-left: 200px; 
            color: #333;
        }
        .container {
            background: white;
            border-radius: 15px;
            padding: 55px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            width: 300px;
            text-align: left; 
        }
        h1 {
            margin-bottom: 20px;
            color: #4CAF50;
            text-align: center; 
        }
        input {
            width: calc(100% - 24px);
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
            transition: border-color 0.3s;
        }
        input:focus {
            border-color: #4CAF50;
            outline: none;
        }
        .button-container {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }
        button {
            padding: 12px;
            border: none;
            border-radius: 30px;
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.3s;
            font-size: 15px;
        }
        .sign-in-button {
            background-color: green; 
            width: 100%;
        }
        .back-button {
            background-color: green;
            width: 90px;
        }
        button:hover {
            transform: scale(1.05);
            background-color: #45a049;
        }
        .error-message {
            color: red; 
            text-align: center; 
            margin-top: 10px;
        }
        .links {
            margin-top: 20px; 
            text-align: center; 
        }
        .links a {
            text-decoration: none;
            color: #4CAF50; 
            margin: 0 10px; 
        }
        .links a:hover {
            text-decoration: underline; 
        }
        #flat-selection {
            margin-top: 20px;
            text-align: center;
        }
        select {
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Sign In</h1>
        <form id="loginForm">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>

            <div class="button-container">
                <button type="submit" class="sign-in-button">Sign In</button>
                <a href="homepage.php"><button type="button" class="back-button">Back</button></a>
            </div>
        </form>

        <div id="error-message" class="error-message"></div>

        <div class="links">
            <a href="forget_password.php">Change Password</a>
            <span>|</span>
            <a href="registerpage.php">Sign Up</a>
        </div>

        <div id="flat-selection"></div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('ajax', '1'); // Mark as AJAX request

            fetch('loginpage.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.redirect) {
                    window.location.href = data.redirect;
                } else if (data.multiple_flats) {
                    const flatDiv = document.getElementById('flat-selection');
                    flatDiv.innerHTML = `
                        <h2>Select Your Flat</h2>
                        <select id="flatSelect"></select>
                        <button id="flatSubmit">Proceed</button>
                    `;
                    const flatSelect = document.getElementById('flatSelect');
                    data.flats.forEach(flat => {
                        const option = document.createElement('option');
                        option.value = flat;
                        option.textContent = "Flat No: " + flat;
                        flatSelect.appendChild(option);
                    });

                    document.getElementById('flatSubmit').addEventListener('click', function() {
                        const selectedFlat = flatSelect.value;
                        window.location.href = `u_profile.php?email=${encodeURIComponent(data.email)}&flat=${encodeURIComponent(selectedFlat)}`;
                    });

                } else if (data.error) {
                    document.getElementById('error-message').innerText = data.error;
                }
            })
            .catch(error => console.error('Error:', error));
        });
    </script>
</body>
</html>
