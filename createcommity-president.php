<?php
session_start();

// Database connection settings
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "project1";

// Create a connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// SQL query to fetch all users from commity table
$sql = "SELECT * FROM commity";
$result = $conn->query($sql);

// Check if the query was successful
if ($result === false) {
    die("Error executing query: " . $conn->error);
}

$pendingUsers = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pendingUsers[] = $row;
    }
}

// Handle form submission (selection of a president)
if (isset($_POST['submit'])) {
    if (isset($_POST['commity_president'])) {
        $selectedId = $_POST['commity_president'];

        // Fetch the selected user's data
        $userQuery = "SELECT * FROM commity WHERE id = ?";
        $stmt = $conn->prepare($userQuery);
        $stmt->bind_param("i", $selectedId);
        $stmt->execute();
        $userResult = $stmt->get_result();

        if ($userResult->num_rows > 0) {
            $user = $userResult->fetch_assoc();

            // Begin transaction
            $conn->begin_transaction();

            try {
                // Check if there's an existing president
                $checkPresidentQuery = "SELECT * FROM president LIMIT 1";
                $presidentResult = $conn->query($checkPresidentQuery);
                
                if ($presidentResult->num_rows > 0) {
                    // If president exists, move to history
                    $oldPresident = $presidentResult->fetch_assoc();
                    
                    // Insert old president into history table
                    $insertHistoryQuery = "INSERT INTO president_history 
                                         (id, flat, name, number, email, created_at) 
                                         VALUES (?, ?, ?, ?, ?, ?)";
                    $insertStmt = $conn->prepare($insertHistoryQuery);
                    $insertStmt->bind_param("isssss", 
                        $oldPresident['id'], 
                        $oldPresident['flat'], 
                        $oldPresident['name'], 
                        $oldPresident['number'], 
                        $oldPresident['email'],
                        $oldPresident['created_at']
                    );
                    
                    if (!$insertStmt->execute()) {
                        throw new Exception("Failed to move old president to history");
                    }
                    
                    // Delete old president
                    $deleteQuery = "DELETE FROM president";
                    if (!$conn->query($deleteQuery)) {
                        throw new Exception("Failed to delete old president");
                    }
                }

                // Insert the new president
                $insertQuery = "INSERT INTO president 
                               (id, flat, name, number, email, created_at) 
                               VALUES (?, ?, ?, ?, ?, NOW())";
                $insertStmt = $conn->prepare($insertQuery);
                $insertStmt->bind_param("issss", 
                    $user['id'], 
                    $user['flat'], 
                    $user['name'], 
                    $user['number'], 
                    $user['email']
                );

                if ($insertStmt->execute()) {
                    $_SESSION['selected_president'] = $user['id'];
                    $conn->commit();
                    $successMessage = "President selected and added successfully.";
                    header("Location: createcommity-secretory.php");
                    exit();
                } else {
                    throw new Exception("Failed to add the new president");
                }
            } catch (Exception $e) {
                $conn->rollback();
                $errorMessage = $e->getMessage();
            }
        } else {
            $errorMessage = "User not found.";
        }
        $stmt->close();
    } else {
        $errorMessage = "Please select a president.";
    }
}

// Get selected secretary and vice-secretary from session
$selectedSecretary = isset($_SESSION['selected_secretary']) ? $_SESSION['selected_secretary'] : null;
$selectedViceSecretary = isset($_SESSION['selected_vice_secretary']) ? $_SESSION['selected_vice_secretary'] : null;

$checkViseQuery = "SELECT COUNT(*) as count FROM visesecretory";
$viseResult = $conn->query($checkViseQuery);
$viseCount = 0;
if ($viseResult && $viseResult->num_rows > 0) {
    $row = $viseResult->fetch_assoc();
    $viseCount = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Community</title>
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 05px;
            background-color: #eef2f3;
            color: #333;
            display: flex;
        }
        .sidebar {
            width: 250px;
            background: #6793AC;
            color: white;
            height: 100vh;
            padding: 9.5px;
            position: fixed;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        }
        .sidebar h2 {
            text-align: center;
            margin-bottom: 20px;
            font-size: 22px;
        }
        .sidebar a {
            display: block;
            padding: 15px;
            color: white;
            text-decoration: none;
            margin-bottom: 10px;
            border-radius: 5px;
            transition: background-color 0.3s, transform 0.3s ease;
        }
        .sidebar a:hover {
            background: #5a7a87;
            transform: scale(1.05);
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
            flex-grow: 1;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #6793AC;
            color: white;
            padding: 15px 20px;
            border-radius: 5px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header .logout {
            background: #e74c3c;
            border: none;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .header .logout:hover {
            background: #c0392b;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 5px;
            color: #fff;
            display: none;
        }
        .alert.success {
            background-color: #4CAF50;
            border-color: #4CAF50;
        }
        .alert.error {
            background-color: #f44336;
            border-color: #f44336;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        .approve-button, .reject-button {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            color: white;
            font-weight: bold;
            transition: background-color 0.3s, transform 0.3s;
        }
        .approve-button {
            background-color: #4CAF50;
            text-decoration: none;
        }
        .reject-button {
            background-color: #f44336;
            text-decoration: none;
        }
        .approve-button:hover {
            background-color: #45a049;
            transform: scale(1.05);
        }
        .reject-button:hover {
            background-color: #d32f2f;
            transform: scale(1.05);
        }
        button {
        width: 100px;
        padding: 12px;
        margin-top: 5px;
        border: none;
        border-radius: 30px;
        background-color: lightgray;
        color: black;
        font-weight: bold;
        cursor: pointer;
        transition: background-color 0.3s, transform 0.3s;
        font-size: 15px;
        margin-left:10px;
    }
    .select {
        display: flex;
        font-size: 14.5px;
        margin: -15px 0 15px ;
        margin-top: 10px;
    }
    .select .select1{
        width: 100px;
        padding: 12px;
        margin-top: 5px;
        border: none;
        border-radius: 30px;
        background-color: lightgray;
        color: black;
        font-weight: bold;
        font-size: 15px;
        margin-left:20px;
        text-align:center;
    }
    .select .select1 a{
        color : black;
        text-decoration:none;
    }
    .con{
        display: flex;
            justify-content: space-between;
            align-items: center;
            background: #6793AC;
            color: white;
            padding: 15px 20px;
            border-radius: 5px;
        
    }
    
    </style>
</head>
<body>
<div class="sidebar">
        <h2 style="font-size:20px;">👥 Admin Dashboard</h2>
        <a href="residency_details.php">👤Profile</a>
        <a href="message.php">📩Messages</a>
        <a href="report.php">🏠Resident</a>
        <a href="#">🔧Maintenances</a>
        <a href="#">🗝️Aminities Booking</a>
        <a href="<?php echo ($viseCount > 0) ? 'c_details.php' : 'selectcommitymember.php'; ?>">👥Create Community</a>
        <a href="community_history.php">📜 Community History</a>    
        <a href="loginpage.php">⬅️Logout</a>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Select President</h1>
            <a href="loginpage.php"><button class="logout">Logout</button></a>
        </div>

        <!-- Success/Error Alert -->
        <?php if (isset($successMessage)): ?>
            <div class="alert success" style="display: block;">
                <?= $successMessage ?>
            </div>
        <?php endif; ?>
        <?php if (isset($errorMessage)): ?>
            <div class="alert error" style="display: block;">
                <?= $errorMessage ?>
            </div>
        <?php endif; ?>
        <div class="select" style="display:flex;">
            <div class="select1"><a href="createcommity-president.php">President</a></div>
            <div class="select1" style="opacity:0.5;"><a href="createcommity-secretory.php">Secretory</a></div>
            <div class="select1" style="opacity:0.5;"><a href="createcommity-vise-secretory.php">Vise-Secretory</a></div>
        </div>
        <form method="POST">
            <table>
                <thead>
                    <tr>
                        <th>Flat Number</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Select</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingUsers as $user): ?>
                        <tr>
                            <td><?= $user['flat'] ?></td>
                            <td><?= $user['name'] ?></td>
                            <td><?= $user['email'] ?></td>
                            <td>
                                <input type="radio" id="president" name="commity_president" value="<?= $user['id'] ?>" 
                                <?= ($user['id'] == $selectedSecretary || $user['id'] == $selectedViceSecretary) ? 'disabled' : '' ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="con">
                <h4>Press the confirm button to select the president</h4>
                <div class="button-container">
                    <button type="submit" name="submit">Confirm</button>
                </div>
            </div>
        </form>
    </div>
</body>
</html>