<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $path = ''): string { return rtrim(BASE_URL, '/') . ($path === '' ? '' : '/' . ltrim($path, '/')); }
function redirect(string $path): never { header('Location: ' . url($path)); exit; }
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Your session expired. Reload the page and try again.');
    }
}
function flash(string $type, string $message): void { $_SESSION['flash'][] = ['type' => $type, 'message' => $message]; }
function flashes(): array { $items = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $items; }
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_role(string $role): array
{
    $user = current_user();
    if (!$user) redirect('login.php');
    if (($user['role'] ?? '') !== $role) { http_response_code(403); exit('Access denied.'); }
    return $user;
}
function require_login(): array
{
    $user = current_user();
    if (!$user) redirect('login.php');
    return $user;
}
function valid_password(string $password): bool
{
    return strlen($password) >= 8 && preg_match('/[A-Z]/', $password) && preg_match('/[a-z]/', $password) && preg_match('/\d/', $password) && preg_match('/[^A-Za-z0-9]/', $password);
}
function valid_iso_date(string $date): bool
{
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}
function notify_user(int $userId, ?int $eventId, string $type, string $title, string $message): void
{
    db()->prepare('INSERT INTO notifications (user_id,event_id,type,title,message) VALUES (?,?,?,?,?)')->execute([$userId,$eventId,$type,$title,$message]);
    if (!EMAIL_ENABLED) return;
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (is_file($autoload)) require_once $autoload;
    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) throw new RuntimeException('Email is enabled, but PHPMailer is missing. Install it with Composer or turn EMAIL_ENABLED off.');
    $userQuery = db()->prepare('SELECT email,name FROM users WHERE id=?'); $userQuery->execute([$userId]); $recipient = $userQuery->fetch();
    if (!$recipient) throw new RuntimeException('Notification recipient was not found for email delivery.');
    $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mailer->isSMTP(); $mailer->Host = SMTP_HOST; $mailer->SMTPAuth = true;
    $mailer->Username = SMTP_USER; $mailer->Password = SMTP_PASS; $mailer->Port = SMTP_PORT;
    $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->setFrom(SMTP_FROM !== '' ? SMTP_FROM : SMTP_USER, APP_NAME);
    $mailer->addAddress($recipient['email'], $recipient['name']);
    $mailer->Subject = $title; $mailer->Body = $message; $mailer->AltBody = $message;
    $mailer->send();
}
function notify_event_students(int $eventId, string $type, string $title, string $message): void
{
    $stmt = db()->prepare("SELECT DISTINCT user_id FROM registrations WHERE event_id=? AND status <> 'Cancelled'");
    $stmt->execute([$eventId]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $uid) notify_user((int)$uid, $eventId, $type, $title, $message);
}
function unread_count(int $userId): int
{
    $s = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
    $s->execute([$userId]); return (int)$s->fetchColumn();
}
function event_seats_left(int $eventId, int $capacity): int
{
    $s = db()->prepare("SELECT COUNT(*) FROM registrations WHERE event_id=? AND status <> 'Cancelled'");
    $s->execute([$eventId]); return max(0, $capacity - (int)$s->fetchColumn());
}
function send_page_reminders(): void
{
    if (PHP_SAPI === 'cli') return;
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $checked = $pdo->query('SELECT reminders_checked_at FROM scheduler_state WHERE id=1 FOR UPDATE')->fetchColumn();
        if ($checked !== false && $checked !== null && time() - strtotime((string)$checked) < 3600) {
            $pdo->commit();
            return;
        }
        $pdo->exec('UPDATE scheduler_state SET reminders_checked_at=NOW() WHERE id=1');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    require_once dirname(__DIR__) . '/cron/reminder_runner.php';
    run_reminders();
}
function save_poster(array $file, ?string $old = null): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return $old;
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > MAX_POSTER_BYTES) throw new RuntimeException('Poster must be an image smaller than 3 MB.');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($allowed[$mime]) || !@getimagesize($file['tmp_name'])) throw new RuntimeException('Upload a valid JPG, PNG, or WEBP poster.');
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) throw new RuntimeException('Upload folder could not be created.');
    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $name)) throw new RuntimeException('Poster upload failed.');
    return $name;
}
function registration_snapshot(array $r): array
{
    $keys = ['user_id','event_id','name','reg_no','dob','department','phone','email','study_year','section','team_name','tshirt_size','diet','emergency_contact','special_requirements','consent','created_at'];
    $data = [];
    foreach ($keys as $key) $data[$key] = $r[$key] ?? null;
    return $data;
}
function create_registration(int $userId, int $eventId, array $input): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $eventStmt = $pdo->prepare("SELECT * FROM events WHERE id=? AND deleted_at IS NULL FOR UPDATE");
        $eventStmt->execute([$eventId]); $event = $eventStmt->fetch();
        if (!$event || $event['status'] !== 'Open' || strtotime($event['reg_deadline']) < time()) throw new DomainException('Registration for this event is closed.');
        if (event_seats_left($eventId, (int)$event['max_participants']) < 1) throw new DomainException('This event is full.');
        if (!empty($input['update_profile'])) {
            $profileValues = [
                trim((string)($input['name'] ?? '')),
                trim((string)($input['reg_no'] ?? '')),
                (string)($input['dob'] ?? ''),
                trim((string)($input['department'] ?? '')),
                trim((string)($input['phone'] ?? '')),
                strtolower(trim((string)($input['email'] ?? ''))),
            ];
            $conflict = $pdo->prepare('SELECT id FROM users WHERE (email=? OR reg_no=?) AND id<>?');
            $conflict->execute([$profileValues[5], $profileValues[1], $userId]);
            if ($conflict->fetch()) throw new DomainException('That email or registration ID belongs to another account; your profile was not changed.');
            $pdo->prepare('UPDATE users SET name=?,reg_no=?,dob=?,department=?,phone=?,email=? WHERE id=?')
                ->execute([...$profileValues, $userId]);
        }
        $profile = $pdo->prepare('SELECT * FROM users WHERE id=? AND role="student"'); $profile->execute([$userId]); $student = $profile->fetch();
        if (!$student) throw new DomainException('Student profile not found.');
        $values = [
            'name'=>trim((string)($input['name'] ?? $student['name'])),
            'reg_no'=>trim((string)($input['reg_no'] ?? $student['reg_no'])),
            'dob'=>(string)($input['dob'] ?? $student['dob']),
            'department'=>trim((string)($input['department'] ?? $student['department'])),
            'phone'=>trim((string)($input['phone'] ?? $student['phone'])),
            'email'=>trim((string)($input['email'] ?? $student['email'])),
        ];
        $studyYear = (int)($input['study_year'] ?? 1);
        $shirtSize = strtoupper(trim((string)($input['tshirt_size'] ?? '')));
        $section = trim((string)($input['section'] ?? ''));
        $team = trim((string)($input['team_name'] ?? ''));
        $diet = trim((string)($input['diet'] ?? ''));
        $emergency = trim((string)($input['emergency_contact'] ?? ''));
        if (empty($input['consent']) || !$values['name'] || mb_strlen($values['name'])>120 || !$values['reg_no'] || mb_strlen($values['reg_no'])>40 ||
            !$values['department'] || mb_strlen($values['department'])>100 || mb_strlen($values['email'])>190 ||
            !filter_var($values['email'], FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{10}$/', $values['phone']) ||
            !valid_iso_date($values['dob']) || $values['dob'] > date('Y-m-d') ||
            $studyYear < 1 || $studyYear > 5 ||
            mb_strlen($section)>30 || mb_strlen($team)>120 || mb_strlen($diet)>100 ||
            ($shirtSize !== '' && !in_array($shirtSize, ['XS','S','M','L','XL','XXL'], true)) ||
            ($emergency !== '' && !preg_match('/^\+?[0-9 -]{7,20}$/', $emergency))) {
            throw new DomainException('Check the name, registration ID, date of birth, phone, and email fields.');
        }
        $existingStmt = $pdo->prepare('SELECT id,status FROM registrations WHERE user_id=? AND event_id=? FOR UPDATE');
        $existingStmt->execute([$userId,$eventId]); $existing = $existingStmt->fetch();
        if ($existing && $existing['status'] !== 'Cancelled') throw new DomainException('You are already registered for this event.');
        $extraValues = [
            $studyYear,$section,$team,$shirtSize,$diet,$emergency,
            trim((string)($input['special_requirements'] ?? '')),
        ];
        if ($existing) {
            $pdo->prepare("UPDATE registrations SET name=?,reg_no=?,dob=?,department=?,phone=?,email=?,study_year=?,section=?,team_name=?,tshirt_size=?,diet=?,emergency_contact=?,special_requirements=?,consent=1,status='Pending',created_at=NOW() WHERE id=?")
                ->execute([...array_values($values), ...$extraValues, $existing['id']]);
            $id = (int)$existing['id'];
        } else {
            $insert = $pdo->prepare("INSERT INTO registrations (user_id,event_id,name,reg_no,dob,department,phone,email,study_year,section,team_name,tshirt_size,diet,emergency_contact,special_requirements,consent) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)");
            $insert->execute([$userId,$eventId,...array_values($values),...$extraValues]);
            $id = (int)$pdo->lastInsertId();
        }
        $read = $pdo->prepare('SELECT * FROM registrations WHERE id=?'); $read->execute([$id]); $registration = $read->fetch();
        append_block($registration);
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof PDOException && $e->getCode() === '23000') throw new DomainException('You are already registered for this event.');
        throw $e;
    }
}
