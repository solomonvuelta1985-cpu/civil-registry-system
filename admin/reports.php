<?php
/**
 * Reports & Analytics - Civil Registry Document Management System (CRDMS)
 * Comprehensive analytics dashboard with charts, statistics, and export functionality
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require authentication
requireAuth();

// =====================================================
// GET FILTER PARAMETERS FROM URL
// =====================================================
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$certificate_type = $_GET['certificate_type'] ?? 'all';
$period = $_GET['period'] ?? 'month';
$sort_by = $_GET['sort'] ?? 'date-desc';

// Validate and sanitize dates
$date_from = date('Y-m-d', strtotime($date_from));
$date_to = date('Y-m-d', strtotime($date_to));

// Build date range SQL condition
$date_condition = "DATE(created_at) BETWEEN '$date_from' AND '$date_to'";

// Build certificate type condition
$type_conditions = [
    'all' => "1=1", // All certificates
    'birth' => "certificate_type = 'birth'",
    'marriage' => "certificate_type = 'marriage'",
    'death' => "certificate_type = 'death'",
    'license' => "certificate_type = 'license'"
];

// Initialize all statistics arrays
$stats = [
    'total_births' => 0,
    'total_marriages' => 0,
    'total_deaths' => 0,
    'total_licenses' => 0,
    'this_month_births' => 0,
    'this_month_marriages' => 0,
    'this_month_deaths' => 0,
    'this_month_licenses' => 0,
    'last_month_births' => 0,
    'last_month_marriages' => 0,
    'last_month_deaths' => 0,
    'last_month_licenses' => 0,
    'birth_trend' => 0,
    'marriage_trend' => 0,
    'death_trend' => 0,
    'license_trend' => 0,
    'active_records' => 0,
    'archived_records' => 0,
    'total_records' => 0
];

$monthly_data = [];
$yearly_comparison = [];
$gender_distribution = [];
$age_demographics = [];
$daily_registrations = [];
$citizenship_stats = [];
$marriage_nature_distribution = [];

// New analytics arrays
$type_of_birth_distribution = [];
$birth_order_distribution = [];
$birth_place_type_distribution = [];
$death_sex_distribution = [];
$death_age_sex_matrix = []; // pivoted: [age_group => [Male => n, Female => n]]
$top_birth_locations = [];
$top_marriage_venues = [];
$top_death_places = [];
$marriage_couple_citizenship = [];
$husband_wife_age = []; // [bracket => [Husband => n, Wife => n]]
$groom_bride_age = []; // [bracket => [Groom => n, Bride => n]]

try {
    // =====================================================
    // BIRTH CERTIFICATES STATISTICS (WITH FILTERS)
    // =====================================================

    // Total birth certificates (with date filter)
    if ($certificate_type == 'all' || $certificate_type == 'birth') {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_live_birth WHERE status = 'Active' AND $date_condition");
        $stats['total_births'] = $stmt->fetch()['count'] ?? 0;
    }

    // This month's births
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_live_birth WHERE status = 'Active' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $stats['this_month_births'] = $stmt->fetch()['count'] ?? 0;

    // Last month's births (for trend)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_live_birth WHERE status = 'Active' AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))");
    $stats['last_month_births'] = $stmt->fetch()['count'] ?? 0;

    // Birth trend percentage
    $stats['birth_trend'] = $stats['last_month_births'] > 0
        ? round((($stats['this_month_births'] - $stats['last_month_births']) / $stats['last_month_births']) * 100)
        : ($stats['this_month_births'] > 0 ? 100 : 0);

    // Gender distribution for births (with date filter)
    if ($certificate_type == 'all' || $certificate_type == 'birth') {
        $stmt = $pdo->query("SELECT child_sex, COUNT(*) as count FROM certificate_of_live_birth WHERE status = 'Active' AND $date_condition AND child_sex IS NOT NULL GROUP BY child_sex");
        $gender_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =====================================================
    // MARRIAGE CERTIFICATES STATISTICS (WITH FILTERS)
    // =====================================================

    // Total marriage certificates (with date filter)
    if ($certificate_type == 'all' || $certificate_type == 'marriage') {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_marriage WHERE status = 'Active' AND $date_condition");
        $stats['total_marriages'] = $stmt->fetch()['count'] ?? 0;
    }

    // This month's marriages
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_marriage WHERE status = 'Active' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $stats['this_month_marriages'] = $stmt->fetch()['count'] ?? 0;

    // Last month's marriages
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_marriage WHERE status = 'Active' AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))");
    $stats['last_month_marriages'] = $stmt->fetch()['count'] ?? 0;

    // Marriage trend
    $stats['marriage_trend'] = $stats['last_month_marriages'] > 0
        ? round((($stats['this_month_marriages'] - $stats['last_month_marriages']) / $stats['last_month_marriages']) * 100)
        : ($stats['this_month_marriages'] > 0 ? 100 : 0);

    // Nature of Solemnization distribution for marriages (with date filter)
    if ($certificate_type == 'all' || $certificate_type == 'marriage') {
        $stmt = $pdo->query("SELECT nature_of_solemnization, COUNT(*) as count FROM certificate_of_marriage WHERE status = 'Active' AND $date_condition AND nature_of_solemnization IS NOT NULL GROUP BY nature_of_solemnization");
        $marriage_nature_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =====================================================
    // DEATH CERTIFICATES STATISTICS (WITH FILTERS)
    // =====================================================

    // Total death certificates (with date filter)
    if ($certificate_type == 'all' || $certificate_type == 'death') {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_death WHERE status = 'Active' AND $date_condition");
        $stats['total_deaths'] = $stmt->fetch()['count'] ?? 0;
    }

    // This month's deaths
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_death WHERE status = 'Active' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $stats['this_month_deaths'] = $stmt->fetch()['count'] ?? 0;

    // Last month's deaths
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_death WHERE status = 'Active' AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))");
    $stats['last_month_deaths'] = $stmt->fetch()['count'] ?? 0;

    // Death trend
    $stats['death_trend'] = $stats['last_month_deaths'] > 0
        ? round((($stats['this_month_deaths'] - $stats['last_month_deaths']) / $stats['last_month_deaths']) * 100)
        : ($stats['this_month_deaths'] > 0 ? 100 : 0);

    // Age demographics for deaths (with date filter)
    if ($certificate_type == 'all' || $certificate_type == 'death') {
        $stmt = $pdo->query("
            SELECT
                CASE
                    WHEN age_unit IN ('months','days') THEN 'Infant (< 1)'
                    WHEN age < 1 THEN 'Infant (< 1)'
                    WHEN age BETWEEN 1 AND 17 THEN 'Child (1-17)'
                    WHEN age BETWEEN 18 AND 35 THEN 'Young Adult (18-35)'
                    WHEN age BETWEEN 36 AND 55 THEN 'Middle Age (36-55)'
                    WHEN age BETWEEN 56 AND 75 THEN 'Senior (56-75)'
                    ELSE 'Elderly (75+)'
                END as age_group,
                COUNT(*) as count
            FROM certificate_of_death
            WHERE status = 'Active' AND age IS NOT NULL AND $date_condition
            GROUP BY age_group
            ORDER BY CASE WHEN age_unit IN ('months','days') THEN -1 ELSE MIN(age) END
        ");
        $age_demographics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $age_demographics = [];
    }

    // =====================================================
    // MARRIAGE LICENSE APPLICATIONS STATISTICS
    // =====================================================

    // Total marriage license applications (with date filter)
    if ($certificate_type == 'all' || $certificate_type == 'license') {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM application_for_marriage_license WHERE status = 'Active' AND $date_condition");
        $stats['total_licenses'] = $stmt->fetch()['count'] ?? 0;

        // This month's licenses
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM application_for_marriage_license WHERE status = 'Active' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
        $stats['this_month_licenses'] = $stmt->fetch()['count'] ?? 0;

        // Last month's licenses
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM application_for_marriage_license WHERE status = 'Active' AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))");
        $stats['last_month_licenses'] = $stmt->fetch()['count'] ?? 0;

        // License trend
        $stats['license_trend'] = $stats['last_month_licenses'] > 0
            ? round((($stats['this_month_licenses'] - $stats['last_month_licenses']) / $stats['last_month_licenses']) * 100)
            : ($stats['this_month_licenses'] > 0 ? 100 : 0);
    } else {
        $stats['total_licenses'] = 0;
        $stats['this_month_licenses'] = 0;
        $stats['last_month_licenses'] = 0;
        $stats['license_trend'] = 0;
    }

    // Citizenship statistics for marriage licenses (with date filter)
    if ($certificate_type == 'all' || $certificate_type == 'license') {
        $stmt = $pdo->query("
            SELECT groom_citizenship as citizenship, COUNT(*) as count
            FROM application_for_marriage_license
            WHERE status = 'Active' AND groom_citizenship IS NOT NULL AND $date_condition
            GROUP BY groom_citizenship
            UNION ALL
            SELECT bride_citizenship as citizenship, COUNT(*) as count
            FROM application_for_marriage_license
            WHERE status = 'Active' AND bride_citizenship IS NOT NULL AND $date_condition
            GROUP BY bride_citizenship
        ");
        $citizenship_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Aggregate citizenship stats
        $citizenship_stats = [];
        foreach ($citizenship_raw as $row) {
            $citizenship = strtoupper(trim($row['citizenship']));
            if (!isset($citizenship_stats[$citizenship])) {
                $citizenship_stats[$citizenship] = 0;
            }
            $citizenship_stats[$citizenship] += $row['count'];
        }
        arsort($citizenship_stats);
        $citizenship_stats = array_slice($citizenship_stats, 0, 10, true); // Top 10
    } else {
        $citizenship_stats = [];
    }

    // =====================================================
    // MONTHLY TREND DATA (Last 12 months)
    // =====================================================

    for ($i = 11; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $month_label = date('M Y', strtotime("-$i months"));
        $short_label = date('M', strtotime("-$i months"));

        // Births
        $births = 0;
        if ($certificate_type == 'all' || $certificate_type == 'birth') {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM certificate_of_live_birth WHERE status = 'Active' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
            $stmt->execute([$month]);
            $births = $stmt->fetch()['count'] ?? 0;
        }

        // Marriages
        $marriages = 0;
        if ($certificate_type == 'all' || $certificate_type == 'marriage') {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM certificate_of_marriage WHERE status = 'Active' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
            $stmt->execute([$month]);
            $marriages = $stmt->fetch()['count'] ?? 0;
        }

        // Deaths
        $deaths = 0;
        if ($certificate_type == 'all' || $certificate_type == 'death') {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM certificate_of_death WHERE status = 'Active' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
            $stmt->execute([$month]);
            $deaths = $stmt->fetch()['count'] ?? 0;
        }

        // Licenses
        $licenses = 0;
        if ($certificate_type == 'all' || $certificate_type == 'license') {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM application_for_marriage_license WHERE status = 'Active' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
            $stmt->execute([$month]);
            $licenses = $stmt->fetch()['count'] ?? 0;
        }

        $monthly_data[] = [
            'month' => $month_label,
            'short' => $short_label,
            'births' => $births,
            'marriages' => $marriages,
            'deaths' => $deaths,
            'licenses' => $licenses,
            'total' => $births + $marriages + $deaths + $licenses
        ];
    }

    // =====================================================
    // YEARLY COMPARISON (Current vs Previous Year)
    // =====================================================

    $current_year = date('Y');
    $previous_year = $current_year - 1;

    // Current year totals (with certificate type filter)
    $current_year_births = 0;
    if ($certificate_type == 'all' || $certificate_type == 'birth') {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_live_birth WHERE status = 'Active' AND YEAR(created_at) = YEAR(CURDATE())");
        $current_year_births = $stmt->fetch()['count'] ?? 0;
    }

    $current_year_marriages = 0;
    if ($certificate_type == 'all' || $certificate_type == 'marriage') {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_marriage WHERE status = 'Active' AND YEAR(created_at) = YEAR(CURDATE())");
        $current_year_marriages = $stmt->fetch()['count'] ?? 0;
    }

    $current_year_deaths = 0;
    if ($certificate_type == 'all' || $certificate_type == 'death') {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_death WHERE status = 'Active' AND YEAR(created_at) = YEAR(CURDATE())");
        $current_year_deaths = $stmt->fetch()['count'] ?? 0;
    }

    $current_year_licenses = 0;
    if ($certificate_type == 'all' || $certificate_type == 'license') {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM application_for_marriage_license WHERE status = 'Active' AND YEAR(created_at) = YEAR(CURDATE())");
        $current_year_licenses = $stmt->fetch()['count'] ?? 0;
    }

    // Previous year totals (with certificate type filter)
    $previous_year_births = 0;
    if ($certificate_type == 'all' || $certificate_type == 'birth') {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_live_birth WHERE status = 'Active' AND YEAR(created_at) = YEAR(CURDATE()) - 1");
        $previous_year_births = $stmt->fetch()['count'] ?? 0;
    }

    $previous_year_marriages = 0;
    if ($certificate_type == 'all' || $certificate_type == 'marriage') {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_marriage WHERE status = 'Active' AND YEAR(created_at) = YEAR(CURDATE()) - 1");
        $previous_year_marriages = $stmt->fetch()['count'] ?? 0;
    }

    $previous_year_deaths = 0;
    if ($certificate_type == 'all' || $certificate_type == 'death') {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_death WHERE status = 'Active' AND YEAR(created_at) = YEAR(CURDATE()) - 1");
        $previous_year_deaths = $stmt->fetch()['count'] ?? 0;
    }

    $previous_year_licenses = 0;
    if ($certificate_type == 'all' || $certificate_type == 'license') {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM application_for_marriage_license WHERE status = 'Active' AND YEAR(created_at) = YEAR(CURDATE()) - 1");
        $previous_year_licenses = $stmt->fetch()['count'] ?? 0;
    }

    $yearly_comparison = [
        'current_year' => $current_year,
        'previous_year' => $previous_year,
        'current' => [
            'births' => $current_year_births,
            'marriages' => $current_year_marriages,
            'deaths' => $current_year_deaths,
            'licenses' => $current_year_licenses,
            'total' => $current_year_births + $current_year_marriages + $current_year_deaths + $current_year_licenses
        ],
        'previous' => [
            'births' => $previous_year_births,
            'marriages' => $previous_year_marriages,
            'deaths' => $previous_year_deaths,
            'licenses' => $previous_year_licenses,
            'total' => $previous_year_births + $previous_year_marriages + $previous_year_deaths + $previous_year_licenses
        ]
    ];

    // =====================================================
    // DAILY REGISTRATIONS (Last 30 days)
    // =====================================================

    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $day_label = date('M d', strtotime("-$i days"));

        // Build query based on certificate type filter
        $query_parts = [];
        if ($certificate_type == 'all' || $certificate_type == 'birth') {
            $query_parts[] = "(SELECT COUNT(*) FROM certificate_of_live_birth WHERE status = 'Active' AND DATE(created_at) = ?)";
        }
        if ($certificate_type == 'all' || $certificate_type == 'marriage') {
            $query_parts[] = "(SELECT COUNT(*) FROM certificate_of_marriage WHERE status = 'Active' AND DATE(created_at) = ?)";
        }
        if ($certificate_type == 'all' || $certificate_type == 'death') {
            $query_parts[] = "(SELECT COUNT(*) FROM certificate_of_death WHERE status = 'Active' AND DATE(created_at) = ?)";
        }
        if ($certificate_type == 'all' || $certificate_type == 'license') {
            $query_parts[] = "(SELECT COUNT(*) FROM application_for_marriage_license WHERE status = 'Active' AND DATE(created_at) = ?)";
        }

        if (count($query_parts) > 0) {
            $query = "SELECT " . implode(" + ", $query_parts) . " as total";
            $params = array_fill(0, count($query_parts), $date);
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $total = $stmt->fetch()['total'] ?? 0;
        } else {
            $total = 0;
        }

        $daily_registrations[] = [
            'date' => $day_label,
            'count' => $total
        ];
    }

    // =====================================================
    // PER-TYPE TOP LOCATIONS (replaces cross-type UNION)
    // =====================================================

    // Top Birth Hospitals & Locations (births only) — surfaces hospital + place_type
    if ($certificate_type == 'all' || $certificate_type == 'birth') {
        $stmt = $pdo->query("
            SELECT child_place_of_birth as place, place_type, COUNT(*) as total
            FROM certificate_of_live_birth
            WHERE status = 'Active' AND child_place_of_birth IS NOT NULL AND child_place_of_birth <> '' AND $date_condition
            GROUP BY child_place_of_birth, place_type
            ORDER BY total DESC
            LIMIT 10
        ");
        $top_birth_locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Top Marriage Venues (marriages only)
    if ($certificate_type == 'all' || $certificate_type == 'marriage') {
        $stmt = $pdo->query("
            SELECT place_of_marriage as place, COUNT(*) as total
            FROM certificate_of_marriage
            WHERE status = 'Active' AND place_of_marriage IS NOT NULL AND place_of_marriage <> '' AND $date_condition
            GROUP BY place_of_marriage
            ORDER BY total DESC
            LIMIT 10
        ");
        $top_marriage_venues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Top Places of Death (deaths only)
    if ($certificate_type == 'all' || $certificate_type == 'death') {
        $stmt = $pdo->query("
            SELECT place_of_death as place, COUNT(*) as total
            FROM certificate_of_death
            WHERE status = 'Active' AND place_of_death IS NOT NULL AND place_of_death <> '' AND $date_condition
            GROUP BY place_of_death
            ORDER BY total DESC
            LIMIT 10
        ");
        $top_death_places = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =====================================================
    // NEW BIRTH ANALYTICS (type_of_birth, birth_order, place_type)
    // =====================================================

    if ($certificate_type == 'all' || $certificate_type == 'birth') {
        // Type of birth distribution (Single / Twin / Triplets)
        $stmt = $pdo->query("
            SELECT type_of_birth, COUNT(*) as count
            FROM certificate_of_live_birth
            WHERE status = 'Active' AND $date_condition AND type_of_birth IS NOT NULL AND type_of_birth <> ''
            GROUP BY type_of_birth
            ORDER BY count DESC
        ");
        $type_of_birth_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Birth order distribution (1st, 2nd, 3rd, 4th+)
        $stmt = $pdo->query("
            SELECT birth_order, COUNT(*) as count
            FROM certificate_of_live_birth
            WHERE status = 'Active' AND $date_condition AND birth_order IS NOT NULL AND birth_order <> ''
            GROUP BY birth_order
            ORDER BY count DESC
        ");
        $birth_order_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Place type (Hospital / Home / Health Center)
        $stmt = $pdo->query("
            SELECT place_type, COUNT(*) as count
            FROM certificate_of_live_birth
            WHERE status = 'Active' AND $date_condition AND place_type IS NOT NULL AND place_type <> ''
            GROUP BY place_type
            ORDER BY count DESC
        ");
        $birth_place_type_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =====================================================
    // NEW DEATH ANALYTICS (sex, deaths by age & sex)
    // =====================================================

    if ($certificate_type == 'all' || $certificate_type == 'death') {
        // Sex of deceased (uses migration 012)
        $stmt = $pdo->query("
            SELECT sex, COUNT(*) as count
            FROM certificate_of_death
            WHERE status = 'Active' AND $date_condition AND sex IS NOT NULL
            GROUP BY sex
        ");
        $death_sex_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Deaths by Age & Sex (combined grouped bar)
        $stmt = $pdo->query("
            SELECT
                CASE
                    WHEN age < 1 THEN 'Infant (<1)'
                    WHEN age BETWEEN 1 AND 17 THEN 'Child (1-17)'
                    WHEN age BETWEEN 18 AND 35 THEN 'Young Adult (18-35)'
                    WHEN age BETWEEN 36 AND 55 THEN 'Middle Age (36-55)'
                    WHEN age BETWEEN 56 AND 75 THEN 'Senior (56-75)'
                    ELSE 'Elderly (75+)'
                END as age_group,
                sex,
                COUNT(*) as count,
                MIN(age) as min_age
            FROM certificate_of_death
            WHERE status = 'Active' AND age IS NOT NULL AND sex IS NOT NULL AND $date_condition
            GROUP BY age_group, sex
            ORDER BY MIN(age)
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Pivot into [age_group => [Male => n, Female => n]] preserving order
        $age_group_order = ['Infant (<1)', 'Child (1-17)', 'Young Adult (18-35)', 'Middle Age (36-55)', 'Senior (56-75)', 'Elderly (75+)'];
        foreach ($age_group_order as $bracket) {
            $death_age_sex_matrix[$bracket] = ['Male' => 0, 'Female' => 0];
        }
        foreach ($rows as $row) {
            $bracket = $row['age_group'];
            $sex = $row['sex'];
            if (isset($death_age_sex_matrix[$bracket]) && isset($death_age_sex_matrix[$bracket][$sex])) {
                $death_age_sex_matrix[$bracket][$sex] = (int)$row['count'];
            }
        }
    }

    // =====================================================
    // NEW MARRIAGE ANALYTICS (couple citizenship, husband vs wife age)
    // =====================================================

    if ($certificate_type == 'all' || $certificate_type == 'marriage') {
        // Couple citizenship (husbands + wives combined, top 10)
        $stmt = $pdo->query("
            SELECT husband_citizenship as citizenship, COUNT(*) as count
            FROM certificate_of_marriage
            WHERE status = 'Active' AND husband_citizenship IS NOT NULL AND husband_citizenship <> '' AND $date_condition
            GROUP BY husband_citizenship
            UNION ALL
            SELECT wife_citizenship as citizenship, COUNT(*) as count
            FROM certificate_of_marriage
            WHERE status = 'Active' AND wife_citizenship IS NOT NULL AND wife_citizenship <> '' AND $date_condition
            GROUP BY wife_citizenship
        ");
        $marriage_cit_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $aggregate = [];
        foreach ($marriage_cit_raw as $row) {
            $key = strtoupper(trim($row['citizenship']));
            if (!isset($aggregate[$key])) {
                $aggregate[$key] = 0;
            }
            $aggregate[$key] += (int)$row['count'];
        }
        arsort($aggregate);
        $marriage_couple_citizenship = array_slice($aggregate, 0, 10, true);

        // Husband vs Wife age at marriage (grouped bar)
        $bracket_order = ['18-24', '25-34', '35-44', '45+'];
        foreach ($bracket_order as $b) {
            $husband_wife_age[$b] = ['Husband' => 0, 'Wife' => 0];
        }

        $age_case = "
            CASE
                WHEN age BETWEEN 18 AND 24 THEN '18-24'
                WHEN age BETWEEN 25 AND 34 THEN '25-34'
                WHEN age BETWEEN 35 AND 44 THEN '35-44'
                WHEN age >= 45 THEN '45+'
                ELSE NULL
            END
        ";

        // Husband
        $stmt = $pdo->query("
            SELECT bracket, COUNT(*) as count FROM (
                SELECT $age_case as bracket
                FROM (
                    SELECT TIMESTAMPDIFF(YEAR, husband_date_of_birth, date_of_marriage) as age
                    FROM certificate_of_marriage
                    WHERE status = 'Active' AND husband_date_of_birth IS NOT NULL AND date_of_marriage IS NOT NULL AND $date_condition
                ) as ages
            ) as bucketed
            WHERE bracket IS NOT NULL
            GROUP BY bracket
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($husband_wife_age[$row['bracket']])) {
                $husband_wife_age[$row['bracket']]['Husband'] = (int)$row['count'];
            }
        }

        // Wife
        $stmt = $pdo->query("
            SELECT bracket, COUNT(*) as count FROM (
                SELECT $age_case as bracket
                FROM (
                    SELECT TIMESTAMPDIFF(YEAR, wife_date_of_birth, date_of_marriage) as age
                    FROM certificate_of_marriage
                    WHERE status = 'Active' AND wife_date_of_birth IS NOT NULL AND date_of_marriage IS NOT NULL AND $date_condition
                ) as ages
            ) as bucketed
            WHERE bracket IS NOT NULL
            GROUP BY bracket
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($husband_wife_age[$row['bracket']])) {
                $husband_wife_age[$row['bracket']]['Wife'] = (int)$row['count'];
            }
        }
    }

    // =====================================================
    // NEW LICENSE ANALYTICS (groom vs bride age profile)
    // =====================================================

    if ($certificate_type == 'all' || $certificate_type == 'license') {
        $bracket_order = ['18-24', '25-34', '35-44', '45+'];
        foreach ($bracket_order as $b) {
            $groom_bride_age[$b] = ['Groom' => 0, 'Bride' => 0];
        }

        $age_case_lic = "
            CASE
                WHEN age BETWEEN 18 AND 24 THEN '18-24'
                WHEN age BETWEEN 25 AND 34 THEN '25-34'
                WHEN age BETWEEN 35 AND 44 THEN '35-44'
                WHEN age >= 45 THEN '45+'
                ELSE NULL
            END
        ";

        // Groom
        $stmt = $pdo->query("
            SELECT bracket, COUNT(*) as count FROM (
                SELECT $age_case_lic as bracket
                FROM (
                    SELECT TIMESTAMPDIFF(YEAR, groom_date_of_birth, CURDATE()) as age
                    FROM application_for_marriage_license
                    WHERE status = 'Active' AND groom_date_of_birth IS NOT NULL AND $date_condition
                ) as ages
            ) as bucketed
            WHERE bracket IS NOT NULL
            GROUP BY bracket
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($groom_bride_age[$row['bracket']])) {
                $groom_bride_age[$row['bracket']]['Groom'] = (int)$row['count'];
            }
        }

        // Bride
        $stmt = $pdo->query("
            SELECT bracket, COUNT(*) as count FROM (
                SELECT $age_case_lic as bracket
                FROM (
                    SELECT TIMESTAMPDIFF(YEAR, bride_date_of_birth, CURDATE()) as age
                    FROM application_for_marriage_license
                    WHERE status = 'Active' AND bride_date_of_birth IS NOT NULL AND $date_condition
                ) as ages
            ) as bucketed
            WHERE bracket IS NOT NULL
            GROUP BY bracket
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($groom_bride_age[$row['bracket']])) {
                $groom_bride_age[$row['bracket']]['Bride'] = (int)$row['count'];
            }
        }
    }

    // =====================================================
    // RECORD STATUS SUMMARY
    // =====================================================

    // Active records count
    $stmt = $pdo->query("
        SELECT
            (SELECT COUNT(*) FROM certificate_of_live_birth WHERE status = 'Active') +
            (SELECT COUNT(*) FROM certificate_of_marriage WHERE status = 'Active') +
            (SELECT COUNT(*) FROM certificate_of_death WHERE status = 'Active') +
            (SELECT COUNT(*) FROM application_for_marriage_license WHERE status = 'Active') as total
    ");
    $stats['active_records'] = $stmt->fetch()['total'] ?? 0;

    // Archived records count
    $stmt = $pdo->query("
        SELECT
            (SELECT COUNT(*) FROM certificate_of_live_birth WHERE status = 'Archived') +
            (SELECT COUNT(*) FROM certificate_of_marriage WHERE status = 'Archived') +
            (SELECT COUNT(*) FROM certificate_of_death WHERE status = 'Archived') +
            (SELECT COUNT(*) FROM application_for_marriage_license WHERE status = 'Archived') as total
    ");
    $stats['archived_records'] = $stmt->fetch()['total'] ?? 0;

    $stats['total_records'] = $stats['total_births'] + $stats['total_marriages'] + $stats['total_deaths'] + $stats['total_licenses'];

} catch (PDOException $e) {
    error_log("Reports Error: " . $e->getMessage());
}

$user_name = $_SESSION['full_name'] ?? 'Admin User';
$user_first_name = explode(' ', $user_name)[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - CRDMS</title>
    <?= google_fonts_tag('Inter:wght@400;500;600;700;800') ?>
    <link rel="stylesheet" href="<?= asset_url('fontawesome_css') ?>">
    <script src="<?= asset_url('lucide') ?>"></script>
    <script src="<?= asset_url('chartjs') ?>"></script>

    <!-- Shared Sidebar Styles -->
    <link rel="stylesheet" href="../assets/css/sidebar.css">

    <style>
        :root {
            --text-secondary-nav: #94a3b8;
            --accent-color: #3b82f6;

            /* Material Design 3 Colors */
            --md-primary: #6750A4;
            --md-on-primary: #FFFFFF;
            --md-surface: #FFFBFE;
            --md-on-surface: #1C1B1F;

            /* Semantic Colors */
            --color-birth: #2196F3;
            --color-marriage: #E91E63;
            --color-death: #FF9800;
            --color-license: #9C27B0;
            --color-success: #10B981;
            --color-warning: #F59E0B;
            --color-danger: #EF4444;

            /* Background & Surface */
            --bg-primary: #f9fafb;
            --bg-card: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;

            /* Shadows */
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 14px;
            line-height: 1.6;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Content Area */
        .content {
            margin-left: 260px;
            padding: 84px 24px 24px 24px;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }

        .sidebar-collapsed .content {
            margin-left: 72px;
        }

        .reports-container {
            max-width: 1600px;
            margin: 0 auto;
        }

        /* Page Header */
        .page-header {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 24px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-title h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.02em;
        }

        .page-title-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: #f0f4ff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--md-primary);
            font-size: 1.25rem;
            border: 2px solid #e0e7ff;
        }

        .page-subtitle {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-top: 4px;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: var(--md-primary);
            color: white;
        }

        .btn-primary:hover {
            background: #5a47a1;
            transform: translateY(-1px);
        }

        .btn-outline {
            background: white;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        .btn-outline:hover {
            background: var(--bg-primary);
            border-color: var(--md-primary);
            color: var(--md-primary);
        }

        /* Filter Controls */
        .filter-controls {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 28px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .filter-section {
            display: grid;
            grid-template-columns: 1.2fr 1fr 1fr 1fr auto;
            gap: 16px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .filter-label {
            font-size: 0.6875rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 2px;
        }

        .filter-label i {
            width: 15px;
            height: 15px;
            color: #6750A4;
        }

        .filter-select {
            padding: 11px 16px;
            padding-right: 36px;
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.875rem;
            font-family: inherit;
            background: white;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 12px;
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 500;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }

        .filter-select:hover {
            border-color: #94a3b8;
            background-color: #f8fafc;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--md-primary);
            background-color: white;
            box-shadow: 0 0 0 3px rgba(103, 80, 164, 0.1);
        }

        .date-range-inputs {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: nowrap;
        }

        .date-input {
            flex: 1;
            min-width: 135px;
            padding: 11px 14px;
            padding-right: 10px;
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.875rem;
            font-family: inherit;
            background: white;
            color: var(--text-primary);
            transition: all 0.2s ease;
            font-weight: 500;
            line-height: 1.5;
        }

        .date-input::-webkit-calendar-picker-indicator {
            margin-left: 6px;
            cursor: pointer;
            opacity: 0.6;
            transition: opacity 0.2s ease;
        }

        .date-input:hover::-webkit-calendar-picker-indicator {
            opacity: 1;
        }

        .date-input:hover {
            border-color: #94a3b8;
            background-color: #f8fafc;
        }

        .date-input:focus {
            outline: none;
            border-color: var(--md-primary);
            background-color: white;
            box-shadow: 0 0 0 3px rgba(103, 80, 164, 0.1);
        }

        .date-separator {
            font-size: 0.75rem;
            color: #94a3b8;
            font-weight: 600;
            flex-shrink: 0;
        }

        .btn-apply-filters {
            padding: 11px 28px;
            background: var(--md-primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(103, 80, 164, 0.25);
            white-space: nowrap;
            align-self: flex-end;
        }

        .btn-apply-filters:hover {
            background: #5a47a1;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(103, 80, 164, 0.35);
        }

        .btn-apply-filters:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(103, 80, 164, 0.25);
        }

        .btn-apply-filters i {
            width: 18px;
            height: 18px;
        }

        /* Summary Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 20px;
            position: relative;
            overflow: hidden;
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }

        .stat-card.birth::before { background: var(--color-birth); }
        .stat-card.marriage::before { background: var(--color-marriage); }
        .stat-card.death::before { background: var(--color-death); }
        .stat-card.license::before { background: var(--color-license); }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .stat-card.birth .stat-icon {
            background: rgba(33, 150, 243, 0.1);
            color: var(--color-birth);
        }

        .stat-card.marriage .stat-icon {
            background: rgba(233, 30, 99, 0.1);
            color: var(--color-marriage);
        }

        .stat-card.death .stat-icon {
            background: rgba(255, 152, 0, 0.1);
            color: var(--color-death);
        }

        .stat-card.license .stat-icon {
            background: rgba(156, 39, 176, 0.1);
            color: var(--color-license);
        }

        .stat-content {
            flex: 1;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
            margin-bottom: 4px;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .stat-trend {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 20px;
            margin-top: 8px;
        }

        .stat-trend.up {
            background: rgba(16, 185, 129, 0.1);
            color: var(--color-success);
        }

        .stat-trend.down {
            background: rgba(239, 68, 68, 0.1);
            color: var(--color-danger);
        }

        .stat-trend.neutral {
            background: rgba(107, 114, 128, 0.1);
            color: var(--text-secondary);
        }

        .stat-meta {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        .chart-card {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 24px;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .chart-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chart-title i {
            color: var(--md-primary);
        }

        .chart-subtitle {
            color: var(--text-secondary);
            font-size: 0.8125rem;
            margin-top: 2px;
        }

        .chart-actions {
            display: flex;
            gap: 8px;
        }

        .chart-filter {
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: white;
            font-size: 0.8125rem;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .chart-filter:hover, .chart-filter.active {
            background: var(--md-primary);
            color: white;
            border-color: var(--md-primary);
        }

        .chart-container {
            position: relative;
            height: 320px;
        }

        .chart-container.small {
            height: 280px;
        }

        .chart-container.large {
            height: 380px;
        }

        /* Secondary Charts Grid */
        .secondary-charts-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 24px;
        }

        /* Data Tables */
        .data-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        .data-card {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .data-card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .data-card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .data-card-title i {
            color: var(--md-primary);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            padding: 12px 24px;
            text-align: left;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-color);
        }

        .data-table td {
            padding: 14px 24px;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.875rem;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .data-table tbody tr:hover {
            background: var(--bg-primary);
        }

        .rank-badge {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--md-primary);
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .rank-badge.gold { background: #F59E0B; }
        .rank-badge.silver { background: #9CA3AF; }
        .rank-badge.bronze { background: #B45309; }

        .progress-bar-container {
            width: 100%;
            height: 8px;
            background: var(--bg-primary);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: var(--md-primary);
            border-radius: 4px;
            transition: width 0.5s ease;
        }

        /* Year Comparison */
        .comparison-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .comparison-card {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 20px;
            text-align: center;
        }

        .comparison-label {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 8px;
        }

        .comparison-values {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 16px;
        }

        .comparison-value {
            text-align: center;
        }

        .comparison-value .year {
            font-size: 0.6875rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .comparison-value .number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .comparison-arrow {
            font-size: 1.25rem;
            color: var(--text-secondary);
        }

        .comparison-change {
            margin-top: 8px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .comparison-change.up { color: var(--color-success); }
        .comparison-change.down { color: var(--color-danger); }

        /* Quick Stats Bar */
        /* KPI Strip — replaces quick-stats-bar AND stats-grid */
        .kpi-strip {
            background: white;
            border-radius: 12px;
            margin-bottom: 24px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0;
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .kpi-item {
            padding: 22px 24px;
            position: relative;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .kpi-item + .kpi-item {
            border-left: 1px solid var(--border-color);
        }

        .kpi-item:hover {
            background: #f9fafb;
        }

        .kpi-item .kpi-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            margin-bottom: 4px;
        }

        .kpi-item.birth .kpi-icon { background: rgba(33, 150, 243, 0.1); color: var(--color-birth); }
        .kpi-item.marriage .kpi-icon { background: rgba(233, 30, 99, 0.1); color: var(--color-marriage); }
        .kpi-item.death .kpi-icon { background: rgba(255, 152, 0, 0.1); color: var(--color-death); }
        .kpi-item.license .kpi-icon { background: rgba(156, 39, 176, 0.1); color: var(--color-license); }

        .kpi-item .kpi-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.1;
        }

        .kpi-item .kpi-label {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            font-weight: 600;
        }

        .kpi-item .kpi-trend {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 4px;
        }

        .kpi-item .kpi-trend.up { color: var(--color-success); }
        .kpi-item .kpi-trend.down { color: var(--color-danger); }
        .kpi-item .kpi-trend.neutral { color: var(--text-secondary); }

        .kpi-item .kpi-trend-label {
            font-size: 0.6875rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Tab Navigation */
        .tab-nav {
            display: flex;
            gap: 4px;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 6px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
        }

        .tab-btn {
            flex: 1;
            min-width: 120px;
            padding: 12px 18px;
            border: none;
            background: transparent;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
            font-family: inherit;
            white-space: nowrap;
        }

        .tab-btn:hover {
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .tab-btn.active {
            background: var(--md-primary);
            color: white;
            box-shadow: 0 2px 6px rgba(103, 80, 164, 0.25);
        }

        .tab-btn i {
            width: 16px;
            height: 16px;
        }

        .tab-panel {
            display: none;
            animation: fadeIn 0.25s ease;
        }

        .tab-panel.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Tab content grids */
        .tab-charts-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 24px;
        }

        .tab-charts-row.three-col {
            grid-template-columns: repeat(3, 1fr);
        }

        .tab-full-row {
            margin-bottom: 24px;
        }

        /* Export Section */
        .export-section {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 24px;
            margin-bottom: 24px;
        }

        .export-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .export-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .export-options {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        .export-option {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .export-option:hover {
            border-color: var(--md-primary);
            background: rgba(103, 80, 164, 0.05);
            transform: translateY(-2px);
        }

        .export-option-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            margin: 0 auto 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .export-option-icon.pdf {
            background: rgba(239, 68, 68, 0.1);
            color: #EF4444;
        }

        .export-option-icon.excel {
            background: rgba(16, 185, 129, 0.1);
            color: #10B981;
        }

        .export-option-icon.csv {
            background: rgba(59, 130, 246, 0.1);
            color: #3B82F6;
        }

        .export-option-icon.print {
            background: rgba(107, 114, 128, 0.1);
            color: #6B7280;
        }

        .export-option-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .export-option-desc {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        /* Date Range Picker */
        .date-range-picker {
            display: flex;
            gap: 12px;
            align-items: center;
            background: var(--bg-primary);
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .date-range-picker input {
            border: none;
            background: transparent;
            font-size: 0.875rem;
            color: var(--text-primary);
            padding: 4px 8px;
            outline: none;
        }

        .date-range-picker span {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        /* Responsive */
        @media (max-width: 1400px) {
            .kpi-strip {
                grid-template-columns: repeat(2, 1fr);
            }

            .kpi-item + .kpi-item {
                border-left: none;
            }

            .kpi-item:nth-child(n+2) {
                border-top: 1px solid var(--border-color);
            }

            .kpi-item:nth-child(2) {
                border-top: none;
            }

            .kpi-item:nth-child(odd) {
                border-right: 1px solid var(--border-color);
            }

            .tab-charts-row,
            .tab-charts-row.three-col {
                grid-template-columns: repeat(2, 1fr);
            }

            .comparison-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .export-options {
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-section {
                grid-template-columns: 1fr 1fr;
                gap: 16px;
            }

            .btn-apply-filters {
                grid-column: span 2;
            }
        }

        @media (max-width: 992px) {
            .data-section {
                grid-template-columns: 1fr;
            }

            .tab-charts-row,
            .tab-charts-row.three-col {
                grid-template-columns: 1fr;
            }

            .filter-section {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .btn-apply-filters {
                grid-column: 1;
            }

            .date-range-inputs {
                flex-wrap: wrap;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-actions {
                width: 100%;
                flex-direction: column;
            }

            .header-actions .btn {
                width: 100%;
                justify-content: center;
            }

            .kpi-strip {
                grid-template-columns: 1fr;
            }

            .kpi-item + .kpi-item,
            .kpi-item:nth-child(odd) {
                border-left: none;
                border-right: none;
                border-top: 1px solid var(--border-color);
            }

            .tab-nav {
                flex-wrap: nowrap;
            }

            .tab-btn {
                min-width: 100px;
                padding: 10px 12px;
                font-size: 0.8125rem;
            }

            .btn-apply-filters {
                width: 100%;
            }

            .date-range-inputs {
                flex-direction: column;
                align-items: stretch;
            }

            .date-input {
                width: 100%;
                min-width: unset;
            }

            .date-separator {
                text-align: center;
            }

            .comparison-grid {
                grid-template-columns: 1fr;
            }

            .export-options {
                grid-template-columns: 1fr;
            }
        }

        /* Print Styles */
        @media print {
            .sidebar, .top-navbar, .mobile-header, .header-actions, .export-section, .tab-nav {
                display: none !important;
            }

            .content {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            .chart-card, .data-card, .kpi-item {
                break-inside: avoid;
            }

            /* Expand all tab panels for printing */
            .tab-panel {
                display: block !important;
                page-break-before: always;
            }

            .tab-panel:first-of-type {
                page-break-before: auto;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/preloader.php'; ?>
    <?php include '../includes/mobile_header.php'; ?>
    <?php include '../includes/sidebar_nav.php'; ?>
    <?php include '../includes/top_navbar.php'; ?>

    <div class="content">
        <div class="reports-container">
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-title">
                    <div class="page-title-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div>
                        <h1>Reports & Analytics</h1>
                        <p class="page-subtitle">Comprehensive civil registry statistics and insights</p>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="btn btn-outline" onclick="refreshData()">
                        <i data-lucide="refresh-cw"></i>
                        Refresh
                    </button>
                    <button class="btn btn-primary" onclick="window.print()">
                        <i data-lucide="printer"></i>
                        Print Report
                    </button>
                </div>
            </div>

            <!-- Filter & Sort Controls -->
            <div class="filter-controls">
                <div class="filter-section">
                    <div class="filter-group">
                        <label class="filter-label">
                            <i data-lucide="calendar"></i>
                            Date Range
                        </label>
                        <div class="date-range-inputs">
                            <input type="date" id="dateFrom" class="date-input" value="<?php echo date('Y-m-01'); ?>">
                            <span class="date-separator">to</span>
                            <input type="date" id="dateTo" class="date-input" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">
                            <i data-lucide="filter"></i>
                            Certificate Type
                        </label>
                        <select id="certificateFilter" class="filter-select">
                            <option value="all">All Certificates</option>
                            <option value="birth">Birth Certificates</option>
                            <option value="marriage">Marriage Certificates</option>
                            <option value="death">Death Certificates</option>
                            <option value="license">Marriage Licenses</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">
                            <i data-lucide="trending-up"></i>
                            Time Period
                        </label>
                        <select id="periodFilter" class="filter-select">
                            <option value="month">This Month</option>
                            <option value="quarter">This Quarter</option>
                            <option value="year">This Year</option>
                            <option value="all">All Time</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">
                            <i data-lucide="bar-chart-2"></i>
                            Sort By
                        </label>
                        <select id="sortBy" class="filter-select">
                            <option value="date-desc">Date (Newest First)</option>
                            <option value="date-asc">Date (Oldest First)</option>
                            <option value="count-desc">Count (High to Low)</option>
                            <option value="count-asc">Count (Low to High)</option>
                        </select>
                    </div>

                    <button class="btn-apply-filters" onclick="applyFilters()">
                        <i data-lucide="check-circle"></i>
                        Apply Filters
                    </button>
                </div>
            </div>

            <!-- Quick Stats Bar -->
            <!-- KPI Strip — replaces old quick-stats-bar AND stats-grid -->
            <div class="kpi-strip">
                <div class="kpi-item birth">
                    <div class="kpi-icon"><i class="fas fa-baby"></i></div>
                    <div class="kpi-value" data-count="<?php echo $stats['total_births']; ?>">0</div>
                    <div class="kpi-label">Birth Certificates</div>
                    <?php if ($stats['birth_trend'] != 0): ?>
                        <div class="kpi-trend <?php echo $stats['birth_trend'] > 0 ? 'up' : 'down'; ?>">
                            <i class="fas fa-<?php echo $stats['birth_trend'] > 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            <?php echo abs($stats['birth_trend']); ?>%
                        </div>
                    <?php else: ?>
                        <div class="kpi-trend neutral"><i class="fas fa-minus"></i> 0%</div>
                    <?php endif; ?>
                    <div class="kpi-trend-label">vs. previous calendar month</div>
                </div>
                <div class="kpi-item marriage">
                    <div class="kpi-icon"><i class="fas fa-ring"></i></div>
                    <div class="kpi-value" data-count="<?php echo $stats['total_marriages']; ?>">0</div>
                    <div class="kpi-label">Marriage Certificates</div>
                    <?php if ($stats['marriage_trend'] != 0): ?>
                        <div class="kpi-trend <?php echo $stats['marriage_trend'] > 0 ? 'up' : 'down'; ?>">
                            <i class="fas fa-<?php echo $stats['marriage_trend'] > 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            <?php echo abs($stats['marriage_trend']); ?>%
                        </div>
                    <?php else: ?>
                        <div class="kpi-trend neutral"><i class="fas fa-minus"></i> 0%</div>
                    <?php endif; ?>
                    <div class="kpi-trend-label">vs. previous calendar month</div>
                </div>
                <div class="kpi-item death">
                    <div class="kpi-icon"><i class="fas fa-cross"></i></div>
                    <div class="kpi-value" data-count="<?php echo $stats['total_deaths']; ?>">0</div>
                    <div class="kpi-label">Death Certificates</div>
                    <?php if ($stats['death_trend'] != 0): ?>
                        <div class="kpi-trend <?php echo $stats['death_trend'] > 0 ? 'up' : 'down'; ?>">
                            <i class="fas fa-<?php echo $stats['death_trend'] > 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            <?php echo abs($stats['death_trend']); ?>%
                        </div>
                    <?php else: ?>
                        <div class="kpi-trend neutral"><i class="fas fa-minus"></i> 0%</div>
                    <?php endif; ?>
                    <div class="kpi-trend-label">vs. previous calendar month</div>
                </div>
                <div class="kpi-item license">
                    <div class="kpi-icon"><i class="fas fa-file-signature"></i></div>
                    <div class="kpi-value" data-count="<?php echo $stats['total_licenses']; ?>">0</div>
                    <div class="kpi-label">License Applications</div>
                    <?php if ($stats['license_trend'] != 0): ?>
                        <div class="kpi-trend <?php echo $stats['license_trend'] > 0 ? 'up' : 'down'; ?>">
                            <i class="fas fa-<?php echo $stats['license_trend'] > 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            <?php echo abs($stats['license_trend']); ?>%
                        </div>
                    <?php else: ?>
                        <div class="kpi-trend neutral"><i class="fas fa-minus"></i> 0%</div>
                    <?php endif; ?>
                    <div class="kpi-trend-label">vs. previous calendar month</div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="tab-nav" role="tablist">
                <button type="button" class="tab-btn active" data-tab="overview" role="tab">
                    <i data-lucide="layout-dashboard"></i> Overview
                </button>
                <button type="button" class="tab-btn" data-tab="births" role="tab">
                    <i data-lucide="baby"></i> Births
                </button>
                <button type="button" class="tab-btn" data-tab="marriages" role="tab">
                    <i data-lucide="heart"></i> Marriages
                </button>
                <button type="button" class="tab-btn" data-tab="deaths" role="tab">
                    <i data-lucide="file-text"></i> Deaths
                </button>
                <button type="button" class="tab-btn" data-tab="licenses" role="tab">
                    <i data-lucide="file-signature"></i> Licenses
                </button>
            </div>

            <!-- ================================================== -->
            <!-- TAB 1: OVERVIEW                                    -->
            <!-- ================================================== -->
            <div class="tab-panel active" data-tab="overview">
                <!-- Monthly Trend Chart (full width) -->
                <div class="tab-full-row">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div>
                                <h3 class="chart-title">
                                    <i class="fas fa-chart-line"></i>
                                    Monthly Registration Trends &mdash; Last 12 Months
                                </h3>
                                <p class="chart-subtitle">All certificate types over the last year</p>
                            </div>
                            <div class="chart-actions">
                                <button class="chart-filter active" data-filter="all">All</button>
                                <button class="chart-filter" data-filter="births">Births</button>
                                <button class="chart-filter" data-filter="marriages">Marriages</button>
                                <button class="chart-filter" data-filter="deaths">Deaths</button>
                            </div>
                        </div>
                        <div class="chart-container large">
                            <canvas id="monthlyTrendChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Year-over-Year Comparison -->
                <div class="comparison-grid">
                    <div class="comparison-card">
                        <div class="comparison-label">Birth Certificates</div>
                        <div class="comparison-values">
                            <div class="comparison-value">
                                <div class="year"><?php echo $yearly_comparison['previous_year']; ?></div>
                                <div class="number"><?php echo number_format($yearly_comparison['previous']['births']); ?></div>
                            </div>
                            <div class="comparison-arrow"><i data-lucide="arrow-right"></i></div>
                            <div class="comparison-value">
                                <div class="year"><?php echo $yearly_comparison['current_year']; ?></div>
                                <div class="number"><?php echo number_format($yearly_comparison['current']['births']); ?></div>
                            </div>
                        </div>
                        <?php
                        $birth_change = $yearly_comparison['previous']['births'] > 0
                            ? round((($yearly_comparison['current']['births'] - $yearly_comparison['previous']['births']) / $yearly_comparison['previous']['births']) * 100)
                            : 0;
                        ?>
                        <div class="comparison-change <?php echo $birth_change >= 0 ? 'up' : 'down'; ?>">
                            <i class="fas fa-<?php echo $birth_change >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            <?php echo abs($birth_change); ?>% <?php echo $birth_change >= 0 ? 'increase' : 'decrease'; ?>
                        </div>
                    </div>

                    <div class="comparison-card">
                        <div class="comparison-label">Marriage Certificates</div>
                        <div class="comparison-values">
                            <div class="comparison-value">
                                <div class="year"><?php echo $yearly_comparison['previous_year']; ?></div>
                                <div class="number"><?php echo number_format($yearly_comparison['previous']['marriages']); ?></div>
                            </div>
                            <div class="comparison-arrow"><i data-lucide="arrow-right"></i></div>
                            <div class="comparison-value">
                                <div class="year"><?php echo $yearly_comparison['current_year']; ?></div>
                                <div class="number"><?php echo number_format($yearly_comparison['current']['marriages']); ?></div>
                            </div>
                        </div>
                        <?php
                        $marriage_change = $yearly_comparison['previous']['marriages'] > 0
                            ? round((($yearly_comparison['current']['marriages'] - $yearly_comparison['previous']['marriages']) / $yearly_comparison['previous']['marriages']) * 100)
                            : 0;
                        ?>
                        <div class="comparison-change <?php echo $marriage_change >= 0 ? 'up' : 'down'; ?>">
                            <i class="fas fa-<?php echo $marriage_change >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            <?php echo abs($marriage_change); ?>% <?php echo $marriage_change >= 0 ? 'increase' : 'decrease'; ?>
                        </div>
                    </div>

                    <div class="comparison-card">
                        <div class="comparison-label">Death Certificates</div>
                        <div class="comparison-values">
                            <div class="comparison-value">
                                <div class="year"><?php echo $yearly_comparison['previous_year']; ?></div>
                                <div class="number"><?php echo number_format($yearly_comparison['previous']['deaths']); ?></div>
                            </div>
                            <div class="comparison-arrow"><i data-lucide="arrow-right"></i></div>
                            <div class="comparison-value">
                                <div class="year"><?php echo $yearly_comparison['current_year']; ?></div>
                                <div class="number"><?php echo number_format($yearly_comparison['current']['deaths']); ?></div>
                            </div>
                        </div>
                        <?php
                        $death_change = $yearly_comparison['previous']['deaths'] > 0
                            ? round((($yearly_comparison['current']['deaths'] - $yearly_comparison['previous']['deaths']) / $yearly_comparison['previous']['deaths']) * 100)
                            : 0;
                        ?>
                        <div class="comparison-change <?php echo $death_change >= 0 ? 'up' : 'down'; ?>">
                            <i class="fas fa-<?php echo $death_change >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            <?php echo abs($death_change); ?>% <?php echo $death_change >= 0 ? 'increase' : 'decrease'; ?>
                        </div>
                    </div>

                    <div class="comparison-card">
                        <div class="comparison-label">License Applications</div>
                        <div class="comparison-values">
                            <div class="comparison-value">
                                <div class="year"><?php echo $yearly_comparison['previous_year']; ?></div>
                                <div class="number"><?php echo number_format($yearly_comparison['previous']['licenses']); ?></div>
                            </div>
                            <div class="comparison-arrow"><i data-lucide="arrow-right"></i></div>
                            <div class="comparison-value">
                                <div class="year"><?php echo $yearly_comparison['current_year']; ?></div>
                                <div class="number"><?php echo number_format($yearly_comparison['current']['licenses']); ?></div>
                            </div>
                        </div>
                        <?php
                        $license_change = $yearly_comparison['previous']['licenses'] > 0
                            ? round((($yearly_comparison['current']['licenses'] - $yearly_comparison['previous']['licenses']) / $yearly_comparison['previous']['licenses']) * 100)
                            : 0;
                        ?>
                        <div class="comparison-change <?php echo $license_change >= 0 ? 'up' : 'down'; ?>">
                            <i class="fas fa-<?php echo $license_change >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            <?php echo abs($license_change); ?>% <?php echo $license_change >= 0 ? 'increase' : 'decrease'; ?>
                        </div>
                    </div>
                </div>

                <!-- Daily Registrations (full width) -->
                <div class="tab-full-row">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div>
                                <h3 class="chart-title">
                                    <i class="fas fa-calendar-day"></i>
                                    Daily Registrations &mdash; Last 30 Days
                                </h3>
                                <p class="chart-subtitle">All certificate types combined per day</p>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="dailyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================================================== -->
            <!-- TAB 2: BIRTHS                                       -->
            <!-- ================================================== -->
            <div class="tab-panel" data-tab="births">
                <div class="tab-charts-row">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div>
                                <h3 class="chart-title"><i class="fas fa-venus-mars"></i> Birth Gender Distribution</h3>
                                <p class="chart-subtitle">Male vs Female births in selected period</p>
                            </div>
                        </div>
                        <div class="chart-container small">
                            <canvas id="genderChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-card">
                        <div class="chart-header">
                            <div>
                                <h3 class="chart-title"><i class="fas fa-baby"></i> Type of Birth</h3>
                                <p class="chart-subtitle">Single, Twin, or Triplets</p>
                            </div>
                        </div>
                        <div class="chart-container small">
                            <canvas id="typeOfBirthChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="tab-charts-row">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div>
                                <h3 class="chart-title"><i class="fas fa-list-ol"></i> Birth Order</h3>
                                <p class="chart-subtitle">First-born, second, third, etc.</p>
                            </div>
                        </div>
                        <div class="chart-container small">
                            <canvas id="birthOrderChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-card">
                        <div class="chart-header">
                            <div>
                                <h3 class="chart-title"><i class="fas fa-hospital"></i> Place Type of Birth</h3>
                                <p class="chart-subtitle">Hospital / Home / Health Center</p>
                            </div>
                        </div>
                        <div class="chart-container small">
                            <canvas id="birthPlaceTypeChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Top Birth Hospitals & Locations -->
                <div class="tab-full-row">
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">
                                <i class="fas fa-map-marker-alt"></i>
                                Top Birth Hospitals &amp; Locations
                            </h3>
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Hospital / Location</th>
                                    <th>Place Type</th>
                                    <th>Births</th>
                                    <th>Share</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $max_birth_loc = !empty($top_birth_locations) ? max(array_column($top_birth_locations, 'total')) : 1;
                                $total_birth_loc = array_sum(array_column($top_birth_locations, 'total'));
                                $rank = 1;
                                foreach ($top_birth_locations as $loc):
                                    $percentage = $total_birth_loc > 0 ? ($loc['total'] / $total_birth_loc) * 100 : 0;
                                ?>
                                <tr>
                                    <td>
                                        <span class="rank-badge <?php echo $rank <= 3 ? ($rank == 1 ? 'gold' : ($rank == 2 ? 'silver' : 'bronze')) : ''; ?>">
                                            <?php echo $rank; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($loc['place'] ?? 'Unknown'); ?></td>
                                    <td><?php echo htmlspecialchars($loc['place_type'] ?? '—'); ?></td>
                                    <td><strong><?php echo number_format($loc['total']); ?></strong></td>
                                    <td>
                                        <div class="progress-bar-container">
                                            <div class="progress-bar" style="width: <?php echo ($loc['total'] / $max_birth_loc) * 100; ?>%; background: var(--color-birth);"></div>
                                        </div>
                                        <small style="color: var(--text-secondary);"><?php echo number_format($percentage, 1); ?>%</small>
                                    </td>
                                </tr>
                                <?php $rank++; endforeach; ?>
                                <?php if (empty($top_birth_locations)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 40px;">
                                        No birth location data available for this period
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ================================================== -->
            <!-- TAB 3: MARRIAGES                                    -->
            <!-- ================================================== -->
            <div class="tab-panel" data-tab="marriages">
                <div class="tab-charts-row">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div>
                                <h3 class="chart-title"><i class="fas fa-church"></i> Nature of Solemnization</h3>
                                <p class="chart-subtitle">Civil / Religious / Other ceremony types</p>
                            </div>
                        </div>
                        <div class="chart-container small">
                            <canvas id="marriageNatureChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-card">
                        <div class="chart-header">
                            <div>
                                <h3 class="chart-title"><i class="fas fa-users"></i> Husband vs Wife Age at Marriage</h3>
                                <p class="chart-subtitle">Age brackets &mdash; computed from date of birth and marriage date</p>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="husbandWifeAgeChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="tab-full-row">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div>
                                <h3 class="chart-title"><i class="fas fa-flag"></i> Couple Citizenship &mdash; Husbands &amp; Wives Combined</h3>
                                <p class="chart-subtitle">Top 10 nationalities across all marriage records in this period</p>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="marriageCitizenshipChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="tab-full-row">
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">
                                <i class="fas fa-map-marker-alt"></i>
                                Top Marriage Venues
                            </h3>
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Venue</th>
                                    <th>Marriages</th>
                                    <th>Share</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $max_venue = !empty($top_marriage_venues) ? max(array_column($top_marriage_venues, 'total')) : 1;
                                $total_venues = array_sum(array_column($top_marriage_venues, 'total'));
                                $rank = 1;
                                foreach ($top_marriage_venues as $venue):
                                    $percentage = $total_venues > 0 ? ($venue['total'] / $total_venues) * 100 : 0;
                                ?>
                                <tr>
                                    <td>
                                        <span class="rank-badge <?php echo $rank <= 3 ? ($rank == 1 ? 'gold' : ($rank == 2 ? 'silver' : 'bronze')) : ''; ?>">
                                            <?php echo $rank; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($venue['place'] ?? 'Unknown'); ?></td>
                                    <td><strong><?php echo number_format($venue['total']); ?></strong></td>
                                    <td>
                                        <div class="progress-bar-container">
                                            <div class="progress-bar" style="width: <?php echo ($venue['total'] / $max_venue) * 100; ?>%; background: var(--color-marriage);"></div>
                                        </div>
                                        <small style="color: var(--text-secondary);"><?php echo number_format($percentage, 1); ?>%</small>
                                    </td>
                                </tr>
                                <?php $rank++; endforeach; ?>
                                <?php if (empty($top_marriage_venues)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--text-secondary); padding: 40px;">
                                        No marriage venue data available for this period
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ================================================== -->
            <!-- TAB 4: DEATHS                                       -->
            <!-- ================================================== -->
            <div class="tab-panel" data-tab="deaths">
                <div class="tab-charts-row">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div>
                                <h3 class="chart-title"><i class="fas fa-venus-mars"></i> Sex of Deceased</h3>
                                <p class="chart-subtitle">Male vs Female deaths in selected period</p>
                            </div>
                        </div>
                        <div class="chart-container small">
                            <canvas id="deathSexChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-card">
                        <div class="chart-header">
                            <div>
                                <h3 class="chart-title"><i class="fas fa-chart-bar"></i> Deaths by Age &amp; Sex</h3>
                                <p class="chart-subtitle">Age brackets split by Male / Female</p>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="deathAgeSexChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="tab-full-row">
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">
                                <i class="fas fa-map-marker-alt"></i>
                                Top Places of Death
                            </h3>
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Place</th>
                                    <th>Deaths</th>
                                    <th>Share</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $max_dp = !empty($top_death_places) ? max(array_column($top_death_places, 'total')) : 1;
                                $total_dp = array_sum(array_column($top_death_places, 'total'));
                                $rank = 1;
                                foreach ($top_death_places as $dp):
                                    $percentage = $total_dp > 0 ? ($dp['total'] / $total_dp) * 100 : 0;
                                ?>
                                <tr>
                                    <td>
                                        <span class="rank-badge <?php echo $rank <= 3 ? ($rank == 1 ? 'gold' : ($rank == 2 ? 'silver' : 'bronze')) : ''; ?>">
                                            <?php echo $rank; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($dp['place'] ?? 'Unknown'); ?></td>
                                    <td><strong><?php echo number_format($dp['total']); ?></strong></td>
                                    <td>
                                        <div class="progress-bar-container">
                                            <div class="progress-bar" style="width: <?php echo ($dp['total'] / $max_dp) * 100; ?>%; background: var(--color-death);"></div>
                                        </div>
                                        <small style="color: var(--text-secondary);"><?php echo number_format($percentage, 1); ?>%</small>
                                    </td>
                                </tr>
                                <?php $rank++; endforeach; ?>
                                <?php if (empty($top_death_places)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--text-secondary); padding: 40px;">
                                        No death location data available for this period
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ================================================== -->
            <!-- TAB 5: LICENSES                                     -->
            <!-- ================================================== -->
            <div class="tab-panel" data-tab="licenses">
                <div class="tab-full-row">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div>
                                <h3 class="chart-title"><i class="fas fa-users"></i> Groom vs Bride Age Profile</h3>
                                <p class="chart-subtitle">Age brackets at time of license application</p>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="groomBrideAgeChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="tab-full-row">
                    <div class="data-card">
                        <div class="data-card-header">
                            <h3 class="data-card-title">
                                <i class="fas fa-flag"></i>
                                Marriage License Applicant Citizenship &mdash; Grooms &amp; Brides
                            </h3>
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Citizenship</th>
                                    <th>Applicants</th>
                                    <th>Share</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $max_citizenship = !empty($citizenship_stats) ? max($citizenship_stats) : 1;
                                $total_citizenship = array_sum($citizenship_stats);
                                $rank = 1;
                                foreach ($citizenship_stats as $citizenship => $count):
                                    $percentage = $total_citizenship > 0 ? ($count / $total_citizenship) * 100 : 0;
                                ?>
                                <tr>
                                    <td>
                                        <span class="rank-badge <?php echo $rank <= 3 ? ($rank == 1 ? 'gold' : ($rank == 2 ? 'silver' : 'bronze')) : ''; ?>">
                                            <?php echo $rank; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($citizenship); ?></td>
                                    <td><strong><?php echo number_format($count); ?></strong></td>
                                    <td>
                                        <div class="progress-bar-container">
                                            <div class="progress-bar" style="width: <?php echo ($count / $max_citizenship) * 100; ?>%; background: var(--color-license);"></div>
                                        </div>
                                        <small style="color: var(--text-secondary);"><?php echo number_format($percentage, 1); ?>%</small>
                                    </td>
                                </tr>
                                <?php $rank++; endforeach; ?>
                                <?php if (empty($citizenship_stats)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--text-secondary); padding: 40px;">
                                        No citizenship data available for this period
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- /TABS -->

            <!-- Export Section -->
            <div class="export-section">
                <div class="export-header">
                    <h3 class="export-title">
                        <i class="fas fa-download"></i>
                        Export Reports
                    </h3>
                </div>
                <div class="export-options">
                    <div class="export-option" onclick="exportReport('pdf')">
                        <div class="export-option-icon pdf">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div class="export-option-label">PDF Report</div>
                        <div class="export-option-desc">Download full report as PDF</div>
                    </div>
                    <div class="export-option" onclick="exportReport('excel')">
                        <div class="export-option-icon excel">
                            <i class="fas fa-file-excel"></i>
                        </div>
                        <div class="export-option-label">Excel Export</div>
                        <div class="export-option-desc">Download data as Excel file</div>
                    </div>
                    <div class="export-option" onclick="exportReport('csv')">
                        <div class="export-option-icon csv">
                            <i class="fas fa-file-csv"></i>
                        </div>
                        <div class="export-option-label">CSV Export</div>
                        <div class="export-option-desc">Download raw data as CSV</div>
                    </div>
                    <div class="export-option" onclick="window.print()">
                        <div class="export-option-icon print">
                            <i class="fas fa-print"></i>
                        </div>
                        <div class="export-option-label">Print Report</div>
                        <div class="export-option-desc">Print current view</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/sidebar_scripts.php'; ?>

    <script>
        // Initialize Lucide icons
        document.addEventListener('DOMContentLoaded', function() {
            lucide.createIcons();
        });

        // Count-up Animation
        function animateValue(element, start, end, duration) {
            if (end === 0) {
                element.textContent = '0';
                return;
            }

            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const value = Math.floor(progress * (end - start) + start);
                element.textContent = value.toLocaleString();
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            window.requestAnimationFrame(step);
        }

        // Animate all stat numbers on page load
        window.addEventListener('load', () => {
            document.querySelectorAll('[data-count]').forEach(el => {
                const finalValue = parseInt(el.getAttribute('data-count'));
                animateValue(el, 0, finalValue, 1500);
            });
        });

        // Chart.js Configuration
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = '#6b7280';

        // Registry of chart instances — used by tab switcher to call resize()
        // because Chart.js renders at 0x0 when its container starts display:none
        const chartInstances = {};

        // Helper: draw "No data available" fallback on a canvas context
        function drawEmptyState(ctx, message) {
            const canvas = ctx.canvas;
            const cx = canvas.width / 2;
            const cy = canvas.height / 2;
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.font = '600 14px Inter, sans-serif';
            ctx.fillStyle = '#9ca3af';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(message || 'No data available', cx, cy);
        }

        // Monthly Trend Chart
        const monthlyCtx = document.getElementById('monthlyTrendChart').getContext('2d');
        const monthlyData = <?php echo json_encode($monthly_data); ?>;

        chartInstances.monthlyTrend = new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: monthlyData.map(d => d.short),
                datasets: [
                    {
                        label: 'Birth Certificates',
                        data: monthlyData.map(d => d.births),
                        borderColor: '#2196F3',
                        backgroundColor: 'rgba(33, 150, 243, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#2196F3',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    },
                    {
                        label: 'Marriage Certificates',
                        data: monthlyData.map(d => d.marriages),
                        borderColor: '#E91E63',
                        backgroundColor: 'rgba(233, 30, 99, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#E91E63',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    },
                    {
                        label: 'Death Certificates',
                        data: monthlyData.map(d => d.deaths),
                        borderColor: '#FF9800',
                        backgroundColor: 'rgba(255, 152, 0, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#FF9800',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    },
                    {
                        label: 'License Applications',
                        data: monthlyData.map(d => d.licenses),
                        borderColor: '#9C27B0',
                        backgroundColor: 'rgba(156, 39, 176, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#9C27B0',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: { size: 12, weight: '500' }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(255, 255, 255, 0.95)',
                        titleColor: '#1f2937',
                        bodyColor: '#6b7280',
                        borderColor: '#e5e7eb',
                        borderWidth: 1,
                        padding: 12,
                        boxPadding: 6,
                        usePointStyle: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0, 0, 0, 0.05)', drawBorder: false },
                        ticks: { font: { size: 11 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 } }
                    }
                },
                animation: { duration: 2000, easing: 'easeInOutQuart' }
            }
        });

        // Chart filter buttons
        document.querySelectorAll('.chart-filter').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.chart-filter').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const filter = this.dataset.filter;
                chartInstances.monthlyTrend.data.datasets.forEach((dataset, index) => {
                    if (filter === 'all') {
                        dataset.hidden = false;
                    } else {
                        const filterMap = { births: 0, marriages: 1, deaths: 2, licenses: 3 };
                        dataset.hidden = index !== filterMap[filter];
                    }
                });
                chartInstances.monthlyTrend.update();
            });
        });

        // Daily Chart
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        const dailyData = <?php echo json_encode($daily_registrations); ?>;

        chartInstances.daily = new Chart(dailyCtx, {
            type: 'bar',
            data: {
                labels: dailyData.map(d => d.date),
                datasets: [{
                    label: 'Registrations',
                    data: dailyData.map(d => d.count),
                    backgroundColor: 'rgba(103, 80, 164, 0.7)',
                    borderColor: '#6750A4',
                    borderWidth: 1,
                    borderRadius: 4,
                    barThickness: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(31, 41, 55, 0.95)',
                        padding: 10
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0, 0, 0, 0.05)', drawBorder: false },
                        ticks: { font: { size: 10 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 9 },
                            maxRotation: 45,
                            minRotation: 45,
                            callback: function(val, index) {
                                return index % 5 === 0 ? this.getLabelForValue(val) : '';
                            }
                        }
                    }
                },
                animation: { duration: 1500 }
            }
        });

        // Gender Distribution Chart
        const genderCtx = document.getElementById('genderChart').getContext('2d');
        const genderData = <?php echo json_encode($gender_distribution); ?>;

        const genderLabels = genderData.map(g => g.child_sex || 'Unknown');
        const genderValues = genderData.map(g => parseInt(g.count));
        const genderColors = genderLabels.map(label => {
            if (label.toLowerCase() === 'male') return '#3B82F6';
            if (label.toLowerCase() === 'female') return '#EC4899';
            return '#9CA3AF';
        });

        if (genderValues.length > 0 && genderValues.some(v => v > 0)) {
            chartInstances.gender = new Chart(genderCtx, {
                type: 'doughnut',
                data: {
                    labels: genderLabels,
                    datasets: [{
                        data: genderValues,
                        backgroundColor: genderColors,
                        borderWidth: 3,
                        borderColor: '#ffffff',
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { usePointStyle: true, padding: 12, font: { size: 11 } }
                        }
                    },
                    animation: { duration: 1500 }
                }
            });
        } else {
            drawEmptyState(genderCtx, 'No gender data available');
        }

        // Marriage Nature of Solemnization Chart
        const marriageNatureCtx = document.getElementById('marriageNatureChart').getContext('2d');
        const marriageNatureData = <?php echo json_encode($marriage_nature_distribution); ?>;

        const natureLabels = marriageNatureData.map(n => n.nature_of_solemnization || 'Unknown');
        const natureValues = marriageNatureData.map(n => parseInt(n.count));
        const natureColors = natureLabels.map(label => {
            if (label === 'Church') return '#9C27B0';
            if (label === 'Civil') return '#2196F3';
            if (label === 'Other Religious Sect') return '#FF9800';
            return '#9CA3AF';
        });

        if (natureValues.length > 0 && natureValues.some(v => v > 0)) {
            chartInstances.marriageNature = new Chart(marriageNatureCtx, {
                type: 'doughnut',
                data: {
                    labels: natureLabels,
                    datasets: [{
                        data: natureValues,
                        backgroundColor: natureColors,
                        borderWidth: 3,
                        borderColor: '#ffffff',
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { usePointStyle: true, padding: 12, font: { size: 11 } }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(31, 41, 55, 0.95)',
                            padding: 12,
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                    return `${context.label}: ${context.parsed.toLocaleString()} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    animation: { duration: 1500 }
                }
            });
        } else {
            drawEmptyState(marriageNatureCtx, 'No marriage data available');
        }

        // ==============================
        // NEW CHARTS — Births Tab
        // ==============================

        // Type of Birth (Single / Twin / Triplets)
        (function() {
            const ctx = document.getElementById('typeOfBirthChart').getContext('2d');
            const data = <?php echo json_encode($type_of_birth_distribution); ?>;
            const labels = data.map(d => d.type_of_birth || 'Unknown');
            const values = data.map(d => parseInt(d.count));

            if (values.length > 0 && values.some(v => v > 0)) {
                chartInstances.typeOfBirth = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: ['#6366F1', '#F59E0B', '#10B981', '#EF4444', '#8B5CF6'],
                            borderWidth: 3,
                            borderColor: '#ffffff',
                            hoverOffset: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '60%',
                        plugins: {
                            legend: { position: 'bottom', labels: { usePointStyle: true, padding: 12, font: { size: 11 } } }
                        },
                        animation: { duration: 1200 }
                    }
                });
            } else {
                drawEmptyState(ctx, 'No type-of-birth data available');
            }
        })();

        // Birth Order (1st, 2nd, 3rd, 4th+)
        (function() {
            const ctx = document.getElementById('birthOrderChart').getContext('2d');
            const raw = <?php echo json_encode($birth_order_distribution); ?>;
            // Bucket 4th and above
            const buckets = { '1st': 0, '2nd': 0, '3rd': 0, '4th+': 0 };
            raw.forEach(r => {
                const order = String(r.birth_order || '').trim();
                const cnt = parseInt(r.count) || 0;
                if (order === '1' || order.toLowerCase() === '1st' || order.toLowerCase() === 'first') buckets['1st'] += cnt;
                else if (order === '2' || order.toLowerCase() === '2nd' || order.toLowerCase() === 'second') buckets['2nd'] += cnt;
                else if (order === '3' || order.toLowerCase() === '3rd' || order.toLowerCase() === 'third') buckets['3rd'] += cnt;
                else if (order !== '') buckets['4th+'] += cnt;
            });
            const labels = Object.keys(buckets);
            const values = labels.map(l => buckets[l]);

            if (values.some(v => v > 0)) {
                chartInstances.birthOrder = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Births',
                            data: values,
                            backgroundColor: 'rgba(16, 185, 129, 0.7)',
                            borderColor: '#10B981',
                            borderWidth: 1,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 10 } } },
                            x: { grid: { display: false }, ticks: { font: { size: 11 } } }
                        },
                        animation: { duration: 1200 }
                    }
                });
            } else {
                drawEmptyState(ctx, 'No birth order data available');
            }
        })();

        // Place Type of Birth (Hospital / Home / Barangay Health Center / Other)
        (function() {
            const ctx = document.getElementById('birthPlaceTypeChart').getContext('2d');
            const data = <?php echo json_encode($birth_place_type_distribution); ?>;
            const labels = data.map(d => d.place_type || 'Unknown');
            const values = data.map(d => parseInt(d.count));

            if (values.length > 0 && values.some(v => v > 0)) {
                chartInstances.birthPlaceType = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Births',
                            data: values,
                            backgroundColor: ['#3B82F6', '#F59E0B', '#10B981', '#8B5CF6', '#6B7280'],
                            borderWidth: 1,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 10 } } },
                            x: { grid: { display: false }, ticks: { font: { size: 10 }, maxRotation: 20, minRotation: 0 } }
                        },
                        animation: { duration: 1200 }
                    }
                });
            } else {
                drawEmptyState(ctx, 'No place type data available');
            }
        })();

        // ==============================
        // NEW CHARTS — Deaths Tab
        // ==============================

        // Sex of Deceased
        (function() {
            const ctx = document.getElementById('deathSexChart').getContext('2d');
            const data = <?php echo json_encode($death_sex_distribution); ?>;
            const labels = data.map(d => d.sex || 'Unknown');
            const values = data.map(d => parseInt(d.count));
            const colors = labels.map(l => {
                const n = l.toLowerCase();
                if (n === 'male') return '#3B82F6';
                if (n === 'female') return '#EC4899';
                return '#9CA3AF';
            });

            if (values.length > 0 && values.some(v => v > 0)) {
                chartInstances.deathSex = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: colors,
                            borderWidth: 3,
                            borderColor: '#ffffff',
                            hoverOffset: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '60%',
                        plugins: {
                            legend: { position: 'bottom', labels: { usePointStyle: true, padding: 12, font: { size: 11 } } }
                        },
                        animation: { duration: 1200 }
                    }
                });
            } else {
                drawEmptyState(ctx, 'No sex data available');
            }
        })();

        // Deaths by Age & Sex (grouped bar)
        (function() {
            const ctx = document.getElementById('deathAgeSexChart').getContext('2d');
            const matrix = <?php echo json_encode($death_age_sex_matrix); ?>;
            const labels = Object.keys(matrix);
            const male = labels.map(l => matrix[l].Male || 0);
            const female = labels.map(l => matrix[l].Female || 0);
            const hasData = male.some(v => v > 0) || female.some(v => v > 0);

            if (hasData) {
                chartInstances.deathAgeSex = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Male',
                                data: male,
                                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                                borderColor: '#3B82F6',
                                borderWidth: 1,
                                borderRadius: 4
                            },
                            {
                                label: 'Female',
                                data: female,
                                backgroundColor: 'rgba(236, 72, 153, 0.8)',
                                borderColor: '#EC4899',
                                borderWidth: 1,
                                borderRadius: 4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { usePointStyle: true, padding: 12, font: { size: 11 } } }
                        },
                        scales: {
                            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 10 } } },
                            x: { grid: { display: false }, ticks: { font: { size: 10 }, maxRotation: 30, minRotation: 0 } }
                        },
                        animation: { duration: 1200 }
                    }
                });
            } else {
                drawEmptyState(ctx, 'No age & sex data available');
            }
        })();

        // ==============================
        // NEW CHARTS — Marriages Tab
        // ==============================

        // Couple Citizenship (horizontal bar, top 10)
        (function() {
            const ctx = document.getElementById('marriageCitizenshipChart').getContext('2d');
            const data = <?php echo json_encode($marriage_couple_citizenship); ?>;
            const labels = Object.keys(data);
            const values = Object.values(data).map(v => parseInt(v));

            if (values.length > 0 && values.some(v => v > 0)) {
                chartInstances.marriageCitizenship = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Spouses (Husbands + Wives)',
                            data: values,
                            backgroundColor: 'rgba(236, 72, 153, 0.75)',
                            borderColor: '#EC4899',
                            borderWidth: 1,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 10 } } },
                            y: { grid: { display: false }, ticks: { font: { size: 10 } } }
                        },
                        animation: { duration: 1200 }
                    }
                });
            } else {
                drawEmptyState(ctx, 'No citizenship data available');
            }
        })();

        // Husband vs Wife Age at Marriage (grouped bar)
        (function() {
            const ctx = document.getElementById('husbandWifeAgeChart').getContext('2d');
            const matrix = <?php echo json_encode($husband_wife_age); ?>;
            const labels = Object.keys(matrix);
            const husband = labels.map(l => matrix[l].Husband || 0);
            const wife = labels.map(l => matrix[l].Wife || 0);
            const hasData = husband.some(v => v > 0) || wife.some(v => v > 0);

            if (hasData) {
                chartInstances.husbandWifeAge = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Husband',
                                data: husband,
                                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                                borderColor: '#3B82F6',
                                borderWidth: 1,
                                borderRadius: 4
                            },
                            {
                                label: 'Wife',
                                data: wife,
                                backgroundColor: 'rgba(236, 72, 153, 0.8)',
                                borderColor: '#EC4899',
                                borderWidth: 1,
                                borderRadius: 4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { usePointStyle: true, padding: 12, font: { size: 11 } } }
                        },
                        scales: {
                            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 10 } } },
                            x: { grid: { display: false }, ticks: { font: { size: 11 } } }
                        },
                        animation: { duration: 1200 }
                    }
                });
            } else {
                drawEmptyState(ctx, 'No age data available');
            }
        })();

        // ==============================
        // NEW CHART — Licenses Tab
        // ==============================

        // Groom vs Bride Age Profile (grouped bar)
        (function() {
            const ctx = document.getElementById('groomBrideAgeChart').getContext('2d');
            const matrix = <?php echo json_encode($groom_bride_age); ?>;
            const labels = Object.keys(matrix);
            const groom = labels.map(l => matrix[l].Groom || 0);
            const bride = labels.map(l => matrix[l].Bride || 0);
            const hasData = groom.some(v => v > 0) || bride.some(v => v > 0);

            if (hasData) {
                chartInstances.groomBrideAge = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Groom',
                                data: groom,
                                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                                borderColor: '#3B82F6',
                                borderWidth: 1,
                                borderRadius: 4
                            },
                            {
                                label: 'Bride',
                                data: bride,
                                backgroundColor: 'rgba(236, 72, 153, 0.8)',
                                borderColor: '#EC4899',
                                borderWidth: 1,
                                borderRadius: 4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { usePointStyle: true, padding: 12, font: { size: 11 } } }
                        },
                        scales: {
                            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 10 } } },
                            x: { grid: { display: false }, ticks: { font: { size: 11 } } }
                        },
                        animation: { duration: 1200 }
                    }
                });
            } else {
                drawEmptyState(ctx, 'No applicant age data available');
            }
        })();

        // ==============================
        // TAB SWITCHING
        // ==============================
        (function() {
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabPanels = document.querySelectorAll('.tab-panel');

            function activateTab(tabName) {
                tabButtons.forEach(btn => {
                    btn.classList.toggle('active', btn.dataset.tab === tabName);
                    btn.setAttribute('aria-selected', btn.dataset.tab === tabName ? 'true' : 'false');
                });
                tabPanels.forEach(panel => {
                    const isActive = panel.dataset.tab === tabName;
                    panel.classList.toggle('active', isActive);
                    if (isActive) {
                        // Critical: Chart.js canvases rendered while display:none are 0x0.
                        // Resize every chart instance after the panel becomes visible.
                        requestAnimationFrame(() => {
                            Object.values(chartInstances).forEach(chart => {
                                if (chart && typeof chart.resize === 'function') {
                                    try { chart.resize(); } catch (e) { /* ignore */ }
                                }
                            });
                        });
                    }
                });
            }

            tabButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    activateTab(this.dataset.tab);
                });
            });

            // Auto-activate tab from URL param
            const urlParams = new URLSearchParams(window.location.search);
            const certType = urlParams.get('certificate_type');
            const typeToTab = {
                'birth': 'births',
                'marriage': 'marriages',
                'death': 'deaths',
                'license': 'licenses'
            };
            if (certType && typeToTab[certType]) {
                activateTab(typeToTab[certType]);
            }
        })();

        // Export functionality
        function exportReport(format) {
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;

            // Show loading state
            const btn = event.target.closest('.export-option');
            const originalContent = btn.innerHTML;
            btn.innerHTML = '<div class="export-option-icon"><i class="fas fa-spinner fa-spin"></i></div><div class="export-option-label">Generating...</div>';

            // Simulate export (replace with actual API call)
            setTimeout(() => {
                btn.innerHTML = originalContent;

                // For now, show an alert. In production, this would trigger actual download
                alert(`Exporting ${format.toUpperCase()} report for period ${dateFrom} to ${dateTo}\n\nThis feature would generate and download the report file.`);
            }, 1500);
        }

        // Refresh data
        function refreshData() {
            location.reload();
        }

        // Apply filters function
        function applyFilters() {
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            const certificateFilter = document.getElementById('certificateFilter').value;
            const periodFilter = document.getElementById('periodFilter').value;
            const sortBy = document.getElementById('sortBy').value;

            // Build query parameters
            const params = new URLSearchParams({
                date_from: dateFrom,
                date_to: dateTo,
                certificate_type: certificateFilter,
                period: periodFilter,
                sort: sortBy
            });

            // Show loading indicator
            const applyBtn = document.querySelector('.btn-apply-filters');
            const originalHTML = applyBtn.innerHTML;
            applyBtn.innerHTML = '<i data-lucide="loader" class="spin"></i> Applying...';
            applyBtn.disabled = true;

            // Reload with filters
            setTimeout(() => {
                window.location.href = `?${params.toString()}`;
            }, 500);
        }

        // Auto-apply period filter
        document.getElementById('periodFilter')?.addEventListener('change', function() {
            const period = this.value;
            const today = new Date();
            let dateFrom, dateTo;

            switch(period) {
                case 'month':
                    dateFrom = new Date(today.getFullYear(), today.getMonth(), 1);
                    dateTo = today;
                    break;
                case 'quarter':
                    const quarter = Math.floor(today.getMonth() / 3);
                    dateFrom = new Date(today.getFullYear(), quarter * 3, 1);
                    dateTo = today;
                    break;
                case 'year':
                    dateFrom = new Date(today.getFullYear(), 0, 1);
                    dateTo = today;
                    break;
                case 'all':
                    dateFrom = new Date(2020, 0, 1); // Default start
                    dateTo = today;
                    break;
            }

            if (dateFrom && dateTo) {
                document.getElementById('dateFrom').value = dateFrom.toISOString().split('T')[0];
                document.getElementById('dateTo').value = dateTo.toISOString().split('T')[0];
            }
        });

        // Restore filter values from URL parameters on page load
        window.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);

            // Restore date range
            const dateFrom = urlParams.get('date_from');
            const dateTo = urlParams.get('date_to');
            if (dateFrom) document.getElementById('dateFrom').value = dateFrom;
            if (dateTo) document.getElementById('dateTo').value = dateTo;

            // Restore certificate type
            const certType = urlParams.get('certificate_type');
            if (certType) document.getElementById('certificateFilter').value = certType;

            // Restore period
            const period = urlParams.get('period');
            if (period) document.getElementById('periodFilter').value = period;

            // Restore sort
            const sort = urlParams.get('sort');
            if (sort) document.getElementById('sortBy').value = sort;
        });
    </script>
</body>
</html>
