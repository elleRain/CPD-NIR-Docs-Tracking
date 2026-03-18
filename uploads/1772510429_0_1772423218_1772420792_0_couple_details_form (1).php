<?php
include('../includes/conn.php');
include('../includes/session.php');
include('../includes/image_helper.php');
include('../includes/header.php');

$access_id = isset($_GET['access_id']) ? (int)$_GET['access_id'] : 0;
$profiles = ['male' => null, 'female' => null];
// Filing metadata
$access_code_value = null;
$date_created_value = null;

if ($access_id > 0) {
    try {
        $stmt = $conn->prepare("SELECT * FROM couple_profile LEFT JOIN address ON address.address_id = couple_profile.address_id WHERE access_id = ?");
        $stmt->bind_param("i", $access_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $profiles[$row['sex'] === 'Male' ? 'male' : 'female'] = $row;
        }
    } catch (Exception $e) {
        // Log the actual error for debugging
        error_log("Error fetching couple details in " . __FILE__ . " line " . __LINE__ . ": " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        error_log("Access ID: " . $access_id);
        
        $_SESSION['error_message'] = "Unable to load couple details. Please try again.";
        header("Location: couple_list.php");
        exit();
    }
}

// Fetch access code and date of filing from couple_access
if ($access_id > 0) {
    try {
        $stmt = $conn->prepare("SELECT access_code, date_created FROM couple_access WHERE access_id = ?");
        $stmt->bind_param("i", $access_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $access_code_value = $row['access_code'];
            $date_created_value = $row['date_created'];
        }
    } catch (Exception $e) {
        // Non-fatal; allow page to render with today's date fallback
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Couple Details</title>
    <?php include '../includes/header.php'; ?>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/sidebar.php'; ?>

        <div class="content-wrapper">
            <section class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-12 col-sm-6">
                            <h1 class="responsive-title">Couple Details</h1>
                        </div>
                        <div class="col-12 col-sm-6">
                            <div class="d-flex justify-content-sm-end justify-content-start mt-2 mt-sm-0 no-print">
                                <a href="couple_list.php" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-arrow-left mr-1"></i> Back to List
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="content">
                <div class="container-fluid">
                    <?php include '../includes/messages.php'; ?>

                    <!-- Profile Toggle Buttons (similar to Email/SMS logs) -->
                    <div class="row no-print mb-3">
                        <div class="col-12 text-right">
                            <button type="button" class="btn btn-sm btn-primary mr-1" id="maleTabBtn">
                                <i class="fas fa-male mr-1"></i> Male Profile
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="femaleTabBtn">
                                <i class="fas fa-female mr-1"></i> Female Profile
                            </button>
                        </div>
                    </div>

                    <!-- Male Profile Form -->
                    <div class="row" id="maleProfileRow">
                        <div class="col-12">
                            <div class="mb-2 no-print text-right">
                                <button class="btn btn-sm btn-outline-primary" onclick="printMaleProfile()">
                                    <i class="fas fa-print mr-1"></i> Print Male Profile
                                </button>
                            </div>
                            <div class="card card-primary" id="maleProfileForm">
                                <div class="card-header">
                                    <div class="row align-items-center">
                                        <!-- Logos -->
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center">
                                                <div class="logo-container">
                                                    <img src="../images/popcom1.png" alt="POPCOM" class="logo-img">
                                                </div>
                                                <div class="logo-container">
                                                    <img src="../images/City_of_Bago_Logo1.png" alt="City of Bago" class="logo-img">
                                                </div>
                                                <div class="logo-container">
                                                    <img src="<?= getSecureImagePath('../images/bcpdo.png') ?>" alt="BCPDO" class="logo-img">
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Form Title and Info -->
                                        <div class="col-md-8">
                                            <div class="header-text">
                                                <h2 class="mb-2 text-white"><strong>MALE PROFILE FORM</strong></h2>
                                                <p class="mb-1 text-white">Form No.: BCPDO-MPF-001</p>
                                                <p class="mb-1 text-white">Version No.: 01 | Effective: August 13, 2024</p>
                                                <p class="mb-0 text-white"><strong>COUPLE NO.:</strong> None | 
                                                <strong>DATE OF FILING:</strong> <?= !empty($profiles['male']['date_of_filing']) ? date('F j, Y', strtotime($profiles['male']['date_of_filing'])) : ($date_created_value ? date('F j, Y', strtotime($date_created_value)) : date('F j, Y')) ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php if ($profiles['male']): $male = $profiles['male']; ?>
                                        <!-- Residency Type -->
                                        <div class="form-group row">
                                            <div class="col-md-12">
                                                <label class="col-form-label">Residency Type</label>
                                                <input type="text" class="form-control" value="<?= ucfirst(htmlspecialchars($male['residency_type'] ?? '')) ?>" readonly>
                                            </div>
                                        </div>

                                        <!-- Personal Information -->
                                        <div class="form-group row">
                                            <div class="col-md-3 col-sm-6">
                                                <label class="col-form-label">First Name</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($male['first_name']) ?>" readonly>
                                            </div>
                                            <div class="col-md-3 col-sm-6">
                                                <label class="col-form-label">Middle Name</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($male['middle_name']) ?>" readonly>
                                            </div>
                                            <div class="col-md-3 col-sm-6">
                                                <label class="col-form-label">Last Name</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($male['last_name']) ?>" readonly>
                                            </div>
                                            <div class="col-md-3 col-sm-6">
                                                <label class="col-form-label">Suffix</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($male['suffix'] ?? '') ?>" readonly>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <div class="col-md-4">
                                                <label class="col-form-label">Sex</label>
                                                <input type="text" class="form-control" value="<?= $male['sex'] ?>" readonly>
                                            </div>
                                            <div class="col-md-8">
                                                <label class="col-form-label">Email</label>
                                                <input type="email" class="form-control" value="<?= htmlspecialchars($male['email_address']) ?>" readonly>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <div class="col-md-3 col-sm-4">
                                                <label class="col-form-label">Birth Month</label>
                                                <input type="text" class="form-control" value="<?= date('F', strtotime($male['date_of_birth'])) ?>" readonly>
                                            </div>
                                            <div class="col-md-3 col-sm-4">
                                                <label class="col-form-label">Birth Day</label>
                                                <input type="text" class="form-control" value="<?= date('j', strtotime($male['date_of_birth'])) ?>" readonly>
                                            </div>
                                            <div class="col-md-3 col-sm-4">
                                                <label class="col-form-label">Birth Year</label>
                                                <input type="text" class="form-control" value="<?= date('Y', strtotime($male['date_of_birth'])) ?>" readonly>
                                            </div>
                                            <div class="col-md-3 col-sm-12">
                                                <label class="col-form-label">Age</label>
                                                <input type="text" class="form-control" value="<?= $male['age'] ?>" readonly>
                                            </div>
                                        </div>



                                        <!-- Address Section -->
                                        <?php if (($male['residency_type'] ?? '') === 'bago'): ?>
                                        <!-- Bago Address -->
                                        <div class="form-group row">
                                            <div class="col-md-4">
                                                <label class="col-form-label">City</label>
                                                <input type="text" class="form-control" value="Bago" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="col-form-label">Barangay</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($male['barangay'] ?? '') ?>" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="col-form-label">Purok</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($male['purok'] ?? '') ?>" readonly>
                                            </div>
                                        </div>
                                        <?php elseif (($male['residency_type'] ?? '') === 'non-bago'): ?>
                                        <!-- Non-Bago Address -->
                                        <div class="form-group row">
                                            <div class="col-md-4">
                                                <label class="col-form-label">City</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($male['city'] ?? '') ?>" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="col-form-label">Barangay</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($male['barangay'] ?? '') ?>" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="col-form-label">Purok</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($male['purok'] ?? '') ?>" readonly>
                                            </div>
                                        </div>
                                        <?php elseif (($male['residency_type'] ?? '') === 'foreigner'): ?>
                                        <!-- Foreigner Address -->
                                        <div class="form-group row">
                                            <div class="col-md-4">
                                                <label class="col-form-label">Country</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($male['country'] ?? '') ?>" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="col-form-label">State/Province</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($male['state_province'] ?? '') ?>" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="col-form-label">City</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($male['city'] ?? '') ?>" readonly>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <div class="form-group row">
                                            <div class="col-md-6">
                                                <label class="col-form-label">Contact Number</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($male['contact_number']) ?>" readonly>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="col-form-label">Civil Status</label>
                                                <input type="text" class="form-control" value="<?= $male['civil_status'] ?>" readonly>
                                            </div>
                                        </div>

                                        <?php if ($male['civil_status'] === 'Living In'): ?>
                                            <div class="form-group row">
                                                <div class="col-md-6">
                                                    <label class="col-form-label">Years Together</label>
                                                    <input type="text" class="form-control" value="<?= $male['years_living_together'] ?>" readonly>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="col-form-label">Living Reason</label>
                                                    <input type="text" class="form-control" value="<?= htmlspecialchars($male['living_in_reason']) ?>" readonly>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <div class="form-group row">
                                            <div class="col-md-6">
                                                <label class="col-form-label">Education</label>
                                                <input type="text" class="form-control" value="<?= $male['education'] ?>" readonly>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="col-form-label">Religion</label>
                                                <input type="text" class="form-control"
                                                    value="<?= $male['religion'] ?><?= !empty($male['other_religion']) ? ' (' . htmlspecialchars($male['other_religion']) . ')' : '' ?>"
                                                    readonly>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <div class="col-md-6">
                                                <label class="col-form-label">Nationality</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($male['nationality']) ?>" readonly>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="col-form-label">Wedding Type</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($male['wedding_type']) ?>" readonly>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <div class="col-md-4">
                                                <label class="col-form-label">Employment Status</label>
                                                <input type="text" class="form-control" value="<?= $male['employment_status'] ?>" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="col-form-label">Occupation</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($male['occupation']) ?>" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="col-form-label">Monthly Income</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($male['monthly_income']) ?>" readonly>
                                            </div>
                                        </div>

                                        <!-- Family Planning Section -->
                                        <div class="card mt-3">
                                            <div class="card-body">
                                                <div class="form-group row">
                                                    <label class="col-form-label col-md-4">Heard About FP</label>
                                                    <div class="col-md-8">
                                                        <input type="text" class="form-control" value="<?= $male['heard_fp'] ?>" readonly>
                                                    </div>
                                                </div>

                                                <?php if ($male['heard_fp'] === 'Yes'): ?>
                                                    <div class="form-group row">
                                                        <label class="col-form-label col-md-4">FP Facility</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control"
                                                                value="<?= $male['fp_facility'] ?><?= !empty($male['other_facility']) ? ' (' . htmlspecialchars($male['other_facility']) . ')' : '' ?>"
                                                                readonly>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($male['heard_fp'] === 'Yes'): ?>
                                                    <div class="form-group row">
                                                        <label class="col-form-label col-md-4">FP Channel</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control"
                                                                value="<?= $male['fp_channel'] ?><?= !empty($male['other_channel']) ? ' (' . htmlspecialchars($male['other_channel']) . ')' : '' ?>"
                                                                readonly>
                                                        </div>
                                                    </div>

                                                <?php else: ?>
                                                    <div class="form-group row">
                                                        <label class="col-form-label col-md-4">Reason Not Heard</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control" value="<?= htmlspecialchars($male['not_heard_reason']) ?>" readonly>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="form-group row">
                                                    <label class="col-form-label col-md-4">Intend to Use FP</label>
                                                    <div class="col-md-8">
                                                        <input type="text" class="form-control" value="<?= $male['intend_fp'] ?>" readonly>
                                                    </div>
                                                </div>

                                                <?php if ($male['intend_fp'] === 'Yes'): ?>
                                                    <div class="form-group row">
                                                        <label class="col-form-label col-md-4">FP Method</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control"
                                                                value="<?= $male['fp_male_method'] ?><?= !empty($male['male_other_method']) ? ' (' . htmlspecialchars($male['male_other_method']) . ')' : '' ?>"
                                                                readonly>
                                                        </div>
                                                    </div>
                                                <?php else: ?>

                                                    <div class="form-group row">
                                                        <label class="col-form-label col-md-4">Reason Not Intending</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control"
                                                                value="<?= htmlspecialchars($male['not_intend_reason']) ?>" readonly>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="card mt-3">
                                            <div class="card-body">
                                                <?php if ($male['desired_children'] > 0): ?>
                                                    <div class="form-group row">
                                                        <label class="col-form-label col-md-4">Desired Children</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control"
                                                                value="<?= $male['desired_children'] ?>" readonly>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row">
                                                        <label class="col-form-label col-md-4">Reason for Wanting Children</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control"
                                                                value="<?= htmlspecialchars($male['children_reason']) ?>" readonly>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="form-group row">
                                                        <label class="col-form-label col-md-4">Reason for No Children</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control"
                                                                value="<?= htmlspecialchars($male['no_children_reason']) ?>" readonly>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="form-group row">
                                                    <label class="col-form-label col-md-4">Children from Past Union</label>
                                                    <div class="col-md-8">
                                                        <input type="text" class="form-control"
                                                            value="<?= ($male['past_children_count'] > 0) ? $male['past_children_count'] : 'No' ?>"
                                                            readonly>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- PhilHealth Membership -->
                                        <div class="form-group row">
                                            <label class="col-form-label col-md-4">PhilHealth Member</label>
                                            <div class="col-md-8">
                                                <input type="text" class="form-control"
                                                    value="<?= $male['philhealth_member'] ?>" readonly>
                                            </div>
                                        </div>

                                        <!-- Marriage Reasons -->
                                        <div class="form-group row">
                                            <label class="col-form-label col-md-4">Reasons for Marriage</label>
                                            <div class="col-md-8">
                                                <textarea class="form-control" rows="3" readonly><?= htmlspecialchars($male['marriage_reasons']) ?></textarea>
                                            </div>
                                        </div>

                                    <?php else: ?>
                                        <div class="alert alert-warning">No male profile found</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Female Profile Form -->
                    <div class="row mt-4 profile-tab-hidden" id="femaleProfileRow">
                        <div class="col-12">
                            <div class="mb-2 no-print text-right">
                                <button class="btn btn-sm btn-outline-danger" onclick="printFemaleProfile()">
                                    <i class="fas fa-print mr-1"></i> Print Female Profile
                                </button>
                            </div>
                            <div class="card card-danger" id="femaleProfileForm">
                                <div class="card-header">
                                    <div class="row align-items-center">
                                        <!-- Logos -->
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center">
                                                <div class="logo-container">
                                                    <img src="../images/popcom1.png" alt="POPCOM" class="logo-img">
                                                </div>
                                                <div class="logo-container">
                                                    <img src="../images/City_of_Bago_Logo1.png" alt="City of Bago" class="logo-img">
                                                </div>
                                                <div class="logo-container">
                                                    <img src="<?= getSecureImagePath('../images/bcpdo.png') ?>" alt="BCPDO" class="logo-img">
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Form Title and Info -->
                                        <div class="col-md-8">
                                            <div class="header-text">
                                                <h2 class="mb-2 text-white"><strong>FEMALE PROFILE FORM</strong></h2>
                                                <p class="mb-1 text-white">Form No.: BCPDO-FPF-001</p>
                                                <p class="mb-1 text-white">Version No.: 01 | Effective: August 13, 2024</p>
                                                <p class="mb-0 text-white"><strong>COUPLE NO.:</strong> None | 
                                                <strong>DATE OF FILING:</strong> <?= !empty($profiles['female']['date_of_filing']) ? date('F j, Y', strtotime($profiles['female']['date_of_filing'])) : ($date_created_value ? date('F j, Y', strtotime($date_created_value)) : date('F j, Y')) ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php if ($profiles['female']): $female = $profiles['female']; ?>
                                        <!-- Residency Type -->
                                        <div class="form-group row">
                                            <div class="col-md-12">
                                                <label class="col-form-label">Residency Type</label>
                                                <input type="text" class="form-control" value="<?= ucfirst(htmlspecialchars($female['residency_type'] ?? '')) ?>" readonly>
                                            </div>
                                        </div>

                                        <!-- Personal Information -->
                                        <div class="form-group row">
                                            <div class="col-md-3 col-sm-6">
                                                <label class="col-form-label">First Name</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($female['first_name']) ?>" readonly>
                                            </div>
                                            <div class="col-md-3 col-sm-6">
                                                <label class="col-form-label">Middle Name</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($female['middle_name']) ?>" readonly>
                                            </div>
                                            <div class="col-md-3 col-sm-6">
                                                <label class="col-form-label">Last Name</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($female['last_name']) ?>" readonly>
                                            </div>
                                            <div class="col-md-3 col-sm-6">
                                                <label class="col-form-label">Suffix</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($female['suffix'] ?? '') ?>" readonly>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <div class="col-md-4">
                                                <label class="col-form-label">Sex</label>
                                                <input type="text" class="form-control" value="<?= $female['sex'] ?>" readonly>
                                            </div>
                                            <div class="col-md-8">
                                                <label class="col-form-label">Email</label>
                                                <input type="email" class="form-control" value="<?= htmlspecialchars($female['email_address']) ?>" readonly>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <div class="col-md-3 col-sm-4">
                                                <label class="col-form-label">Birth Month</label>
                                                <input type="text" class="form-control" value="<?= date('F', strtotime($female['date_of_birth'])) ?>" readonly>
                                            </div>
                                            <div class="col-md-3 col-sm-4">
                                                <label class="col-form-label">Birth Day</label>
                                                <input type="text" class="form-control" value="<?= date('j', strtotime($female['date_of_birth'])) ?>" readonly>
                                            </div>
                                            <div class="col-md-3 col-sm-4">
                                                <label class="col-form-label">Birth Year</label>
                                                <input type="text" class="form-control" value="<?= date('Y', strtotime($female['date_of_birth'])) ?>" readonly>
                                            </div>
                                            <div class="col-md-3 col-sm-12">
                                                <label class="col-form-label">Age</label>
                                                <input type="text" class="form-control" value="<?= $female['age'] ?>" readonly>
                                            </div>
                                        </div>



                                        <!-- Address Section -->
                                        <?php if (($female['residency_type'] ?? '') === 'bago'): ?>
                                        <!-- Bago Address -->
                                        <div class="form-group row">
                                            <div class="col-md-4">
                                                <label class="col-form-label">City</label>
                                                <input type="text" class="form-control" value="Bago" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="col-form-label">Barangay</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($female['barangay'] ?? '') ?>" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="col-form-label">Purok</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($female['purok'] ?? '') ?>" readonly>
                                            </div>
                                        </div>
                                        <?php elseif (($female['residency_type'] ?? '') === 'non-bago'): ?>
                                        <!-- Non-Bago Address -->
                                        <div class="form-group row">
                                            <div class="col-md-4">
                                                <label class="col-form-label">City</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($female['city'] ?? '') ?>" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="col-form-label">Barangay</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($female['barangay'] ?? '') ?>" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="col-form-label">Purok</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($female['purok'] ?? '') ?>" readonly>
                                            </div>
                                        </div>
                                        <?php elseif (($female['residency_type'] ?? '') === 'foreigner'): ?>
                                        <!-- Foreigner Address -->
                                        <div class="form-group row">
                                            <div class="col-md-4">
                                                <label class="col-form-label">Country</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($female['country'] ?? '') ?>" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="col-form-label">State/Province</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($female['state_province'] ?? '') ?>" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="col-form-label">City</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($female['city'] ?? '') ?>" readonly>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <div class="form-group row">
                                            <div class="col-md-6">
                                                <label class="col-form-label">Contact Number</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($female['contact_number']) ?>" readonly>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="col-form-label">Civil Status</label>
                                                <input type="text" class="form-control" value="<?= $female['civil_status'] ?>" readonly>
                                            </div>
                                        </div>

                                        <?php if ($female['civil_status'] === 'Living In'): ?>
                                            <div class="form-group row">
                                                <div class="col-md-6">
                                                    <label class="col-form-label">Years Together</label>
                                                    <input type="text" class="form-control" value="<?= $female['years_living_together'] ?>" readonly>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="col-form-label">Living Reason</label>
                                                    <input type="text" class="form-control" value="<?= htmlspecialchars($female['living_in_reason']) ?>" readonly>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <div class="form-group row">
                                            <div class="col-md-6">
                                                <label class="col-form-label">Education</label>
                                                <input type="text" class="form-control" value="<?= $female['education'] ?>" readonly>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="col-form-label">Religion</label>
                                                <input type="text" class="form-control"
                                                    value="<?= $female['religion'] ?><?= !empty($female['other_religion']) ? ' (' . htmlspecialchars($female['other_religion']) . ')' : '' ?>"
                                                    readonly>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <div class="col-md-6">
                                                <label class="col-form-label">Nationality</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($female['nationality']) ?>" readonly>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="col-form-label">Wedding Type</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($female['wedding_type']) ?>" readonly>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <div class="col-md-4">
                                                <label class="col-form-label">Employment Status</label>
                                                <input type="text" class="form-control" value="<?= $female['employment_status'] ?>" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="col-form-label">Occupation</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($female['occupation']) ?>" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="col-form-label">Monthly Income</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($female['monthly_income']) ?>" readonly>
                                            </div>
                                        </div>

                                        <!-- Family Planning Section -->
                                        <div class="card mt-3">
                                            <div class="card-body">
                                                <div class="form-group row">
                                                    <label class="col-form-label col-md-4">Heard About FP</label>
                                                    <div class="col-md-8">
                                                        <input type="text" class="form-control" value="<?= $female['heard_fp'] ?>" readonly>
                                                    </div>
                                                </div>

                                                <?php if ($female['heard_fp'] === 'Yes'): ?>
                                                    <div class="form-group row">
                                                        <label class="col-form-label col-md-4">FP Facility</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control"
                                                                value="<?= $female['fp_facility'] ?><?= !empty($female['other_facility']) ? ' (' . htmlspecialchars($female['other_facility']) . ')' : '' ?>"
                                                                readonly>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($female['heard_fp'] === 'Yes'): ?>
                                                    <div class="form-group row">
                                                        <label class="col-form-label col-md-4">FP Channel</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control"
                                                                value="<?= $female['fp_channel'] ?><?= !empty($female['other_channel']) ? ' (' . htmlspecialchars($female['other_channel']) . ')' : '' ?>"
                                                                readonly>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="form-group row">
                                                        <label class="col-form-label col-md-4">Reason Not Heard</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control"
                                                                value="<?= htmlspecialchars($female['not_heard_reason']) ?>" readonly>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="form-group row">
                                                    <label class="col-form-label col-md-4">Intend to Use FP</label>
                                                    <div class="col-md-8">
                                                        <input type="text" class="form-control" value="<?= $female['intend_fp'] ?>" readonly>
                                                    </div>
                                                </div>

                                                <?php if ($female['intend_fp'] === 'Yes'): ?>
                                                    <div class="form-group row">
                                                        <label class="col-form-label col-md-4">FP Method</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control"
                                                                value="<?= $female['fp_female_method'] ?><?= !empty($female['female_other_method']) ? ' (' . htmlspecialchars($female['female_other_method']) . ')' : '' ?>"
                                                                readonly>
                                                        </div>
                                                    </div>
                                                <?php else: ?>

                                                    <div class="form-group row">
                                                        <label class="col-form-label col-md-4">Reason Not Intending</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control"
                                                                value="<?= htmlspecialchars($female['not_intend_reason']) ?>" readonly>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Child Preferences -->
                                        <div class="card mt-3">
                                            <div class="card-body">
                                                <?php if ($female['desired_children'] > 0): ?>
                                                    <div class="form-group row">
                                                        <label class="col-form-label col-md-4">Desired Children</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control"
                                                                value="<?= $female['desired_children'] ?>" readonly>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row">
                                                        <label class="col-form-label col-md-4">Reason for Wanting Children</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control"
                                                                value="<?= htmlspecialchars($female['children_reason']) ?>" readonly>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="form-group row">
                                                        <label class="col-form-label col-md-4">Reason for No Children</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control"
                                                                value="<?= htmlspecialchars($female['no_children_reason']) ?>" readonly>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="form-group row">
                                                    <label class="col-form-label col-md-4">Children from Past Union</label>
                                                    <div class="col-md-8">
                                                        <input type="text" class="form-control"
                                                            value="<?= ($female['past_children_count'] > 0) ? $female['past_children_count'] : 'No' ?>"
                                                            readonly>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- PhilHealth Membership -->
                                        <div class="form-group row">
                                            <label class="col-form-label col-md-4">PhilHealth Member</label>
                                            <div class="col-md-8">
                                                <input type="text" class="form-control"
                                                    value="<?= $female['philhealth_member'] ?>" readonly>
                                            </div>
                                        </div>

                                        <!-- Marriage Reasons -->
                                        <div class="form-group row">
                                            <label class="col-form-label col-md-4">Reasons for Marriage</label>
                                            <div class="col-md-8">
                                                <textarea class="form-control" rows="3" readonly><?= htmlspecialchars($female['marriage_reasons']) ?></textarea>
                                            </div>
                                        </div>

                                        <!-- Pregnancy Information -->
                                        <div class="card mt-3">
                                            <div class="card-body">
                                                <div class="form-group row">
                                                    <label class="col-form-label col-md-4">Currently Pregnant</label>
                                                    <div class="col-md-8">
                                                        <input type="text" class="form-control" value="<?= $female['currently_pregnant'] ?>" readonly>
                                                    </div>
                                                </div>

                                                <?php if ($female['currently_pregnant'] === 'Yes'): ?>
                                                    <div class="form-group row">
                                                        <label class="col-form-label col-md-4">Gestation Age</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control" value="<?= $female['gestation_age'] ?> weeks" readonly>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="form-group row">
                                                        <label class="col-form-label col-md-4">Pregnancy Plan</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control" value="<?= htmlspecialchars($female['pregnancy_plan']) ?>" readonly>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                    <?php else: ?>
                                        <div class="alert alert-warning">No female profile found</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                    
                </div>

            </section>
        </div>
        <?php include '../includes/footer.php'; ?>
    </div>
    <?php include '../includes/scripts.php'; ?>
    <style>
        .wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .content-wrapper {
            flex: 1;
            padding-bottom: 60px;
            /* Space for footer */
        }

        .main-footer {
            margin-top: auto;
        }

        /* Print Header Styling */
        .print-header {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .logo-container {
            display: inline-block;
            vertical-align: middle;
            margin-right: 15px;
            text-align: center;
        }

        .logo-img {
            height: 70px;
            width: 70px;
            object-fit: contain;
            display: block;
        }

        .header-text h2 {
            font-size: 1.8rem;
            font-weight: bold;
        }
        
        .header-text p {
            font-size: 1rem;
        }

        .couple-info {
            background: #e9ecef;
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #ced4da;
            font-size: 0.9rem;
        }
        
        .card-header {
            padding: 15px 20px;
        }
        
        .card-header .logo-img {
            height: 50px;
            width: 50px;
        }
        
        .card-primary .card-header {
            background-color: #007bff;
        }
        
        .card-danger .card-header {
            background-color: #dc3545;
        }

        .instructions {
            background: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #ffeaa7;
        }

        .instructions-section {
            margin-bottom: 20px;
        }

        .instructions-section .alert {
            border-left: 4px solid #17a2b8;
        }

        .profile-form {
            margin-bottom: 30px;
        }

        .profile-form .card {
            border: 2px solid #dee2e6;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .profile-form .print-header {
            margin-bottom: 20px;
        }

        .profile-form .header-text h2 {
            font-size: 1.3rem;
        }

        .profile-form .logo-img {
            height: 50px;
            width: 50px;
        }

        /* Couple profile tab visibility */
        .profile-tab-hidden {
            display: none;
        }

        /* Print Styles */
        @media print {
            @page {
                margin: 0.1cm;
                size: auto;
            }
            
            * {
                box-sizing: border-box;
            }
            
            .no-print {
                display: none !important;
            }
            
            .main-sidebar,
            .main-header,
            .content-header,
            .main-footer {
                display: none !important;
            }
            
            .content-wrapper {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            
            .card {
                border: 1px solid #000 !important;
                break-inside: avoid;
                page-break-inside: avoid;
                margin-bottom: 3px !important;
                padding: 0 !important;
            }
            
            .card-header {
                background: #007bff !important;
                border-bottom: 1px solid #000 !important;
                color: #fff !important;
                padding: 5px 10px !important;
                margin-bottom: 0 !important;
            }
            
            .card-primary .card-header {
                background: #007bff !important;
            }
            
            .card-danger .card-header {
                background: #dc3545 !important;
            }
            
            .card-header .text-white {
                color: #fff !important;
            }
            
            .card-header h2 {
                font-size: 12pt !important;
                margin: 2px 0 !important;
                line-height: 1.2 !important;
            }
            
            .card-header p {
                font-size: 8pt !important;
                margin: 1px 0 !important;
                line-height: 1.2 !important;
            }
            
            .card-header .logo-img {
                height: 40px !important;
                width: 40px !important;
            }
            
            .card-body {
                padding: 2px 4px !important;
            }
            
            body {
                font-size: 6pt !important;
                line-height: 1 !important;
            }
            
            .form-group {
                margin-bottom: 1px !important;
            }
            
            .form-group.row {
                margin-left: -2px !important;
                margin-right: -2px !important;
            }
            
            .form-group .col-md-3,
            .form-group .col-md-4,
            .form-group .col-md-6,
            .form-group .col-md-8,
            .form-group .col-md-12 {
                padding-left: 2px !important;
                padding-right: 2px !important;
            }
            
            .col-form-label {
                font-size: 5.5pt !important;
                margin-bottom: 0 !important;
                padding-top: 0 !important;
                padding-bottom: 0 !important;
                line-height: 1 !important;
            }
            
            .form-control {
                border: 1px solid #000 !important;
                background: white !important;
                font-size: 6pt !important;
                padding: 0 2px !important;
                height: auto !important;
                min-height: 12px !important;
                line-height: 1.1 !important;
            }
            
            textarea.form-control {
                min-height: 20px !important;
                padding: 0 2px !important;
                line-height: 1.1 !important;
            }
            
            .card.mt-3 {
                margin-top: 2px !important;
                margin-bottom: 2px !important;
            }
            
            .card.mt-3 .card-body {
                padding: 2px 3px !important;
            }
            
            .alert {
                display: none !important;
            }
            
            .btn {
                display: none !important;
            }
            
            .container-fluid {
                padding: 0 !important;
            }
            
            /* Ensure proper spacing between forms */
            .row.mt-4 {
                margin-top: 3px !important;
            }
            
            /* Reduce spacing in header */
            .card-header .row {
                margin: 0 !important;
            }
            
            .card-header .col-md-4,
            .card-header .col-md-8 {
                padding: 0 2px !important;
            }
            
            /* Make nested sections more compact */
            .card .card {
                margin-top: 2px !important;
                margin-bottom: 2px !important;
            }
            
            /* Reduce all margins and paddings */
            .mb-4, .mb-3, .mb-2, .mb-1 {
                margin-bottom: 1px !important;
            }
            
            .mt-4, .mt-3, .mt-2, .mt-1 {
                margin-top: 1px !important;
            }
            
            /* Make form fields use more horizontal space */
            .form-group .col-md-3 {
                width: 20% !important;
            }
            
            .form-group .col-md-4 {
                width: 25% !important;
            }
            
            .form-group .col-md-6 {
                width: 48% !important;
            }
            
            .form-group .col-md-8 {
                width: 65% !important;
            }
        }
    </style>

    <script>
        // Toggle between male and female profile views (similar to Email/SMS logs tabs)
        document.addEventListener('DOMContentLoaded', function () {
            const maleRow = document.getElementById('maleProfileRow');
            const femaleRow = document.getElementById('femaleProfileRow');
            const maleBtn = document.getElementById('maleTabBtn');
            const femaleBtn = document.getElementById('femaleTabBtn');

            if (!maleRow || !femaleRow || !maleBtn || !femaleBtn) return;

            function showMale() {
                maleRow.classList.remove('profile-tab-hidden');
                femaleRow.classList.add('profile-tab-hidden');

                maleBtn.classList.add('btn-primary', 'active');
                maleBtn.classList.remove('btn-outline-secondary');

                femaleBtn.classList.add('btn-outline-secondary');
                femaleBtn.classList.remove('btn-primary', 'active');
            }

            function showFemale() {
                femaleRow.classList.remove('profile-tab-hidden');
                maleRow.classList.add('profile-tab-hidden');

                femaleBtn.classList.add('btn-primary', 'active');
                femaleBtn.classList.remove('btn-outline-secondary');

                maleBtn.classList.add('btn-outline-secondary');
                maleBtn.classList.remove('btn-primary', 'active');
            }

            maleBtn.addEventListener('click', function (e) {
                e.preventDefault();
                showMale();
                window.scrollTo({ top: maleRow.offsetTop - 80, behavior: 'smooth' });
            });

            femaleBtn.addEventListener('click', function (e) {
                e.preventDefault();
                showFemale();
                window.scrollTo({ top: femaleRow.offsetTop - 80, behavior: 'smooth' });
            });

            // Default state: show male profile
            showMale();
        });

        function printMaleProfile() {
            // Hide elements that shouldn't be printed
            const noPrintElements = document.querySelectorAll('.no-print');
            const sidebar = document.querySelector('.main-sidebar');
            const navbar = document.querySelector('.main-header');
            const contentHeader = document.querySelector('.content-header');
            const femaleProfileForm = document.getElementById('femaleProfileForm');
            const maleProfileForm = document.getElementById('maleProfileForm');
            const maleRow = document.getElementById('maleProfileRow');
            const femaleRow = document.getElementById('femaleProfileRow');

            // Remember original tab visibility
            const maleRowWasHidden = maleRow ? maleRow.classList.contains('profile-tab-hidden') : false;
            const femaleRowWasHidden = femaleRow ? femaleRow.classList.contains('profile-tab-hidden') : false;
            
            // Hide non-printable elements
            noPrintElements.forEach(el => el.style.display = 'none');
            if (sidebar) sidebar.style.display = 'none';
            if (navbar) navbar.style.display = 'none';
            if (contentHeader) contentHeader.style.display = 'none';
            if (femaleProfileForm) femaleProfileForm.style.display = 'none';

            // Ensure male tab/row and form are visible for printing
            if (maleRow) maleRow.classList.remove('profile-tab-hidden');
            if (femaleRow) femaleRow.classList.add('profile-tab-hidden');
            if (maleProfileForm) maleProfileForm.style.display = 'block';
            
            // Print the page
            window.print();
            
            // Restore elements after printing
            setTimeout(() => {
                noPrintElements.forEach(el => el.style.display = '');
                if (sidebar) sidebar.style.display = '';
                if (navbar) navbar.style.display = '';
                if (contentHeader) contentHeader.style.display = '';
                if (femaleProfileForm) femaleProfileForm.style.display = '';

                // Restore original tab visibility
                if (maleRow) {
                    if (maleRowWasHidden) maleRow.classList.add('profile-tab-hidden');
                    else maleRow.classList.remove('profile-tab-hidden');
                }
                if (femaleRow) {
                    if (femaleRowWasHidden) femaleRow.classList.add('profile-tab-hidden');
                    else femaleRow.classList.remove('profile-tab-hidden');
                }
            }, 1000);
        }

        function printFemaleProfile() {
            // Hide elements that shouldn't be printed
            const noPrintElements = document.querySelectorAll('.no-print');
            const sidebar = document.querySelector('.main-sidebar');
            const navbar = document.querySelector('.main-header');
            const contentHeader = document.querySelector('.content-header');
            const maleProfileForm = document.getElementById('maleProfileForm');
            const femaleProfileForm = document.getElementById('femaleProfileForm');
            const maleRow = document.getElementById('maleProfileRow');
            const femaleRow = document.getElementById('femaleProfileRow');

            // Remember original tab visibility
            const maleRowWasHidden = maleRow ? maleRow.classList.contains('profile-tab-hidden') : false;
            const femaleRowWasHidden = femaleRow ? femaleRow.classList.contains('profile-tab-hidden') : false;
            
            // Hide non-printable elements
            noPrintElements.forEach(el => el.style.display = 'none');
            if (sidebar) sidebar.style.display = 'none';
            if (navbar) navbar.style.display = 'none';
            if (contentHeader) contentHeader.style.display = 'none';
            if (maleProfileForm) maleProfileForm.style.display = 'none';
            
            // Ensure female tab/row and form are visible for printing
            if (femaleRow) femaleRow.classList.remove('profile-tab-hidden');
            if (maleRow) maleRow.classList.add('profile-tab-hidden');
            if (femaleProfileForm) femaleProfileForm.style.display = 'block';
            
            // Print the page
            window.print();
            
            // Restore elements after printing
            setTimeout(() => {
                noPrintElements.forEach(el => el.style.display = '');
                if (sidebar) sidebar.style.display = '';
                if (navbar) navbar.style.display = '';
                if (contentHeader) contentHeader.style.display = '';
                if (maleProfileForm) maleProfileForm.style.display = '';

                // Restore original tab visibility
                if (maleRow) {
                    if (maleRowWasHidden) maleRow.classList.add('profile-tab-hidden');
                    else maleRow.classList.remove('profile-tab-hidden');
                }
                if (femaleRow) {
                    if (femaleRowWasHidden) femaleRow.classList.add('profile-tab-hidden');
                    else femaleRow.classList.remove('profile-tab-hidden');
                }
            }, 1000);
        }
    </script>
</body>

</html>