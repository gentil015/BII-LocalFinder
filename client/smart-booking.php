<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Booking - AI Assistant</title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        
        .container { max-width: 1000px; margin: 0 auto; }
        
        .main-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 3s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 1; }
        }
        
        .ai-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2.5rem;
            position: relative;
            z-index: 1;
        }
        
        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }
        
        .header p {
            opacity: 0.95;
            font-size: 1.1rem;
            position: relative;
            z-index: 1;
        }
        
        .content {
            padding: 2.5rem;
        }
        
        .chat-interface {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 1.5rem;
            min-height: 200px;
            margin-bottom: 1.5rem;
        }
        
        .input-group {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .prompt-textarea {
            width: 100%;
            min-height: 120px;
            padding: 1.5rem;
            border: 2px solid #e9ecef;
            border-radius: 16px;
            font-size: 1.1rem;
            resize: vertical;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .prompt-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .char-counter {
            position: absolute;
            bottom: -20px;
            right: 10px;
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .quick-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        
        .quick-btn {
            flex: 1;
            min-width: 200px;
            padding: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            text-align: left;
        }
        
        .quick-btn:hover {
            border-color: #667eea;
            background: #f8f9ff;
            transform: translateY(-2px);
        }
        
        .quick-btn i {
            color: #667eea;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .quick-btn strong {
            display: block;
            margin-bottom: 0.25rem;
            color: #212529;
        }
        
        .quick-btn small {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .ai-button {
            width: 100%;
            padding: 1.25rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }
        
        .ai-button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .ai-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .ai-button .spinner {
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .results-section {
            margin-top: 2rem;
            display: none;
        }
        
        .results-section.active {
            display: block;
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .summary-card {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-left: 4px solid #667eea;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .summary-card h5 {
            color: #667eea;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .info-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .info-tag {
            background: white;
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .info-tag i {
            color: #667eea;
        }
        
        .info-tag strong {
            margin-right: 0.5rem;
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .provider-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        
        .provider-card:hover {
            border-color: #667eea;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            transform: translateY(-4px);
        }
        
        .provider-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
        }
        
        .provider-card.selected::after {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 32px;
            height: 32px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .provider-header {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .provider-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            flex-shrink: 0;
            overflow: hidden;
        }
        
        .provider-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .provider-info {
            flex: 1;
        }
        
        .provider-name {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: #212529;
        }
        
        .provider-profession {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }
        
        .stars {
            color: #ffc107;
        }
        
        .match-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.9rem;
        }
        
        .provider-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }
        
        .detail-item {
            display: flex
            align-items: center;
            gap: 0.5rem;
            color: #6c757d;
        }
        
        .detail-item i {
            color: #667eea;
        }
        
        .success-message {
            text-align: center;
            padding: 3rem 2rem;
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            background: #198754;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            color: white;
            font-size: 3rem;
            animation: successPop 0.5s ease-out;
        }
        
        @keyframes successPop {
            0% { transform: scale(0); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .alert {
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border: none;
        }
        
        .alert-info {
            background: #e7f3ff;
            color: #004085;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        @media (max-width: 768px) {
            .content { padding: 1.5rem; }
            .header { padding: 2rem 1rem; }
            .header h1 { font-size: 1.75rem; }
            .quick-btn { min-width: 100%; }
            .provider-details { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-card">
            <div class="header">
                <div class="ai-icon">
                    <i class="fas fa-robot"></i>
                </div>
                <h1>Smart Booking Assistant</h1>
                <p>Describe what you need in your own words - AI does the rest!</p>
            </div>
            
            <div class="content">
                <!-- Input Section -->
                <div id="inputSection">
                    <div class="input-group">
                        <textarea 
                            id="promptInput" 
                            class="prompt-textarea" 
                            placeholder="Example: I need a plumber in Kimironko tomorrow morning to fix my leaking kitchen sink. It's kind of urgent..."
                            maxlength="500"
                        ></textarea>
                        <span class="char-counter"><span id="charCount">0</span>/500</span>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-lightbulb me-2"></i>
                        <strong>Tip:</strong> Include service type, location, when you need it, and any special requirements for best results.
                    </div>
                    
                    <h6 class="mb-3" style="font-weight: 600; color: #6c757d;">
                        <i class="fas fa-magic me-2"></i>Quick Templates:
                    </h6>
                    <div class="quick-actions">
                        <button class="quick-btn" onclick="setTemplate('plumber')">
                            <i class="fas fa-wrench"></i>
                            <strong>Plumbing</strong>
                            <small>Fix leaks, pipes, drainage</small>
                        </button>
                        <button class="quick-btn" onclick="setTemplate('electrician')">
                            <i class="fas fa-bolt"></i>
                            <strong>Electrical</strong>
                            <small>Wiring, outlets, repairs</small>
                        </button>
                        <button class="quick-btn" onclick="setTemplate('cleaner')">
                            <i class="fas fa-broom"></i>
                            <strong>Cleaning</strong>
                            <small>House, office cleaning</small>
                        </button>
                        <button class="quick-btn" onclick="setTemplate('carpenter')">
                            <i class="fas fa-hammer"></i>
                            <strong>Carpentry</strong>
                            <small>Furniture, doors, repairs</small>
                        </button>
                    </div>
                    
                    <button id="processBtn" class="ai-button" onclick="processBooking()">
                        <i class="fas fa-magic"></i>
                        <span>Process with AI</span>
                    </button>
                </div>
                
                <!-- Results Section -->
                <div id="resultsSection" class="results-section">
                    <div class="summary-card">
                        <h5><i class="fas fa-brain me-2"></i>AI Understanding:</h5>
                        <p id="summaryText"></p>
                        <div class="info-tags" id="infoTags"></div>
                    </div>
                    
                    <h5 style="font-weight: 700; margin-bottom: 1.5rem;">
                        <i class="fas fa-users me-2"></i>
                        Matching Providers (<span id="providerCount">0</span>)
                    </h5>
                    
                    <div id="providersContainer"></div>
                    
                    <button id="confirmBtn" class="ai-button" onclick="confirmBooking()" style="display: none;">
                        <i class="fas fa-check"></i>
                        <span>Confirm Booking</span>
                    </button>
                    
                    <button class="ai-button" onclick="startOver()" style="background: #6c757d; margin-top: 1rem;">
                        <i class="fas fa-redo"></i>
                        <span>Start Over</span>
                    </button>
                </div>
                
                <!-- Success Section -->
                <div id="successSection" style="display: none;">
                    <div class="success-message">
                        <div class="success-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <h3 style="font-weight: 700; margin-bottom: 1rem;">Booking Confirmed!</h3>
                        <p style="color: #6c757d; font-size: 1.1rem; margin-bottom: 2rem;">
                            Your booking request has been sent. The provider will contact you shortly.
                        </p>
                        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                            <button class="ai-button" onclick="location.href='dashboard.php'" style="flex: 1; max-width: 300px; background: #198754;">
                                <i class="fas fa-home"></i>
                                <span>Go to Dashboard</span>
                            </button>
                            <button class="ai-button" onclick="startOver()" style="flex: 1; max-width: 300px;">
                                <i class="fas fa-plus"></i>
                                <span>New Booking</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        let bookingData = null;
        let selectedProviderId = null;

        function normalizeProviders(providers) {
            return providers.map(p => {
                // Coerce numeric fields robustly
                p.average_rating = (p.average_rating === null || p.average_rating === undefined) ? 0 : Number(p.average_rating);
                if (!isFinite(p.average_rating)) p.average_rating = 0;
                p.total_reviews  = (p.total_reviews === null || p.total_reviews === undefined) ? 0 : parseInt(p.total_reviews) || 0;
                p.hourly_rate    = (p.hourly_rate === null || p.hourly_rate === undefined) ? 0 : Number(p.hourly_rate) || 0;
                p.match_score    = (p.match_score === null || p.match_score === undefined) ? 0 : Number(p.match_score) || 0;
                // ensure strings exist
                p.profile_image = p.profile_image ?? '';
                p.full_name = p.full_name ?? 'Provider';
                p.location = p.location ?? '';
                p.district = p.district ?? '';
                p.availability = p.availability ?? 'unknown';
                p.profession = p.profession ?? '';
                return p;
            });
        }

        // Example: when receiving API JSON, call normalizeProviders before rendering:
        async function handleApiResult(result) {
            // result.providers is from API
            const providers = normalizeProviders(result.providers || []);
            // now safe to call providers[i].average_rating.toFixed(1)
            // ...render providers...
        }

        // Character counter
        document.getElementById('promptInput').addEventListener('input', function() {
            document.getElementById('charCount').textContent = this.value.length;
        });
        
        // Quick templates
        const templates = {
            plumber: "I need a plumber in Kimironko tomorrow morning to fix a leaking pipe in my bathroom",
            electrician: "Looking for an electrician in Remera today afternoon to fix electrical outlets",
            cleaner: "Need a house cleaner in Nyarutarama next Monday for deep cleaning",
            carpenter: "Urgent: Need a carpenter in Kicukiro to repair broken door today"
        };
        
        function setTemplate(type) {
            document.getElementById('promptInput').value = templates[type];
            document.getElementById('charCount').textContent = templates[type].length;
        }
        
        async function processBooking() {
            const prompt = document.getElementById('promptInput').value.trim();

            if (!prompt) {
                alert('Please describe what service you need');
                return;
            }

            const btn = document.getElementById('processBtn');
            btn.disabled = true;
            btn.innerHTML = '<div class="spinner"></div><span>Processing...</span>';

            try {
                const response = await fetch('../api/ai_booking.php?action=process', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ prompt })
                });

                // always read as text first (so we can inspect non-JSON responses)
                const text = await response.text();

                let result;
                try {
                    result = JSON.parse(text);
                } catch (err) {
                    console.error('Non-JSON response from API:', text);
                    alert('Server returned unexpected response. See console for details.');
                    return;
                }

                if (response.ok && result.success) {
                    // Normalize numeric provider fields to avoid toFixed errors
                    if (result.data && Array.isArray(result.data.providers)) {
                        result.data.providers = normalizeProviders(result.data.providers);
                    }

                    bookingData = result.data;
                    displayResults();
                } else {
                    alert('Error: ' + (result.error || result.message || 'Failed to process booking'));
                }
            } catch (error) {
                alert('Error connecting to server: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-magic"></i><span>Process with AI</span>';
            }
        }
        
        function displayResults() {
            // Normalize numeric fields if providers exist
            if (bookingData && Array.isArray(bookingData.providers)) {
                bookingData.providers = normalizeProviders(bookingData.providers);
            }

            // If services were returned, normalize service numbers
            if (bookingData && Array.isArray(bookingData.services)) {
                bookingData.services = bookingData.services.map(s => {
                    s.service_price = s.service_price ? Number(s.service_price) : null;
                    s.provider_rating = s.provider_rating ? Number(s.provider_rating) : 0;
                    s.provider_total_reviews = s.provider_total_reviews ? Number(s.provider_total_reviews) : 0;
                    return s;
                });
            }

            document.getElementById('inputSection').style.display = 'none';
            document.getElementById('resultsSection').classList.add('active');

            // Display summary
            document.getElementById('summaryText').innerHTML = bookingData.summary || '';

            // Display extracted info tags (service/location/date/time)
            const tagsHtml = `
                <div class="info-tag">
                    <i class="fas fa-briefcase"></i>
                    <strong>Service:</strong> <span>${bookingData.extracted?.service?.profession || 'Not specified'}</span>
                </div>
                <div class="info-tag">
                    <i class="fas fa-map-marker-alt"></i>
                    <strong>Location:</strong> <span>${bookingData.extracted?.location?.location || 'Not specified'}</span>
                </div>
                <div class="info-tag">
                    <i class="fas fa-calendar"></i>
                    <strong>Date:</strong> <span>${bookingData.extracted?.date?.date || 'Not specified'}</span>
                </div>
                <div class="info-tag">
                    <i class="fas fa-clock"></i>
                    <strong>Time:</strong> <span>${bookingData.extracted?.time?.time || 'Not specified'}</span>
                </div>
            `;
            document.getElementById('infoTags').innerHTML = tagsHtml;

            // Decide whether to render providers or services
            if (bookingData.intent === 'service' && Array.isArray(bookingData.services)) {
                const services = bookingData.services;
                document.getElementById('providerCount').textContent = services.length;

                if (services.length === 0) {
                    document.getElementById('providersContainer').innerHTML = `<div class="empty-state"><h4>No matching services found</h4><p>Try rephrasing your request or specifying a location.</p></div>`;
                    return;
                }

                const servicesHtml = services.map(s => `
                    <div class="provider-card" onclick="selectService(${s.service_id}, this)">
                        <div class="match-badge">${(s.provider_rating || 0).toFixed(1)} ★</div>
                        <div class="provider-header">
                            <div class="provider-avatar">
                                ${s.provider_profile_image ? `<img src="../${s.provider_profile_image}" />` : (s.provider_name ? s.provider_name.charAt(0).toUpperCase() : 'S')}
                            </div>
                            <div class="provider-info">
                                <div class="provider-name">${s.service_name}</div>
                                <div class="provider-profession">${s.category_name || ''} • Offered by ${s.provider_name || 'Provider'}</div>
                                <div class="rating">
                                    <span class="stars">${getStars(s.provider_rating)}</span>
                                    <span>(${s.provider_total_reviews || 0} reviews)</span>
                                </div>
                                ${s.service_price ? `<div style="color: #0d6efd; font-weight:600; margin-top:0.25rem;">RWF ${Number(s.service_price).toLocaleString()}</div>` : ''}
                            </div>
                        </div>
                        <div class="provider-details">
                            <div class="detail-item"><i class="fas fa-info-circle"></i><span>${(s.service_description || '').substring(0, 120)}</span></div>
                            <div class="detail-item"><i class="fas fa-map-marker-alt"></i><span>${s.provider_location || s.provider_district || 'Location N/A'}</span></div>
                            <div class="detail-item"><i class="fas fa-clock"></i><span>${s.service_duration ? s.service_duration + ' mins' : 'Duration N/A'}</span></div>
                            <div class="detail-item"><i class="fas fa-check-circle"></i><span>${s.is_available ? 'Available' : 'Unavailable'}</span></div>
                        </div>
                    </div>
                `).join('');

                document.getElementById('providersContainer').innerHTML = servicesHtml;
                // show confirm button only after selecting a service/provider
                document.getElementById('confirmBtn').style.display = 'none';
                return;
            }

            // Default: providers
            const providers = bookingData.providers || [];
            document.getElementById('providerCount').textContent = providers.length;

            if (providers.length === 0) {
                document.getElementById('providersContainer').innerHTML = `<div class="empty-state"><h4>No matching providers found</h4><p>Try rephrasing your request or expanding the search area.</p></div>`;
                return;
            }

            const providersHtml = providers.map(p => `
                <div class="provider-card" onclick="selectProvider(${p.id}, this)">
                    <div class="match-badge">${(p.combined_score ?? p.score ?? 0).toFixed(1)}/10</div>
                    <div class="provider-header">
                        <div class="provider-avatar">
                            ${p.profile_image ? `<img src="../${p.profile_image}" />` : (p.full_name ? p.full_name.charAt(0).toUpperCase() : 'P')}
                        </div>
                        <div class="provider-info">
                            <div class="provider-name">${p.full_name}</div>
                            <div class="provider-profession">${p.profession}</div>
                            <div class="rating">
                                <span class="stars">${getStars(p.average_rating)}</span>
                                <span>(${p.total_reviews || 0} reviews)</span>
                            </div>
                        </div>
                    </div>
                    <div class="provider-details">
                        <div class="detail-item"><i class="fas fa-briefcase"></i><span>${p.experience_years || 0} yrs</span></div>
                        <div class="detail-item"><i class="fas fa-dollar-sign"></i><span>${p.hourly_rate ? 'RWF ' + p.hourly_rate.toLocaleString() + '/hr' : 'N/A'}</span></div>
                        <div class="detail-item"><i class="fas fa-map-marker-alt"></i><span>${p.location || p.district || 'Location N/A'}</span></div>
                        <div class="detail-item"><i class="fas fa-clock"></i><span>${(p.availability || 'N/A')}</span></div>
                    </div>
                </div>
            `).join('');

            document.getElementById('providersContainer').innerHTML = providersHtml;
            document.getElementById('confirmBtn').style.display = 'flex';
        }
        
        function selectProvider(id, element) {
            document.querySelectorAll('.provider-card').forEach(card => {
                card.classList.remove('selected');
            });
            element.classList.add('selected');
            selectedProviderId = id;
            document.getElementById('confirmBtn').style.display = 'flex';
        }
        
        function selectService(serviceId, element) {
            // visually mark selected service
            document.querySelectorAll('.provider-card').forEach(card => card.classList.remove('selected'));
            element.classList.add('selected');
            // store selected "provider service" in selectedProviderId so confirmBooking can use it
            selectedProviderId = 'service:' + serviceId;
            document.getElementById('confirmBtn').style.display = 'flex';
        }
        
        async function confirmBooking() {
            if (!selectedProviderId) {
                alert('Please select a provider or a service');
                return;
            }

            const btn = document.getElementById('confirmBtn');
            btn.disabled = true;
            btn.innerHTML = '<div class="spinner"></div><span>Creating Booking...</span>';

            try {
                // If a service is selected, extract the service id
                let providerIdToSend = null;
                let extractedDataToSend = bookingData.extracted;
                if (typeof selectedProviderId === 'string' && selectedProviderId.startsWith('service:')) {
                    const serviceId = parseInt(selectedProviderId.split(':')[1], 10);
                    // When creating booking for a service, we might want to use provider id of that service
                    // Fetch service to get provider id client-side from bookingData.services
                    const svc = (bookingData.services || []).find(s => s.service_id === serviceId);
                    if (!svc) throw new Error('Selected service not found.');
                    providerIdToSend = svc.provider_id;
                    // include service id in payload for server side use
                    extractedDataToSend.service_id = serviceId;
                } else {
                    providerIdToSend = selectedProviderId;
                }

                const response = await fetch('../api/ai_booking.php?action=create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        provider_id: providerIdToSend,
                        extracted_data: extractedDataToSend
                    })
                });

                const result = await response.json();

                if (result.success) {
                    document.getElementById('resultsSection').style.display = 'none';
                    document.getElementById('successSection').style.display = 'block';
                } else {
                    alert('Error: ' + (result.message || 'Failed to create booking'));
                }
            } catch (error) {
                alert('Error: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check"></i><span>Confirm Booking</span>';
            }
        }
    </script>
</body>
</html>