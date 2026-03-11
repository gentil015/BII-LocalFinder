<?php
/**
 * Script to add translation calls to provider/profile.php
 * This will systematically replace hardcoded text with __() calls
 */

$file_path = 'provider/profile.php';
$content = file_get_contents($file_path);

// Track replacements
$replacements = [];

// Title tag
$replacements[] = [
    'old' => '<title>My Profile - <?php echo getPlatformName(); ?></title>',
    'new' => '<title><?php echo __("title", [], "profile"); ?> - <?php echo getPlatformName(); ?></title>',
    'desc' => 'Page title'
];

// Edit Profile header
$replacements[] = [
    'old' => '<h1><i class="fas fa-user-edit"></i> Edit Profile</h1>',
    'new' => '<h1><i class="fas fa-user-edit"></i> <?php echo __("title", [], "profile"); ?></h1>',
    'desc' => 'Edit Profile heading'
];

// Update your professional info subtitle
$replacements[] = [
    'old' => '<p>Update your professional information and services</p>',
    'new' => '<p><?php echo __("subtitle", [], "profile"); ?></p>',
    'desc' => 'Profile subtitle'
];

// Tab navigation items
$replacements[] = [
    'old' => '<i class="fas fa-user"></i>
                Basic Information',
    'new' => '<i class="fas fa-user"></i>
                <?php echo __("tabs.basic", [], "profile"); ?>',
    'desc' => 'Basic tab'
];

$replacements[] = [
    'old' => '<i class="fas fa-briefcase"></i>
                Services & Categories',
    'new' => '<i class="fas fa-briefcase"></i>
                <?php echo __("tabs.services", [], "profile"); ?>',
    'desc' => 'Services tab'
];

$replacements[] = [
    'old' => '<i class="fas fa-images"></i>
                Portfolio',
    'new' => '<i class="fas fa-images"></i>
                <?php echo __("tabs.portfolio", [], "profile"); ?>',
    'desc' => 'Portfolio tab'
];

$replacements[] = [
    'old' => '<i class="fas fa-share-alt"></i>
                Social Media',
    'new' => '<i class="fas fa-share-alt"></i>
                <?php echo __("tabs.social", [], "profile"); ?>',
    'desc' => 'Social media tab'
];

$replacements[] = [
    'old' => '<i class="fas fa-tasks"></i>
                Profile Completion',
    'new' => '<i class="fas fa-tasks"></i>
                <?php echo __("tabs.requirements", [], "profile"); ?>',
    'desc' => 'Requirements tab'
];

// Profile picture section
$replacements[] = [
    'old' => '<h2><i class="fas fa-image"></i> Profile Picture</h2>',
    'new' => '<h2><i class="fas fa-image"></i> <?php echo __("profile_picture.title", [], "profile"); ?></h2>',
    'desc' => 'Profile Picture heading'
];

// Basic Information section
$replacements[] = [
    'old' => '<h2><i class="fas fa-info-circle"></i> Basic Information</h2>',
    'new' => '<h2><i class="fas fa-info-circle"></i> <?php echo __("basic_info.title", [], "profile"); ?></h2>',
    'desc' => 'Basic Information heading'
];

// Form labels for Basic Info
$replacements[] = [
    'old' => '                        <label class="form-label"><span class="required">*</span> Full Name</label>',
    'new' => '                        <label class="form-label"><span class="required">*</span> <?php echo __("basic_info.full_name", [], "profile"); ?></label>',
    'desc' => 'Full Name label'
];

$replacements[] = [
    'old' => '                        <label class="form-label">Email Address</label>',
    'new' => '                        <label class="form-label"><?php echo __("basic_info.email", [], "profile"); ?></label>',
    'desc' => 'Email Address label'
];

$replacements[] = [
    'old' => '                            <div class="form-text">Email cannot be changed. Contact support if needed.</div>',
    'new' => '                            <div class="form-text"><?php echo __("alerts.email_cannot_change", [], "profile"); ?></div>',
    'desc' => 'Email cannot change message'
];

$replacements[] = [
    'old' => '                        <label class="form-label"><span class="required">*</span> Phone Number</label>',
    'new' => '                        <label class="form-label"><span class="required">*</span> <?php echo __("basic_info.phone", [], "profile"); ?></label>',
    'desc' => 'Phone Number label'
];

$replacements[] = [
    'old' => '                            <input type="tel" name="phone" class="form-control" required value="<?php echo htmlspecialchars($provider[\'phone\']); ?>" placeholder="e.g., +250 788 123 456">',
    'new' => '                            <input type="tel" name="phone" class="form-control" required value="<?php echo htmlspecialchars($provider[\'phone\']); ?>" placeholder="<?php echo __("basic_info.phone_placeholder", [], "profile"); ?>">',
    'desc' => 'Phone placeholder'
];

$replacements[] = [
    'old' => '                            <div class="form-text">Include country code for international clients</div>',
    'new' => '                            <div class="form-text"><?php echo __("basic_info.phone_help", [], "profile"); ?></div>',
    'desc' => 'Phone help text'
];

$replacements[] = [
    'old' => '                        <label class="form-label"><span class="required">*</span> Professional Title</label>',
    'new' => '                        <label class="form-label"><span class="required">*</span> <?php echo __("basic_info.profession", [], "profile"); ?></label>',
    'desc' => 'Professional Title label'
];

$replacements[] = [
    'old' => '                            <input type="text" name="profession" class="form-control" required value="<?php echo htmlspecialchars($provider[\'profession\']); ?>" placeholder="e.g., Electrician, Plumber, Carpenter">',
    'new' => '                            <input type="text" name="profession" class="form-control" required value="<?php echo htmlspecialchars($provider[\'profession\']); ?>" placeholder="<?php echo __("basic_info.profession_placeholder", [], "profile"); ?>">',
    'desc' => 'Profession placeholder'
];

$replacements[] = [
    'old' => '                        <label class="form-label">Years of Experience</label>',
    'new' => '                        <label class="form-label"><?php echo __("basic_info.experience_years", [], "profile"); ?></label>',
    'desc' => 'Years of Experience label'
];

$replacements[] = [
    'old' => '                            <div class="form-text">How many years of professional experience do you have?</div>',
    'new' => '                            <div class="form-text"><?php echo __("basic_info.experience_help", [], "profile"); ?></div>',
    'desc' => 'Experience help text'
];

$replacements[] = [
    'old' => '                        <label class="form-label">Professional Bio</label>',
    'new' => '                        <label class="form-label"><?php echo __("basic_info.bio", [], "profile"); ?></label>',
    'desc' => 'Professional Bio label'
];

$replacements[] = [
    'old' => '                        <textarea name="bio" class="form-control" rows="5" placeholder="Tell clients about your experience, skills, certifications, and what makes you unique...">',
    'new' => '                        <textarea name="bio" class="form-control" rows="5" placeholder="<?php echo __("basic_info.bio_placeholder", [], "profile"); ?>">',
    'desc' => 'Bio placeholder'
];

$replacements[] = [
    'old' => '                        <div class="form-text">This description helps clients understand your expertise and approach</div>',
    'new' => '                        <div class="form-text"><?php echo __("basic_info.bio_help", [], "profile"); ?></div>',
    'desc' => 'Bio help text'
];

$replacements[] = [
    'old' => '                        <button type="button" class="btn btn-success" onclick="saveSection(\'basic_info\', this)">
                            <i class="fas fa-save"></i> Save Changes
                        </button>',
    'new' => '                        <button type="button" class="btn btn-success" onclick="saveSection(\'basic_info\', this)">
                            <i class="fas fa-save"></i> <?php echo __("actions.save", [], "profile"); ?>
                        </button>',
    'desc' => 'Save Changes button'
];

// Location Information section
$replacements[] = [
    'old' => '                <h2><i class="fas fa-map-marker-alt"></i> Location Information</h2>',
    'new' => '                <h2><i class="fas fa-map-marker-alt"></i> <?php echo __("location_info.title", [], "profile"); ?></h2>',
    'desc' => 'Location Information heading'
];

$replacements[] = [
    'old' => '                        <label class="form-label"><span class="required">*</span> City / Location</label>',
    'new' => '                        <label class="form-label"><span class="required">*</span> <?php echo __("location_info.location", [], "profile"); ?></label>',
    'desc' => 'Location label'
];

$replacements[] = [
    'old' => '                            <input type="text" name="location" class="form-control" required value="<?php echo htmlspecialchars($provider[\'location\'] ?? \'\'); ?>" placeholder="e.g., Kigali, Rubavu, Musanze">',
    'new' => '                            <input type="text" name="location" class="form-control" required value="<?php echo htmlspecialchars($provider[\'location\'] ?? \'\'); ?>" placeholder="<?php echo __("location_info.location_placeholder", [], "profile"); ?>">',
    'desc' => 'Location placeholder'
];

$replacements[] = [
    'old' => '                        <label class="form-label"><span class="required">*</span> District</label>',
    'new' => '                        <label class="form-label"><span class="required">*</span> <?php echo __("location_info.district", [], "profile"); ?></label>',
    'desc' => 'District label'
];

$replacements[] = [
    'old' => '                            <option value="">Select District</option>',
    'new' => '                            <option value=""><?php echo __("location_info.district_placeholder", [], "profile"); ?></option>',
    'desc' => 'District select placeholder'
];

$replacements[] = [
    'old' => '                        <div class="form-text">Select your primary service area</div>',
    'new' => '                        <div class="form-text"><?php echo __("location_info.district_help", [], "profile"); ?></div>',
    'desc' => 'District help text'
];

$replacements[] = [
    'old' => '                        <label class="form-label">Sector / Neighborhood</label>',
    'new' => '                        <label class="form-label"><?php echo __("location_info.sector", [], "profile"); ?></label>',
    'desc' => 'Sector label'
];

$replacements[] = [
    'old' => '                            <input type="text" name="sector" class="form-control" value="<?php echo htmlspecialchars($provider[\'sector\'] ?? \'\'); ?>" placeholder="e.g., Kimironko, Remera, Gisenyi">',
    'new' => '                            <input type="text" name="sector" class="form-control" value="<?php echo htmlspecialchars($provider[\'sector\'] ?? \'\'); ?>" placeholder="<?php echo __("location_info.sector_placeholder", [], "profile"); ?>">',
    'desc' => 'Sector placeholder'
];

$replacements[] = [
    'old' => '                        <div class="form-text">Specific sector or neighborhood within your district</div>',
    'new' => '                        <div class="form-text"><?php echo __("location_info.sector_help", [], "profile"); ?></div>',
    'desc' => 'Sector help text'
];

$replacements[] = [
    'old' => '                    <button type="button" class="btn btn-success" onclick="saveSection(\'location_info\', this)">
                        <i class="fas fa-save"></i> Save Changes
                    </button>',
    'new' => '                    <button type="button" class="btn btn-success" onclick="saveSection(\'location_info\', this)">
                        <i class="fas fa-save"></i> <?php echo __("actions.save", [], "profile"); ?>
                    </button>',
    'desc' => 'Location save button'
];

// Apply all replacements
$applied = 0;
foreach ($replacements as $replacement) {
    if (strpos($content, $replacement['old']) !== false) {
        $content = str_replace($replacement['old'], $replacement['new'], $content);
        $applied++;
        echo "[✓] {$replacement['desc']}\n";
    } else {
        echo "[✗] {$replacement['desc']} - NOT FOUND\n";
    }
}

// Write updated content back
file_put_contents($file_path, $content);

echo "\n=== Translation Progress ===\n";
echo "Applied: $applied replacements\n";
echo "File updated: $file_path\n";
?>
