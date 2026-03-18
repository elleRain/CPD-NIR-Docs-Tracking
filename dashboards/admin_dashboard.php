<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}
include '../plugins/conn.php';

// Fetch stats
$total_docs = $conn->query("SELECT COUNT(*) FROM documents WHERE status_id != 1")->fetch_row()[0];
// Pending Review: Submitted (2), In Review (3), or For Revision (4)
$stats_pending_docs = $conn->query("SELECT COUNT(*) FROM documents WHERE status_id IN (2, 3, 4)")->fetch_row()[0];
// Completed: Approved (5), Rejected (6), or Archived (7)
$completed_docs = $conn->query("SELECT COUNT(*) FROM documents WHERE status_id IN (5, 6, 7)")->fetch_row()[0];
// Count only staff/users, excluding superadmin
$active_users = $conn->query("SELECT COUNT(*) FROM users WHERE role != 'superadmin'")->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - DTS</title>
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

        <!-- Stats Grid -->
        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-blue-light">
                        <i class="bi bi-files"></i>
                    </div>
                    <h3 class="fs-4 fw-bold mb-1"><?php echo number_format($total_docs); ?></h3>
                    <p class="text-secondary mb-0">Total Documents</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-orange-light">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <h3 class="fs-4 fw-bold mb-1"><?php echo number_format($stats_pending_docs); ?></h3>
                    <p class="text-secondary mb-0">Pending Review</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-green-light">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <h3 class="fs-4 fw-bold mb-1"><?php echo number_format($completed_docs); ?></h3>
                    <p class="text-secondary mb-0">Completed</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-purple-light">
                        <i class="bi bi-people"></i>
                    </div>
                    <h3 class="fs-4 fw-bold mb-1"><?php echo number_format($active_users); ?></h3>
                    <p class="text-secondary mb-0">Active Users</p>
                </div>
            </div>
        </div>

        <!-- Recent Activity Table -->
        <div class="table-card">
            <div class="table-header">
                <h5 class="fw-bold mb-0">Recent Documents</h5>
                <button class="btn btn-sm btn-outline-secondary">View All</button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Tracking Number</th>
                            <th>Document Title</th>
                            <th>Origin</th>
                            <th>Status</th>
                            <th>Date Received</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT d.tracking_number, d.title, u.firstname, u.lastname, s.status_name, s.status_id, d.created_at 
                                  FROM documents d 
                                  LEFT JOIN users u ON d.created_by = u.user_id 
                                  LEFT JOIN document_status s ON d.status_id = s.status_id 
                                  WHERE d.status_id != 1
                                  ORDER BY d.created_at DESC 
                                  LIMIT 5";
                        $result = $conn->query($query);

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
                                
                                $origin = htmlspecialchars($row['firstname'] . ' ' . $row['lastname']);
                                $date_received = date('M d, Y', strtotime($row['created_at']));
                        ?>
                        <tr>
                            <td class="ps-4 fw-medium"><?php echo htmlspecialchars($row['tracking_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo $origin; ?></td>
                            <td><?php echo $status_badge; ?></td>
                            <td><?php echo $date_received; ?></td>
                            <td>
                                <button class="btn btn-sm btn-light"><i class="bi bi-eye"></i></button>
                                <button class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></button>
                            </td>
                        </tr>
                        <?php
                            }
                        } else {
                        ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-secondary">No recent documents found</td>
                        </tr>
                        <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

