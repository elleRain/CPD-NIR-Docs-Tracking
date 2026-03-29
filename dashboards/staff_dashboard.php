<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'staff') {
    header("Location: ../index.php");
    exit();
}
include '../plugins/conn.php';

$user_id = $_SESSION['user_id'];

// Fetch stats for the logged-in staff member
// Total Documents created by this user (excluding drafts? Usually stats show all active work. Let's show all non-drafts or all? 
// Admin dashboard excludes drafts (status_id != 1). I'll follow that convention.
$total_docs = $conn->query("SELECT COUNT(*) FROM documents WHERE created_by = $user_id AND status_id != 1")->fetch_row()[0];

// All Documents: Total count of documents created by this user (including drafts)
$all_docs = $conn->query("SELECT COUNT(*) FROM documents WHERE created_by = $user_id")->fetch_row()[0];

// Completed: Approved (5), Rejected (6), or Archived (7) - for this user
$completed_docs = $conn->query("SELECT COUNT(*) FROM documents WHERE created_by = $user_id AND status_id IN (5, 6, 7)")->fetch_row()[0];

// Drafts - Might be useful for staff to see their draft count
$draft_docs = $conn->query("SELECT COUNT(*) FROM documents WHERE created_by = $user_id AND status_id = 1")->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - DTS</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/custom.css">
</head>
<body>

    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <div class="content-topbar">
            <div class="breadcrumb">
                <a href="staff_dashboard.php">Home</a>
                <span class="breadcrumb-sep">&rsaquo;</span>
                <span class="breadcrumb-current">Dashboard</span>
            </div>
            <div class="topbar-actions">
                <a href="../documents/my_documents.php" class="btn btn-outline btn-sm">My Documents</a>
                <a href="../documents/document.php" class="btn btn-gold btn-sm">All Documents</a>
            </div>
        </div>

        <div class="content-scroll">

        <div class="dashboard-hero">
            <div>
                <h1>Staff Workspace</h1>
                <p>Track your documents, drafts, and processing progress in one clean view.</p>
            </div>
            <div class="dashboard-hero-meta">
                <span class="meta-label">Last Updated</span>
                <strong><?php echo date('M d, Y h:i A'); ?></strong>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="row g-3 mb-4 dashboard-kpi-row">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon bg-blue-light">
                        <i class="bi bi-files"></i>
                    </div>
                    <h3 class="fs-4 fw-bold mb-1"><?php echo number_format($total_docs); ?></h3>
                    <p class="text-secondary mb-0">My Documents</p>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon bg-purple-light">
                        <i class="bi bi-folder2-open"></i>
                    </div>
                    <h3 class="fs-4 fw-bold mb-1"><?php echo number_format($all_docs); ?></h3>
                    <p class="text-secondary mb-0">All Documents</p>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon bg-green-light">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <h3 class="fs-4 fw-bold mb-1"><?php echo number_format($completed_docs); ?></h3>
                    <p class="text-secondary mb-0">Completed</p>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon bg-gray-light">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <h3 class="fs-4 fw-bold mb-1"><?php echo number_format($draft_docs); ?></h3>
                    <p class="text-secondary mb-0">Drafts</p>
                </div>
            </div>
        </div>

        <!-- Recent Activity Table -->
        <div class="table-card">
            <div class="table-header">
                <div>
                    <h5 class="fw-bold mb-0">My Recent Documents</h5>
                    <p class="table-subtitle mb-0">Latest 5 documents created by your account.</p>
                </div>
                <a href="../documents/my_documents.php" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Tracking Number</th>
                            <th>Document Title</th>
                            <th>Status</th>
                            <th>Date Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT d.tracking_number, d.title, s.status_name, s.status_id, d.created_at 
                                  FROM documents d 
                                  LEFT JOIN document_status s ON d.status_id = s.status_id 
                                  WHERE d.created_by = ?
                                  ORDER BY d.created_at DESC 
                                  LIMIT 5";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $status_badge = '';
                                // IDs based on new list: 1=Draft, 2=Submitted, 3=In Review, 4=For Revision, 5=Approved, 6=Rejected, 7=Archived
                                switch ($row['status_id']) {
                                    case 1: // Draft
                                        $status_badge = '<span class="badge bg-secondary">Draft</span>';
                                        break;
                                    case 2: // Submitted
                                        $status_badge = '<span class="badge bg-primary">Submitted</span>';
                                        break;
                                    case 3: // In Review
                                        $status_badge = '<span class="badge bg-info text-dark">In Review</span>';
                                        break;
                                    case 4: // For Revision
                                        $status_badge = '<span class="badge bg-warning text-dark">For Revision</span>';
                                        break;
                                    case 5: // Approved
                                        $status_badge = '<span class="badge bg-success">Approved</span>';
                                        break;
                                    case 6: // Rejected
                                        $status_badge = '<span class="badge bg-danger">Rejected</span>';
                                        break;
                                    case 7: // Archived
                                        $status_badge = '<span class="badge bg-dark">Archived</span>';
                                        break;
                                    default:
                                        $status_badge = '<span class="badge bg-light text-dark">' . htmlspecialchars($row['status_name']) . '</span>';
                                }
                                
                                $date_created = date('M d, Y', strtotime($row['created_at']));
                        ?>
                        <tr>
                            <td class="ps-4 fw-medium"><?php echo htmlspecialchars($row['tracking_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo $status_badge; ?></td>
                            <td><?php echo $date_created; ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="../documents/my_documents.php" class="btn btn-sm btn-light">Open</a>
                                </div>
                            </td>
                        </tr>
                        <?php
                            }
                        } else {
                        ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-secondary">No recent documents found</td>
                        </tr>
                        <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        </div>
    </main>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

