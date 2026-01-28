<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Database configuration
$db_host = 'localhost';
$db_user = 'rank3598_apk';
$db_pass = 'Hakim123!';
$db_name = 'rank3598_apk';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]));
}

$conn->set_charset("utf8");

$action = $_GET['action'] ?? '';

switch($action) {
    case 'get_all_data':
        get_all_data();
        break;
    case 'get_banners':
        get_banners();
        break;
    case 'add_banner':
        add_banner();
        break;
    case 'delete_banner':
        delete_banner();
        break;
    case 'get_categories':
        get_categories();
        break;
    case 'add_category':
        add_category();
        break;
    case 'delete_category':
        delete_category();
        break;
    case 'get_campaigns':
        get_campaigns();
        break;
    case 'add_campaign':
        add_campaign();
        break;
    case 'delete_campaign':
        delete_campaign();
        break;
    case 'get_banner_recommendations':
        get_banner_recommendations();
        break;
    case 'add_banner_recommendation':
        add_banner_recommendation();
        break;
    case 'delete_banner_recommendation':
        delete_banner_recommendation();
        break;
    case 'get_recommendations':
        get_recommendations();
        break;
    case 'add_recommendation':
        add_recommendation();
        break;
    case 'delete_recommendation':
        delete_recommendation();
        break;
    case 'get_donor_prayers':
        get_donor_prayers();
        break;
    case 'add_donor_prayer':
        add_donor_prayer();
        break;
    case 'delete_donor_prayer':
        delete_donor_prayer();
        break;
    case 'get_campaign_stats':
        get_campaign_stats();
        break;
    case 'get_quick_campaign_data':
        get_quick_campaign_data();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function get_all_data() {
    global $conn;
    
    $banners = get_banners_data();
    $categories = get_categories_data();
    $latest_campaigns = get_campaigns_data('latest', 5);
    $event_recommendations = get_campaigns_data('event', 5);
    $favorite_categories = get_campaigns_data('favorite', 5);
    $banner_recommendations = get_banner_recommendations_data();
    $recommendations = get_recommendations_data(5);
    $donor_prayers = get_donor_prayers_data();
    
    echo json_encode([
        'success' => true,
        'banners' => $banners,
        'categories' => $categories,
        'latest_campaigns' => $latest_campaigns,
        'event_recommendations' => $event_recommendations,
        'favorite_categories' => $favorite_categories,
        'banner_recommendations' => $banner_recommendations,
        'recommendations' => $recommendations,
        'donor_prayers' => $donor_prayers
    ]);
}

function get_banners() {
    echo json_encode(['success' => true, 'data' => get_banners_data()]);
}

function get_banners_data() {
    global $conn;
    $result = $conn->query("SELECT * FROM banners ORDER BY `order` ASC");
    $banners = [];
    while($row = $result->fetch_assoc()) {
        $banners[] = $row;
    }
    return $banners;
}

function add_banner() {
    global $conn;
    $title = $conn->real_escape_string($_POST['title'] ?? '');
    $subtitle = $conn->real_escape_string($_POST['subtitle'] ?? '');
    $image = $conn->real_escape_string($_POST['image'] ?? '');
    $order = intval($_POST['order'] ?? 0);
    
    $sql = "INSERT INTO banners (title, subtitle, image, `order`) VALUES ('$title', '$subtitle', '$image', $order)";
    
    if($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'Banner added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
}

function delete_banner() {
    global $conn;
    $id = intval($_GET['id'] ?? 0);
    
    $sql = "DELETE FROM banners WHERE id = $id";
    
    if($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'Banner deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
}

function get_categories() {
    echo json_encode(['success' => true, 'data' => get_categories_data()]);
}

function get_categories_data() {
    global $conn;
    $result = $conn->query("SELECT * FROM categories ORDER BY `order` ASC");
    $categories = [];
    while($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    return $categories;
}

function add_category() {
    global $conn;
    $name = $conn->real_escape_string($_POST['name'] ?? '');
    $icon = $conn->real_escape_string($_POST['icon'] ?? '');
    $link = $conn->real_escape_string($_POST['link'] ?? '');
    $order = intval($_POST['order'] ?? 0);
    
    $sql = "INSERT INTO categories (name, icon, link, `order`) VALUES ('$name', '$icon', '$link', $order)";
    
    if($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'Category added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
}

function delete_category() {
    global $conn;
    $id = intval($_GET['id'] ?? 0);
    
    $sql = "DELETE FROM categories WHERE id = $id";
    
    if($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
}

function get_campaigns() {
    $type = $_GET['type'] ?? 'latest';
    $limit = intval($_GET['limit'] ?? 5);
    echo json_encode(['success' => true, 'data' => get_campaigns_data($type, $limit)]);
}

function get_campaigns_data($type = 'latest', $limit = 5) {
    global $conn;
    $result = $conn->query("SELECT * FROM campaigns WHERE type = '$type' LIMIT $limit");
    $campaigns = [];
    while($row = $result->fetch_assoc()) {
        $campaigns[] = $row;
    }
    return $campaigns;
}

function add_campaign() {
    global $conn;
    $title = $conn->real_escape_string($_POST['title'] ?? '');
    $emoji = $conn->real_escape_string($_POST['emoji'] ?? '');
    $image = $conn->real_escape_string($_POST['image'] ?? '');
    $organizer = $conn->real_escape_string($_POST['organizer'] ?? '');
    $progress = intval($_POST['progress'] ?? 0);
    $amount = $conn->real_escape_string($_POST['amount'] ?? '');
    $link = $conn->real_escape_string($_POST['link'] ?? '');
    $type = $conn->real_escape_string($_POST['type'] ?? 'latest');
    
    $sql = "INSERT INTO campaigns (title, emoji, image, organizer, progress, amount, link, type) 
            VALUES ('$title', '$emoji', '$image', '$organizer', $progress, '$amount', '$link', '$type')";
    
    if($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'Campaign added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
}

function delete_campaign() {
    global $conn;
    $id = intval($_GET['id'] ?? 0);
    
    $sql = "DELETE FROM campaigns WHERE id = $id";
    
    if($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'Campaign deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
}

function get_banner_recommendations() {
    echo json_encode(['success' => true, 'data' => get_banner_recommendations_data()]);
}

function get_banner_recommendations_data() {
    global $conn;
    $result = $conn->query("SELECT * FROM banner_recommendations ORDER BY `order` ASC");
    $banners = [];
    while($row = $result->fetch_assoc()) {
        $banners[] = $row;
    }
    return $banners;
}

function add_banner_recommendation() {
    global $conn;
    $image = $conn->real_escape_string($_POST['image'] ?? '');
    $link = $conn->real_escape_string($_POST['link'] ?? '');
    $order = intval($_POST['order'] ?? 0);
    
    $sql = "INSERT INTO banner_recommendations (image, link, `order`) VALUES ('$image', '$link', $order)";
    
    if($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'Banner recommendation added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
}

function delete_banner_recommendation() {
    global $conn;
    $id = intval($_GET['id'] ?? 0);
    
    $sql = "DELETE FROM banner_recommendations WHERE id = $id";
    
    if($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'Banner recommendation deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
}

function get_recommendations() {
    $limit = intval($_GET['limit'] ?? 5);
    echo json_encode(['success' => true, 'data' => get_recommendations_data($limit)]);
}

function get_recommendations_data($limit = 5) {
    global $conn;
    // Get random recommendations
    $result = $conn->query("SELECT * FROM recommendations ORDER BY RAND() LIMIT $limit");
    $recommendations = [];
    while($row = $result->fetch_assoc()) {
        $recommendations[] = $row;
    }
    return $recommendations;
}

function add_recommendation() {
    global $conn;
    $title = $conn->real_escape_string($_POST['title'] ?? '');
    $emoji = $conn->real_escape_string($_POST['emoji'] ?? '');
    $image = $conn->real_escape_string($_POST['image'] ?? '');
    $organizer = $conn->real_escape_string($_POST['organizer'] ?? '');
    $progress = intval($_POST['progress'] ?? 0);
    $amount = $conn->real_escape_string($_POST['amount'] ?? '');
    $days_left = $conn->real_escape_string($_POST['days_left'] ?? '');
    $link = $conn->real_escape_string($_POST['link'] ?? '');
    
    $sql = "INSERT INTO recommendations (title, emoji, image, organizer, progress, amount, days_left, link) 
            VALUES ('$title', '$emoji', '$image', '$organizer', $progress, '$amount', '$days_left', '$link')";
    
    if($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'Recommendation added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
}

function delete_recommendation() {
    global $conn;
    $id = intval($_GET['id'] ?? 0);
    
    $sql = "DELETE FROM recommendations WHERE id = $id";
    
    if($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'Recommendation deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
}

// ============================================================================
// DONOR PRAYERS FUNCTIONS
// ============================================================================

function get_donor_prayers() {
    echo json_encode(['success' => true, 'data' => get_donor_prayers_data()]);
}

function get_donor_prayers_data() {
    global $conn;
    // Cek apakah table donor_prayers ada
    $checkTable = $conn->query("SHOW TABLES LIKE 'donor_prayers'");
    if ($checkTable->num_rows == 0) {
        return [];
    }
    
    $result = $conn->query("SELECT * FROM donor_prayers ORDER BY created_at DESC LIMIT 10");
    $prayers = [];
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $prayers[] = $row;
        }
    }
    return $prayers;
}

function add_donor_prayer() {
    global $conn;
    
    // Cek apakah table ada, jika tidak buat
    $checkTable = $conn->query("SHOW TABLES LIKE 'donor_prayers'");
    if ($checkTable->num_rows == 0) {
        $createTable = "CREATE TABLE donor_prayers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            donor_name VARCHAR(100) NOT NULL,
            donor_image VARCHAR(255),
            prayer_text TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->query($createTable);
    }
    
    $donor_name = $conn->real_escape_string($_POST['donor_name'] ?? '');
    $donor_image = $conn->real_escape_string($_POST['donor_image'] ?? '');
    $prayer_text = $conn->real_escape_string($_POST['prayer_text'] ?? '');
    
    $sql = "INSERT INTO donor_prayers (donor_name, donor_image, prayer_text) 
            VALUES ('$donor_name', '$donor_image', '$prayer_text')";
    
    if($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'Prayer added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
}

function delete_donor_prayer() {
    global $conn;
    $id = intval($_GET['id'] ?? 0);
    
    $sql = "DELETE FROM donor_prayers WHERE id = $id";
    
    if($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'Prayer deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
}

function get_campaign_stats() {
    global $conn;
    $id = intval($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid campaign ID']);
        return;
    }
    
    $result = $conn->query("SELECT target_terkumpul, donasi_terkumpul FROM campaigns WHERE id = $id");
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'target' => intval($row['target_terkumpul']),
            'terkumpul' => intval($row['donasi_terkumpul'])
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Campaign not found']);
    }
}

function get_quick_campaign_data() {
    global $conn;
    $id = intval($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid campaign ID']);
        return;
    }
    
    // Get campaign data
    $campaign_result = $conn->query("SELECT target_terkumpul, donasi_terkumpul FROM campaigns WHERE id = $id");
    
    if (!$campaign_result || $campaign_result->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Campaign not found']);
        return;
    }
    
    $campaign = $campaign_result->fetch_assoc();
    $target = intval($campaign['target_terkumpul']);
    $terkumpul = intval($campaign['donasi_terkumpul']);
    
    // Get recent donations (last 20)
    $donations = [];
    $donations_result = $conn->query("SELECT donor_name, amount, message, is_anonymous, created_at FROM donations WHERE campaign_id = $id AND status = 'PAID' ORDER BY created_at DESC LIMIT 20");
    
    if ($donations_result) {
        while ($row = $donations_result->fetch_assoc()) {
            $donations[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'target' => $target,
        'terkumpul' => $terkumpul,
        'donations' => $donations
    ]);
}

$conn->close();
?>