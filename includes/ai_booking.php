<?php
// includes/ai_booking.php
// AI-powered booking system using Hugging Face for natural language processing

class AIBookingHandler
{
    private $hf_api_key;
    private $db;
    private $model_endpoint = 'https://router.huggingface.co/models/facebook/bart-large-mnli';
    
    public function __construct($db)
    {
        $this->db = $db;
        $this->hf_api_key = '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';
    }
    
    /**
     * Process natural language booking request
     * 
     * @param string $prompt User's natural language booking request
     * @param int $client_id ID of the client making the request
     * @return array Structured booking data
     */
    public function processBookingRequest($prompt, $client_id)
    {
        try {
            // Extract structured information from the prompt
            $extracted = $this->extractBookingInfo($prompt);

            // Determine user intent (service vs provider)
            $intent = $this->detectIntent($prompt);

            // Generate AI summary (keeps consistent output)
            $summary = $this->generateBookingSummary($extracted);

            if ($intent === 'service') {
                // Return related services (cards) rather than providers
                $services = $this->findRelatedServices($extracted);

                return [
                    'success' => true,
                    'intent' => 'service',
                    'extracted' => $extracted,
                    'services' => $services,
                    'summary' => $summary,
                    'client_id' => $client_id
                ];
            }

            // Default: find providers (existing behavior)
            $providers = $this->findMatchingProviders($extracted);

            return [
                'success' => true,
                'intent' => 'provider',
                'extracted' => $extracted,
                'providers' => $providers,
                'summary' => $summary,
                'client_id' => $client_id
            ];
        } catch (Exception $e) {
            error_log("Error processing booking request: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Internal Server Error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Extract booking information using Hugging Face NLP
     */
    private function extractBookingInfo($prompt)
    {
        $extracted = [
            'service' => $this->extractService($prompt),
            'location' => $this->extractLocation($prompt),
            'date' => $this->extractDate($prompt),
            'time' => $this->extractTime($prompt),
            'description' => $prompt,
            'urgency' => $this->detectUrgency($prompt),
            'original_prompt' => $prompt
        ];
        
        return $extracted;
    }
    
    /**
     * Extract service type from prompt with intelligent keyword mapping
     */
    private function extractService($prompt)
    {
        $prompt_lower = strtolower($prompt);

        // Start with the comprehensive built-in mapping (kept as fallback/seed)
        $staticKeywords = [
            'electrician' => [
                'electrician','electrical','electricity',
                'socket','sockets','outlet','outlets','plug','plugs',
                'wire','wiring','rewire','rewiring',
                'switch','switches','light switch',
                'circuit breaker','breaker','fuse box','electrical panel',
                'light','lights','lighting','bulb','lamp',
                'power','power outlet','electrical outlet',
                'short circuit','electrical fault','power failure',
                'fan','ceiling fan','exhaust fan',
                'chandelier','electrical fixture',
                'generator','inverter','ups',
                'install socket','fix socket','replace outlet',
                'electrical work','electrical repair',
                'no power','power issue','electricity problem'
            ],
            'plumber' => [
                'plumber','plumbing',
                'pipe','pipes','piping','pipeline',
                'leak','leaking','leakage','water leak',
                'tap','faucet','water tap',
                'sink','basin','washbasin',
                'toilet','wc','commode','flush',
                'shower','bath','bathtub',
                'drain','drainage','blocked drain','clogged drain',
                'water heater','geyser','boiler',
                'sewage','sewer','septic',
                'water pump','pump',
                'valve','water valve',
                'dripping','burst pipe','broken pipe',
                'water problem','no water','low water pressure',
                'blocked toilet','clogged sink','overflowing'
            ],
            'carpenter' => [
                'carpenter','carpentry','woodwork','woodworker',
                'door','doors','wooden door',
                'window','windows','window frame',
                'cabinet','cupboard','wardrobe',
                'furniture','table','chair','bed','shelf','shelves',
                'drawer','drawers',
                'deck','decking',
                'frame','wooden frame',
                'floor','flooring','wooden floor','parquet',
                'ceiling','wooden ceiling',
                'fix door','repair door','install door',
                'broken door','door handle','door lock',
                'wood repair','furniture repair',
                'custom furniture','build furniture'
            ],
            'cleaner' => [
                'cleaner','cleaning','clean','janitor','housekeeper',
                'house cleaning','home cleaning','office cleaning',
                'deep cleaning','spring cleaning',
                'carpet cleaning','upholstery cleaning',
                'window cleaning','glass cleaning',
                'floor cleaning','mopping','sweeping',
                'dusting','vacuum','vacuuming',
                'bathroom cleaning','kitchen cleaning',
                'bedroom cleaning','living room cleaning',
                'dirty','messy','dusty','sanitize','disinfect','sterilize'
            ],
            'painter' => [
                'painter','painting','paint',
                'wall painting','interior painting','exterior painting',
                'repaint','repainting','whitewash','whitewashing',
                'color','colour','paint color',
                'wall','walls','ceiling paint','spray paint','paint job',
                'peeling paint','cracked paint','faded paint'
            ],
            'mechanic' => [
                'mechanic','mechanical','car mechanic','auto mechanic',
                'car','vehicle','automobile','auto','motorcycle','motorbike','bike','truck','van',
                'engine','motor','brake','brakes','tire','tyre','wheel',
                'battery','car battery','oil change','engine oil','transmission',
                'suspension','exhaust','car problem','car breakdown','car repair','not starting','engine problem'
            ],
            'gardener' => [
                'gardener','gardening','landscaper','landscaping',
                'lawn','grass','lawn mowing','mowing',
                'tree','trees','tree trimming','pruning',
                'hedge','hedges','hedge trimming',
                'plant','plants','planting','flowers','garden','yard','backyard',
                'weeding','weeds','fertilizer','fertilizing','irrigation','watering system'
            ],
            'mason' => [
                'mason','masonry','bricklayer',
                'brick','bricks','brickwork','wall','walls','build wall',
                'concrete','cement','plaster','plastering','tiles','tiling','tile work',
                'stone','stonework','foundation','construction','building'
            ],
            'welder' => [
                'welder','welding','weld','metal','iron','steel',
                'gate','gates','metal gate','iron gate','fence','fencing','metal fence',
                'railing','railings','handrail','grill','grills','security grill','fabrication','metal fabrication'
            ],
            'tailor' => [
                'tailor','sewing','seamstress','dressmaker',
                'clothes','clothing','garment','dress','shirt','trousers','suit',
                'alteration','alter','adjust','hem','hemming','stitch','stitching',
                'repair clothes','mend','custom clothing','make clothes'
            ],
            'roofer' => [
                'roofer','roofing','roof','rooftop','leak roof','roof leak','leaking roof',
                'roof repair','fix roof','tiles','roof tiles','roofing tiles','gutter','gutters','rain gutter','ceiling leak'
            ],
            'driver' => [
                'driver','driving','drive','chauffeur',
                'taxi','cab','ride','transportation','transport',
                'car rental','car hire','car service',
                'long distance drive','airport transfer','wedding drive',
                'trip','journey','commute','delivery driver'
            ]
        ];

        // Initialize mapping with static keywords
        $serviceKeywords = $staticKeywords;

        // Try to load category keywords from DB and merge them
        try {
            $stmt = $this->db->query("SELECT name, ai_keywords, keywords FROM categories WHERE is_active = 1");
            $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cats as $cat) {
                $catName = trim($cat['name']);
                if ($catName === '') continue;
                $key = strtolower($catName);

                // Build keywords list from ai_keywords or keywords column (comma-separated)
                $catKeywords = [$key];
                $raw = '';
                if (!empty($cat['ai_keywords'])) {
                    $raw = $cat['ai_keywords'];
                } elseif (!empty($cat['keywords'])) {
                    $raw = $cat['keywords'];
                }

                if ($raw !== '') {
                    $parts = array_filter(array_map('trim', explode(',', $raw)));
                    foreach ($parts as $p) {
                        if ($p !== '') $catKeywords[] = strtolower($p);
                    }
                }

                // Ensure unique
                $catKeywords = array_values(array_unique($catKeywords));

                // Merge with any existing (static) keywords for that profession
                if (isset($serviceKeywords[$key])) {
                    $serviceKeywords[$key] = array_values(array_unique(array_merge($serviceKeywords[$key], $catKeywords)));
                } else {
                    $serviceKeywords[$key] = $catKeywords;
                }
            }
        } catch (Exception $e) {
            error_log("Failed to load category keywords: " . $e->getMessage());
            // proceed with static mapping only
        }

        // First: Check for direct keyword matches with confidence scoring
        $matches = [];
        foreach ($serviceKeywords as $profession => $keywords) {
            $matchCount = 0;
            $matchedKeywords = [];

            foreach ($keywords as $keyword) {
                if ($keyword === '') continue;
                if (strpos($prompt_lower, $keyword) !== false) {
                    $matchCount++;
                    $matchedKeywords[] = $keyword;
                }
            }

            if ($matchCount > 0) {
                // Calculate confidence based on number of matches and keyword length
                $confidence = min(0.7 + ($matchCount * 0.1), 0.99);
                $matches[$profession] = [
                    'count' => $matchCount,
                    'confidence' => $confidence,
                    'keywords' => $matchedKeywords
                ];
            }
        }

        // If we found matches, return the best one
        if (!empty($matches)) {
            // Sort by match count and confidence
            uasort($matches, function($a, $b) {
                if ($a['count'] !== $b['count']) {
                    return $b['count'] - $a['count'];
                }
                return $b['confidence'] <=> $a['confidence'];
            });

            $topMatch = array_key_first($matches);
            return [
                'profession' => $topMatch,
                'confidence' => $matches[$topMatch]['confidence'],
                'matched_keywords' => $matches[$topMatch]['keywords']
            ];
        }

        // Second: Try Hugging Face zero-shot classification using active provider professions
        $stmt = $this->db->query("
            SELECT DISTINCT profession 
            FROM service_providers 
            WHERE is_active = 1 
            ORDER BY profession
        ");
        $professions = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($professions)) {
            $result = $this->callHuggingFace([
                'inputs' => $prompt,
                'parameters' => [
                    'candidate_labels' => $professions
                ]
            ]);

            if ($result && isset($result['labels'][0])) {
                return [
                    'profession' => $result['labels'][0],
                    'confidence' => $result['scores'][0] ?? 0,
                    'matched_keywords' => []
                ];
            }
        }

        // Third: Generic keyword fallback - check professions list
        foreach ($professions as $profession) {
            if (stripos($prompt, $profession) !== false) {
                return [
                    'profession' => $profession,
                    'confidence' => 0.6,
                    'matched_keywords' => [$profession]
                ];
            }
        }

        return [
            'profession' => null,
            'confidence' => 0,
            'matched_keywords' => []
        ];
    }
    
    /**
     * Extract location from prompt with comprehensive Rwanda locations
     */
    private function extractLocation($prompt)
    {
        $prompt_lower = strtolower($prompt);
        
        // Get districts from database
        $stmt = $this->db->query("SELECT DISTINCT district FROM service_providers WHERE district IS NOT NULL");
        $dbDistricts = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Comprehensive Rwanda location database
        $rwandaLocations = [
            // Kigali City Districts
            'Gasabo' => ['gasabo', 'gisozi', 'remera', 'kimironko', 'kacyiru', 'kimihurura', 'nyarutarama', 'kibagabaga', 'kanombe', 'kinyinya', 'jabana', 'jali', 'gikomero', 'ndera', 'rusororo', 'rutunga'],
            'Kicukiro' => ['kicukiro', 'gikondo', 'nyarugunga', 'gahanga', 'kanombe', 'kigarama', 'masaka', 'niboye'],
            'Nyarugenge' => ['nyarugenge', 'nyamirambo', 'kigali central', 'downtown', 'city center', 'centre ville', 'muhima', 'nyakabanda', 'kimisagara', 'gitega', 'rwezamenyo'],
            
            // Other major cities
            'Musanze' => ['musanze', 'ruhengeri', 'muhoza'],
            'Rubavu' => ['rubavu', 'gisenyi', 'rugerero'],
            'Huye' => ['huye', 'butare', 'ngoma', 'tumba'],
            'Muhanga' => ['muhanga', 'gitarama'],
            'Nyanza' => ['nyanza'],
            'Rusizi' => ['rusizi', 'cyangugu', 'kamembe'],
            'Rwamagana' => ['rwamagana'],
            'Karongi' => ['karongi', 'kibuye'],
            
            // Common area descriptors
            'Common Areas' => ['home', 'house', 'apartment', 'office', 'shop', 'store', 'restaurant', 'hotel', 'school', 'church', 'my place', 'our place']
        ];
        
        // Flatten all locations for matching
        $allLocations = array_merge($dbDistricts, ['Kigali']);
        foreach ($rwandaLocations as $district => $areas) {
            $allLocations[] = $district;
            $allLocations = array_merge($allLocations, $areas);
        }
        $allLocations = array_unique($allLocations);
        
        // First: Direct location matching with context
        $matches = [];
        foreach ($allLocations as $location) {
            $location_lower = strtolower($location);
            
            // Check for exact match with context words
            $contextPatterns = [
                '/(?:in|at|near|around|from)\s+' . preg_quote($location_lower, '/') . '\b/i',
                '/\b' . preg_quote($location_lower, '/') . '\s+(?:area|sector|district|zone)\b/i',
                '/\b' . preg_quote($location_lower, '/') . '\b/i'
            ];
            
            foreach ($contextPatterns as $index => $pattern) {
                if (preg_match($pattern, $prompt_lower)) {
                    // Higher confidence for context matches
                    $confidence = 0.95 - ($index * 0.1);
                    $matches[$location] = $confidence;
                    break;
                }
            }
        }
        
        // Return best match if found
        if (!empty($matches)) {
            arsort($matches);
            $bestLocation = array_key_first($matches);
            
            // Find parent district if it's a sub-location
            $parentDistrict = null;
            foreach ($rwandaLocations as $district => $areas) {
                if (in_array(strtolower($bestLocation), array_map('strtolower', $areas))) {
                    $parentDistrict = $district;
                    break;
                }
            }
            
            return [
                'location' => ucwords($bestLocation),
                'district' => $parentDistrict,
                'confidence' => $matches[$bestLocation]
            ];
        }
        
        // Second: Pattern matching for unknown locations
        $locationPatterns = [
            '/(?:in|at|near|around|from)\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)/i',
            '/([A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)\s+(?:area|sector|district|zone)/i',
        ];
        
        foreach ($locationPatterns as $pattern) {
            if (preg_match($pattern, $prompt, $match)) {
                return [
                    'location' => $match[1],
                    'district' => null,
                    'confidence' => 0.6
                ];
            }
        }
        
        return [
            'location' => null,
            'district' => null,
            'confidence' => 0
        ];
    }
    
    /**
     * Extract date from prompt with natural language understanding
     */
    private function extractDate($prompt)
    {
        $prompt_lower = strtolower($prompt);
        $date = null;
        $confidence = 0;
        
        // Today/Now/ASAP
        if (preg_match('/\b(today|now|asap|immediately|right now|this moment)\b/i', $prompt)) {
            $date = date('Y-m-d');
            $confidence = 0.98;
        }
        // Tomorrow
        elseif (preg_match('/\btomorrow\b/i', $prompt)) {
            $date = date('Y-m-d', strtotime('+1 day'));
            $confidence = 0.98;
        }
        // Day after tomorrow
        elseif (preg_match('/\b(day after tomorrow|overmorrow)\b/i', $prompt)) {
            $date = date('Y-m-d', strtotime('+2 days'));
            $confidence = 0.95;
        }
        // This week/weekend
        elseif (preg_match('/\b(this weekend|weekend)\b/i', $prompt)) {
            // Get next Saturday
            $date = date('Y-m-d', strtotime('next saturday'));
            $confidence = 0.85;
        }
        elseif (preg_match('/\bthis week\b/i', $prompt)) {
            $date = date('Y-m-d', strtotime('+2 days'));
            $confidence = 0.75;
        }
        // Next week
        elseif (preg_match('/\bnext week\b/i', $prompt)) {
            $date = date('Y-m-d', strtotime('+1 week'));
            $confidence = 0.85;
        }
        // Next month
        elseif (preg_match('/\bnext month\b/i', $prompt)) {
            $date = date('Y-m-d', strtotime('+1 month'));
            $confidence = 0.8;
        }
        // Specific date formats: 12/15/2024, 15-12-2024, 15.12.2024
        elseif (preg_match('/\b(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})\b/', $prompt, $matches)) {
            $day = intval($matches[1]);
            $month = intval($matches[2]);
            $year = intval($matches[3]);
            
            // Handle 2-digit years
            if ($year < 100) {
                $year = 2000 + $year;
            }
            
            // Validate date
            if (checkdate($month, $day, $year)) {
                $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $confidence = 0.95;
            } elseif (checkdate($day, $month, $year)) {
                // Try reversed (DD/MM vs MM/DD)
                $date = sprintf('%04d-%02d-%02d', $year, $day, $month);
                $confidence = 0.9;
            }
        }
        // Specific day of week: "next Monday", "this Friday"
        elseif (preg_match('/\b(next|this|coming)\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/i', $prompt, $matches)) {
            $modifier = strtolower($matches[1]);
            $dayName = strtolower($matches[2]);
            
            if ($modifier === 'next' || $modifier === 'coming') {
                $date = date('Y-m-d', strtotime("next $dayName"));
            } else {
                // "this Friday" - if Friday hasn't passed, use it; otherwise next Friday
                $targetDay = date('Y-m-d', strtotime("this $dayName"));
                if (strtotime($targetDay) < strtotime('today')) {
                    $targetDay = date('Y-m-d', strtotime("next $dayName"));
                }
                $date = $targetDay;
            }
            $confidence = 0.9;
        }
        // Just day of week without modifier
        elseif (preg_match('/\b(monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/i', $prompt, $matches)) {
            $dayName = strtolower($matches[1]);
            $targetDay = date('Y-m-d', strtotime("next $dayName"));
            
            // If the day is today, use today
            if (strtolower(date('l')) === $dayName) {
                $targetDay = date('Y-m-d');
            }
            
            $date = $targetDay;
            $confidence = 0.85;
        }
        // In X days: "in 2 days", "in 5 days"
        elseif (preg_match('/\bin\s+(\d+)\s+days?\b/i', $prompt, $matches)) {
            $days = intval($matches[1]);
            $date = date('Y-m-d', strtotime("+{$days} days"));
            $confidence = 0.95;
        }
        // In X weeks
        elseif (preg_match('/\bin\s+(\d+)\s+weeks?\b/i', $prompt, $matches)) {
            $weeks = intval($matches[1]);
            $date = date('Y-m-d', strtotime("+{$weeks} weeks"));
            $confidence = 0.9;
        }
        // Month and day: "December 15", "15th December"
        elseif (preg_match('/\b(\d{1,2})(?:st|nd|rd|th)?\s+(january|february|march|april|may|june|july|august|september|october|november|december)\b/i', $prompt, $matches)) {
            $day = intval($matches[1]);
            $month = date('m', strtotime($matches[2]));
            $year = date('Y');
            
            // If the date has passed this year, use next year
            $testDate = "$year-$month-$day";
            if (strtotime($testDate) < strtotime('today')) {
                $year++;
            }
            
            $date = "$year-$month-$day";
            $confidence = 0.9;
        }
        elseif (preg_match('/\b(january|february|march|april|may|june|july|august|september|october|november|december)\s+(\d{1,2})(?:st|nd|rd|th)?\b/i', $prompt, $matches)) {
            $month = date('m', strtotime($matches[1]));
            $day = intval($matches[2]);
            $year = date('Y');
            
            // If the date has passed this year, use next year
            $testDate = "$year-$month-$day";
            if (strtotime($testDate) < strtotime('today')) {
                $year++;
            }
            
            $date = "$year-$month-$day";
            $confidence = 0.9;
        }
        // Soon/shortly
        elseif (preg_match('/\b(soon|shortly|soonest)\b/i', $prompt)) {
            $date = date('Y-m-d', strtotime('+2 days'));
            $confidence = 0.6;
        }
        // Urgent (implies today or tomorrow)
        elseif (preg_match('/\b(urgent|emergency)\b/i', $prompt)) {
            $date = date('Y-m-d');
            $confidence = 0.7;
        }
        // Default to tomorrow if no date found
        else {
            $date = date('Y-m-d', strtotime('+1 day'));
            $confidence = 0.3;
        }
        
        return [
            'date' => $date,
            'confidence' => $confidence
        ];
    }
    
    /**
     * Extract time from prompt with flexible time understanding
     */
    private function extractTime($prompt)
    {
        $prompt_lower = strtolower($prompt);
        
        // 12-hour format with am/pm: "9am", "2:30pm", "9:00 am"
        if (preg_match('/\b(\d{1,2})(?::(\d{2}))?\s*(am|pm|a\.m\.|p\.m\.)\b/i', $prompt, $matches)) {
            $hour = intval($matches[1]);
            $minute = isset($matches[2]) ? $matches[2] : '00';
            $period = strtolower(str_replace('.', '', $matches[3]));
            
            if ($period === 'pm' && $hour < 12) $hour += 12;
            if ($period === 'am' && $hour == 12) $hour = 0;
            
            return [
                'time' => sprintf('%02d:%s:00', $hour, $minute),
                'confidence' => 0.95
            ];
        }
        
        // 24-hour format: "14:00", "09:30", "13:45"
        if (preg_match('/\b([01]?\d|2[0-3]):([0-5]\d)\b/', $prompt, $matches)) {
            return [
                'time' => sprintf('%02d:%02d:00', $matches[1], $matches[2]),
                'confidence' => 0.95
            ];
        }
        
        // Hour only without am/pm (assume based on context)
        if (preg_match('/\b(?:at\s+)?(\d{1,2})\s*(?:o\'?clock|hour|hrs?)\b/i', $prompt, $matches)) {
            $hour = intval($matches[1]);
            
            // Smart assumption: 7-11 could be AM or PM, 12-6 likely PM, 1-6 likely AM if mentioned with morning
            if ($hour >= 7 && $hour <= 11) {
                // Check for morning/afternoon context
                if (preg_match('/\bmorning\b/i', $prompt)) {
                    $hour = $hour;
                } elseif (preg_match('/\b(afternoon|evening|night)\b/i', $prompt)) {
                    $hour = ($hour < 12) ? $hour + 12 : $hour;
                } else {
                    // Default to AM for 7-11
                    $hour = $hour;
                }
            } elseif ($hour >= 1 && $hour <= 6) {
                // Default to PM for 1-6 unless morning is mentioned
                if (preg_match('/\bmorning\b/i', $prompt)) {
                    $hour = $hour;
                } else {
                    $hour = $hour + 12;
                }
            }
            
            return [
                'time' => sprintf('%02d:00:00', $hour),
                'confidence' => 0.75
            ];
        }
        
        // Time of day keywords
        $timeKeywords = [
            '/\b(?:early\s+)?morning\b/i' => ['08:00:00', 0.7],
            '/\b(?:late\s+)?morning\b/i' => ['10:00:00', 0.7],
            '/\bmid\s*day|noon|lunch\s*time\b/i' => ['12:00:00', 0.8],
            '/\b(?:early\s+)?afternoon\b/i' => ['14:00:00', 0.7],
            '/\b(?:mid|late)\s*afternoon\b/i' => ['16:00:00', 0.7],
            '/\b(?:early\s+)?evening\b/i' => ['18:00:00', 0.7],
            '/\b(?:late\s+)?evening|night|tonight\b/i' => ['19:00:00', 0.7],
            '/\bmidnight\b/i' => ['23:59:00', 0.8],
        ];
        
        foreach ($timeKeywords as $pattern => $timeData) {
            if (preg_match($pattern, $prompt_lower)) {
                return [
                    'time' => $timeData[0],
                    'confidence' => $timeData[1]
                ];
            }
        }
        
        // Relative time: "in 2 hours", "after 3 hours"
        if (preg_match('/\b(?:in|after)\s+(\d+)\s+hours?\b/i', $prompt, $matches)) {
            $hours = intval($matches[1]);
            $targetTime = strtotime("+{$hours} hours");
            return [
                'time' => date('H:i:00', $targetTime),
                'confidence' => 0.85
            ];
        }
        
        // Business hours if mentioned
        if (preg_match('/\b(business|office|work(?:ing)?)\s+hours?\b/i', $prompt)) {
            return [
                'time' => '09:00:00',
                'confidence' => 0.65
            ];
        }
        
        // ASAP/Urgent - default to nearest available time
        if (preg_match('/\b(asap|urgent|immediately|right\s+now|emergency)\b/i', $prompt)) {
            // Use current time rounded to next hour
            $nextHour = date('H') + 1;
            return [
                'time' => sprintf('%02d:00:00', $nextHour),
                'confidence' => 0.6
            ];
        }
        
        // Default to 10am if nothing found
        return [
            'time' => '10:00:00',
            'confidence' => 0.3
        ];
    }
    
    /**
     * Detect urgency level
     */
    private function detectUrgency($prompt)
    {
        $urgentKeywords = ['urgent', 'emergency', 'asap', 'immediately', 'now', 'today', 'help'];
        
        $prompt_lower = strtolower($prompt);
        foreach ($urgentKeywords as $keyword) {
            if (strpos($prompt_lower, $keyword) !== false) {
                return 'high';
            }
        }
        
        // Check if date is today or tomorrow
        $dateInfo = $this->extractDate($prompt);
        if ($dateInfo['date'] === date('Y-m-d')) {
            return 'high';
        } elseif ($dateInfo['date'] === date('Y-m-d', strtotime('+1 day'))) {
            return 'medium';
        }
        
        return 'normal';
    }
    
    /**
     * Find matching providers based on extracted information
     *
     * Robust matching with proper fallbacks and scoring
     *
     * @param array $extracted
     * @return array
     */
    private function findMatchingProviders($extracted)
    {
        require_once __DIR__ . '/geolocation.php';
        
        $service = $extracted['service'];
        $location = $extracted['location'];
        $prompt = $extracted['original_prompt'] ?? ($extracted['description'] ?? '');

        if (empty($service['profession'])) {
            return [];
        }

        // Get client location coordinates (for distance calculation)
        $clientCoordinates = GeolocationHelper::getLocationCoordinates(
            $this->db, 
            $location['location'] ?? $location['district'] ?? ''
        );

        // Try to find categories first
        $matchingCategories = $this->findMatchingCategories($service['profession']);
        $categoryIds = [];
        if (!empty($matchingCategories)) {
            $categoryIds = array_map(function($c) { return $c['id']; }, $matchingCategories);
        }

        // If no categories found, fallback: find provider IDs from provider_services by name/description match
        $fallbackProviderIds = [];
        if (empty($categoryIds)) {
            try {
                $like = '%' . strtolower($service['profession'] . '%');
                $psStmt = $this->db->prepare("
                    SELECT DISTINCT provider_id
                    FROM provider_services
                    WHERE LOWER(name) LIKE ? OR LOWER(description) LIKE ?
                ");
                $psStmt->execute([$like, $like]);
                $rows = $psStmt->fetchAll(PDO::FETCH_COLUMN);
                $fallbackProviderIds = $rows ?: [];
            } catch (Exception $e) {
                error_log("Fallback provider lookup error: " . $e->getMessage());
            }

            // If still nothing, broaden with profession token words (split and search)
            if (empty($fallbackProviderIds)) {
                $terms = preg_split('/\s+/', trim($service['profession']));
                foreach ($terms as $t) {
                    if (strlen($t) < 3) continue;
                    try {
                        $like = '%' . strtolower($t) . '%';
                        $psStmt = $this->db->prepare("
                            SELECT DISTINCT provider_id
                            FROM provider_services
                            WHERE LOWER(name) LIKE ? OR LOWER(description) LIKE ?
                            LIMIT 200
                        ");
                        $psStmt->execute([$like, $like]);
                        $rows = $psStmt->fetchAll(PDO::FETCH_COLUMN);
                        if (!empty($rows)) {
                            $fallbackProviderIds = array_merge($fallbackProviderIds, $rows);
                        }
                    } catch (Exception $e) {
                        error_log("Term fallback lookup error: " . $e->getMessage());
                    }
                }
                $fallbackProviderIds = array_unique($fallbackProviderIds);
            }

            // If no provider ids found at all, give up
            if (empty($fallbackProviderIds)) {
                return [];
            }
        }

        // Build provider candidate query
        $baseQuery = "
            SELECT DISTINCT
                sp.id,
                sp.user_id,
                sp.profession,
                sp.bio,
                sp.location,
                sp.district,
                sp.sector,
                sp.hourly_rate,
                sp.experience_years,
                sp.is_active,
                sp.availability,
                sp.average_rating,
                sp.total_reviews,
                u.id as user_db_id,
                u.full_name,
                u.email,
                u.phone,
                u.profile_image
            FROM service_providers sp
            JOIN users u ON sp.user_id = u.id
            WHERE sp.is_active = 1
        ";

        $params = [];

        if (!empty($categoryIds)) {
            // If we have category IDs, prefer providers who have services in these categories.
            $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
            $baseQuery .= " AND sp.id IN (
                SELECT DISTINCT ps.provider_id FROM provider_services ps
                WHERE ps.category_id IN ({$placeholders})
            )";
            $params = array_merge($params, $categoryIds);
        } else {
            // We have fallback provider ids from service name/description matching
            $placeholders = implode(',', array_fill(0, count($fallbackProviderIds), '?'));
            $baseQuery .= " AND sp.id IN ({$placeholders})";
            $params = array_merge($params, $fallbackProviderIds);
        }

        // Optional location narrowing (soft filter)
        if (!empty($location['location']) && !empty($location['district'])) {
            $baseQuery .= " AND (sp.district = ? OR sp.sector LIKE ? OR sp.location LIKE ?)";
            $params[] = $location['district'];
            $params[] = '%' . $location['location'] . '%';
            $params[] = '%' . $location['location'] . '%';
        } elseif (!empty($location['location'])) {
            $baseQuery .= " AND (sp.location LIKE ? OR sp.sector LIKE ? OR sp.district LIKE ?)";
            $search = '%' . $location['location'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $baseQuery .= " LIMIT 250";

        $stmt = $this->db->prepare($baseQuery);
        $stmt->execute($params);
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($candidates)) {
            return [];
        }

        $scored = [];

        foreach ($candidates as $prov) {
            // Fetch provider services
            if (!empty($categoryIds)) {
                $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
                $svcSql = "
                    SELECT ps.*, c.name AS category_name
                    FROM provider_services ps
                    LEFT JOIN categories c ON ps.category_id = c.id
                    WHERE ps.provider_id = ?
                    AND ps.category_id IN ({$placeholders})
                    ORDER BY ps.is_available DESC, ps.created_at DESC
                ";
                $svcParams = array_merge([$prov['id']], $categoryIds);
            } else {
                $like = '%' . strtolower($service['profession'] . '%');
                $svcSql = "
                    SELECT ps.*, c.name AS category_name
                    FROM provider_services ps
                    LEFT JOIN categories c ON ps.category_id = c.id
                    WHERE ps.provider_id = ?
                    AND (LOWER(ps.name) LIKE ? OR LOWER(ps.description) LIKE ?)
                    ORDER BY ps.is_available DESC, ps.created_at DESC
                ";
                $svcParams = [$prov['id'], $like, $like];
            }

            try {
                $sStmt = $this->db->prepare($svcSql);
                $sStmt->execute($svcParams);
                $providerServices = $sStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log("Provider services lookup error for provider {$prov['id']}: " . $e->getMessage());
                $providerServices = [];
            }

            if (empty($providerServices)) {
                continue;
            }

            // Get reviews
            $reviews = ['avg_rating' => 0, 'review_count' => 0];
            try {
                $reviewStmt = $this->db->prepare("
                    SELECT AVG(rating) as avg_rating, COUNT(*) as review_count
                    FROM reviews
                    WHERE provider_id = ?
                ");
                $reviewStmt->execute([$prov['id']]);
                $r = $reviewStmt->fetch(PDO::FETCH_ASSOC);
                if ($r) {
                    $reviews = [
                        'avg_rating' => floatval($r['avg_rating'] ?? 0),
                        'review_count' => intval($r['review_count'] ?? 0)
                    ];
                }
            } catch (Exception $e) {
                error_log("Reviews lookup error: " . $e->getMessage());
            }

            // SCORING: Base score starts at 2.0 (guaranteed minimum for matched providers)
            $score = 2.0;
            $maxScore = 10.0;

            // 1) Service text similarity (3.0 points max)
            $bestServiceMatch = 0;
            $bestService = null;
            foreach ($providerServices as $svc) {
                // Compare profession against service name AND category name
                $sim = $this->textSimilarity(
                    strtolower($service['profession']),
                    strtolower($svc['name'] ?? '')
                );
                
                // Also compare against category name (e.g., "Driver")
                if (!empty($svc['category_name'])) {
                    $simCat = $this->textSimilarity(
                        strtolower($service['profession']),
                        strtolower($svc['category_name'])
                    );
                    $sim = max($sim, $simCat);
                }
                
                // Boost if description also contains term
                if (!empty($svc['description'])) {
                    $simDesc = $this->textSimilarity(
                        strtolower($service['profession']),
                        strtolower($svc['description'])
                    );
                    $sim = max($sim, ($sim + $simDesc) / 2);
                }
                if ($sim > $bestServiceMatch) {
                    $bestServiceMatch = $sim;
                    $bestService = $svc;
                }
            }
            $score += ($bestServiceMatch / 100) * 3.0;

            // 2) Location proximity (2.5 points max)
            if (!empty($location['location'])) {
                if (!empty($location['district']) && strcasecmp($prov['district'] ?? '', $location['district']) === 0) {
                    $score += 2.5;
                } elseif (
                    (!empty($prov['location']) && stripos($prov['location'], $location['location']) !== false) ||
                    (!empty($prov['sector']) && stripos($prov['sector'], $location['location']) !== false)
                ) {
                    $score += 1.8;
                } elseif (!empty($prov['district']) && stripos($prov['district'], $location['location']) !== false) {
                    $score += 1.2;
                }
            }

            // 3) Service availability (1.0)
            if (!empty($bestService['is_available'])) {
                $score += 1.0;
            }

            // 4) Provider rating (1.5 points max)
            $avgRating = floatval($reviews['avg_rating'] ?? ($prov['average_rating'] ?? 0));
            if ($avgRating > 0) {
                $score += ($avgRating / 5) * 1.5;
            }

            // 5) Experience (0.75)
            if (!empty($prov['experience_years'])) {
                $score += min(($prov['experience_years'] / 10) * 0.75, 0.75);
            }

            // 6) Provider availability (0.5)
            if (!empty($prov['availability']) && $prov['availability'] === 'available') {
                $score += 0.5;
            }

            // 7) Bio relevance (0.5)
            if (!empty($prov['bio'])) {
                $bioSim = $this->textSimilarity($prompt, $prov['bio']);
                $score += ($bioSim / 100) * 0.5;
            }

            // 8) Payment type match (0.25)
            if ($requestedPayment && !empty($bestService['payment_type'])) {
                if ($bestService['payment_type'] === $requestedPayment) {
                    $score += 0.25;
                }
            }

            // Get provider coordinates from database or database location mapping
            $providerCoordinates = null;
            if (!empty($prov['latitude']) && !empty($prov['longitude'])) {
                $providerCoordinates = [
                    'latitude' => floatval($prov['latitude']),
                    'longitude' => floatval($prov['longitude'])
                ];
            } else {
                // Fallback: get coordinates from location name
                $providerCoordinates = GeolocationHelper::getLocationCoordinates(
                    $this->db,
                    $prov['location'] ?? $prov['district'] ?? ''
                );
            }
            
            // Calculate distance score if we have coordinates
            $distanceScore = 5.0; // Default middle score
            $distance = null;
            if ($clientCoordinates && $providerCoordinates) {
                $distance = GeolocationHelper::haversineDistance(
                    $clientCoordinates['latitude'],
                    $clientCoordinates['longitude'],
                    $providerCoordinates['latitude'],
                    $providerCoordinates['longitude']
                );
                $distanceScore = GeolocationHelper::calculateDistanceScore($distance);
            }
            
            // Calculate rating score (0-5 stars to 0-10 scale)
            $ratingScore = GeolocationHelper::calculateRatingScore($avgRating);
            
            // Calculate combined ranking score
            $isAvailable = !empty($prov['availability']) && $prov['availability'] === 'available';
            $combinedScore = GeolocationHelper::calculateCombinedScore(
                $distanceScore,
                $ratingScore,
                intval($reviews['review_count'] ?? 0),
                $isAvailable
            );
            
            // Add to scored results
            $scored[] = [
                'id' => $prov['id'],
                'distance_km' => $distance,
                'distance_score' => $distanceScore,
                'rating_score' => $ratingScore,
                'combined_score' => $combinedScore,
                'user_id' => $prov['user_id'],
                'full_name' => $prov['full_name'],
                'email' => $prov['email'] ?? '',
                'phone' => $prov['phone'] ?? '',
                'profession' => $prov['profession'] ?? '',
                'bio' => $prov['bio'] ?? '',
                'location' => $prov['location'] ?? '',
                'district' => $prov['district'] ?? '',
                'sector' => $prov['sector'] ?? '',
                'hourly_rate' => floatval($prov['hourly_rate'] ?? 0),
                'experience_years' => intval($prov['experience_years'] ?? 0),
                'availability' => $prov['availability'] ?? '',
                'average_rating' => floatval($prov['average_rating'] ?? 0),
                'total_reviews' => intval($prov['total_reviews'] ?? 0),
                'profile_image' => $prov['profile_image'] ?? '',
                'score' => round($score, 2),
                'max_score' => $maxScore,
                'match_score' => round(($score / $maxScore) * 100, 1),
                'reviews' => $reviews,
                'services' => $providerServices,
                'best_service' => $bestService
            ];
        }

        // Sort by combined score (primary), then by distance, then rating
        usort($scored, function($a, $b) {
            // Primary: Combined score (descending)
            if ($b['combined_score'] != $a['combined_score']) {
                return $b['combined_score'] <=> $a['combined_score'];
            }
            // Secondary: Distance (ascending - closer is better)
            if (($a['distance_km'] ?? 999) != ($b['distance_km'] ?? 999)) {
                return ($a['distance_km'] ?? 999) <=> ($b['distance_km'] ?? 999);
            }
            // Tertiary: Rating (descending - higher is better)
            return ($b['average_rating'] ?? 0) <=> ($a['average_rating'] ?? 0);
        });
        
        return array_slice($scored, 0, 25);
    }

    /**
     * Find related services (provider_services) matching the extracted service profession/keywords.
     * Improved: Now searches by profession + uses category AI keywords + has fallback chain
     *
     * @param array $extracted Extracted booking info returned by extractBookingInfo()
     * @return array List of related services with provider/context
     */
    private function findRelatedServices($extracted)
    {
        $profession = $extracted['service']['profession'] ?? null;
        $prompt = $extracted['original_prompt'] ?? ($extracted['description'] ?? '');

        if (empty($profession)) {
            return [];
        }

        // Step 1: Try to find categories matching the profession
        $categories = $this->findMatchingCategories($profession);
        $categoryIds = [];
        if (!empty($categories)) {
            $categoryIds = array_map(function($c) { return $c['id']; }, $categories);
        }

        // Step 2: Build the main query - search by profession + category + service keywords
        $params = [];
        $like = '%' . strtolower($profession) . '%';
        
        // Build WHERE conditions
        $whereConditions = [
            "LOWER(ps.name) LIKE ?",
            "LOWER(ps.description) LIKE ?",
            "LOWER(c.name) LIKE ?",
            "LOWER(sp.profession) LIKE ?",
            "LOWER(c.ai_keywords) LIKE ?"
        ];
        
        $params = [$like, $like, $like, $like, $like];

        // Add category filter if we found matching categories
        if (!empty($categoryIds)) {
            $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
            $whereConditions[] = "ps.category_id IN ($placeholders)";
            $params = array_merge($params, $categoryIds);
        }

        $whereClause = "(" . implode(" OR ", $whereConditions) . ")";

        $sql = "
            SELECT
                ps.id AS service_id,
                ps.provider_id,
                ps.name AS service_name,
                ps.description AS service_description,
                ps.price AS service_price,
                ps.duration AS service_duration,
                ps.is_available,
                ps.payment_type,
                c.name AS category_name,
                sp.location AS provider_location,
                sp.district AS provider_district,
                sp.sector AS provider_sector,
                sp.profession AS provider_profession,
                sp.average_rating AS provider_rating,
                sp.total_reviews AS provider_total_reviews,
                u.full_name AS provider_name,
                u.profile_image AS provider_profile_image
            FROM provider_services ps
            JOIN service_providers sp ON ps.provider_id = sp.id
            JOIN users u ON sp.user_id = u.id
            LEFT JOIN categories c ON ps.category_id = c.id
            WHERE 
                sp.is_active = 1 
                AND sp.is_banned = 0
                AND u.is_verified = 1
                AND $whereClause
            ORDER BY ps.is_available DESC, sp.average_rating DESC, ps.created_at DESC
            LIMIT 50
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // If we got results, normalize and return
            if (!empty($rows)) {
                foreach ($rows as &$r) {
                    $r['service_price'] = isset($r['service_price']) ? floatval($r['service_price']) : null;
                    $r['service_duration'] = isset($r['service_duration']) ? intval($r['service_duration']) : null;
                    $r['provider_rating'] = isset($r['provider_rating']) ? floatval($r['provider_rating']) : 0.0;
                    $r['provider_total_reviews'] = isset($r['provider_total_reviews']) ? intval($r['provider_total_reviews']) : 0;
                }
                return $rows;
            }

            // FALLBACK 1: If no results, search ONLY by provider profession (matches driver, plumber, etc.)
            $fallbackSql = "
                SELECT
                    ps.id AS service_id,
                    ps.provider_id,
                    ps.name AS service_name,
                    ps.description AS service_description,
                    ps.price AS service_price,
                    ps.duration AS service_duration,
                    ps.is_available,
                    ps.payment_type,
                    c.name AS category_name,
                    sp.location AS provider_location,
                    sp.district AS provider_district,
                    sp.sector AS provider_sector,
                    sp.profession AS provider_profession,
                    sp.average_rating AS provider_rating,
                    sp.total_reviews AS provider_total_reviews,
                    u.full_name AS provider_name,
                    u.profile_image AS provider_profile_image
                FROM provider_services ps
                JOIN service_providers sp ON ps.provider_id = sp.id
                JOIN users u ON sp.user_id = u.id
                LEFT JOIN categories c ON ps.category_id = c.id
                WHERE 
                    sp.is_active = 1 
                    AND sp.is_banned = 0
                    AND u.is_verified = 1
                    AND LOWER(sp.profession) = LOWER(?)
                ORDER BY ps.is_available DESC, sp.average_rating DESC, ps.created_at DESC
                LIMIT 50
            ";

            $stmt = $this->db->prepare($fallbackSql);
            $stmt->execute([$profession]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                foreach ($rows as &$r) {
                    $r['service_price'] = isset($r['service_price']) ? floatval($r['service_price']) : null;
                    $r['service_duration'] = isset($r['service_duration']) ? intval($r['service_duration']) : null;
                    $r['provider_rating'] = isset($r['provider_rating']) ? floatval($r['provider_rating']) : 0.0;
                    $r['provider_total_reviews'] = isset($r['provider_total_reviews']) ? intval($r['provider_total_reviews']) : 0;
                }
                return $rows;
            }

            return [];
        } catch (Exception $e) {
            error_log("Find related services error: " . $e->getMessage() . " | SQL: " . $sql);
            return [];
        }
    }
    
    /**
     * Extract service type from prompt with intelligent keyword mapping
     */
    private function findMatchingCategories($profession)
    {
        if (empty($profession)) {
            return [];
        }

        // Query categories by exact name, keyword match, or AI keywords
        $query = "
            SELECT DISTINCT c.id, c.name, c.ai_keywords
            FROM categories c
            WHERE c.is_active = 1
            AND (
                LOWER(c.name) = LOWER(?)
                OR LOWER(c.name) LIKE CONCAT('%', LOWER(?), '%')
                OR LOWER(c.ai_keywords) LIKE CONCAT('%', LOWER(?), '%')
                OR LOWER(c.keywords) LIKE CONCAT('%', LOWER(?), '%')
            )
            ORDER BY 
                CASE 
                    WHEN LOWER(c.name) = LOWER(?) THEN 1
                    WHEN LOWER(c.name) LIKE CONCAT('%', LOWER(?), '%') THEN 2
                    ELSE 3
                END ASC
            LIMIT 10
        ";

        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $profession,
                $profession,
                $profession,
                $profession,
                $profession,
                $profession
            ]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Categories found for profession '$profession': " . count($result));
            return $result;
        } catch (Exception $e) {
            error_log("Find matching categories error: " . $e->getMessage() . " | Profession: $profession");
            return [];
        }
    }

    /**
     * Detect payment type preference from prompt
     * 
     * @param string $prompt User's booking request
     * @return string|null Payment type: 'per_service', 'per_hour', 'per_day', or null
     */
    private function detectPaymentTypeFromPrompt($prompt)
    {
        $prompt_lower = strtolower($prompt);

        if (preg_match('/\b(hourly|per\s+hour|hour\s+rate|by\s+the\s+hour|\/\s*hour)\b/i', $prompt)) {
            return 'per_hour';
        } elseif (preg_match('/\b(daily|per\s+day|by\s+the\s+day|full\s+day|day\s+rate|\/\s*day)\b/i', $prompt)) {
            return 'per_day';
        } elseif (preg_match('/\b(per\s+service|flat\s+rate|fixed\s+price|one\s+time|one-time)\b/i', $prompt)) {
            return 'per_service';
        }

        return null;
    }

    /**
     * Calculate text similarity using multiple algorithms
     * 
     * Combines similar_text() and Levenshtein distance for robust matching
     * 
     * @param string $str1 First string
     * @param string $str2 Second string
     * @return float Similarity percentage (0-100)
     */
    private function textSimilarity($str1, $str2)
    {
        // Normalize strings
        $str1 = trim(strtolower($str1));
        $str2 = trim(strtolower($str2));

        if ($str1 === $str2) {
            return 100;
        }

        if (empty($str1) || empty($str2)) {
            return 0;
        }

        // Method 1: PHP's built-in similar_text()
        similar_text($str1, $str2, $percent1);

        // Method 2: Levenshtein distance
        $maxLen = max(strlen($str1), strlen($str2));
        $levenshtein = levenshtein($str1, $str2);
        $percent2 = (1 - ($levenshtein / $maxLen)) * 100;

        // Method 3: Check for substring containment
        $percent3 = 0;
        if (strpos($str1, $str2) !== false || strpos($str2, $str1) !== false) {
            $percent3 = 85; // Strong match if one contains the other
        }

        // Average all methods with weights
        $similarity = ($percent1 * 0.4) + ($percent2 * 0.3) + ($percent3 * 0.3);

        return max(0, min(100, $similarity));
    }

    /**
     * Generate booking summary from extracted data
     * 
     * @param array $extracted Extracted booking information
     * @return string Human-readable booking summary
     */
    private function generateBookingSummary($extracted)
    {
        $parts = [];

        $parts[] = "Service: " . ($extracted['service']['profession'] ?? 'Not specified');
        $parts[] = "Date: " . ($extracted['date']['date'] ?? 'Not specified');
        $parts[] = "Time: " . ($extracted['time']['time'] ?? 'Not specified');
        $parts[] = "Location: " . ($extracted['location']['location'] ?? 'Not specified');

        if ($extracted['urgency'] === 'high') {
            $parts[] = "Urgency: High (ASAP required)";
        } elseif ($extracted['urgency'] === 'medium') {
            $parts[] = "Urgency: Medium";
        }

        return implode(" | ", $parts);
    }
    
    /**
     * Create booking from AI extraction
     */
    public function createBooking($extractedData, $providerId, $clientId)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO bookings (
                    client_id, 
                    provider_id, 
                    location, 
                    service_description, 
                    preferred_date, 
                    preferred_time, 
                    status,
                    ai_generated
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', 1)
            ");
            
            $result = $stmt->execute([
                $clientId,
                $providerId,
                $extractedData['location']['location'] ?? 'Not specified',
                $extractedData['description'],
                $extractedData['date']['date'],
                $extractedData['time']['time']
            ]);
            
            if ($result) {
                $bookingId = $this->db->lastInsertId();
                
                // Update user_profiles to track booking metrics
                $update_profile = $this->db->prepare("
                    INSERT INTO user_profiles (user_id, user_total_bookings, user_avg_price, user_avg_response_time) 
                    VALUES (?, 1, 0, 24) 
                    ON DUPLICATE KEY UPDATE 
                        user_total_bookings = user_total_bookings + 1,
                        updated_at = CURRENT_TIMESTAMP
                ");
                $update_profile->execute([$clientId]);
                
                // insert initial chat message so conversation exists
                try {
                    require_once __DIR__ . '/chat.php';
                    require_once __DIR__ . '/event_tracking.php';
                    $booking_ref = '#BK-' . date('Y') . '-' . str_pad($bookingId,5,'0',STR_PAD_LEFT);
                    // provider's user id is available via getProviderInfo
                    $providerData = $this->getProviderInfo($providerId);
                    if (!empty($providerData['user_id'])) {
                        sendMessage($clientId, $providerData['user_id'], "New booking created: " . $booking_ref);
                    }
                } catch (Throwable $e) {
                    error_log('AI booking chat init error: ' . $e->getMessage());
                }

                // Get provider and client info for notifications
                $provider = $this->getProviderInfo($providerId);
                $client = $this->getClientInfo($clientId);
                
                // Send notifications
                $this->sendBookingNotifications($bookingId, $provider, $client, $extractedData);
                
                // Log activity
                $this->logActivity($clientId, 'ai_booking_created', "AI booking created: #{$bookingId}");

                // Track booking created event
                trackEvent('booking_created', 'booking', $bookingId, [
                    'client_id' => $clientId,
                    'provider_id' => $providerId,
                    'ai_generated' => true,
                    'location' => $extractedData['location']['location'] ?? 'Not specified',
                    'preferred_date' => $extractedData['date']['date'],
                    'preferred_time' => $extractedData['time']['time']
                ], $clientId);
                
                return [
                    'success' => true,
                    'booking_id' => $bookingId,
                    'message' => 'Booking created successfully'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to create booking'
            ];
            
        } catch (Exception $e) {
            error_log("AI Booking creation error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error creating booking: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get provider information
     */
    private function getProviderInfo($providerId)
    {
        $stmt = $this->db->prepare("
            SELECT sp.*, u.id AS user_id, u.full_name, u.email, u.phone
            FROM service_providers sp
            JOIN users u ON sp.user_id = u.id
            WHERE sp.id = ?
        ");
        $stmt->execute([$providerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get client information
     */
    private function getClientInfo($clientId)
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$clientId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Send booking notifications
     */
    private function sendBookingNotifications($bookingId, $provider, $client, $extracted)
    {
        require_once __DIR__ . '/mailer.php';

        // Notify provider by email (if available)
        if (!empty($provider['email'])) {
            try {
                $subject = "New Booking Request - " . ($extracted['service']['profession'] ?? 'Service');
                $body = "
                    <h2>You have a new booking request!</h2>
                    <p><strong>Client:</strong> {$client['full_name']}</p>
                    <p><strong>Service:</strong> {$extracted['service']['profession']}</p>
                    <p><strong>Location:</strong> {$extracted['location']['location']}</p>
                    <p><strong>Date:</strong> {$extracted['date']['date']}</p>
                    <p><strong>Time:</strong> {$extracted['time']['time']}</p>
                    <p><strong>Details:</strong> {$extracted['description']}</p>
                    <p>Log in to your dashboard to respond to this request.</p>
                ";
                
                sendEmail($provider['email'], $subject, $body);
            } catch (Exception $e) {
                error_log("Failed to send provider email: " . $e->getMessage());
            }
        }

        // Insert booking notification record
        try {
            // Check which user column exists in booking_notifications table
            $userCol = null;
            $tableInfo = $this->db->query("DESCRIBE booking_notifications")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('provider_id', $tableInfo)) {
                $userCol = 'provider_id';
            } elseif (in_array('user_id', $tableInfo)) {
                $userCol = 'user_id';
            }

            $notifyUserId = $provider['id'] ?? $provider['user_id'] ?? null;

            if ($userCol && $notifyUserId) {
                $sql = "INSERT INTO booking_notifications (booking_id, {$userCol}, notification_type, sent_at) 
                        VALUES (?, ?, 'booking_request', NOW())";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$bookingId, $notifyUserId]);
            } else {
                // Fallback: insert minimal notification record
                $stmt = $this->db->prepare("INSERT INTO booking_notifications (booking_id, notification_type, sent_at) 
                                        VALUES (?, 'booking_request', NOW())");
                $stmt->execute([$bookingId]);
            }
        } catch (Exception $e) {
            error_log("Failed to insert booking notification: " . $e->getMessage());
        }
    }
    
    /**
     * Log activity
     */
    private function logActivity($userId, $action, $description)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_log (user_id, action, description, ip_address, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $userId,
                $action,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
            ]);
        } catch (Exception $e) {
            error_log("Activity logging error: " . $e->getMessage());
        }
    }

    /**
     * Call Hugging Face API for zero-shot classification
     * 
     * Robust behavior:
     * - Uses the configured $this->model_endpoint
     * - On HTTP 410 (deprecated endpoint / router error) it records a short-disable flag
     *   (15 minutes) to avoid repeated calls during outages and returns null so callers
     *   fall back to local heuristics.
     * - Logs curl/http errors with context.
     *
     * @param array $payload Request payload
     * @return array|null API response or null if failed
     */
    private function callHuggingFace($payload)
    {
        // Short-circuit: if HF was recently found unavailable, skip trying again.
        $flagFile = __DIR__ . '/../logs/hf_unavailable.flag';
        $disableSeconds = 15 * 60; // 15 minutes

        if (file_exists($flagFile)) {
            $ts = intval(@file_get_contents($flagFile));
            if ($ts > 0 && (time() - $ts) < $disableSeconds) {
                error_log("Skipping Hugging Face call (temporarily disabled until " . date('c', $ts + $disableSeconds) . ")");
                return null;
            } else {
                @unlink($flagFile);
            }
        }

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->model_endpoint,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->hf_api_key,
                    'Content-Type: application/json'
                ],
                CURLOPT_POSTFIELDS => json_encode($payload)
            ]);

            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
            curl_close($ch);

            if (!empty($curlError)) {
                error_log("Hugging Face API curl error: " . $curlError);
                return null;
            }

            // Successful response
            if ($httpCode === 200) {
                $decoded = json_decode($response, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
                // Unexpected non-JSON response
                error_log("Hugging Face API returned non-JSON response (HTTP 200). Raw: " . substr($response, 0, 1000));
                return null;
            }

            // Handle deprecation / router issues gracefully
            if ($httpCode === 410) {
                // Write a short flag so we don't keep hammering the HF endpoint
                @file_put_contents($flagFile, (string) time());
                error_log("Hugging Face API connection issue. HTTP Code: 410; response: " . substr($response ?? '', 0, 1000));
                return null;
            }

            // Other HTTP errors
            error_log("Hugging Face API error (HTTP $httpCode): " . substr($response ?? '', 0, 1000));
            return null;
        } catch (Exception $e) {
            error_log("Error calling Hugging Face API: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Detect user intent from prompt: 'service' (wants service options) or 'provider' (wants provider results)
     * Returns 'service'|'provider'
     */
    private function detectIntent($prompt)
    {
        $p = strtolower(trim($prompt));

        // Explicit provider requests (user wants providers / hire / book)
        if (preg_match('/\b(provider|providers|hire|book|available providers|show me providers|find providers|who can)\b/i', $p)) {
            return 'provider';
        }

        // If prompt mentions explicit "service" words
        if (preg_match('/\b(service|services|what do you offer|what services|list services|show services)\b/i', $p)) {
            return 'service';
        }

        // If we can extract a profession/service, prefer 'service' intent
        $svc = $this->extractService($prompt);
        if (!empty($svc['profession'])) {
            return 'service';
        }

        // Default to provider (safer for bookings)
        return 'provider';
    }
}