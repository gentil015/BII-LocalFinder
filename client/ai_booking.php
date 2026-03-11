<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/ai_booking.php';

// Check if user is logged in and is a client
if (!isLoggedIn()) {
    redirect('login.php');
}

if (isProvider()) {
    redirect('provider/dashboard.php');
}

$db = Database::getInstance()->getConnection();
$aiBooking = new AIBookingHandler($db);

$step = $_GET['step'] ?? 'input';
$bookingData = null;
$error = null;
$success = null;

// Handle AI processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_prompt'])) {
    $prompt = trim($_POST['booking_prompt'] ?? '');
    
    if (empty($prompt)) {
        $error = "Please describe what service you need";
    } else {
        try {
            $bookingData = $aiBooking->processBookingRequest($prompt, $_SESSION['user_id']);
            $_SESSION['ai_booking_data'] = $bookingData;
            $step = 'review';
        } catch (Exception $e) {
            $error = "Error processing your request: " . $e->getMessage();
        }
    }
}

// Handle booking confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_booking'])) {
    $bookingData = $_SESSION['ai_booking_data'] ?? null;
    $providerId = intval($_POST['provider_id'] ?? 0);
    
    if (!$bookingData || !$providerId) {
        $error = "Invalid booking data";
    } else {
        $result = $aiBooking->createBooking(
            $bookingData['extracted'],
            $providerId,
            $_SESSION['user_id']
        );
        
        if ($result['success']) {
            unset($_SESSION['ai_booking_data']);
            $_SESSION['booking_success'] = $result['booking_id'];
            header("Location: ai-booking.php?step=success&booking_id=" . $result['booking_id']);
            exit();
        } else {
            $error = $result['message'];
        }
    }
}

// Load saved booking data if on review step
if ($step === 'review' && isset($_SESSION['ai_booking_data'])) {
    $bookingData = $_SESSION['ai_booking_data'];
}

// Load booking details for success page
$bookingDetails = null;
if ($step === 'success' && isset($_GET['booking_id'])) {
    $stmt = $db->prepare("
        SELECT b.*, sp.profession, u.full_name as provider_name, u.email as provider_email, u.phone as provider_phone
        FROM bookings b
        JOIN service_providers sp ON b.provider_id = sp.id
        JOIN users u ON sp.user_id = u.id
        WHERE b.id = ? AND b.client_id = ?
    ");
    $stmt->execute([$_GET['booking_id'], $_SESSION['user_id']]);
    $bookingDetails = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Booking Assistant - <?php echo getPlatformName(); ?></title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0d6efd;
            --secondary: #6c757d;
            --success: #198754;
            --info: #0dcaf0;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .ai-container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .ai-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .ai-header {
            background: var(--gradient);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .ai-header h1 {
            margin: 0 0 0.5rem 0;
            font-weight: 700;
            font-size: 2rem;
        }
        
        .ai-header p {
            margin: 0;
            opacity: 0.9;
        }
        
        .ai-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2.5rem;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .ai-body {
            padding: 2.5rem;
        }
        
        .prompt-input {
            position: relative;
            margin-bottom: 2rem;
        }
        
        .prompt-input textarea {
            width: 100%;
            min-height: 150px;
            padding: 1.5rem;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            font-size: 1.1rem;
            transition: all 0.3s;
            resize: vertical;
        }
        
        .prompt-input textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.1);
            outline: none;
        }
        
        .example-prompts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .example-prompt {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .example-prompt:hover {
            border-color: var(--primary);
            background: #e7f1ff;
            transform: translateY(-2px);
        }
        
        .example-prompt i {
            color: var(--primary);
            margin-right: 0.5rem;
        }
        
        .ai-summary {
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--primary);
        }
        
        .provider-match {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }
        
        .provider-match:hover {
            border-color: var(--primary);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .provider-match.selected {
            border-color: var(--primary);
            background: #e7f1ff;
        }
        
        .provider-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.5rem;
            margin-right: 1.5rem;
            flex-shrink: 0;
        }
        
        .provider-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .match-score {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .rating-stars {
            color: #ffc107;
            margin-right: 0.5rem;
        }
        
        .btn-ai {
            background: var(--gradient);
            border: none;
            color: white;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-ai:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            color: white;
        }
        
        .btn-ai i {
            font-size: 1.3rem;
        }
        
        .success-animation {
            text-align: center;
            padding: 2rem 0;
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            background: var(--success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 3rem;
            animation: successPop 0.5s ease-out;
        }
        
        @keyframes successPop {
            0% { transform: scale(0); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            border-left: 4px solid var(--primary);
        }
        
        .info-item i {
            color: var(--primary);
            margin-right: 0.5rem;
        }
        
        .info-item strong {
            display: block;
            color: var(--secondary);
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
        }
        
        .back-link {
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .back-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        @media (max-width: 768px) {
            .ai-body {
                padding: 1.5rem;
            }
            
            .example-prompts {
                grid-template-columns: 1fr;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="ai-container">
        <a href="dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        
        <div class="ai-card">
            <div class="ai-header">
                <div class="ai-icon">
                    <i class="fas fa-robot"></i>
                </div>
                <h1>AI Booking Assistant</h1>
                <p>Just describe what you need in plain English - our AI will handle the rest!</p>
            </div>
            
            <div class="ai-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($step === 'input'): ?>
                    <!-- STEP 1: Input Prompt -->
                    <form method="POST" action="">
                        <div class="prompt-input">
                            <label class="form-label fw-bold fs-5 mb-3">
                                <i class="fas fa-comment-dots me-2"></i>
                                What service do you need?
                            </label>
                            <textarea 
                                name="booking_prompt" 
                                class="form-control" 
                                placeholder="Example: I need a plumber in Kimironko tomorrow morning to fix a leaking pipe. It's urgent!"
                                required
                            ><?php echo htmlspecialchars($_POST['booking_prompt'] ?? ''); ?></textarea>
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Include: service type, location, date/time, and any special requirements
                            </small>
                        </div>
                        
                        <h6 class="fw-bold mb-3">
                            <i class="fas fa-lightbulb me-2"></i>
                            Try these examples:
                        </h6>
                        <div class="example-prompts">
                            <div class="example-prompt" onclick="setPrompt(this.querySelector('p').textContent)">
                                <i class="fas fa-wrench"></i>
                                <strong>Plumbing</strong>
                                <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem; color: #666;">
                                    Need a plumber in Remera today to fix my toilet
                                </p>
                            </div>
                            <div class="example-prompt" onclick="setPrompt(this.querySelector('p').textContent)">
                                <i class="fas fa-bolt"></i>
                                <strong>Electrical</strong>
                                <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem; color: #666;">
                                    Electrician needed tomorrow at 2pm in Kicukiro for wiring
                                </p>
                            </div>
                            <div class="example-prompt" onclick="setPrompt(this.querySelector('p').textContent)">
                                <i class="fas fa-broom"></i>
                                <strong>Cleaning</strong>
                                <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem; color: #666;">
                                    Looking for house cleaner in Gasabo next Monday morning
                                </p>
                            </div>
                            <div class="example-prompt" onclick="setPrompt(this.querySelector('p').textContent)">
                                <i class="fas fa-hammer"></i>
                                <strong>Carpentry</strong>
                                <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem; color: #666;">
                                    Need carpenter ASAP in Nyarugenge to fix broken door
                                </p>
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" name="process_prompt" class="btn-ai">
                                <i class="fas fa-magic"></i>
                                Process with AI
                            </button>
                        </div>
                    </form>
                    
                <?php elseif ($step === 'review' && $bookingData): ?>
                    <!-- STEP 2: Review and Select Provider -->
                    <div class="ai-summary">
                        <h5 class="fw-bold mb-3">
                            <i class="fas fa-brain me-2"></i>
                            AI Understanding:
                        </h5>
                        <p class="mb-0"><?php echo $bookingData['summary']; ?></p>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <strong><i class="fas fa-briefcase"></i> Service</strong>
                            <?php echo htmlspecialchars($bookingData['extracted']['service']['profession'] ?? 'Not specified'); ?>
                            <small class="text-muted d-block">
                                (<?php echo round(($bookingData['extracted']['service']['confidence'] ?? 0) * 100); ?>% confidence)
                            </small>
                        </div>
                        <div class="info-item">
                            <strong><i class="fas fa-map-marker-alt"></i> Location</strong>
                            <?php echo htmlspecialchars($bookingData['extracted']['location']['location'] ?? 'Not specified'); ?>
                        </div>
                        <div class="info-item">
                            <strong><i class="fas fa-calendar"></i> Date</strong>
                            <?php echo date('M d, Y', strtotime($bookingData['extracted']['date']['date'])); ?>
                        </div>
                        <div class="info-item">
                            <strong><i class="fas fa-clock"></i> Time</strong>
                            <?php echo date('g:i A', strtotime($bookingData['extracted']['time']['time'])); ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($bookingData['providers'])): ?>
                        <h5 class="fw-bold mb-3 mt-4">
                            <i class="fas fa-users me-2"></i>
                            Matching Providers (<?php echo count($bookingData['providers']); ?> found)
                        </h5>
                        
                        <form method="POST" action="" id="providerForm">
                            <?php foreach ($bookingData['providers'] as $index => $provider): ?>
                                <div class="provider-match" onclick="selectProvider(<?php echo $provider['id']; ?>, this)">
                                    <div class="d-flex align-items-start">
                                        <div class="provider-avatar">
                                            <?php if (!empty($provider['profile_image'])): ?>
                                                <img src="../uploads/profiles/<?php echo htmlspecialchars($provider['profile_image']); ?>" alt="">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($provider['full_name'], 0, 1)); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                                <div>
                                                    <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($provider['full_name']); ?></h6>
                                                    <p class="text-primary mb-1"><?php echo htmlspecialchars($provider['profession']); ?></p>
                                                </div>
                                                <span class="match-score">
                                                    <?php echo round($provider['match_score']); ?>% Match
                                                </span>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <span class="rating-stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <?php if ($i <= $provider['average_rating']): ?>
                                                            <i class="fas fa-star"></i>
                                                        <?php else: ?>
                                                            <i class="far fa-star"></i>
                                                        <?php endif; ?>
                                                    <?php endfor; ?>
                                                </span>
                                                <span class="text-muted small">
                                                    <?php echo number_format($provider['average_rating'], 1); ?> 
                                                    (<?php echo $provider['total_reviews']; ?> reviews)
                                                </span>
                                            </div>
                                            
                                            <p class="mb-2">
                                                <i class="fas fa-map-marker-alt text-muted me-1"></i>
                                                <?php echo htmlspecialchars($provider['location']); ?>
                                                <?php if ($provider['district']): ?>
                                                    , <?php echo htmlspecialchars($provider['district']); ?>
                                                <?php endif; ?>
                                            </p>
                                            
                                            <?php if ($provider['hourly_rate']): ?>
                                                <p class="mb-2 fw-bold text-primary">
                                                    <i class="fas fa-money-bill-wave me-1"></i>
                                                    RWF <?php echo number_format($provider['hourly_rate']); ?>/hour
                                                </p>
                                            <?php endif; ?>
                                            
                                            <span class="badge bg-<?php echo $provider['availability'] === 'available' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($provider['availability']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <input type="hidden" name="provider_id" id="selectedProviderId" required>
                            
                            <div class="d-flex gap-2 mt-4">
                                <a href="ai-booking.php" class="btn btn-outline-secondary flex-grow-1">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    Start Over
                                </a>
                                <button type="submit" name="confirm_booking" class="btn-ai flex-grow-1" id="confirmBtn" disabled>
                                    <i class="fas fa-check"></i>
                                    Confirm Booking
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No providers found matching your criteria. Please try adjusting your search or 
                            <a href="ai-booking.php" class="alert-link">start a new search</a>.
                        </div>
                    <?php endif; ?>
                    
                <?php elseif ($step === 'success' && $bookingDetails): ?>
                    <!-- STEP 3: Success -->
                    <div class="success-animation">
                        <div class="success-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <h3 class="fw-bold mb-3">Booking Confirmed!</h3>
                        <p class="text-muted mb-4">
                            Your booking request has been sent to the provider. 
                            They'll contact you shortly to confirm the details.
                        </p>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <strong>Booking ID</strong>
                            #<?php echo $bookingDetails['id']; ?>
                        </div>
                        <div class="info-item">
                            <strong>Provider</strong>
                            <?php echo htmlspecialchars($bookingDetails['provider_name']); ?>
                        </div>
                        <div class="info-item">
                            <strong>Service</strong>
                            <?php echo htmlspecialchars($bookingDetails['profession']); ?>
                        </div>
                        <div class="info-item">
                            <strong>Status</strong>
                            <span class="badge bg-warning">Pending</span>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-4">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>What's next?</strong><br>
                        The provider will review your request and contact you at 
                        <?php echo htmlspecialchars($bookingDetails['provider_phone'] ?? $bookingDetails['provider_email']); ?>
                        to confirm the booking details.
                    </div>
                    
                    <div class="d-flex gap-2 mt-4">
                        <a href="dashboard.php" class="btn btn-outline-secondary flex-grow-1">
                            <i class="fas fa-home me-2"></i>
                            Go to Dashboard
                        </a>
                        <a href="ai-booking.php" class="btn-ai flex-grow-1">
                            <i class="fas fa-plus me-2"></i>
                            Create Another Booking
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        function setPrompt(text) {
            document.querySelector('textarea[name="booking_prompt"]').value = text;
        }
        
        function selectProvider(providerId, element) {
            // Remove selection from all providers
            document.querySelectorAll('.provider-match').forEach(p => {
                p.classList.remove('selected');
            });
            
            // Add selection to clicked provider
            element.classList.add('selected');
            
            // Set hidden input value
            document.getElementById('selectedProviderId').value = providerId;
            
            // Enable confirm button
            document.getElementById('confirmBtn').disabled = false;
        }
        
        // Auto-select first provider if only one available
        document.addEventListener('DOMContentLoaded', function() {
            const providers = document.querySelectorAll('.provider-match');
            if (providers.length === 1) {
                const providerId = providers[0].querySelector('.provider-avatar').closest('.provider-match').onclick.toString().match(/\d+/)[0];
                selectProvider(parseInt(providerId), providers[0]);
            }
        });
    </script>
</body>
</html>