<?php
/**
 * Empire RP - API Backend
 * Whirlpool hashing (WP_Hash من SAMP)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

// ============================================================
// ⚙️  إعدادات قاعدة البيانات — غيّر هذه فقط
// ============================================================
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'server_800_Empire');
define('DB_USER', 'server_800');
define('DB_PASS', 'fujzhedx71');
// ============================================================

function db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit;
        }
    }
    return $pdo;
}

function json_out($data) {
    echo json_encode($data);
    exit;
}

// إنشاء جداول Avito تلقائياً إذا لم تكن موجودة
function ensureAvitoTables() {
    $pdo = db();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS avito_listings (
            id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            seller     VARCHAR(64) NOT NULL,
            title      VARCHAR(200) NOT NULL,
            price      INT UNSIGNED NOT NULL DEFAULT 0,
            descr      TEXT,
            img        MEDIUMTEXT,
            created_at DATETIME DEFAULT NOW()
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS avito_messages (
            id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            listing_id BIGINT UNSIGNED NOT NULL,
            sender     VARCHAR(64) NOT NULL,
            msg_text   TEXT,
            is_offer   TINYINT(1) DEFAULT 0,
            offer_amt  INT UNSIGNED DEFAULT 0,
            is_read    TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT NOW(),
            INDEX idx_listing (listing_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
}

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ─── LOGIN ───────────────────────────────────────────────
    case 'login':
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';

        if (!$username || !$password)
            json_out(['success' => false, 'message' => 'Username and password are required']);

        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user)
            json_out(['success' => false, 'message' => 'Account not found']);

        $hashed = hash('whirlpool', $password);
        if (strtolower($hashed) !== strtolower($user['password']))
            json_out(['success' => false, 'message' => 'Incorrect password']);

        $pdo->prepare("UPDATE users SET lastlogin = NOW() WHERE uid = ?")->execute([$user['uid']]);

        // بيانات البيت
        $house = null;
        $h = $pdo->prepare("SELECT * FROM houses WHERE ownerid = ? LIMIT 1");
        $h->execute([$user['uid']]);
        $hRow = $h->fetch(PDO::FETCH_ASSOC);
        if ($hRow) {
            $house = [
                'owner'     => $hRow['owner'],
                'type'      => $hRow['type'],
                'level'     => $hRow['level'],
                'cash'      => $hRow['cash'],
                'price'     => $hRow['price'],
                'rentprice' => $hRow['rentprice'],
                'locked'    => $hRow['locked'],
                'dcash'     => $hRow['dcash'],
            ];
        }

        // بيانات البيزنس
        $business = null;
        $bq = $pdo->prepare("SELECT * FROM businesses WHERE ownerid = ? LIMIT 1");
        $bq->execute([$user['uid']]);
        $bRow = $bq->fetch(PDO::FETCH_ASSOC);
        if ($bRow) {
            $business = [
                'name'     => $bRow['name'],
                'owner'    => $bRow['owner'],
                'type'     => $bRow['type'],
                'price'    => $bRow['price'],
                'entryfee' => $bRow['entryfee'],
                'cash'     => $bRow['cash'],
                'products' => $bRow['products'],
                'locked'   => $bRow['locked'],
            ];
        }

        // الأسلحة
        $weapons = [];
        for ($i = 0; $i <= 12; $i++) {
            $wcol = 'weapon_' . $i;
            $acol = 'ammo_' . $i;
            if (isset($user[$wcol]) && $user[$wcol] > 0) {
                $weapons[] = ['slot' => $i, 'weapon' => $user[$wcol], 'ammo' => $user[$acol] ?? 0];
            }
        }

        $player = [
            'uid'           => $user['uid'],
            'username'      => $user['username'],
            'level'         => $user['level'],
            'exp'           => $user['exp'],
            'cash'          => $user['cash'],
            'bank'          => $user['bank'],
            'dirtycash'     => $user['dirtycash']      ?? 0,
            'bitcoin'       => $user['bitcoin']        ?? 0,
            'paycheck'      => $user['paycheck']       ?? 0,
            'health'        => round($user['health']),
            'armor'         => round($user['armor']),
            'hunger'        => $user['hunger']         ?? 100,
            'thirst'        => $user['thirst']         ?? 100,
            'hours'         => $user['hours'],
            'minutes'       => $user['minutes'],
            'crimes'        => $user['crimes'],
            'arrested'      => $user['arrested'],
            'warnings'      => $user['warnings'],
            'job'           => $user['job'],
            'faction'       => $user['faction'],
            'gang'          => $user['gang'],
            'vippackage'    => $user['vippackage'],
            'phone'         => $user['phone'],
            'adminlevel'    => $user['adminlevel'],
            'helperlevel'   => $user['helperlevel']    ?? 0,
            // inventory - exact column names
            'materials'     => $user['materials']      ?? 0,
            'pot'           => $user['pot']            ?? 0,
            'weed'          => $user['weed']           ?? 0,
            'crack'         => $user['crack']          ?? 0,
            'meth'          => $user['meth']           ?? 0,
            'painkillers'   => $user['painkillers']    ?? 0,
            'seeds'         => $user['seeds']          ?? 0,
            'bakingsoda'    => $user['bakingsoda']      ?? 0,
            'cigars'        => $user['cigars']          ?? 0,
            'spraycans'     => $user['spraycans']       ?? 0,
            'bombs'         => $user['bombs']           ?? 0,
            'gascan'        => $user['gascan']          ?? 0,
            'repairkit'     => $user['repairkit']       ?? 0,
            'fishingrod'    => $user['fishingrod']      ?? 0,
            'backpack'      => $user['backpack']        ?? 0,
            // mining/fishing items
            'stone'         => $user['stone']           ?? 0,
            'iron'          => $user['iron']            ?? 0,
            'shovel'        => $user['shovel']          ?? 0,
            'diamond'       => $user['diamond']         ?? 0,
            'silver'        => $user['silver']          ?? 0,
            'fish'          => $user['fishweight']      ?? 0, // fishweight = كمية الأسماك
            // skills
            'courierskill'  => $user['courierskill']    ?? 0,
            'fishingskill'  => $user['fishingskill']    ?? 0,
            'guardskill'    => $user['guardskill']      ?? 0,
            'weaponskill'   => $user['weaponskill']     ?? 0,
            'mechanicskill' => $user['mechanicskill']   ?? 0,
            'lawyerskill'   => $user['lawyerskill']     ?? 0,
            'detectiveskill'=> $user['detectiveskill']  ?? 0,
            'smugglerskill' => $user['smugglerskill']   ?? 0,
            // stats
            'completedhits' => $user['completedhits']   ?? 0,
            'failedhits'    => $user['failedhits']      ?? 0,
            'wantedlevel'   => $user['wantedlevel']     ?? 0,
            'jailtime'      => $user['jailtime']        ?? 0,
            'money_earned'  => $user['money_earned']    ?? 0,
            'money_spent'   => $user['money_spent']     ?? 0,
            'regdate'       => $user['regdate'],
            'lastlogin'     => $user['lastlogin'],
            'house'         => $house,
            'business'      => $business,
            'weapons'       => $weapons,
        ];

        json_out(['success' => true, 'player' => $player]);
        break;

    // ─── BANK ────────────────────────────────────────────────
    case 'bank_action':
        $uid    = intval($input['uid']    ?? 0);
        $type   = $input['type']          ?? '';
        $amount = intval($input['amount'] ?? 0);
        if (!$uid || $amount <= 0) json_out(['success' => false, 'message' => 'Invalid input']);
        $pdo = db();
        if ($type === 'deposit') {
            $pdo->prepare("UPDATE users SET cash = cash - ?, bank = bank + ? WHERE uid = ? AND cash >= ?")->execute([$amount, $amount, $uid, $amount]);
        } elseif ($type === 'withdraw') {
            $pdo->prepare("UPDATE users SET bank = bank - ?, cash = cash + ? WHERE uid = ? AND bank >= ?")->execute([$amount, $amount, $uid, $amount]);
        }
        json_out(['success' => true]);
        break;

    // ─── TRANSFER ────────────────────────────────────────────
    case 'transfer':
        $uid    = intval($input['uid']    ?? 0);
        $target = trim($input['target']   ?? '');
        $amount = intval($input['amount'] ?? 0);
        if (!$uid || !$target || $amount <= 0) json_out(['success' => false, 'message' => 'Invalid input']);
        $pdo = db();
        $ts = $pdo->prepare("SELECT uid FROM users WHERE username = ? LIMIT 1");
        $ts->execute([$target]);
        $tuser = $ts->fetch(PDO::FETCH_ASSOC);
        if (!$tuser) json_out(['success' => false, 'message' => 'Player not found']);
        $pdo->prepare("UPDATE users SET bank = bank - ? WHERE uid = ? AND bank >= ?")->execute([$amount, $uid, $amount]);
        $pdo->prepare("UPDATE users SET bank = bank + ? WHERE uid = ?")->execute([$amount, $tuser['uid']]);
        json_out(['success' => true]);
        break;

    // ─── HOUSE ───────────────────────────────────────────────
    case 'toggle_lock':
        $uid    = intval($input['uid']    ?? 0);
        $locked = intval($input['locked'] ?? 0);
        if (!$uid) json_out(['success' => false, 'message' => 'Invalid uid']);
        db()->prepare("UPDATE houses SET locked = ? WHERE ownerid = ?")->execute([$locked, $uid]);
        json_out(['success' => true]);
        break;

    case 'house_deposit':
        $uid    = intval($input['uid']    ?? 0);
        $amount = intval($input['amount'] ?? 0);
        if (!$uid || $amount <= 0) json_out(['success' => false, 'message' => 'Invalid input']);
        $pdo = db();
        $pdo->prepare("UPDATE users  SET cash = cash - ? WHERE uid = ? AND cash >= ?")->execute([$amount, $uid, $amount]);
        $pdo->prepare("UPDATE houses SET cash = cash + ? WHERE ownerid = ?")->execute([$amount, $uid]);
        json_out(['success' => true]);
        break;

    case 'house_withdraw':
        $uid    = intval($input['uid']    ?? 0);
        $amount = intval($input['amount'] ?? 0);
        if (!$uid || $amount <= 0) json_out(['success' => false, 'message' => 'Invalid input']);
        $pdo = db();
        $pdo->prepare("UPDATE houses SET cash = cash - ? WHERE ownerid = ? AND cash >= ?")->execute([$amount, $uid, $amount]);
        $pdo->prepare("UPDATE users  SET cash = cash + ? WHERE uid = ?")->execute([$amount, $uid]);
        json_out(['success' => true]);
        break;

    // ─── AVITO LISTINGS ──────────────────────────────────────
    case 'avito_post':
        ensureAvitoTables();
        $seller = trim($input['seller'] ?? '');
        $title  = trim($input['title']  ?? '');
        $price  = intval($input['price'] ?? 0);
        $descr  = trim($input['descr']  ?? '');
        $img    = $input['img'] ?? null; // base64 string
        if (!$seller || !$title || $price <= 0)
            json_out(['success' => false, 'message' => 'Missing fields']);
        $pdo = db();
        $pdo->prepare("INSERT INTO avito_listings (seller,title,price,descr,img) VALUES (?,?,?,?,?)")
            ->execute([$seller, $title, $price, $descr, $img]);
        json_out(['success' => true, 'id' => $pdo->lastInsertId()]);
        break;

    case 'avito_listings':
        ensureAvitoTables();
        $pdo  = db();
        $rows = $pdo->query("SELECT id,seller,title,price,descr,img,created_at FROM avito_listings ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
        json_out(['success' => true, 'listings' => $rows]);
        break;

    case 'avito_delete':
        ensureAvitoTables();
        $id     = intval($input['id']     ?? 0);
        $seller = trim($input['seller']   ?? '');
        if (!$id || !$seller) json_out(['success' => false, 'message' => 'Invalid input']);
        $pdo = db();
        // فقط صاحب الإعلان يمكنه الحذف
        $pdo->prepare("DELETE FROM avito_listings WHERE id = ? AND seller = ?")->execute([$id, $seller]);
        $pdo->prepare("DELETE FROM avito_messages WHERE listing_id = ?")->execute([$id]);
        json_out(['success' => true]);
        break;

    // ─── AVITO CHAT ──────────────────────────────────────────
    case 'chat_send':
        ensureAvitoTables();
        $listing_id = intval($input['listing_id'] ?? 0);
        $sender     = trim($input['sender']       ?? '');
        $msg_text   = trim($input['msg_text']     ?? '');
        $is_offer   = intval($input['is_offer']   ?? 0);
        $offer_amt  = intval($input['offer_amt']  ?? 0);
        if (!$listing_id || !$sender) json_out(['success' => false, 'message' => 'Invalid input']);
        if (!$is_offer && !$msg_text)  json_out(['success' => false, 'message' => 'Empty message']);
        db()->prepare("INSERT INTO avito_messages (listing_id,sender,msg_text,is_offer,offer_amt) VALUES (?,?,?,?,?)")
            ->execute([$listing_id, $sender, $msg_text, $is_offer, $offer_amt]);
        json_out(['success' => true]);
        break;

    case 'chat_load':
        ensureAvitoTables();
        $listing_id = intval($input['listing_id'] ?? $_GET['listing_id'] ?? 0);
        $reader     = trim($input['reader']       ?? $_GET['reader']     ?? '');
        if (!$listing_id) json_out(['success' => false, 'message' => 'Invalid listing_id']);
        $pdo = db();
        $msgs = $pdo->prepare("SELECT id,sender,msg_text,is_offer,offer_amt,is_read,created_at FROM avito_messages WHERE listing_id = ? ORDER BY id ASC");
        $msgs->execute([$listing_id]);
        $rows = $msgs->fetchAll(PDO::FETCH_ASSOC);
        // Mark as read للقارئ
        if ($reader) {
            $pdo->prepare("UPDATE avito_messages SET is_read = 1 WHERE listing_id = ? AND sender != ? AND is_read = 0")
                ->execute([$listing_id, $reader]);
        }
        json_out(['success' => true, 'messages' => $rows]);
        break;

    case 'chat_unread':
        ensureAvitoTables();
        $username = trim($input['username'] ?? $_GET['username'] ?? '');
        if (!$username) json_out(['success' => false, 'message' => 'Invalid username']);
        $pdo = db();
        // عدد الرسائل الغير مقروءة لكل إعلان يملكه أو يشارك فيه
        $rows = $pdo->prepare("
            SELECT listing_id, COUNT(*) as cnt
            FROM avito_messages
            WHERE sender != ? AND is_read = 0
            GROUP BY listing_id
        ");
        $rows->execute([$username]);
        json_out(['success' => true, 'unread' => $rows->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    default:
        json_out(['success' => false, 'message' => 'Unknown action: ' . $action]);
}
