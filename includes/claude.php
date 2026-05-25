<?php
// NutroApp - Anthropic API Caller + Price Helpers
// PHP 5.6 compatible

function callClaudeRaw($prompt, $max_tokens = 1500) {
    $url  = 'https://api.anthropic.com/v1/messages';
    $body = json_encode(array(
        'model'      => 'claude-sonnet-4-6',
        'max_tokens' => $max_tokens,
        'messages'   => array(array('role' => 'user', 'content' => $prompt))
    ));
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'x-api-key: ' . ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01'
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false) return array('error' => 'Curl error: ' . curl_error($ch));
    $data = json_decode($response, true);
    if ($http_code !== 200) {
        $err_msg = isset($data['error']['message']) ? $data['error']['message'] : $response;
        return array('error' => 'API HTTP ' . $http_code . ': ' . substr($err_msg, 0, 200));
    }
    if (!isset($data['content'][0]['text'])) {
        return array('error' => 'No content in response. stop_reason=' . (isset($data['stop_reason'])?$data['stop_reason']:'unknown') . ' tokens=' . (isset($data['usage']['output_tokens'])?$data['usage']['output_tokens']:'?'));
    }
    $text = '';
    foreach ($data['content'] as $block) {
        if ($block['type'] === 'text') $text .= $block['text'];
    }
    // Strip markdown fences
    $text = preg_replace('/```json\s*/i', '', $text);
    $text = preg_replace('/```\s*/', '', $text);
    $text = trim($text);

    // Extract JSON object — find first { and last }
    $start = strpos($text, '{');
    $end   = strrpos($text, '}');
    if ($start !== false && $end !== false && $end > $start) {
        $text = substr($text, $start, $end - $start + 1);
    }

    $parsed = json_decode($text, true);

    // If still failing, try to fix common issues
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Remove trailing commas before } or ]
        $text = preg_replace('/,\s*([}\]])/m', '$1', $text);
        // Fix unescaped newlines inside strings
        $text = preg_replace_callback('/"((?:[^"\\]|\\.)*)"/s', function($m) {
            return '"' . str_replace(array("\n", "\r", "\t"), array(' ', ' ', ' '), $m[1]) . '"';
        }, $text);
        $parsed = json_decode($text, true);
    }

    if (json_last_error() !== JSON_ERROR_NONE) {
        $stop = isset($data['stop_reason']) ? $data['stop_reason'] : 'unknown';
        $out_tokens = isset($data['usage']['output_tokens']) ? $data['usage']['output_tokens'] : '?';
        $max_t = isset($max_tokens) ? $max_tokens : '?';
        return array('error' => 'JSON error: ' . json_last_error_msg() . ' | stop=' . $stop . ' | out_tokens=' . $out_tokens . '/' . $max_t . ' | raw: ' . substr($text, 0, 200));
    }
    return $parsed;
}

function callClaude($prompt) {
    // Legacy wrapper — kept for compatibility
    return callClaudeRaw($prompt, 1500);
}

function buildDayPrompt($base_info, $day_num, $prev_meals) {
    $prev_str = !empty($prev_meals) ? implode(',', $prev_meals) : '';

    $p  = '=== MANDATORY RULES — VIOLATION IS NOT ACCEPTABLE ===' . "\n";
    $p .= 'You are generating Day ' . $day_num . ' of a personalized Georgian meal plan.' . "\n";
    $p .= '' . "\n";
    $p .= '1. OUTPUT FORMAT: Return ONLY raw JSON. No markdown, no explanation, no text outside {}.' . "\n";
    $p .= '2. EXACTLY 4 MEALS — no more, no less:' . "\n";
    $p .= '   - sauzme   meal_time=08:00  (breakfast)' . "\n";
    $p .= '   - branchi  meal_time=11:00  (brunch/snack)' . "\n";
    $p .= '   - sadili   meal_time=14:00  (lunch)' . "\n";
    $p .= '   - vaxshami meal_time=19:00  (dinner)' . "\n";
    $p .= '3. MEAL NAMES: Georgian language, adjective+noun order. WRONG: "ჩირი ქლიავის" RIGHT: "ქლიავის ჩირი".' . "\n";
    $p .= '4. MEAL FORMULAS — follow exactly:' . "\n";
    $p .= '   sauzme:   protein(egg/cottage_cheese) + carb(rye_bread/oats) + fresh_veg_or_fruit' . "\n";
    $p .= '   branchi:  light only — fruit, nuts, yogurt, or small protein' . "\n";
    $p .= '   sadili:   meat(chicken/veal) + carb(rice/potato) + vegetables(pepper/cabbage/carrot) + olive_oil+lemon' . "\n";
    $p .= '   vaxshami: protein only(beef/tuna/omelette) + green_vegetables. FORBIDDEN: bread, rice, potato, beans, buckwheat' . "\n";

    if ($prev_str) {
        $p .= '5. ZERO REPETITION — CRITICAL RULE:' . "\n";
        $p .= '   These exact meal names were already used: ' . $prev_str . "\n";
        $p .= '   DO NOT use the same main ingredient twice across the plan.' . "\n";
        $p .= '   DO NOT use chicken in 2 different days. DO NOT use eggs in 2 sauzme.' . "\n";
        $p .= '   Protein rotation REQUIRED: day1=chicken, day2=beef/veal, day3=fish/tuna, day4=eggs/cottage_cheese, day5=turkey/shrimp.' . "\n";
        $p .= '   Carb rotation REQUIRED: day1=rice, day2=potato, day3=oats/bread, day4=buckwheat, day5=lentils.' . "\n";
        $p .= '   Vegetable rotation REQUIRED: use different vegetables each day.' . "\n";
    }

    $p .= '6. ONLY use ingredients from the provided list.' . "\n";
    $p .= '7. Add hack_ka: one practical Georgian cooking tip (max 15 words).' . "\n";
    $p .= '8. CALORIES must match target within ±50kcal per meal.' . "\n";
    $p .= '===================================================' . "\n\n";
    $p .= $base_info;
    $p .= '\nReturn this exact JSON structure:' . "\n";
    $p .= '{"day":' . $day_num . ',"total_calories":N,"estimated_cost_gel":N,"meals":[{"type":"sauzme","name":"GEORGIAN_NAME","portion":"Xg","calories":N,"protein_g":N,"carbs_g":N,"fat_g":N,"cost_gel":N,"best_store":"S","hack_ka":"TIP","ingredient_list":[{"name":"N","amount":"Xg","price_gel":N,"store":"S"}],"meal_time":"08:00"}]}';
    return $p;
}

// ── Store helpers ────────────────────────────────────────────────────────────

function getActiveStores() {
    static $stores = null;
    if ($stores === null) {
        $db   = getDB();
        $stmt = $db->query('SELECT * FROM stores WHERE is_active=1 ORDER BY sort_order');
        $stores = $stmt->fetchAll();
    }
    return $stores;
}

// ── Price helpers ─────────────────────────────────────────────────────────────

function getPriceTable() {
    $db   = getDB();
    $stmt = $db->query('SELECT * FROM ingredient_prices ORDER BY name_ka');
    return $stmt->fetchAll();
}

function getIngredientPrices($ingredient_id) {
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT s.slug, s.name, isp.price, isp.ai_estimated
         FROM ingredient_store_prices isp
         JOIN stores s ON s.id = isp.store_id
         WHERE isp.ingredient_id = ? AND s.is_active = 1 AND isp.price IS NOT NULL
         ORDER BY s.sort_order'
    );
    $stmt->execute(array($ingredient_id));
    $rows   = $stmt->fetchAll();
    $result = array();
    foreach ($rows as $r) {
        $result[$r['name']] = (float)$r['price'];
    }
    return $result;
}

function getMinPrice($row) {
    // Try new dynamic table first
    if (isset($row['id'])) {
        $prices = getIngredientPrices($row['id']);
        if (!empty($prices)) {
            $min_price = null;
            $min_store = 'ბაზარი';
            foreach ($prices as $store => $price) {
                if ($min_price === null || $price < $min_price) {
                    $min_price = $price;
                    $min_store = $store;
                }
            }
            return array('price' => $min_price, 'store' => $min_store);
        }
    }
    // Fallback to legacy columns
    $stores = getActiveStores();
    $col_map = array(
        'agrohub'   => 'agrohub_price',
        'nabiji'    => 'nabiji_price',
        'carrefour' => 'carrefour_price',
        'goodwill'  => 'goodwill_price',
        'spar'      => 'spar_price',
    );
    $min_price = null;
    $min_store = 'ბაზარი';
    foreach ($stores as $s) {
        $col = isset($col_map[$s['slug']]) ? $col_map[$s['slug']] : null;
        $price = ($col && isset($row[$col])) ? $row[$col] : null;
        if ($price !== null && ($min_price === null || (float)$price < (float)$min_price)) {
            $min_price = (float)$price;
            $min_store = $s['name'];
        }
    }
    return array('price' => $min_price, 'store' => $min_store);
}

function buildPriceTableText() {
    // Compact: cheapest price+store only, saves tokens
    $db      = getDB();
    $ingreds = getPriceTable();
    $parts   = array();
    foreach ($ingreds as $ing) {
        $cheapest = getMinPrice($ing);
        if ($cheapest['price']) {
            $parts[] = $ing['name_ka'] . ':' . $cheapest['price'] . '/' . $ing['unit'] . '(' . $cheapest['store'] . ')';
        }
    }
    return implode(', ', $parts);
}


function buildDietPrompt($profile) {
    // Returns base_info string used in per-day prompts
    $goal         = $profile['goal'];
    $activity     = $profile['activity_level'];
    $budget       = $profile['budget'];
    $age          = (int)$profile['age'];
    $gender       = $profile['gender'];
    $weight       = (float)$profile['weight_kg'];
    $height       = (float)$profile['height_cm'];
    $allergies    = !empty($profile['allergies'])        ? $profile['allergies']        : 'none';
    $health_notes = !empty($profile['health_notes'])     ? $profile['health_notes']     : 'none';
    $target_wt    = !empty($profile['target_weight_kg']) ? (float)$profile['target_weight_kg'] : round($weight * 0.92, 1);
    $loss_kg      = max(0, round($weight - $target_wt, 1));
    $weeks        = max(4, round($loss_kg / 0.5));
    $bmi          = round($weight / (($height/100) * ($height/100)), 1);

    // Single combined list: name=price(store) — avoids duplication, saves tokens
    $db         = getDB();

    // ── Smart adaptation: load user food preferences ──────────────────────────
    $prefs = array();
    try {
        $pref_stmt = $db->prepare('SELECT preference_type, item FROM user_food_prefs WHERE user_id=?');
        $pref_stmt->execute(array(isset($profile['user_id']) ? $profile['user_id'] : 0));
        foreach ($pref_stmt->fetchAll() as $p) {
            $prefs[$p['preference_type']][] = $p['item'];
        }
    } catch(Exception $e) {}

    $price_rows = $db->query('SELECT * FROM ingredient_prices ORDER BY name_ka')->fetchAll();
    $ing_prices = array();
    foreach ($price_rows as $r) {
        $cheapest = getMinPrice($r);
        if ($cheapest['price']) {
            $ing_prices[] = $r['name_ka'] . '=' . $cheapest['price'] . '(' . $cheapest['store'] . ')';
        }
    }
    $ing_price_str = implode(',', $ing_prices);

    if ($goal === 'Weight Loss') {
        $goal_line = "WL:lose {$loss_kg}kg/{$weeks}wk.FORBIDDEN:ჩახოხბილი,ლობიანი,მწვადი,ხინკალი,საცივი,შემწვარი.boiled/baked/grilled only.max12gfat.veg every meal.";
    } elseif ($goal === 'Muscle Gain') {
        $prot = round($weight * 2.2);
        $goal_line = "MG:+12%cal.protein>={$prot}g/day.protein every meal.";
    } else {
        $goal_line = "M:balanced,moderate.";
    }

    if ($budget === 'Low') {
        $budget_line = "BUDGET:LOW(max15₾/day).use cheapest:კვერცხი,ლობიო,ბრინჯი,კარტოფილი,კომბოსტო.NO salmon/walnuts/chicken.";
    } elseif ($budget === 'High') {
        $budget_line = "BUDGET:HIGH(20-35₾/day).premium:ორაგული,ქათამი,ნიგოზი,მაწონი,ახალი ბოსტნეული.";
    } else {
        $budget_line = "BUDGET:MEDIUM(15-25₾/day).chicken,eggs,matsoni,beans,veg.";
    }

    // TDEE calculation for context
    if ($gender === 'male') {
        $tdee_est = round((10*$weight + 6.25*$height - 5*$age + 5) * 1.4);
    } else {
        $tdee_est = round((10*$weight + 6.25*$height - 5*$age - 161) * 1.4);
    }
    if ($goal === 'Weight Loss')  $target_cal = round($tdee_est * 0.85);
    elseif ($goal === 'Muscle Gain') $target_cal = round($tdee_est * 1.12);
    else $target_cal = $tdee_est;
    $prot_g  = round($weight * 1.8);
    $fat_g   = round($target_cal * 0.28 / 9);
    $carb_g  = round(($target_cal - $prot_g*4 - $fat_g*9) / 4);

    $info  = "Patient:{$age}y {$gender},{$weight}kg,{$height}cm,BMI:{$bmi}.\n";
    $info .= "Goal:{$goal_line}\n";
    $info .= "{$budget_line}\n";
    $info .= "Targets:~{$target_cal}kcal/day,P:{$prot_g}g,C:{$carb_g}g,F:{$fat_g}g.\n";
    $info .= "Activity:{$activity}|Allergies:{$allergies}|Health:{$health_notes}\n";
    $info .= "Ingredients(name=price(store)):{$ing_price_str}\n";
    if (!empty($prefs['avoid'])) {
        $avoid = implode(',', $prefs['avoid']);
        $info .= "NEVER use these ingredients(user dislikes):{$avoid}\n";
    }
    if (!empty($prefs['prefer'])) {
        $prefer = implode(',', $prefs['prefer']);
        $info .= "User prefers these ingredients(use more often):{$prefer}\n";
    }
    // Budget optimizer
    if (!empty($profile['weekly_budget']) && (float)$profile['weekly_budget'] > 0) {
        $daily_budget = round((float)$profile['weekly_budget'] / 7, 2);
        $info .= "STRICT daily grocery budget:{$daily_budget}gel. Optimize ingredient costs.\n";
    }

    $info .= "CALORIE TARGETS(mandatory): sauzme:300-400kcal, branchi:200-300kcal, sadili:400-550kcal, vaxshami:280-380kcal. Max 5 ingredients per meal. Use ONLY ingredients from the list above.\n";
    $info .= "VARIETY: Each day COMPLETELY different meals. Rotate proteins(chicken/beef/fish/eggs/cottage_cheese). Rotate carbs(rice/potato/bread/oats). Never same dish twice.\n";
    $info .= "MEAL FORMULAS (follow strictly):\n";
    $info .= "sauzme: 1)protein(egg or cottage cheese) + 2)small carb(rye bread or oats) + 3)fresh veg/fruit(tomato,apple). Tip: fry tomato first then add egg.\n";
    $info .= "sadili: 1)meat(chicken or veal) + 2)carb(rice or potato) + 3)lots of vegetables(pepper,cabbage,carrot) + olive oil and lemon. Tip: sear meat on high heat for crust.\n";
    $info .= "vaxshami: LIGHT ONLY - protein(beef/tuna/omelette) + green vegetables(parsley,cabbage,herbs,garlic,lemon). NO bread,rice,buckwheat,beans at dinner.\n";
    $info .= "branchi: light snack - fruit, nuts, yogurt, or small protein.\n";

    return $info;
}

function generateDayByDay($profile, $job_id, $db) {
    $days       = (int)$profile['days'];
    $base       = buildDietPrompt($profile);
    $used_meals = array();

    $weight = (float)$profile['weight_kg'];
    $height = (float)$profile['height_cm'];
    $age    = (int)$profile['age'];
    $gender = $profile['gender'];
    $goal   = $profile['goal'];

    if ($gender === 'male') $tdee = round((10*$weight+6.25*$height-5*$age+5)*1.4);
    else                    $tdee = round((10*$weight+6.25*$height-5*$age-161)*1.4);

    if ($goal === 'Weight Loss')     $target_cal = round($tdee*0.85);
    elseif ($goal === 'Muscle Gain') $target_cal = round($tdee*1.12);
    else                             $target_cal = $tdee;

    $protein_g = round($weight*1.8);
    $fat_g     = round($target_cal*0.28/9);
    $carb_g    = round(($target_cal-$protein_g*4-$fat_g*9)/4);

    // Create plan record immediately
    $db->exec("SET NAMES utf8mb4");
    $title = date('d/m/Y') . ' - ' . $goal . ' (' . $days . ' დღე)';
    $db->prepare(
        'INSERT INTO diet_plans (user_id, title, days, goal, budget, target_calories, tdee, protein_g, carbs_g, fat_g, raw_json, created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute(array(
        (int)$profile['user_id'] ?? 0,
        $title, $days, $goal,
        isset($profile['budget']) ? $profile['budget'] : 'Medium',
        $target_cal, $tdee, $protein_g, $carb_g, $fat_g,
        json_encode(array('days'=>array())), time()
    ));
    $plan_id = $db->lastInsertId();

    // Save plan_id to job so frontend can start showing partial results
    $db->prepare('UPDATE generate_jobs SET plan_id=?, updated_at=? WHERE job_id=?')
       ->execute(array($plan_id, time(), $job_id));

    $all_days   = array();
    $daily_costs = array();

    for ($d = 1; $d <= $days; $d++) {
        $prompt = buildDayPrompt($base, $d, $used_meals);
        $result = callClaudeRaw($prompt, 2500);

        if (isset($result['error'])) {
            return array('error' => $result['error']);
        }
        if (!isset($result['meals']) || !is_array($result['meals'])) {
            return array('error' => 'Invalid day ' . $d . ' response');
        }

        // Save day to DB immediately
        $day_cost = isset($result['estimated_cost_gel']) ? (float)$result['estimated_cost_gel'] : 0;
        $day_cal  = isset($result['total_calories'])     ? (int)$result['total_calories']        : $target_cal;
        $daily_costs[] = $day_cost;

        $db->prepare('INSERT INTO plan_days (plan_id, day_number, total_calories, estimated_cost_gel) VALUES (?,?,?,?)')
           ->execute(array($plan_id, $d, $day_cal, $day_cost));
        $day_id = $db->lastInsertId();

        foreach ($result['meals'] as $meal) {
            $ingredients_str = '';
            if (!empty($meal['ingredient_list']) && is_array($meal['ingredient_list'])) {
                $names = array();
                foreach ($meal['ingredient_list'] as $ing) {
                    if (!empty($ing['name'])) $names[] = $ing['name'] . (!empty($ing['amount']) ? ' '.$ing['amount'] : '');
                }
                $ingredients_str = implode(', ', $names);
            } elseif (!empty($meal['ingredients'])) {
                $ingredients_str = $meal['ingredients'];
            }
            if (empty($ingredients_str)) $ingredients_str = isset($meal['name']) ? $meal['name'] : '';

            $db->prepare(
                'INSERT INTO plan_meals (day_id, meal_type, name, ingredients, portion, calories, meal_time, hack_ka)
                 VALUES (?,?,?,?,?,?,?,?)'
            )->execute(array(
                $day_id,
                isset($meal['type'])      ? $meal['type']                           : 'კვება',
                isset($meal['name'])      ? $meal['name']                           : '',
                $ingredients_str,
                isset($meal['portion'])   ? mb_substr($meal['portion'], 0, 50)      : '',
                isset($meal['calories'])  ? (int)$meal['calories']                  : 0,
                isset($meal['meal_time']) ? $meal['meal_time']                      : null,
                isset($meal['hack_ka'])   ? mb_substr($meal['hack_ka'], 0, 200)     : null,
            ));

            if (!empty($meal['name'])) {
                $used_meals[] = $meal['name'];
            }
            // Track ALL ingredients to prevent repetition
            if (!empty($meal['ingredient_list']) && is_array($meal['ingredient_list'])) {
                foreach ($meal['ingredient_list'] as $il) {
                    if (!empty($il['name'])) $used_meals[] = $il['name'];
                }
            } elseif (!empty($meal['ingredients'])) {
                foreach (explode(',', $meal['ingredients']) as $ing) {
                    $trimmed = trim($ing);
                    if ($trimmed) $used_meals[] = $trimmed;
                }
            }
        }

        // Update raw_json incrementally + mark day complete
        $result['day'] = $d;
        $all_days[] = $result;

        $raw = array(
            'tdee'                     => $tdee,
            'target_calories'          => $target_cal,
            'protein_g'                => $protein_g,
            'carbs_g'                  => $carb_g,
            'fat_g'                    => $fat_g,
            'estimated_daily_cost_gel' => count($daily_costs) ? round(array_sum($daily_costs)/count($daily_costs),2) : 0,
            'days'                     => $all_days,
        );
        $db->prepare('UPDATE diet_plans SET raw_json=?, updated_at=? WHERE id=?')
           ->execute(array(json_encode($raw, JSON_UNESCAPED_UNICODE), time(), $plan_id));

        // Signal progress to polling
        $db->prepare('UPDATE generate_jobs SET days_done=?, updated_at=? WHERE job_id=?')
           ->execute(array($d, time(), $job_id));
    }

    // Mark complete
    $db->prepare('UPDATE diet_plans SET updated_at=? WHERE id=?')->execute(array(time(), $plan_id));
    return array('plan_id' => $plan_id);
}

function generateFullPlan($profile) {
    $days     = (int)$profile['days'];
    $base     = buildDietPrompt($profile);
    $all_days = array();
    $used_meals = array();

    // Calculate TDEE for final plan object
    $weight = (float)$profile['weight_kg'];
    $height = (float)$profile['height_cm'];
    $age    = (int)$profile['age'];
    $gender = $profile['gender'];
    $goal   = $profile['goal'];
    if ($gender === 'male') {
        $tdee = round((10*$weight + 6.25*$height - 5*$age + 5) * 1.4);
    } else {
        $tdee = round((10*$weight + 6.25*$height - 5*$age - 161) * 1.4);
    }
    if ($goal === 'Weight Loss')     $target_cal = round($tdee * 0.85);
    elseif ($goal === 'Muscle Gain') $target_cal = round($tdee * 1.12);
    else                              $target_cal = $tdee;

    for ($d = 1; $d <= $days; $d++) {
        $prompt  = buildDayPrompt($base, $d, $used_meals);
        $result  = callClaudeRaw($prompt, 2500);

        if (isset($result['error'])) {
            return $result; // propagate error
        }

        // Validate day structure
        if (!isset($result['day']) || !isset($result['meals']) || !is_array($result['meals'])) {
            return array('error' => 'Invalid day structure for day ' . $d . '. Got: ' . json_encode($result));
        }

        $all_days[] = $result;

        // Track meal names to avoid repetition
        foreach ($result['meals'] as $meal) {
            if (!empty($meal['name'])) {
                $used_meals[] = $meal['name'];
            }
        }
    }

    $protein_g = round($weight * 1.8);
    $fat_g     = round($target_cal * 0.28 / 9);
    $carb_g    = round(($target_cal - $protein_g*4 - $fat_g*9) / 4);

    // Get cheapest store overall
    $store_counts = array();
    foreach ($all_days as $day) {
        foreach ($day['meals'] as $meal) {
            if (!empty($meal['best_store'])) {
                $s = $meal['best_store'];
                $store_counts[$s] = isset($store_counts[$s]) ? $store_counts[$s]+1 : 1;
            }
        }
    }
    $cheapest_store = !empty($store_counts) ? array_search(max($store_counts), $store_counts) : 'Carrefour';

    $daily_costs = array_map(function($d){ return isset($d['estimated_cost_gel']) ? (float)$d['estimated_cost_gel'] : 0; }, $all_days);
    $avg_cost    = count($daily_costs) > 0 ? round(array_sum($daily_costs)/count($daily_costs), 2) : 0;

    return array(
        'tdee'                    => $tdee,
        'target_calories'         => $target_cal,
        'protein_g'               => $protein_g,
        'carbs_g'                 => $carb_g,
        'fat_g'                   => $fat_g,
        'estimated_daily_cost_gel'=> $avg_cost,
        'cheapest_store'          => $cheapest_store,
        'days'                    => $all_days,
    );
}


// ── Save plan ─────────────────────────────────────────────────────────────────

function savePlanToDB($user_id, $profile, $plan) {
    $db    = getDB();
    $db->exec("SET NAMES utf8mb4");
    $title = date('d/m/Y') . ' - ' . $profile['goal'] . ' (' . $profile['days'] . ' დღე)';

    $stmt = $db->prepare(
        'INSERT INTO diet_plans (user_id, title, days, tdee, target_calories, protein_g, carbs_g, fat_g, raw_json, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute(array(
        $user_id, $title, $profile['days'],
        (int)$plan['tdee'], (int)$plan['target_calories'],
        (int)$plan['protein_g'], (int)$plan['carbs_g'], (int)$plan['fat_g'],
        json_encode($plan, JSON_UNESCAPED_UNICODE), time()
    ));
    $plan_id = $db->lastInsertId();

    foreach ($plan['days'] as $day) {
        $stmt = $db->prepare('INSERT INTO plan_days (plan_id, day_number, total_calories) VALUES (?, ?, ?)');
        $stmt->execute(array($plan_id, (int)$day['day'], (int)$day['total_calories']));
        $day_id = $db->lastInsertId();
        foreach ($day['meals'] as $meal) {
            // Build ingredients string from ingredient_list or fallback to ingredients field
            $ingredients_str = '';
            if (!empty($meal['ingredient_list']) && is_array($meal['ingredient_list'])) {
                $names = array();
                foreach ($meal['ingredient_list'] as $ing) {
                    if (!empty($ing['name'])) {
                        $names[] = $ing['name'] . (!empty($ing['amount']) ? ' ' . $ing['amount'] : '');
                    }
                }
                $ingredients_str = implode(', ', $names);
            } elseif (!empty($meal['ingredients'])) {
                $ingredients_str = $meal['ingredients'];
            }
            if (empty($ingredients_str)) $ingredients_str = $meal['name'];

            $stmt = $db->prepare(
                'INSERT INTO plan_meals (day_id, meal_type, name, ingredients, portion, calories)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute(array(
                $day_id,
                isset($meal['type'])    ? $meal['type']    : 'კვება',
                isset($meal['name'])    ? $meal['name']    : '',
                $ingredients_str,
                isset($meal['portion']) ? mb_substr($meal['portion'], 0, 50) : '',
                isset($meal['calories'])? (int)$meal['calories'] : 0
            ));
        }
    }
    return $plan_id;
}