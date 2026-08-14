<?php
// Find out which page the user is currently on (e.g., "dashboard.php")
$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
    .navbar { 
        background-color: #031e3b; 
        padding: 15px 20px; 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        color: white; 
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .navbar h2 { margin: 0; font-size: 22px; }
    .nav-right { display: flex; align-items: center; gap: 10px; }
    .nav-greeting { margin-right: 15px; font-size: 16px; }
    .navbar a { 
        color: white; 
        text-decoration: none; 
        font-weight: bold; 
        padding: 8px 15px; 
        border-radius: 4px; 
        transition: 0.2s;
    }
    
    /* Different button colors */
    .nav-dash { background-color: #17a2b8; }
    .nav-dash:hover { background-color: #138496; }
    
    .nav-profile { background-color: #28a745; }
    .nav-profile:hover { background-color: #218838; }

    .nav-edit { background-color: #ffc107; color: #333 !important; }
    .nav-edit:hover { background-color: #e0a800; }
    
    .nav-logout { background-color: #dc3545; }
    .nav-logout:hover { background-color: #c82333; }
</style>

<nav class="navbar">
    <h2>Examify Student Portal</h2>
    <div class="nav-right">
        <span class="nav-greeting">
            Hi, <?php echo htmlspecialchars($_SESSION['student_name'] ?? 'Student'); ?>!
        </span>

        <?php 
        if ($current_page === 'dashboard.php'): 
        ?>
            <a href="profile.php" class="nav-profile">My Profile</a>
            
        <?php 
        elseif ($current_page === 'profile.php'): 
        ?>
            <a href="dashboard.php" class="nav-dash">Dashboard</a>
            <a href="edit-profile.php" class="nav-edit">✎ Edit Profile</a>
            
        <?php 
        elseif ($current_page === 'edit-profile.php'): 
        ?>
            <a href="dashboard.php" class="nav-dash">Dashboard</a>
            <a href="profile.php" class="nav-profile">Cancel Edit</a>
            
        <?php 
        else: 
        ?>
            <a href="dashboard.php" class="nav-dash">Dashboard</a>
            
        <?php endif; ?>

        <a href="logout.php" class="nav-logout">Logout</a>
    </div>
</nav>