<?php
// includes/ai_helpers.php

class AIHelper {
    private $hfApiKey;
    private $db;
    private $useApi = true; // Set to false if API fails

    public function __construct($db) {
        $this->hfApiKey = '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';
        $this->db = $db;

        // Test API connection
        $this->testAPIConnection();
    }

    private function testAPIConnection() {
        try {
            // Simple test to check if API is accessible
            $testUrl = "https://api-inference.huggingface.co/models/gpt2";
            $headers = ['Authorization: Bearer ' . $this->hfApiKey];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $testUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_HEADER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // If API is not accessible, disable API calls
            if ($httpCode === 401 || $httpCode === 403) {
                error_log("Hugging Face API key may be invalid or expired. HTTP Code: " . $httpCode);
                $this->useApi = false;
            } elseif ($httpCode !== 200 && $httpCode !== 204) {
                error_log("Hugging Face API connection issue. HTTP Code: " . $httpCode);
            }
        } catch (Exception $e) {
            error_log("API Connection Test Error: " . $e->getMessage());
            $this->useApi = false;
        }
    }

    /**
     * Classify service from search query using Hugging Face
     */
    public function classifyServiceFromQuery($query) {
        if (empty($query)) return null;

        // Clean the query
        $query = strtolower(trim($query));

        // Check for direct category names first (quick match)
        $directMatch = $this->directCategoryMatch($query);
        if ($directMatch) {
            return $directMatch;
        }

        // Cache in database to avoid API calls for same queries
        $cached = $this->getCachedClassification($query);
        if ($cached) return $cached;

        // If API is disabled, use fallback immediately
        if (!$this->useApi) {
            return $this->fallbackClassify($query);
        }

        try {
            // Use a simpler model that's more likely to work
            $url = "https://api-inference.huggingface.co/models/facebook/bart-large-mnli";
            $headers = [
                'Authorization: Bearer ' . $this->hfApiKey,
                'Content-Type: application/json'
            ];

            // Get service categories from database
            $categories = $this->getServiceCategories();
            $categoryNames = array_column($categories, 'name');

            // Prepare a simpler version of the query for better matching
            $simplifiedQuery = $this->simplifyQuery($query);

            $data = [
                'inputs' => $simplifiedQuery,
                'parameters' => [
                    'candidate_labels' => $categoryNames,
                    'multi_label' => false
                ]
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing only

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                error_log("CURL Error: " . curl_error($ch));
                curl_close($ch);
                return $this->fallbackClassify($query);
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $result = json_decode($response, true);

                if (isset($result['labels']) && isset($result['scores'])) {
                    // Find highest scoring category
                    $maxScore = max($result['scores']);
                    $maxIndex = array_search($maxScore, $result['scores']);
                    $predictedCategory = $result['labels'][$maxIndex];

                    // Only accept if confidence is high enough
                    if ($maxScore > 0.3) {
                        // Find category ID
                        foreach ($categories as $category) {
                            if (strcasecmp($category['name'], $predictedCategory) === 0) {
                                $this->cacheClassification($query, $category['id']);
                                return $category['id'];
                            }
                        }
                    }
                }
            } else {
                error_log("API Error - HTTP Code: $httpCode, Response: $response");
            }

            // Fallback: Try keyword matching
            return $this->fallbackClassify($query);

        } catch (Exception $e) {
            error_log("AI Classification Error: " . $e->getMessage());
            return $this->fallbackClassify($query);
        }
    }

    /**
     * Get service categories with keywords from database
     */
    private function getServiceCategoriesWithKeywords() {
        try {
            $stmt = $this->db->query("
                SELECT id, name, ai_keywords, keywords 
                FROM categories 
                WHERE is_active = 1 
                ORDER BY name
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to fetch categories with keywords: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get common terms for each category from DB
     */
    private function getCommonTerms($categoryName) {
        try {
            $stmt = $this->db->prepare("
                SELECT ai_keywords, keywords 
                FROM categories 
                WHERE LOWER(name) = LOWER(?) 
                LIMIT 1
            ");
            $stmt->execute([$categoryName]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$category) {
                // Fallback to static mapping
                $staticTerms = [
                    'electrician' => ['electrical', 'socket', 'wire', 'light', 'power', 'switch', 'bulb', 'outlet'],
                    'plumber' => ['pipe', 'leak', 'tap', 'toilet', 'drain', 'water', 'sink', 'sewage'],
                    'carpenter' => ['wood', 'door', 'window', 'furniture', 'cabinet', 'shelf', 'frame'],
                    'cleaner' => ['clean', 'dirty', 'dust', 'sanitize', 'sweep', 'mop', 'vacuum'],
                    'painter' => ['paint', 'wall', 'color', 'brush', 'spray', 'ceiling', 'interior'],
                    'mechanic' => ['car', 'engine', 'brake', 'tire', 'oil', 'vehicle', 'repair'],
                    'gardener' => ['lawn', 'tree', 'plant', 'garden', 'grass', 'hedge', 'flower'],
                    'mason' => ['brick', 'cement', 'wall', 'concrete', 'tile', 'stone', 'plaster'],
                    'welder' => ['metal', 'weld', 'iron', 'gate', 'fence', 'steel', 'grill'],
                    'tailor' => ['sewing', 'clothes', 'alter', 'stitch', 'dress', 'hem', 'repair'],
                    'roofer' => ['roof', 'tile', 'leak', 'gutter', 'repair', 'ceiling']
                ];
                return $staticTerms[strtolower($categoryName)] ?? [];
            }

            // Parse keywords from database
            $keywords = [];
            if (!empty($category['ai_keywords'])) {
                $keywords = array_merge($keywords, array_filter(array_map('trim', explode(',', $category['ai_keywords']))));
            }
            if (!empty($category['keywords'])) {
                $keywords = array_merge($keywords, array_filter(array_map('trim', explode(',', $category['keywords']))));
            }

            return array_values(array_unique($keywords));
        } catch (Exception $e) {
            error_log("Error fetching common terms: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Simplify query for better AI matching
     */
    private function simplifyQuery($query) {
        // Remove common stop words
        $stopWords = ['i', 'need', 'want', 'looking', 'for', 'a', 'an', 'the', 'to', 'my', 'me', 'please', 'help'];
        $words = explode(' ', $query);
        $words = array_diff($words, $stopWords);

        return implode(' ', $words);
    }

    /**
     * Detect toxicity in complaint/emergency text
     */
    public function detectToxicity($text) {
        if (empty($text)) return ['is_toxic' => false, 'score' => 0];

        // If API is disabled, use simple keyword detection
        if (!$this->useApi) {
            return $this->simpleToxicityCheck($text);
        }

        try {
            // Use toxicity detection model
            $url = "https://api-inference.huggingface.co/models/unitary/toxic-bert";
            $headers = [
                'Authorization: Bearer ' . $this->hfApiKey,
                'Content-Type: application/json'
            ];

            $data = ['inputs' => $text];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                error_log("CURL Toxicity Error: " . curl_error($ch));
                curl_close($ch);
                return $this->simpleToxicityCheck($text);
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $result = json_decode($response, true);

                // Analyze toxicity scores
                $toxicityScore = 0;
                $isToxic = false;

                if (is_array($result) && isset($result[0])) {
                    foreach ($result[0] as $item) {
                        if (in_array($item['label'], ['toxic', 'obscene', 'insult', 'identity_hate', 'threat'])) {
                            $toxicityScore = max($toxicityScore, $item['score']);
                            if ($item['score'] > 0.7) {
                                $isToxic = true;
                            }
                        }
                    }
                }

                return [
                    'is_toxic' => $isToxic,
                    'score' => $toxicityScore,
                    'flagged' => $isToxic ? 'Toxic content detected' : null
                ];
            }

        } catch (Exception $e) {
            error_log("Toxicity Detection Error: " . $e->getMessage());
        }

        return $this->simpleToxicityCheck($text);
    }

    /**
     * Simple keyword-based toxicity check
     */
    private function simpleToxicityCheck($text) {
        $badWords = [
            // English bad words
            'fuck', 'shit', 'asshole', 'bitch', 'bastard', 'damn', 'hell',
            // Kinyarwanda bad words (add more as needed)
            'gahwa', 'kabiri', 'gito'
        ];

        $text = strtolower($text);
        $score = 0;
        $foundWords = [];

        foreach ($badWords as $word) {
            if (strpos($text, $word) !== false) {
                $score += 0.3;
                $foundWords[] = $word;
            }
        }

        $isToxic = $score > 0.5;

        return [
            'is_toxic' => $isToxic,
            'score' => min($score, 1.0),
            'flagged' => $isToxic ? 'Inappropriate language detected: ' . implode(', ', $foundWords) : null
        ];
    }

    /**
     * Clean or rewrite booking description
     */
    public function cleanBookingDescription($description) {
        if (empty($description) || strlen($description) < 10) {
            return $description;
        }

        // If API is disabled, use basic cleanup
        if (!$this->useApi) {
            return $this->basicTextCleanup($description);
        }

        try {
            // Use text generation model to improve clarity
            $url = "https://api-inference.huggingface.co/models/google/flan-t5-base";
            $headers = [
                'Authorization: Bearer ' . $this->hfApiKey,
                'Content-Type: application/json'
            ];

            $prompt = "Rewrite this service request to be more clear and professional: " . $description;

            $data = [
                'inputs' => $prompt,
                'parameters' => [
                    'max_length' => 200,
                    'temperature' => 0.7,
                    'do_sample' => true
                ]
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                error_log("CURL Text Clean Error: " . curl_error($ch));
                curl_close($ch);
                return $this->basicTextCleanup($description);
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $result = json_decode($response, true);

                if (isset($result[0]['generated_text'])) {
                    $cleaned = $result[0]['generated_text'];
                    // Remove the prompt part if present
                    $cleaned = str_replace($prompt, '', $cleaned);
                    $cleaned = trim($cleaned);

                    if (!empty($cleaned) && strlen($cleaned) > 20) {
                        return $cleaned;
                    }
                }
            }

        } catch (Exception $e) {
            error_log("Text Cleaning Error: " . $e->getMessage());
        }

        // Fallback: Basic cleaning
        return $this->basicTextCleanup($description);
    }

    /**
     * Fallback classification using keyword matching from database
     */
    private function fallbackClassify($query) {
        try {
            $categories = $this->getServiceCategoriesWithKeywords();
        } catch (Exception $e) {
            error_log("Failed to fetch categories for fallback: " . $e->getMessage());
            return null;
        }

        $query = strtolower($query);
        $bestMatch = null;
        $bestScore = 0;

        foreach ($categories as $category) {
            $score = 0;
            $categoryName = strtolower($category['name']);

            // Direct name match gets bonus
            if (strpos($query, $categoryName) !== false) {
                $score += 5;
            }

            // Check ai_keywords
            if (!empty($category['ai_keywords'])) {
                $keywords = array_filter(array_map('trim', explode(',', $category['ai_keywords'])));
                foreach ($keywords as $keyword) {
                    if (strpos($query, strtolower($keyword)) !== false) {
                        $score += 2;
                    }
                }
            }

            // Check keywords
            if (!empty($category['keywords'])) {
                $keywords = array_filter(array_map('trim', explode(',', $category['keywords'])));
                foreach ($keywords as $keyword) {
                    if (strpos($query, strtolower($keyword)) !== false) {
                        $score += 1;
                    }
                }
            }

            // Track best match
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = [
                    'id' => $category['id'],
                    'name' => $category['name'],
                    'score' => $score
                ];
            }
        }

        // Only return if we have a decent match (score >= 2)
        return $bestScore >= 2 ? $bestMatch : null;
    }

    /**
     * Direct category name matching from database
     */
    private function directCategoryMatch($query) {
        try {
            $categories = $this->getServiceCategoriesWithKeywords();

            foreach ($categories as $category) {
                $catName = strtolower($category['name']);
                if ($query === $catName || strpos($query, $catName) === 0) {
                    return [
                        'id' => $category['id'],
                        'name' => $category['name'],
                        'confidence' => 0.95
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("Error in directCategoryMatch: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Get service categories from database
     */
    private function getServiceCategories() {
        try {
            $stmt = $this->db->query("
                SELECT id, name 
                FROM categories 
                WHERE is_active = 1 
                ORDER BY name
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to fetch service categories: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Cache classification result
     */
    private function cacheClassification($query, $categoryId) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO ai_query_cache (query_hash, query, category_id, created_at) 
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE created_at = NOW()
            ");
            $stmt->execute([md5($query), $query, $categoryId]);
        } catch (Exception $e) {
            error_log("Failed to cache classification: " . $e->getMessage());
        }
    }

    /**
     * Get cached classification
     */
    private function getCachedClassification($query) {
        try {
            $stmt = $this->db->prepare("
                SELECT category_id 
                FROM ai_query_cache 
                WHERE query_hash = ? 
                LIMIT 1
            ");
            $stmt->execute([md5($query)]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $catStmt = $this->db->prepare("SELECT id, name FROM categories WHERE id = ? LIMIT 1");
                $catStmt->execute([$result['category_id']]);
                return $catStmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            error_log("Failed to get cached classification: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Log AI usage
     */
    public function logUsage($userId, $actionType, $inputText, $outputData, $processingTime) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO ai_usage_logs (user_id, action_type, input_text, output_data, processing_time, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            return $stmt->execute([$userId, $actionType, substr($inputText, 0, 1000), json_encode($outputData), $processingTime]);
        } catch (Exception $e) {
            error_log("AI Usage Log Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Improve professional bio with AI
     */
    public function improveProfessionalBio($bio, $profession, $experienceYears) {
        if (empty($bio) || strlen($bio) < 20) {
            return $bio;
        }

        try {
            $url = "https://api-inference.huggingface.co/models/google/flan-t5-base";
            $headers = [
                'Authorization: Bearer ' . $this->hfApiKey,
                'Content-Type: application/json'
            ];

            $prompt = "Improve this professional bio for a $profession with $experienceYears years of experience. Make it more professional, engaging, and client-focused: $bio";

            $data = [
                'inputs' => $prompt,
                'parameters' => [
                    'max_length' => 300,
                    'temperature' => 0.7,
                    'do_sample' => true
                ]
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                error_log("Bio improvement CURL error: " . curl_error($ch));
                curl_close($ch);
                return $this->enhanceBioManually($bio, $profession, $experienceYears);
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $result = json_decode($response, true);

                if (isset($result[0]['generated_text'])) {
                    $improved = $result[0]['generated_text'];
                    $improved = str_replace($prompt, '', $improved);
                    $improved = trim($improved);

                    if (!empty($improved) && strlen($improved) > 50) {
                        return $improved;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Bio improvement error: " . $e->getMessage());
        }

        return $this->enhanceBioManually($bio, $profession, $experienceYears);
    }

    /**
     * Enhance bio manually as fallback
     */
    private function enhanceBioManually($bio, $profession, $experienceYears) {
        $enhancements = [];

        // Add profession if not mentioned
        if (!empty($profession) && stripos($bio, $profession) === false) {
            $enhancements[] = "Professional $profession";
        }

        // Add experience if not mentioned
        if ($experienceYears > 0 && stripos($bio, 'year') === false && stripos($bio, 'experience') === false) {
            $enhancements[] = "with $experienceYears years of experience";
        }

        // Add professional qualities if missing
        $qualities = ['reliable', 'professional', 'skilled', 'certified', 'licensed'];
        foreach ($qualities as $quality) {
            if (stripos($bio, $quality) === false) {
                $enhancements[] = $quality;
            }
        }

        if (!empty($enhancements)) {
            $enhanced = trim($bio);
            if (!preg_match('/[.!?]$/', $enhanced)) {
                $enhanced .= '.';
            }
            $enhanced .= ' ' . implode(', ', array_slice($enhancements, 0, 3)) . '.';
            return $enhanced;
        }

        return $bio;
    }

    /**
     * Suggest profession titles based on current profession and bio
     */
    public function suggestProfessionTitles($currentProfession, $bio) {
        if (empty($currentProfession)) {
            return [];
        }

        $suggestions = [];

        // Standard title variations
        $variations = [
            "Senior $currentProfession",
            "Professional $currentProfession",
            "Certified $currentProfession",
            "Licensed $currentProfession",
            "Expert $currentProfession",
            "Master $currentProfession"
        ];

        // Add based on bio content
        $bioLower = strtolower($bio);

        if (strpos($bioLower, 'senior') !== false) {
            $suggestions[] = "Senior $currentProfession";
        }

        if (strpos($bioLower, 'expert') !== false || strpos($bioLower, 'specialist') !== false) {
            $suggestions[] = "Expert $currentProfession";
            $suggestions[] = "Specialist $currentProfession";
        }

        if (strpos($bioLower, 'certified') !== false || strpos($bioLower, 'licensed') !== false) {
            $suggestions[] = "Certified $currentProfession";
            $suggestions[] = "Licensed $currentProfession";
        }

        // Merge with variations and remove duplicates
        $suggestions = array_merge($variations, $suggestions);
        $suggestions = array_unique($suggestions);

        return array_slice($suggestions, 0, 6); // Return top 6 suggestions
    }

    /**
     * Suggest categories based on profession and bio
     */
    public function suggestCategoriesFromProfession($profession, $bio) {
        if (empty($profession)) {
            return [];
        }

        // Get all categories with keywords
        $categories = $this->getServiceCategoriesWithKeywords();
        $professionLower = strtolower($profession);
        $bioLower = strtolower($bio);

        $suggestedCategoryIds = [];
        $matchedKeywords = [];

        foreach ($categories as $category) {
            $score = 0;

            // Check if profession matches category name
            if (stripos($profession, $category['name']) !== false) {
                $score += 10;
                $matchedKeywords[] = $category['name'];
            }

            // Check category keywords
            if (!empty($category['keywords'])) {
                $keywords = explode(',', strtolower($category['keywords']));
                foreach ($keywords as $keyword) {
                    $keyword = trim($keyword);
                    if (!empty($keyword)) {
                        if (strpos($professionLower, $keyword) !== false) {
                            $score += 5;
                            $matchedKeywords[] = $keyword;
                        }
                        if (strpos($bioLower, $keyword) !== false) {
                            $score += 3;
                            $matchedKeywords[] = $keyword;
                        }
                    }
                }
            }

            // Check for common associations
            $associations = $this->getCategoryAssociations($category['name']);
            foreach ($associations as $association) {
                if (strpos($professionLower, $association) !== false || strpos($bioLower, $association) !== false) {
                    $score += 2;
                    $matchedKeywords[] = $association;
                }
            }

            if ($score >= 5) { // Minimum threshold
                $suggestedCategoryIds[] = $category['id'];
            }
        }

        // Cache the suggestions
        $this->cacheCategorySuggestions($profession, $bio, $suggestedCategoryIds, $matchedKeywords);

        return array_unique($suggestedCategoryIds);
    }

    /**
     * Check if a category is relevant for a provider
     */
    public function isCategoryRelevant($categoryId, $profession, $bio) {
        $suggested = $this->suggestCategoriesFromProfession($profession, $bio);
        return in_array($categoryId, $suggested);
    }

    /**
     * Get category associations for better matching
     */
    private function getCategoryAssociations($categoryName) {
        $associations = [
            'Electrician' => ['wiring', 'electrical', 'lights', 'power', 'circuit', 'outlet', 'switch'],
            'Plumber' => ['pipe', 'water', 'leak', 'tap', 'toilet', 'drain', 'shower', 'sink'],
            'Cleaner' => ['clean', 'window', 'floor', 'dust', 'mop', 'house', 'office'],
            'Mechanic' => ['car', 'vehicle', 'engine', 'brake', 'tire', 'oil', 'repair'],
            'Carpenter' => ['wood', 'furniture', 'table', 'chair', 'door', 'window', 'shelf'],
            'Painter' => ['paint', 'wall', 'color', 'brush', 'roller', 'ceiling'],
            'Gardener' => ['garden', 'lawn', 'plant', 'tree', 'flower', 'grass'],
            'Construction' => ['build', 'house', 'room', 'wall', 'roof', 'foundation'],
            'Driver' => ['drive', 'car', 'vehicle', 'transport', 'ride', 'taxi']
        ];

        return $associations[$categoryName] ?? [];
    }

    /**
     * Cache category suggestions
     */
    private function cacheCategorySuggestions($profession, $bio, $categoryIds, $keywords) {
        try {
            $cacheKey = md5($profession . $bio);
            $cacheData = json_encode([
                'category_ids' => $categoryIds,
                'keywords' => $keywords,
                'timestamp' => time()
            ]);

            $stmt = $this->db->prepare("
                INSERT INTO ai_category_suggestions (cache_key, profession, bio_hash, suggestions, matched_keywords, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    suggestions = VALUES(suggestions),
                    matched_keywords = VALUES(matched_keywords),
                    created_at = NOW()
            ");

            $stmt->execute([
                $cacheKey,
                $profession,
                md5($bio),
                json_encode($categoryIds),
                json_encode($keywords)
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Cache category suggestions error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Analyze profile quality and generate suggestions
     */
    public function analyzeProfileQuality($provider, $categories = []) {
        $score = 0;
        $maxScore = 100;
        $suggestions = [];

        // Check profile completeness (30 points)
        if (!empty($provider['full_name'])) $score += 5;
        if (!empty($provider['phone'])) $score += 5;
        if (!empty($provider['profile_image'])) $score += 5;
        if (!empty($provider['profession'])) $score += 5;
        if (!empty($provider['bio']) && strlen($provider['bio']) > 50) $score += 10;
        else $suggestions[] = [
            'category' => 'Bio',
            'message' => 'Add a detailed bio (minimum 50 characters) to attract more clients',
            'action' => 'document.getElementById(\'bioTextarea\').focus()'
        ];

        // Check location information (20 points)
        if (!empty($provider['location'])) $score += 10;
        else $suggestions[] = [
            'category' => 'Location',
            'message' => 'Add your service location to help clients find you',
            'action' => 'document.querySelector(\'input[name="location"]\').focus()'
        ];

        if (!empty($provider['district']) && !empty($provider['sector'])) $score += 10;
        else $suggestions[] = [
            'category' => 'Location Details',
            'message' => 'Add district and sector for better local matching',
            'action' => null
        ];

        // Check professional details (30 points)
        if (!empty($provider['experience_years']) && $provider['experience_years'] > 0) $score += 10;
        else $suggestions[] = [
            'category' => 'Experience',
            'message' => 'Add years of experience to build credibility',
            'action' => 'document.querySelector(\'input[name="experience_years"]\').focus()'
        ];

        if (!empty($provider['hourly_rate']) && $provider['hourly_rate'] > 0) $score += 10;
        else $suggestions[] = [
            'category' => 'Pricing',
            'message' => 'Set an hourly rate to help clients budget',
            'action' => 'document.querySelector(\'input[name="hourly_rate"]\').focus()'
        ];

        if (!empty($provider['verification_level']) && $provider['verification_level'] !== 'none') $score += 10;

        // Check categories (20 points)
        if (!empty($categories)) {
            $score += min(20, count($categories) * 5);
        } else {
            $suggestions[] = [
                'category' => 'Services',
                'message' => 'Add service categories to appear in searches',
                'action' => 'document.querySelector(\'.categories-grid\').scrollIntoView()'
            ];
        }

        return [
            'score' => $score,
            'percentage' => round(($score / $maxScore) * 100),
            'suggestions' => $suggestions,
            'missing_fields' => $this->getMissingFields($provider, $categories)
        ];
    }

    /**
     * Get missing required fields
     */
    private function getMissingFields($provider, $categories) {
        $missing = [];

        if (empty($provider['full_name'])) $missing[] = 'Full Name';
        if (empty($provider['phone'])) $missing[] = 'Phone Number';
        if (empty($provider['profession'])) $missing[] = 'Profession';
        if (empty($provider['bio']) || strlen($provider['bio']) < 50) $missing[] = 'Detailed Bio';
        if (empty($provider['location'])) $missing[] = 'Location';
        if (empty($categories)) $missing[] = 'Service Categories';
        if (empty($provider['experience_years'])) $missing[] = 'Years of Experience';
        if (empty($provider['hourly_rate'])) $missing[] = 'Hourly Rate';

        return $missing;
    }
}
?>