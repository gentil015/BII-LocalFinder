        // Mobile sidebar toggle
        const mobileToggle = document.getElementById('mobileToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        
        mobileToggle.addEventListener('click', () => {
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');
        });
        
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
        });
        
        // Settings navigation - removed (using URL-based navigation instead)
        
        // Initialize map for location section
        let map;
        let markers = [];
        
        function initializeMap() {
            if (document.getElementById('serviceMap')) {
                map = L.map('serviceMap').setView([-1.9441, 30.0619], 12); // Kigali center
                
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);
                
                // Keep map in sync with form
                map.on('click', handleMapClick);

                const chosenRadio = document.querySelector('input[name="primary_area"]:checked');
                selectedServiceAreaIndex = chosenRadio ? Number(chosenRadio.value) : 0;
                setSelectedServiceArea(selectedServiceAreaIndex);
                renderServiceAreasOnMap();
            }
        }
        
        function addServiceAreaToMap(lat, lng, radius, name, isPrimary) {
            // Add circle for service area
            const circle = L.circle([lat, lng], {
                color: isPrimary ? 'blue' : 'green',
                fillColor: isPrimary ? '#3388ff' : '#33cc33',
                fillOpacity: 0.2,
                radius: radius * 1000 // Convert km to meters
            }).addTo(map);
            
            // Add marker
            const marker = L.marker([lat, lng]).addTo(map)
                .bindPopup(`<strong>${name}</strong><br>Radius: ${radius}km<br>${isPrimary ? 'Primary Area' : 'Secondary Area'}`);
            
            markers.push({ circle, marker });
        }
        
        // Service area management
        let serviceAreaCount = <?php echo count($serviceAreas); ?>;
        
        function addServiceArea() {
            const container = document.getElementById('serviceAreasContainer');
            const newIndex = serviceAreaCount;
            
            const newArea = document.createElement('div');
            newArea.className = 'service-area-form';
            newArea.setAttribute('data-index', newIndex);
            newArea.innerHTML = `
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Area Name</label>
                            <input type="text" class="form-control" name="service_areas[${newIndex}][name]" 
                                   placeholder="e.g., Kigali City Center" required>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Latitude</label>
                            <input type="text" class="form-control" name="service_areas[${newIndex}][lat]" 
                                   placeholder="e.g., -1.9441" required>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Longitude</label>
                            <input type="text" class="form-control" name="service_areas[${newIndex}][lng]" 
                                   placeholder="e.g., 30.0619" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Service Radius (km)</label>
                            <input type="number" class="form-control" name="service_areas[${newIndex}][radius]" 
                                   value="10" min="1" max="100" step="1" required>
                            <p class="form-text">Distance you're willing to travel from this point</p>
                        </div>
                    </div>
                    
                    <div class="col-md-6 d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-info me-2" onclick="setSelectedServiceArea(${newIndex});">
                            <i class="fas fa-map-pin"></i> Pick on Map
                        </button>

                        <div class="form-check me-3">
                            <input class="form-check-input" type="radio" name="primary_area" value="${newIndex}">
                            <label class="form-check-label">Set as Primary</label>
                        </div>
                        
                        <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="removeServiceArea(this)">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </div>
                </div>
            `;
            
            container.appendChild(newArea);
            serviceAreaCount++;
            setSelectedServiceArea(newIndex);
            renderServiceAreasOnMap();
        }
        
        function removeServiceArea(button) {
            const areaForm = button.closest('.service-area-form');
            const removedIndex = areaForm.dataset.index;
            areaForm.remove();
            if (selectedServiceAreaIndex === Number(removedIndex)) {
                selectedServiceAreaIndex = 0;
                setSelectedServiceArea(0);
            }
            renderServiceAreasOnMap();
        }

        let selectedServiceAreaIndex = null;

        function setSelectedServiceArea(index) {
            selectedServiceAreaIndex = Number(index);
            document.querySelectorAll('.service-area-form').forEach(form => {
                form.classList.toggle('active', Number(form.dataset.index) === selectedServiceAreaIndex);
            });

            const targetRadio = document.querySelector(`input[name='primary_area'][value='${selectedServiceAreaIndex}']`);
            if (targetRadio) {
                targetRadio.checked = true;
            }

            document.querySelector('.alert-secondary').innerHTML =
                `<i class="fas fa-info-circle me-2"></i>Click on the map to set latitude and longitude for the selected service area (index ${selectedServiceAreaIndex + 1}).`;
        }

        function getServiceAreaDataFromForm(form) {
            const index = form.dataset.index;
            const name = form.querySelector(`[name='service_areas[${index}][name]']`)?.value || `Area ${Number(index) + 1}`;
            const lat = parseFloat(form.querySelector(`[name='service_areas[${index}][lat]']`)?.value);
            const lng = parseFloat(form.querySelector(`[name='service_areas[${index}][lng]']`)?.value);
            const radius = parseFloat(form.querySelector(`[name='service_areas[${index}][radius]']`)?.value) || 10;
            const isPrimary = form.querySelector(`input[name='primary_area']:checked`)?.value === index;
            return { name, lat, lng, radius, isPrimary };
        }

        function renderServiceAreasOnMap() {
            if (!map) return;
            markers.forEach(m => {
                map.removeLayer(m.circle);
                map.removeLayer(m.marker);
            });
            markers = [];

            const areaForms = Array.from(document.querySelectorAll('.service-area-form'));
            const bounds = L.featureGroup();

            areaForms.forEach(form => {
                const area = getServiceAreaDataFromForm(form);
                if (!isNaN(area.lat) && !isNaN(area.lng)) {
                    addServiceAreaToMap(area.lat, area.lng, area.radius, area.name, area.isPrimary);
                    bounds.addLayer(L.circle([area.lat, area.lng], { radius: area.radius * 1000 }));
                }
            });

            if (bounds.getLayers().length > 0) {
                map.fitBounds(bounds.getBounds().pad(0.25));
            }
        }

        function handleMapClick(e) {
            if (selectedServiceAreaIndex === null) {
                alert('Please select a service area first by clicking "Pick on Map"');
                return;
            }

            const form = document.querySelector(`.service-area-form[data-index='${selectedServiceAreaIndex}']`);
            if (!form) {
                alert('Selected service area form not found.');
                return;
            }

            const latInput = form.querySelector(`input[name='service_areas[${selectedServiceAreaIndex}][lat]']`);
            const lngInput = form.querySelector(`input[name='service_areas[${selectedServiceAreaIndex}][lng]']`);
            const nameInput = form.querySelector(`input[name='service_areas[${selectedServiceAreaIndex}][name]']`);

            if (latInput && lngInput) {
                const lat = e.latlng.lat.toFixed(6);
                const lng = e.latlng.lng.toFixed(6);

                latInput.value = lat;
                lngInput.value = lng;

                // Reverse geocode area label if area name is blank or default
                if (nameInput && (!nameInput.value.trim() || nameInput.value.startsWith('Area '))) {
                    const nominatimUrl = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lng)}&zoom=14&addressdetails=1`;

                    fetch(nominatimUrl, { method: 'GET', headers: { 'Accept': 'application/json' } })
                        .then(response => response.json())
                        .then(data => {
                            if (data && data.address) {
                                const address = data.address;
                                const placeParts = [
                                    address.neighbourhood,
                                    address.suburb,
                                    address.city_district,
                                    address.city,
                                    address.town,
                                    address.village,
                                    address.state,
                                    address.country
                                ].filter(Boolean);

                                if (placeParts.length) {
                                    nameInput.value = placeParts[0];
                                }
                            }
                        })
                        .catch(() => {
                            // fallback: keep current name without changing
                        });
                }

                renderServiceAreasOnMap();
            }
        }

        // Payment method management
        let paymentMethodCount = <?php echo count($paymentMethods); ?>;
        
        function addPaymentMethod() {
            const container = document.getElementById('paymentMethodsContainer');
            const newIndex = paymentMethodCount;
            
            const newMethod = document.createElement('div');
            newMethod.className = 'payment-method-card';
            newMethod.setAttribute('data-index', newIndex);
            newMethod.innerHTML = `
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Method Type</label>
                            <select name="payment_methods[${newIndex}][type]" class="form-select">
                                <option value="mobile_money">Mobile Money</option>
                                <option value="bank_account">Bank Account</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Account Name</label>
                            <input type="text" class="form-control" name="payment_methods[${newIndex}][account_name]" 
                                   value="<?php echo htmlspecialchars($provider['full_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Account Number</label>
                            <input type="text" class="form-control" name="payment_methods[${newIndex}][account_number]" 
                                   placeholder="07XXXXXXXX or Account Number" required>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-2">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Bank Name (if applicable)</label>
                            <input type="text" class="form-control" name="payment_methods[${newIndex}][bank_name]" 
                                   placeholder="Bank of Kigali, Equity Bank, etc.">
                        </div>
                    </div>
                    
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="default_payment_method" value="${newIndex}">
                            <label class="form-check-label">Set as Default</label>
                        </div>
                        
                        <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="removePaymentMethod(this)">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </div>
                </div>
            `;
            
            container.appendChild(newMethod);
            paymentMethodCount++;
        }

        // Real-time AI Toggle Saving
        document.querySelectorAll('.ai-toggle').forEach(toggle => {
            toggle.addEventListener('change', async function() {
                const section = this.getAttribute('data-section');
                const key = this.getAttribute('data-key');
                const value = this.checked ? 1 : 0;
                
                // Show loading state
                const label = this.closest('.toggle-switch');
                label.style.opacity = '0.6';
                label.style.pointerEvents = 'none';
                
                try {
                    const response = await fetch('../api/save_provider_setting.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            section: section,
                            key: key,
                            value: value
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // Show success feedback
                        const toggleSwitch = this.closest('.toggle-switch');
                        const card = toggleSwitch.closest('.setting-card');
                        const originalBg = card.style.backgroundColor;
                        
                        card.style.backgroundColor = '#d4edda';
                        card.style.transition = 'background-color 0.3s ease';
                        
                        setTimeout(() => {
                            card.style.backgroundColor = originalBg;
                        }, 2000);
                        
                        console.log('Setting saved:', data.message);
                    } else {
                        // Revert toggle on error
                        this.checked = !this.checked;
                        alert('Error saving setting: ' + data.message);
                    }
                } catch (error) {
                    // Revert toggle on error
                    this.checked = !this.checked;
                    console.error('Error:', error);
                    alert('Failed to save setting. Please try again.');
                } finally {
                    // Remove loading state
                    label.style.opacity = '1';
                    label.style.pointerEvents = 'auto';
                }
            });
        });
                        </button>
                    </div>
                </div>
            `;
            
            container.appendChild(newMethod);
            paymentMethodCount++;
        }
        
        function removePaymentMethod(button) {
            const methodCard = button.closest('.payment-method-card');
            methodCard.remove();
        }
        
        // File preview for identity verification
        function previewFile(input, type) {
            const file = input.files[0];
            if (!file) return;
            
            const previewDiv = document.getElementById(`${type}_preview`);
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewDiv.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            ${file.name} (${(file.size / 1024).toFixed(2)} KB)
                            <img src="${e.target.result}" class="img-thumbnail mt-2" style="max-height:80px;">
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            } else {
                previewDiv.innerHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        ${file.name} (${(file.size / 1024).toFixed(2)} KB)
                    </div>
                `;
            }
        }
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize map when location section is active
            initializeMap();

            const serviceAreasContainer = document.getElementById('serviceAreasContainer');
            if (serviceAreasContainer) {
                serviceAreasContainer.addEventListener('input', function(e) {
                    if (e.target.name && (e.target.name.startsWith('service_areas') || e.target.name === 'primary_area')) {
                        renderServiceAreasOnMap();
                    }
                });

                serviceAreasContainer.addEventListener('click', function(e) {
                    if (e.target.matches('input[name="primary_area"]')) {
                        setSelectedServiceArea(Number(e.target.value));
                        renderServiceAreasOnMap();
                    }
                });
            }
            
            // Set up form submissions
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    // Show loading state
                    const submitBtn = this.querySelector('.btn-save');
                    if (submitBtn) {
                        const originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';
                        submitBtn.disabled = true;
                        
                        // Re-enable button after 3 seconds (in case of error)
                        setTimeout(() => {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }, 3000);
                    }
                });
            });
            
            // Auto-dismiss alerts after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
        
        // Export data function
        function exportData() {
            // Create a form and submit it
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const sectionInput = document.createElement('input');
            sectionInput.type = 'hidden';
            sectionInput.name = 'section';
            sectionInput.value = 'account';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'account_action';
            actionInput.value = 'export_data';
            
            form.appendChild(sectionInput);
            form.appendChild(actionInput);
            document.body.appendChild(form);
            form.submit();
        }
        
        // Withdrawal processing removed (feature unsupported)
        
        // Logout device
        function logoutDevice(device) {
            if (confirm(`Log out from ${device}?`)) {
                fetch('logout_device.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        device: device
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Device logged out successfully');
                        location.reload();
                    } else {
                        alert('Failed to log out device');
                    }
                });
            }
        }
        
        // Handle hash changes for direct section access
        window.addEventListener('hashchange', function() {
            const sectionId = window.location.hash.substring(1);
            if (sectionId && document.getElementById(sectionId)) {
                showSection(sectionId);
            }
        });
        
        // Initialize based on current hash
        if (window.location.hash) {
            const sectionId = window.location.hash.substring(1);
            if (sectionId && document.getElementById(sectionId)) {
                // Remove active class from identity nav link
                document.querySelector('.settings-nav a[href="#identity"]').classList.remove('active');
                document.getElementById('identity').classList.remove('active');
                
                // Show the section from hash
                showSection(sectionId);
            }
        }
        
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Dark Mode Functionality
        class DarkModeManager {
            constructor() {
                this.toggle = document.getElementById('darkModeToggle');
                this.init();
            }

            init() {
                // Get current theme
                const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
                
                // Set toggle state
                this.toggle.checked = currentTheme === 'dark';

                // Update toggle appearance
                this.updateToggleAppearance(currentTheme);

                // Add event listener
                this.toggle.addEventListener('change', (e) => {
                    const theme = e.target.checked ? 'dark' : 'light';
                    this.setTheme(theme);
                    this.saveTheme(theme);
                });
            }

            setTheme(theme) {
                document.documentElement.setAttribute('data-theme', theme);
                localStorage.setItem('provider_theme', theme);
                this.updateToggleAppearance(theme);
            }

            updateToggleAppearance(theme) {
                const icon = this.toggle.closest('.dark-mode-toggle').querySelector('.nav-icon i');
                const label = this.toggle.closest('.dark-mode-toggle').querySelector('.fw-600');
                const sublabel = this.toggle.closest('.dark-mode-toggle').querySelector('.text-xs');

                icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
                label.textContent = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
                sublabel.textContent = theme === 'dark' ? 'Switch to light theme' : 'Modern dark theme';
            }

            saveTheme(theme) {
                // Save to database via AJAX
                fetch('../api/save_theme_preference.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        theme: theme,
                        provider_id: <?php echo $provider['id']; ?>
                    })
                }).catch(err => {
                    console.warn('Failed to save theme preference:', err);
                });
            }
        }

        // Initialize dark mode when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            new DarkModeManager();
        });
    </script>
