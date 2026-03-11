<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    die('Unauthorized access');
}

$complaint_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($complaint_id <= 0) {
    http_response_code(400);
    die('Invalid complaint ID');
}

$db = Database::getInstance()->getConnection();

// Get complaint details
$stmt = $db->prepare("
    SELECT 
        c.*,
        u.full_name as user_name,
        u.email as user_email,
        u.phone as user_phone,
        u.profile_image as user_image,
        sp.profession,
        pv.full_name as provider_name,
        pv.email as provider_email,
        pv.profile_image as provider_image,
        a.full_name as assigned_admin_name
    FROM complaints c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN service_providers sp ON c.provider_id = sp.id
    LEFT JOIN users pv ON sp.user_id = pv.id
    LEFT JOIN users a ON c.assigned_admin_id = a.id
    WHERE c.id = ?
");
$stmt->execute([$complaint_id]);
$complaint = $stmt->fetch();

if (!$complaint) {
    http_response_code(404);
    die('Complaint not found');
}

// Get attachments
$attachments_stmt = $db->prepare("SELECT * FROM complaint_attachments WHERE complaint_id = ? ORDER BY created_at DESC");
$attachments_stmt->execute([$complaint_id]);
$attachments = $attachments_stmt->fetchAll();

// Get responses
$responses_stmt = $db->prepare("
    SELECT cr.*, u.full_name as admin_name, u.email as admin_email
    FROM complaint_responses cr
    LEFT JOIN users u ON cr.admin_id = u.id
    WHERE cr.complaint_id = ?
    ORDER BY cr.created_at DESC
");
$responses_stmt->execute([$complaint_id]);
$responses = $responses_stmt->fetchAll();

// Get internal notes
$notes_stmt = $db->prepare("
    SELECT cn.*, u.full_name as admin_name
    FROM complaint_notes cn
    LEFT JOIN users u ON cn.admin_id = u.id
    WHERE cn.complaint_id = ? AND cn.is_internal = 1
    ORDER BY cn.created_at DESC
");
$notes_stmt->execute([$complaint_id]);
$notes = $notes_stmt->fetchAll();

// Get logs
$logs_stmt = $db->prepare("
    SELECT cl.*, u.full_name as admin_name
    FROM complaint_logs cl
    LEFT JOIN users u ON cl.admin_id = u.id
    WHERE cl.complaint_id = ?
    ORDER BY cl.created_at DESC
");
$logs_stmt->execute([$complaint_id]);
$logs = $logs_stmt->fetchAll();

$complaint_types = [
    'service_quality' => 'Service Quality Issues',
    'professional_behavior' => 'Unprofessional Behavior',
    'pricing_dispute' => 'Pricing Dispute',
    'scheduling' => 'Scheduling/Timing Issues',
    'communication' => 'Communication Problems',
    'safety_concerns' => 'Safety Concerns',
    'fraud' => 'Fraud or Scam',
    'property_damage' => 'Property Damage',
    'other' => 'Other Issues'
];
?>

<!-- Complaint Details Modal Content -->
<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#detailsTab">Details</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#responsesTab">Responses (<?php echo count($responses); ?>)</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#attachmentsTab">Attachments (<?php echo count($attachments); ?>)</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#notesTab">Internal Notes (<?php echo count($notes); ?>)</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#historyTab">History</a>
    </li>
</ul>

<div class="tab-content">
    <!-- Details Tab -->
    <div id="detailsTab" class="tab-pane fade show active">
        <div class="detail-section">
            <h5><i class="fas fa-info-circle me-2"></i> Complaint Information</h5>
            <div class="detail-row">
                <div class="detail-item">
                    <span class="detail-label">Complaint ID</span>
                    <span class="detail-value">#<?php echo $complaint['id']; ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Type</span>
                    <span class="detail-value"><?php echo htmlspecialchars($complaint_types[$complaint['complaint_type']] ?? $complaint['complaint_type']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Status</span>
                    <span class="badge badge-<?php echo $complaint['status']; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Priority</span>
                    <span class="badge badge-priority-<?php echo $complaint['priority_level']; ?>">
                        <?php echo ucfirst($complaint['priority_level']); ?>
                    </span>
                </div>
            </div>
            
            <div class="detail-row">
                <div class="detail-item">
                    <span class="detail-label">Filed On</span>
                    <span class="detail-value"><?php echo date('M d, Y H:i A', strtotime($complaint['created_at'])); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Last Updated</span>
                    <span class="detail-value"><?php echo date('M d, Y H:i A', strtotime($complaint['updated_at'])); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Assigned To</span>
                    <span class="detail-value"><?php echo $complaint['assigned_admin_name'] ? htmlspecialchars($complaint['assigned_admin_name']) : '<span class="text-muted">Unassigned</span>'; ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Anonymous Report</span>
                    <span class="detail-value">
                        <?php echo $complaint['anonymous_report'] ? '<span class="badge bg-warning">Yes</span>' : '<span class="badge bg-success">No</span>'; ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="detail-section">
            <h5><i class="fas fa-user me-2"></i> Complaint By</h5>
            <div class="detail-row">
                <?php if (!$complaint['anonymous_report']): ?>
                    <div class="detail-item">
                        <span class="detail-label">Name</span>
                        <span class="detail-value"><?php echo htmlspecialchars($complaint['user_name']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Email</span>
                        <span class="detail-value">
                            <a href="mailto:<?php echo htmlspecialchars($complaint['user_email']); ?>">
                                <?php echo htmlspecialchars($complaint['user_email']); ?>
                            </a>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Phone</span>
                        <span class="detail-value"><?php echo htmlspecialchars($complaint['user_phone'] ?? 'N/A'); ?></span>
                    </div>
                <?php else: ?>
                    <div class="detail-item">
                        <span class="badge bg-secondary">Anonymous Report</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Contact Email</span>
                        <span class="detail-value">
                            <a href="mailto:<?php echo htmlspecialchars($complaint['user_email']); ?>">
                                <?php echo htmlspecialchars($complaint['user_email']); ?>
                            </a>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="detail-section">
            <h5><i class="fas fa-briefcase me-2"></i> Service Provider</h5>
            <div class="detail-row">
                <div class="detail-item">
                    <span class="detail-label">Name</span>
                    <span class="detail-value"><?php echo htmlspecialchars($complaint['provider_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Profession</span>
                    <span class="detail-value"><?php echo htmlspecialchars($complaint['profession'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Email</span>
                    <span class="detail-value">
                        <a href="mailto:<?php echo htmlspecialchars($complaint['provider_email']); ?>">
                            <?php echo htmlspecialchars($complaint['provider_email']); ?>
                        </a>
                    </span>
                </div>
            </div>
        </div>

        <div class="detail-section">
            <h5><i class="fas fa-align-left me-2"></i> Complaint Description</h5>
            <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; line-height: 1.6;">
                <?php echo nl2br(htmlspecialchars($complaint['description'])); ?>
            </div>
        </div>
    </div>

    <!-- Responses Tab -->
    <div id="responsesTab" class="tab-pane fade">
        <?php if (!empty($responses)): ?>
            <div class="timeline">
                <?php foreach ($responses as $response): ?>
                    <div class="timeline-item">
                        <div class="timeline-date"><?php echo date('M d, Y H:i A', strtotime($response['created_at'])); ?></div>
                        <div class="timeline-content">
                            <strong><?php echo htmlspecialchars($response['admin_name'] ?? 'System'); ?></strong>
                            <p class="mb-0 mt-2"><?php echo nl2br(htmlspecialchars($response['message'])); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> No responses yet
            </div>
        <?php endif; ?>
    </div>

    <!-- Attachments Tab -->
    <div id="attachmentsTab" class="tab-pane fade">
        <?php if (!empty($attachments)): ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Uploaded</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attachments as $attachment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($attachment['file_name']); ?></td>
                                <td><small><?php echo htmlspecialchars($attachment['file_type']); ?></small></td>
                                <td><small><?php echo $attachment['file_size'] ? number_format($attachment['file_size'] / 1024, 2) . ' KB' : 'N/A'; ?></small></td>
                                <td><small><?php echo date('M d, Y', strtotime($attachment['created_at'])); ?></small></td>
                                <td>
                                    <a href="../../uploads/complaints/<?php echo htmlspecialchars($attachment['file_path']); ?>" 
                                       class="btn btn-sm btn-primary" download>
                                        <i class="fas fa-download"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> No attachments
            </div>
        <?php endif; ?>
    </div>

    <!-- Internal Notes Tab -->
    <div id="notesTab" class="tab-pane fade">
        <?php if (!empty($notes)): ?>
            <div class="timeline">
                <?php foreach ($notes as $note): ?>
                    <div class="timeline-item">
                        <div class="timeline-date"><?php echo date('M d, Y H:i A', strtotime($note['created_at'])); ?></div>
                        <div class="timeline-content">
                            <strong><?php echo htmlspecialchars($note['admin_name']); ?></strong>
                            <p class="mb-0 mt-2"><?php echo nl2br(htmlspecialchars($note['note'])); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> No internal notes yet
            </div>
        <?php endif; ?>
    </div>

    <!-- History Tab -->
    <div id="historyTab" class="tab-pane fade">
        <?php if (!empty($logs)): ?>
            <div class="timeline">
                <?php foreach ($logs as $log): ?>
                    <div class="timeline-item">
                        <div class="timeline-date"><?php echo date('M d, Y H:i A', strtotime($log['created_at'])); ?></div>
                        <div class="timeline-content">
                            <strong><?php echo htmlspecialchars($log['admin_name'] ?? 'System'); ?></strong>
                            <br>
                            <small><?php echo htmlspecialchars($log['action']); ?></small>
                            <?php if ($log['details']): ?>
                                <p class="mb-0 mt-2 small"><?php echo htmlspecialchars($log['details']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> No activity log
            </div>
        <?php endif; ?>
    </div>
</div>