<nav class="navbar navbar-expand-lg navbar-light bg-white shadow d-lg-none">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <div class="bg-primary text-white fw-bold rounded d-flex align-items-center justify-content-center me-2" 
                 style="width:40px; height:40px; font-size:18px;">
                <i class="fa-solid fa-user-gear"></i>
            </div>
            <span class="fs-5 fw-semibold">ADMIN</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mobileNavbar" aria-controls="mobileNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mobileNavbar">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fa-solid fa-gauge me-1"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="category.php"><i class="fa-solid fa-layer-group me-1"></i> Category</a></li>
                <li class="nav-item"><a class="nav-link" href="equipments.php"><i class="fa-solid fa-toolbox me-1"></i> Equipment</a></li>
                <li class="nav-item"><a class="nav-link" href="packages.php"><i class="fa-solid fa-box me-1"></i> Packages</a></li>
                <li class="nav-item"><a class="nav-link" href="staff.php"><i class="fa-solid fa-users-gear me-1"></i> Employee</a></li>
                <!-- <li class="nav-item"><a class="nav-link" href="booking.php"><i class="fa-solid fa-calendar-check me-1"></i> Booking</a></li> -->
                <li class="nav-item"><a class="nav-link" href="reports.php"><i class="fa-solid fa-chart-line me-1"></i> Reports</a></li>
                <li class="nav-item"><a class="nav-link text-danger" href="../logout.php"><i class="fa-solid fa-right-from-bracket me-1"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="d-flex min-vh-100">
    <nav class="d-none d-lg-flex flex-column flex-shrink-0 p-3 bg-white shadow" style="width:220px;">
        <div class="d-flex align-items-center mb-4">
            <div class="bg-primary text-white fw-bold rounded d-flex align-items-center justify-content-center me-2" 
                 style="width:40px; height:40px; font-size:18px;">
                <i class="fa-solid fa-user-gear"></i>
            </div>
            <span class="fs-5 fw-semibold">ADMIN</span>
        </div>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item mb-2">
                <a href="dashboard.php" class="nav-link text-dark">
                    <i class="fa-solid fa-gauge me-2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="category.php" class="nav-link text-dark">
                    <i class="fa-solid fa-layer-group me-2"></i> Category
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="equipments.php" class="nav-link text-dark">
                    <i class="fa-solid fa-toolbox me-2"></i> Equipment
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="packages.php" class="nav-link text-dark">
                    <i class="fa-solid fa-box me-2"></i> Packages
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="staff.php" class="nav-link text-dark">
                    <i class="fa-solid fa-users-gear me-2"></i> Employee
                </a>
            </li>
            <!-- <li class="nav-item mb-2">
                <a href="booking.php" class="nav-link text-dark">
                    <i class="fa-solid fa-calendar-check me-2"></i> Booking
                </a>
            </li> -->
            <li class="nav-item mb-2">
                <a href="reports.php" class="nav-link text-dark">
                    <i class="fa-solid fa-chart-line me-2"></i> Reports
                </a>
            </li>
            <li class="nav-item mt-auto">
                <a href="../admin/logout.php" class="nav-link text-danger">
                    <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
                </a>
            </li>
        </ul>
    </nav>