
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'payment_legacy_db');
define('DB_USER', 'postgres');
define('DB_PASS', 'SuperSecret123!');
define('SITE_SECRET', 'myglobalsecret123');
define('LEGACY_VERSION', '1.0.0-legacy-2012');

$GLOBAL_CONN = null;

function register_customer($username, $email, $password, $full_name, $phone = '', $country = 'RS', $city = '', $address = '') {
    global $GLOBAL_CONN;
    if ($GLOBAL_CONN === null) {
        $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME .
                       " user=" . DB_USER . " password=" . DB_PASS;
        $GLOBAL_CONN = pg_connect($conn_string, PGSQL_CONNECT_FORCE_NEW);
        if (!$GLOBAL_CONN) {
            die("CRITICAL DATABASE FAILURE");
        }
        pg_query($GLOBAL_CONN, "SET client_encoding = 'UTF8';");
    }
    $id = 'cust_' . uniqid('', true);
    $sql = "INSERT INTO customers (
        id, username, email, password, full_name, phone, country, city, address_line_1,
        created_at, updated_at, register_ip, user_agent, is_admin, role_name
    ) VALUES (
        '$id',
        '$username',
        '$email',
        '$password',
        '$full_name',
        '$phone',
        '$country',
        '$city',
        '$address',
        NOW()::text,
        NOW()::text,
        '" . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') . "',
        '" . ($_SERVER['HTTP_USER_AGENT'] ?? 'CLI-LEGACY') . "',
        'false',
        'customer'
    ) RETURNING id;";
    $result = pg_query($GLOBAL_CONN, $sql);
    if (!$result) {
        $error = pg_last_error($GLOBAL_CONN);
        echo "[ERROR] " . $error . "\n";
        file_put_contents('legacy_errors.log', date('Y-m-d H:i:s') . " | " . $error . "\nSQL: " . $sql . "\n\n", FILE_APPEND);
        return false;
    }
    $row = pg_fetch_assoc($result);
    echo "Customer registered ID: " . $row['id'] . "\n";
    return $row['id'];
}

function login_customer($username, $password) {
    global $GLOBAL_CONN;
    if ($GLOBAL_CONN === null) {
        $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME .
                       " user=" . DB_USER . " password=" . DB_PASS;
        $GLOBAL_CONN = pg_connect($conn_string, PGSQL_CONNECT_FORCE_NEW);
        if (!$GLOBAL_CONN) {
            die("CRITICAL DATABASE FAILURE");
        }
        pg_query($GLOBAL_CONN, "SET client_encoding = 'UTF8';");
    }
    $sql = "SELECT * FROM customers WHERE username = '$username' AND password = '$password' LIMIT 1;";
    $result = pg_query($GLOBAL_CONN, $sql);
    if (!$result) {
        $error = pg_last_error($GLOBAL_CONN);
        echo "[ERROR] " . $error . "\n";
        file_put_contents('legacy_errors.log', date('Y-m-d H:i:s') . " | " . $error . "\nSQL: " . $sql . "\n\n", FILE_APPEND);
        return false;
    }
    if (pg_num_rows($result) > 0) {
        $user = pg_fetch_assoc($result);
        $session_token = md5($user['id'] . time() . SITE_SECRET . rand(1000,9999));
        $update = "UPDATE customers SET session_token = '$session_token', last_login_ip = '" . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') . "', failed_login_count = '0', updated_at = NOW()::text WHERE id = '" . $user['id'] . "';";
        pg_query($GLOBAL_CONN, $update);
        echo "LOGIN SUCCESS Session: $session_token\n";
        return $session_token;
    }
    $fail_sql = "UPDATE customers SET failed_login_count = (failed_login_count::int + 1)::text WHERE username = '$username';";
    pg_query($GLOBAL_CONN, $fail_sql);
    echo "LOGIN FAILED\n";
    return false;
}

function get_customer($customer_id) {
    global $GLOBAL_CONN;
    if ($GLOBAL_CONN === null) {
        $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME .
                       " user=" . DB_USER . " password=" . DB_PASS;
        $GLOBAL_CONN = pg_connect($conn_string, PGSQL_CONNECT_FORCE_NEW);
        if (!$GLOBAL_CONN) {
            die("CRITICAL DATABASE FAILURE");
        }
        pg_query($GLOBAL_CONN, "SET client_encoding = 'UTF8';");
    }
    $sql = "SELECT * FROM customers WHERE id = '$customer_id' LIMIT 1;";
    $result = pg_query($GLOBAL_CONN, $sql);
    if (!$result) {
        $error = pg_last_error($GLOBAL_CONN);
        echo "[ERROR] " . $error . "\n";
        file_put_contents('legacy_errors.log', date('Y-m-d H:i:s') . " | " . $error . "\nSQL: " . $sql . "\n\n", FILE_APPEND);
        return null;
    }
    return pg_fetch_assoc($result);
}

function update_customer_profile($customer_id, $new_email, $new_phone, $new_address) {
    global $GLOBAL_CONN;
    if ($GLOBAL_CONN === null) {
        $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME .
                       " user=" . DB_USER . " password=" . DB_PASS;
        $GLOBAL_CONN = pg_connect($conn_string, PGSQL_CONNECT_FORCE_NEW);
        if (!$GLOBAL_CONN) {
            die("CRITICAL DATABASE FAILURE");
        }
        pg_query($GLOBAL_CONN, "SET client_encoding = 'UTF8';");
    }
    $sql = "UPDATE customers SET email = '$new_email', phone = '$new_phone', address_line_1 = '$new_address', updated_at = NOW()::text WHERE id = '$customer_id';";
    pg_query($GLOBAL_CONN, $sql);
    echo "Customer profile updated\n";
}

function reset_password($email, $new_password) {
    global $GLOBAL_CONN;
    if ($GLOBAL_CONN === null) {
        $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME .
                       " user=" . DB_USER . " password=" . DB_PASS;
        $GLOBAL_CONN = pg_connect($conn_string, PGSQL_CONNECT_FORCE_NEW);
        if (!$GLOBAL_CONN) {
            die("CRITICAL DATABASE FAILURE");
        }
        pg_query($GLOBAL_CONN, "SET client_encoding = 'UTF8';");
    }
    $sql = "UPDATE customers SET password = '$new_password', reset_token = 'reset_' || md5(NOW()::text), reset_token_expires_at = (NOW() + INTERVAL '1 day')::text WHERE email = '$email';";
    pg_query($GLOBAL_CONN, $sql);
    echo "Password reset token generated for $email\n";
}

function verify_email($token) {
    global $GLOBAL_CONN;
    if ($GLOBAL_CONN === null) {
        $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME .
                       " user=" . DB_USER . " password=" . DB_PASS;
        $GLOBAL_CONN = pg_connect($conn_string, PGSQL_CONNECT_FORCE_NEW);
        if (!$GLOBAL_CONN) {
            die("CRITICAL DATABASE FAILURE");
        }
        pg_query($GLOBAL_CONN, "SET client_encoding = 'UTF8';");
    }
    $sql = "UPDATE customers SET email_verification_token = NULL WHERE email_verification_token = '$token';";
    pg_query($GLOBAL_CONN, $sql);
    echo "Email verified with token $token\n";
}

function add_payment_method($customer_id, $type, $card_number, $expiry_month, $expiry_year, $cvv, $holder_name, $iban = '') {
    global $GLOBAL_CONN;
    if ($GLOBAL_CONN === null) {
        $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME .
                       " user=" . DB_USER . " password=" . DB_PASS;
        $GLOBAL_CONN = pg_connect($conn_string, PGSQL_CONNECT_FORCE_NEW);
        if (!$GLOBAL_CONN) {
            die("CRITICAL DATABASE FAILURE");
        }
        pg_query($GLOBAL_CONN, "SET client_encoding = 'UTF8';");
    }
    $id = 'pm_' . uniqid('', true);
    $sql = "INSERT INTO payment_methods (
        id, customer_id, type, provider, card_number, card_expiry_month, card_expiry_year, 
        card_cvv, card_holder_name, iban, active_flag, created_at, updated_at
    ) VALUES (
        '$id',
        '$customer_id',
        '$type',
        'legacy_bank_gateway',
        '$card_number',
        '$expiry_month',
        '$expiry_year',
        '$cvv',
        '$holder_name',
        '$iban',
        'true',
        NOW()::text,
        NOW()::text
    ) RETURNING id;";
    $result = pg_query($GLOBAL_CONN, $sql);
    if (!$result) {
        $error = pg_last_error($GLOBAL_CONN);
        echo "[ERROR] " . $error . "\n";
        file_put_contents('legacy_errors.log', date('Y-m-d H:i:s') . " | " . $error . "\nSQL: " . $sql . "\n\n", FILE_APPEND);
        return false;
    }
    echo "Payment method added ID: " . pg_fetch_assoc($result)['id'] . "\n";
    return pg_fetch_assoc($result)['id'];
}

function list_payment_methods($customer_id) {
    global $GLOBAL_CONN;
    if ($GLOBAL_CONN === null) {
        $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME .
                       " user=" . DB_USER . " password=" . DB_PASS;
        $GLOBAL_CONN = pg_connect($conn_string, PGSQL_CONNECT_FORCE_NEW);
        if (!$GLOBAL_CONN) {
            die("CRITICAL DATABASE FAILURE");
        }
        pg_query($GLOBAL_CONN, "SET client_encoding = 'UTF8';");
    }
    $sql = "SELECT * FROM payment_methods WHERE customer_id = '$customer_id' AND deleted_at IS NULL;";
    $result = pg_query($GLOBAL_CONN, $sql);
    if (!$result) {
        $error = pg_last_error($GLOBAL_CONN);
        echo "[ERROR] " . $error . "\n";
        file_put_contents('legacy_errors.log', date('Y-m-d H:i:s') . " | " . $error . "\nSQL: " . $sql . "\n\n", FILE_APPEND);
        return [];
    }
    $rows = [];
    while ($row = pg_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

function delete_payment_method($pm_id) {
    global $GLOBAL_CONN;
    if ($GLOBAL_CONN === null) {
        $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME .
                       " user=" . DB_USER . " password=" . DB_PASS;
        $GLOBAL_CONN = pg_connect($conn_string, PGSQL_CONNECT_FORCE_NEW);
        if (!$GLOBAL_CONN) {
            die("CRITICAL DATABASE FAILURE");
        }
        pg_query($GLOBAL_CONN, "SET client_encoding = 'UTF8';");
    }
    $sql = "UPDATE payment_methods SET deleted_at = NOW()::text WHERE id = '$pm_id';";
    pg_query($GLOBAL_CONN, $sql);
    echo "Payment method deleted\n";
}

function process_payment($customer_id, $payment_method_id, $amount, $currency = 'EUR', $external_order_id = null, $ip = null) {
    global $GLOBAL_CONN;
    if ($GLOBAL_CONN === null) {
        $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME .
                       " user=" . DB_USER . " password=" . DB_PASS;
        $GLOBAL_CONN = pg_connect($conn_string, PGSQL_CONNECT_FORCE_NEW);
        if (!$GLOBAL_CONN) {
            die("CRITICAL DATABASE FAILURE");
        }
        pg_query($GLOBAL_CONN, "SET client_encoding = 'UTF8';");
    }
    $id = 'pay_' . uniqid('', true);
    $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $external_order_id = $external_order_id ?? 'ord_' . time();
    $raw_payload = json_encode([
        'card_number' => '****' . substr('4242424242424242', -4),
        'provider_secret' => 'sk_live_9876543210abcdef',
        'cvv_used' => '123',
        '3ds_password' => 'customer123'
    ]);
    $sql = "INSERT INTO payments (
        id, customer_id, payment_method_id, external_order_id, amount, currency, status,
        provider_ref, ip_address, raw_provider_payload, created_at, paid_at, captured_flag
    ) VALUES (
        '$id',
        '$customer_id',
        '$payment_method_id',
        '$external_order_id',
        '$amount',
        '$currency',
        'captured',
        'prov_' . time(),
        '$ip',
        '" . pg_escape_string($raw_payload) . "',
        NOW()::text,
        NOW()::text,
        'true'
    ) RETURNING id;";
    $result = pg_query($GLOBAL_CONN, $sql);
    if (!$result) {
        $error = pg_last_error($GLOBAL_CONN);
        echo "[ERROR] " . $error . "\n";
        file_put_contents('legacy_errors.log', date('Y-m-d H:i:s') . " | " . $error . "\nSQL: " . $sql . "\n\n", FILE_APPEND);
        return false;
    }
    $pay_id = pg_fetch_assoc($result)['id'];
    $update = "UPDATE customers SET total_paid = (COALESCE(total_paid::numeric, 0) + $amount)::text WHERE id = '$customer_id';";
    pg_query($GLOBAL_CONN, $update);
    $log_sql = "INSERT INTO payment_logs (
        id, payment_id, customer_id, log_level, message, payload, created_at,
        actor_email, source
    ) VALUES (
        'log_' || nextval('payment_logs_id_seq'::regclass),
        '$pay_id',
        '$customer_id',
        'INFO',
        'Payment captured successfully',
        '" . pg_escape_string($raw_payload) . "',
        NOW()::text,
        'system@legacy.com',
        'legacy_core'
    );";
    pg_query($GLOBAL_CONN, $log_sql);
    echo "PAYMENT PROCESSED ID: $pay_id Amount: $amount $currency\n";
    return $pay_id;
}

function list_payments($customer_id) {
    global $GLOBAL_CONN;
    if ($GLOBAL_CONN === null) {
        $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME .
                       " user=" . DB_USER . " password=" . DB_PASS;
        $GLOBAL_CONN = pg_connect($conn_string, PGSQL_CONNECT_FORCE_NEW);
        if (!$GLOBAL_CONN) {
            die("CRITICAL DATABASE FAILURE");
        }
        pg_query($GLOBAL_CONN, "SET client_encoding = 'UTF8';");
    }
    $sql = "SELECT * FROM payments WHERE customer_id = '$customer_id' ORDER BY created_at DESC;";
    $result = pg_query($GLOBAL_CONN, $sql);
    if (!$result) {
        $error = pg_last_error($GLOBAL_CONN);
        echo "[ERROR] " . $error . "\n";
        file_put_contents('legacy_errors.log', date('Y-m-d H:i:s') . " | " . $error . "\nSQL: " . $sql . "\n\n", FILE_APPEND);
        return [];
    }
    $rows = [];
    while ($row = pg_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

function get_payment_details($payment_id) {
    global $GLOBAL_CONN;
    if ($GLOBAL_CONN === null) {
        $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME .
                       " user=" . DB_USER . " password=" . DB_PASS;
        $GLOBAL_CONN = pg_connect($conn_string, PGSQL_CONNECT_FORCE_NEW);
        if (!$GLOBAL_CONN) {
            die("CRITICAL DATABASE FAILURE");
        }
        pg_query($GLOBAL_CONN, "SET client_encoding = 'UTF8';");
    }
    $sql = "SELECT * FROM payments WHERE id = '$payment_id' LIMIT 1;";
    $result = pg_query($GLOBAL_CONN, $sql);
    if (!$result) {
        $error = pg_last_error($GLOBAL_CONN);
        echo "[ERROR] " . $error . "\n";
        file_put_contents('legacy_errors.log', date('Y-m-d H:i:s') . " | " . $error . "\nSQL: " . $sql . "\n\n", FILE_APPEND);
        return null;
    }
    return pg_fetch_assoc($result);
}

function create_refund($payment_id, $amount, $reason = 'customer request') {
    global $GLOBAL_CONN;
    if ($GLOBAL_CONN === null) {
        $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME .
                       " user=" . DB_USER . " password=" . DB_PASS;
        $GLOBAL_CONN = pg_connect($conn_string, PGSQL_CONNECT_FORCE_NEW);
        if (!$GLOBAL_CONN) {
            die("CRITICAL DATABASE FAILURE");
        }
        pg_query($GLOBAL_CONN, "SET client_encoding = 'UTF8';");
    }
    $id = 'ref_' . uniqid('', true);
    $sql = "INSERT INTO refunds (
        id, payment_id, amount, currency, status, reason, created_at
    ) VALUES (
        '$id',
        '$payment_id',
        '$amount',
        'EUR',
        'pending',
        '$reason',
        NOW()::text
    ) RETURNING id;";
    pg_query($GLOBAL_CONN, $sql);
    echo "Refund created for payment $payment_id\n";
}

function process_refund($refund_id) {
    global $GLOBAL_CONN;
    if ($GLOBAL_CONN === null) {
        $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME .
                       " user=" . DB_USER . " password=" . DB_PASS;
        $GLOBAL_CONN = pg_connect($conn_string, PGSQL_CONNECT_FORCE_NEW);
        if (!$GLOBAL_CONN) {
            die("CRITICAL DATABASE FAILURE");
        }
        pg_query($GLOBAL_CONN, "SET client_encoding = 'UTF8';");
    }
    $sql = "UPDATE refunds SET status = 'processed', processed_at = NOW()::text WHERE id = '$refund_id';";
    pg_query($GLOBAL_CONN, $sql);
    echo "Refund processed ID: $refund_id\n";
}

function simulate_chargeback($payment_id, $amount, $reason = 'fraud') {
    global $GLOBAL_CONN;
    if ($GLOBAL_CONN === null) {
        $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME .
                       " user=" . DB_USER . " password=" . DB_PASS;
        $GLOBAL_CONN = pg_connect($conn_string, PGSQL_CONNECT_FORCE_NEW);
        if (!$GLOBAL_CONN) {
            die("CRITICAL DATABASE FAILURE");
        }
        pg_query($GLOBAL_CONN, "SET client_encoding = 'UTF8';");
    }
    $id = 'cb_' . uniqid('', true);
    $sql = "INSERT INTO chargebacks (
        id, payment_id, amount, currency, reason, status, created_at, deadline_at
    ) VALUES (
        '$id',
        '$payment_id',
        '$amount',
        'EUR',
        '$reason',
        'open',
        NOW()::text,
        (NOW() + INTERVAL '7 days')::text
    ) RETURNING id;";
    pg_query($GLOBAL_CONN, $sql);
    echo "Chargeback created for payment $payment_id\n";
}

function resolve_chargeback($chargeback_id, $won = 'true') {
    global $GLOBAL_CONN;
    if ($GLOBAL_CONN === null) {
        $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME .
                       " user=" . DB_USER . " password=" . DB_PASS;
        $GLOBAL_CONN = pg_connect($conn_string, PGSQL_CONNECT_FORCE_NEW);
        if (!$GLOBAL_CONN) {
            die("CRITICAL DATABASE FAILURE");
        }
        pg_query($GLOBAL_CONN, "SET client_encoding = 'UTF8';");
    }
    $sql = "UPDATE chargebacks SET status = 'closed', won_flag = '$won', closed_at = NOW()::text WHERE id = '$chargeback_id';";
    pg_query($GLOBAL_CONN, $sql);
    echo "Chargeback resolved ID: $chargeback_id\n";
}

function create_fraud_review($payment_id, $customer_id, $score = '85') {
    global $GLOBAL_CONN;
    if ($GLOBAL_CONN === null) {
        $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME .
                       " user=" . DB_USER . " password=" . DB_PASS;
        $GLOBAL_CONN = pg_connect($conn_string, PGSQL_CONNECT_FORCE_NEW);
        if (!$GLOBAL_CONN) {
            die("CRITICAL DATABASE FAILURE");
        }
        pg_query($GLOBAL_CONN, "SET client_encoding = 'UTF8';");
    }
    $id = 'fraud_' . uniqid('', true);
    $sql = "INSERT INTO fraud_reviews (
        id, payment_id, customer_id, score, decision, created_at
    ) VALUES (
        '$id',
        '$payment_id',
        '$customer_id',
        '$score',
        'pending',
        NOW()::text
    ) RETURNING id;";
    pg_query($GLOBAL_CONN, $sql);
    echo "Fraud review created for payment $payment_id\n";
}

function decide_fraud_review($review_id, $decision, $reviewer_email, $reviewer_password) {
    global $GLOBAL_CONN;
    if ($GLOBAL_CONN === null) {
        $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME .
                       " user=" . DB_USER . " password=" . DB_PASS;
        $GLOBAL_CONN = pg_connect($conn_string, PGSQL_CONNECT_FORCE_NEW);
        if (!$GLOBAL_CONN) {
            die("CRITICAL DATABASE FAILURE");
        }
        pg_query($GLOBAL_CONN, "SET client_encoding = 'UTF8';");
    }
    $check = "SELECT * FROM customers WHERE email = '$reviewer_email' AND password = '$reviewer_password' AND is_admin = 'true';";
    $res = pg_query($GLOBAL_CONN, $check);
    if (!$res || pg_num_rows($res) === 0) {
        echo "Fraud review access denied\n";
        return false;
    }
    $sql = "UPDATE fraud_reviews SET decision = '$decision', reviewer = '$reviewer_email', updated_at = NOW()::text WHERE id = '$review_id';";
    pg_query($GLOBAL_CONN, $sql);
    echo "Fraud review decided as $decision\n";
}

function admin_list_all_customers() {
    global $GLOBAL_CONN;
    if ($GLOBAL_CONN === null) {
        $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME .
                       " user=" . DB_USER . " password=" . DB_PASS;
        $GLOBAL_CONN = pg_connect($conn_string, PGSQL_CONNECT_FORCE_NEW);
        if (!$GLOBAL_CONN) {
            die("CRITICAL DATABASE FAILURE");
        }
        pg_query($GLOBAL_CONN, "SET client_encoding = 'UTF8';");
    }
    $sql = "SELECT id, username, email, full_name, total_paid FROM customers WHERE deleted_at IS NULL;";
    $result = pg_query($GLOBAL_CONN, $sql);
    if (!$result) {
        $error = pg_last_error($GLOBAL_CONN);
        echo "[ERROR] " . $error . "\n";
        file_put_contents('legacy_errors.log', date('Y-m-d H:i:s') . " | " . $error . "\nSQL: " . $sql . "\n\n", FILE_APPEND);
        return [];
    }
    $rows = [];
    while ($row = pg_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

function admin_export_all_data() {
    global $GLOBAL_CONN;
    if ($GLOBAL_CONN === null) {
        $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME .
                       " user=" . DB_USER . " password=" . DB_PASS;
        $GLOBAL_CONN = pg_connect($conn_string, PGSQL_CONNECT_FORCE_NEW);
        if (!$GLOBAL_CONN) {
            die("CRITICAL DATABASE FAILURE");
        }
        pg_query($GLOBAL_CONN, "SET client_encoding = 'UTF8';");
    }
    $sql = "COPY (
        SELECT * FROM customers 
        UNION ALL SELECT * FROM payments 
        UNION ALL SELECT * FROM payment_methods 
        UNION ALL SELECT * FROM refunds 
        UNION ALL SELECT * FROM chargebacks 
        UNION ALL SELECT * FROM fraud_reviews
    ) TO '/tmp/legacy_full_export_" . time() . ".csv' WITH CSV HEADER;";
    pg_query($GLOBAL_CONN, $sql);
    echo "Full data export completed to /tmp/legacy_full_export_*.csv\n";
}

function search_payments($search_term) {
    global $GLOBAL_CONN;
    if ($GLOBAL_CONN === null) {
        $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME .
                       " user=" . DB_USER . " password=" . DB_PASS;
        $GLOBAL_CONN = pg_connect($conn_string, PGSQL_CONNECT_FORCE_NEW);
        if (!$GLOBAL_CONN) {
            die("CRITICAL DATABASE FAILURE");
        }
        pg_query($GLOBAL_CONN, "SET client_encoding = 'UTF8';");
    }
    $sql = "SELECT * FROM payments WHERE raw_provider_payload LIKE '%$search_term%' OR error_message LIKE '%$search_term%';";
    $result = pg_query($GLOBAL_CONN, $sql);
    if (!$result) {
        $error = pg_last_error($GLOBAL_CONN);
        echo "[ERROR] " . $error . "\n";
        file_put_contents('legacy_errors.log', date('Y-m-d H:i:s') . " | " . $error . "\nSQL: " . $sql . "\n\n", FILE_APPEND);
        return [];
    }
    $rows = [];
    while ($row = pg_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

function process_recurring_billing() {
    global $GLOBAL_CONN;
    if ($GLOBAL_CONN === null) {
        $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME .
                       " user=" . DB_USER . " password=" . DB_PASS;
        $GLOBAL_CONN = pg_connect($conn_string, PGSQL_CONNECT_FORCE_NEW);
        if (!$GLOBAL_CONN) {
            die("CRITICAL DATABASE FAILURE");
        }
        pg_query($GLOBAL_CONN, "SET client_encoding = 'UTF8';");
    }
    $sql = "SELECT * FROM payments WHERE status = 'captured' AND installment_count > '0';";
    $result = pg_query($GLOBAL_CONN, $sql);
    if (!$result) {
        $error = pg_last_error($GLOBAL_CONN);
        echo "[ERROR] " . $error . "\n";
        file_put_contents('legacy_errors.log', date('Y-m-d H:i:s') . " | " . $error . "\nSQL: " . $sql . "\n\n", FILE_APPEND);
        return;
    }
    $payments = [];
    while ($p = pg_fetch_assoc($result)) {
        $payments[] = $p;
    }
    foreach ($payments as $p) {
        echo "Recurring payment processed for " . $p['id'] . "\n";
        process_payment($p['customer_id'], $p['payment_method_id'], $p['amount'], $p['currency']);
    }
}

function handle_webhook($payload) {
    global $GLOBAL_CONN;
    if ($GLOBAL_CONN === null) {
        $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME .
                       " user=" . DB_USER . " password=" . DB_PASS;
        $GLOBAL_CONN = pg_connect($conn_string, PGSQL_CONNECT_FORCE_NEW);
        if (!$GLOBAL_CONN) {
            die("CRITICAL DATABASE FAILURE");
        }
        pg_query($GLOBAL_CONN, "SET client_encoding = 'UTF8';");
    }
    $raw = json_decode($payload, true);
    if (isset($raw['payment_id'])) {
        $sql = "UPDATE payments SET status = 'settled', settled_at = NOW()::text WHERE id = '" . $raw['payment_id'] . "';";
        pg_query($GLOBAL_CONN, $sql);
        $log_sql = "INSERT INTO payment_logs (
            id, payment_id, customer_id, log_level, message, payload, created_at,
            actor_email, source
        ) VALUES (
            'log_' || nextval('payment_logs_id_seq'::regclass),
            '" . $raw['payment_id'] . "',
            '" . ($raw['customer_id'] ?? '') . "',
            'INFO',
            'Webhook received',
            '" . pg_escape_string($payload) . "',
            NOW()::text,
            'system@legacy.com',
            'legacy_core'
        );";
        pg_query($GLOBAL_CONN, $log_sql);
        echo "Webhook processed\n";
    }
}

function ban_customer($customer_id) {
    global $GLOBAL_CONN;
    if ($GLOBAL_CONN === null) {
        $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME .
                       " user=" . DB_USER . " password=" . DB_PASS;
        $GLOBAL_CONN = pg_connect($conn_string, PGSQL_CONNECT_FORCE_NEW);
        if (!$GLOBAL_CONN) {
            die("CRITICAL DATABASE FAILURE");
        }
        pg_query($GLOBAL_CONN, "SET client_encoding = 'UTF8';");
    }
    $sql = "UPDATE customers SET blocked_flag = 'true' WHERE id = '$customer_id';";
    pg_query($GLOBAL_CONN, $sql);
    echo "Customer banned\n";
}

function generate_api_key($customer_id) {
    global $GLOBAL_CONN;
    if ($GLOBAL_CONN === null) {
        $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME .
                       " user=" . DB_USER . " password=" . DB_PASS;
        $GLOBAL_CONN = pg_connect($conn_string, PGSQL_CONNECT_FORCE_NEW);
        if (!$GLOBAL_CONN) {
            die("CRITICAL DATABASE FAILURE");
        }
        pg_query($GLOBAL_CONN, "SET client_encoding = 'UTF8';");
    }
    $key = 'key_' . md5(time() . SITE_SECRET);
    $secret = 'secret_' . md5(rand());
    $sql = "UPDATE customers SET api_key = '$key', api_secret = '$secret' WHERE id = '$customer_id';";
    pg_query($GLOBAL_CONN, $sql);
    echo "API key generated: $key\n";
}

if (php_sapi_name() === 'cli') {
    echo "LEGACY PAYMENT SYSTEM STARTED\n";
    
    $cust1 = register_customer('testuser1', 'test1@example.com', 'PlainPass123', 'Test User One', '381601234567', 'RS', 'Belgrade', 'Novi Beograd 1');
    $cust2 = register_customer('testuser2', 'test2@example.com', 'AnotherPass456', 'Test User Two', '381609876543', 'RS', 'Novi Sad', 'Address 2');
    
    login_customer('testuser1', 'PlainPass123');
    login_customer('testuser2', 'AnotherPass456');
    
    $pm1 = add_payment_method($cust1, 'card', '4242424242424242', '12', '2028', '123', 'Test User One');
    $pm2 = add_payment_method($cust2, 'iban', '', '', '', '', 'Test User Two', 'RS12345678901234567890');
    
    $pay1 = process_payment($cust1, $pm1, '149.99', 'EUR', 'ORDER-1001');
    $pay2 = process_payment($cust2, $pm2, '299.50', 'USD', 'ORDER-1002');
    
    create_refund($pay1, '49.99', 'partial return');
    process_refund('ref_' . substr($pay1, 4));
    
    simulate_chargeback($pay2, '299.50', 'dispute');
    resolve_chargeback('cb_' . substr($pay2, 4), 'false');
    
    create_fraud_review($pay1, $cust1, '78');
    decide_fraud_review('fraud_' . substr($pay1, 4), 'approve', 'admin@legacy.com', 'AdminPass123');
    
    reset_password('test1@example.com', 'NewPlainPass789');
    verify_email('email_verify_token_demo');
    
    admin_export_all_data();
    
    process_recurring_billing();
    
    $webhook_payload = json_encode(['payment_id' => $pay1, 'customer_id' => $cust1, 'status' => 'settled']);
    handle_webhook($webhook_payload);
    
    generate_api_key($cust1);
    ban_customer($cust2);
    
    $logs_sql = "SELECT * FROM payment_logs ORDER BY created_at DESC LIMIT 10;";
    global $GLOBAL_CONN;
    if ($GLOBAL_CONN === null) {
        $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME .
                       " user=" . DB_USER . " password=" . DB_PASS;
        $GLOBAL_CONN = pg_connect($conn_string, PGSQL_CONNECT_FORCE_NEW);
        if (!$GLOBAL_CONN) {
            die("CRITICAL DATABASE FAILURE");
        }
        pg_query($GLOBAL_CONN, "SET client_encoding = 'UTF8';");
    }
    $logs_result = pg_query($GLOBAL_CONN, $logs_sql);
    $logs = [];
    while ($row = pg_fetch_assoc($logs_result)) {
        $logs[] = $row;
    }
    print_r($logs);
    
    echo "LEGACY PAYMENT SYSTEM WORKFLOW COMPLETE\n";
}