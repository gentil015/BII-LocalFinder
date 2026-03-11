<?php
/**
 * Standardized Profession Categories and Professional Titles
 * Keeps the platform clean and ensures consistent data
 */

/**
 * Get all available profession categories
 */
function getProfessionCategories() {
    return [
        'Electrician' => 'Electrician',
        'Plumber' => 'Plumber',
        'Mechanic' => 'Mechanic',
        'Carpenter' => 'Carpenter',
        'Painter' => 'Painter',
        'Cleaner' => 'Cleaner',
        'Hair Stylist' => 'Hair Stylist',
        'Tutor' => 'Tutor',
        'Photographer' => 'Photographer',
        'Web Developer' => 'Web Developer',
        'Graphic Designer' => 'Graphic Designer',
        'Consultant' => 'Consultant',
        'HVAC Technician' => 'HVAC Technician',
        'Landscaper' => 'Landscaper',
        'Locksmith' => 'Locksmith',
        'Mason' => 'Mason',
        'Welder' => 'Welder',
        'Architect' => 'Architect',
        'Accountant' => 'Accountant',
        'Event Planner' => 'Event Planner',
    ];
}

/**
 * Get professional titles for a specific category
 * @param string $profession
 * @return array
 */
function getProfessionalTitles($profession) {
    $titles_map = [
        'Electrician' => [
            'Residential Electrical Technician',
            'Commercial Electrical Technician',
            'Industrial Electrician',
            'Licensed Electrician',
            'Master Electrician',
            'Electrical System Installer',
            'Electrical Maintenance Technician',
        ],
        'Plumber' => [
            'Residential Plumber',
            'Commercial Plumber',
            'Master Plumber',
            'Licensed Plumber',
            'Plumbing System Designer',
            'Drainage Specialist',
            'Water Systems Technician',
        ],
        'Mechanic' => [
            'Automotive Mechanic',
            'ASE Certified Mechanic',
            'Engine Specialist',
            'Transmission Specialist',
            'General Auto Repair Technician',
            'Heavy Equipment Mechanic',
            'Small Engine Mechanic',
        ],
        'Carpenter' => [
            'Residential Carpenter',
            'Commercial Carpenter',
            'Master Carpenter',
            'Finish Carpenter',
            'Framing Carpenter',
            'Cabinet Maker',
            'Woodworking Specialist',
        ],
        'Painter' => [
            'Interior Painter',
            'Exterior Painter',
            'Commercial Painter',
            'Residential Painter',
            'Specialty Finishes Painter',
            'Industrial Painter',
            'Master Painter',
        ],
        'Cleaner' => [
            'Residential Cleaner',
            'Commercial Cleaner',
            'Office Cleaning Specialist',
            'Deep Clean Specialist',
            'Window Cleaning Specialist',
            'Carpet Cleaning Technician',
            'Post-Construction Cleaner',
        ],
        'Hair Stylist' => [
            'Hair Stylist',
            'Master Hair Stylist',
            'Color Specialist',
            'Styling Expert',
            'Hair Treatment Specialist',
            'Salon Professional',
            'Certified Cosmetologist',
        ],
        'Tutor' => [
            'Math Tutor',
            'Science Tutor',
            'Language Tutor',
            'Academic Tutor',
            'Test Prep Specialist',
            'Private Tutor',
            'Professional Educator',
        ],
        'Photographer' => [
            'Portrait Photographer',
            'Event Photographer',
            'Commercial Photographer',
            'Wedding Photographer',
            'Product Photography Specialist',
            'Photo Editor',
            'Professional Photographer',
        ],
        'Web Developer' => [
            'Full Stack Web Developer',
            'Frontend Developer',
            'Backend Developer',
            'WordPress Developer',
            'Web Design Developer',
            'E-commerce Developer',
            'Web Application Developer',
        ],
        'Graphic Designer' => [
            'UI/UX Designer',
            'Brand Designer',
            'Logo Designer',
            'Print Designer',
            'Digital Designer',
            'Web Designer',
            'Creative Designer',
        ],
        'Consultant' => [
            'Business Consultant',
            'Marketing Consultant',
            'IT Consultant',
            'Financial Consultant',
            'Management Consultant',
            'Strategy Consultant',
            'Professional Consultant',
        ],
        'HVAC Technician' => [
            'HVAC Installation Technician',
            'HVAC Repair Technician',
            'Cooling System Specialist',
            'Heating System Specialist',
            'EPA Certified HVAC Technician',
            'Commercial HVAC Technician',
            'HVAC Maintenance Specialist',
        ],
        'Landscaper' => [
            'Landscape Designer',
            'Landscape Maintenance Specialist',
            'Hardscape Specialist',
            'Garden Designer',
            'Lawn Care Specialist',
            'Landscape Contractor',
            'Professional Landscaper',
        ],
        'Locksmith' => [
            'Residential Locksmith',
            'Commercial Locksmith',
            'Automotive Locksmith',
            'Master Locksmith',
            'Licensed Locksmith',
            'Emergency Locksmith',
            'Security Systems Specialist',
        ],
        'Mason' => [
            'Brick Mason',
            'Stone Mason',
            'Concrete Specialist',
            'Master Mason',
            'Tile Mason',
            'Masonry Contractor',
            'Structural Mason',
        ],
        'Welder' => [
            'Certified Welder',
            'MIG Welder',
            'TIG Welder',
            'Stick Welder',
            'Structural Welder',
            'Industrial Welder',
            'Master Welder',
        ],
        'Architect' => [
            'Residential Architect',
            'Commercial Architect',
            'Licensed Architect',
            'Design Architect',
            'Architectural Designer',
            'CAD Specialist',
            'Senior Architect',
        ],
        'Accountant' => [
            'CPA',
            'Tax Accountant',
            'Financial Accountant',
            'Bookkeeper',
            'Management Accountant',
            'Audit Specialist',
            'Certified Accountant',
        ],
        'Event Planner' => [
            'Wedding Planner',
            'Corporate Event Planner',
            'Party Planner',
            'Event Coordinator',
            'Venue Coordinator',
            'Catering Coordinator',
            'Professional Event Organizer',
        ],
    ];

    return $titles_map[$profession] ?? [];
}

/**
 * Get all profession-title combinations formatted for dropdown
 * @return array
 */
function getAllProfessionTitles() {
    $all_titles = [];
    foreach (getProfessionCategories() as $profession) {
        $titles = getProfessionalTitles($profession);
        foreach ($titles as $title) {
            $all_titles[] = [
                'profession' => $profession,
                'title' => $title,
            ];
        }
    }
    return $all_titles;
}

/**
 * Validate profession exists
 * @param string $profession
 * @return boolean
 */
function isValidProfession($profession) {
    return array_key_exists($profession, getProfessionCategories());
}

/**
 * Validate professional title for a profession
 * @param string $profession
 * @param string $title
 * @return boolean
 */
function isValidProfessionalTitle($profession, $title) {
    $titles = getProfessionalTitles($profession);
    return in_array($title, $titles);
}

/**
 * Get profession from professional title
 * @param string $title
 * @return string|null
 */
function getProfessionFromTitle($title) {
    foreach (getProfessionCategories() as $profession) {
        if (isValidProfessionalTitle($profession, $title)) {
            return $profession;
        }
    }
    return null;
}
