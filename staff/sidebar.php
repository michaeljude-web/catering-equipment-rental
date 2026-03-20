<?php $staff_firstname = $_SESSION['staff_firstname'] ?? ''; ?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow d-lg-none">
   <div class="container-fluid">
      <a class="navbar-brand d-flex align-items-center" href="#">
         <div class="bg-primary text-white fw-bold rounded d-flex align-items-center justify-content-center me-2" 
            style="width:40px; height:40px; font-size:18px;">
            <i class="fa-solid fa-user"></i>
         </div>
         <span class="fs-5 fw-semibold">
         <?php echo htmlspecialchars($staff_firstname); ?>
         </span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mobileNavbar" aria-controls="mobileNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="mobileNavbar">
         <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
            <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fa-solid fa-home me-1"></i> Home</a></li>
            <li class="nav-item"><a class="nav-link" href="bookings.php"><i class="fa-solid fa-calendar-check me-1"></i> Bookings</a></li>
            <li class="nav-item">
               <a class="nav-link" href="settings.php">
               <i class="fa-solid fa-gear me-1"></i> Settings
               </a>
            </li>
            <li class="nav-item">
               <a class="nav-link text-danger" href="../logout.php">
               <i class="fa-solid fa-sign-out-alt me-1"></i>
               Logout
               </a>
            </li>
         </ul>
      </div>
   </div>
</nav>

<div class="d-flex min-vh-100">
<nav class="d-none d-lg-flex flex-column flex-shrink-0 p-3 bg-white shadow" style="width:220px;">
   <div class="d-flex align-items-center mb-4">
      <div class="bg-primary text-white fw-bold rounded d-flex align-items-center justify-content-center me-2" 
         style="width:40px; height:40px; font-size:18px;">
         <i class="fa-solid fa-user"></i>
      </div>
      <span class="fs-5 fw-semibold">
      <?php echo htmlspecialchars($staff_firstname); ?>
      </span>
   </div>
   <ul class="nav nav-pills flex-column mb-auto">
      <li class="nav-item mb-2">
         <a href="dashboard.php" class="nav-link text-dark">
         <i class="fa-solid fa-home me-2"></i> Home
         </a>
      </li>
      <li class="nav-item mb-2">
         <a href="bookings.php" class="nav-link text-dark">
         <i class="fa-solid fa-calendar-check me-2"></i> Bookings
         </a>
      </li>
      <li class="nav-item mb-2">
         <a href="inventory.php" class="nav-link text-dark">
         <i class="fa-solid fa-box me-2"></i> inventory
         </a>
      </li>
      <li class="nav-item">
         <a class="nav-link text-dark" href="settings.php">
         <i class="fa-solid fa-gear me-1"></i> Settings
         </a>
      </li>
      <li class="nav-item mt-auto">
         <a href="../logout.php" class="nav-link text-danger">
         <i class="fa-solid fa-sign-out-alt me-2"></i>
         Logout
         </a>
      </li>
   </ul>
</nav>
