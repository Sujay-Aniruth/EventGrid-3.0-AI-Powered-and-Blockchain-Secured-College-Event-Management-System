<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/includes/functions.php';
header('Content-Type: application/json; charset=utf-8');
$user=current_user();if(!$user){http_response_code(401);echo json_encode(['error'=>'Sign in required']);exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();db()->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$user['id']]);}
echo json_encode(['unread'=>unread_count((int)$user['id'])],JSON_THROW_ON_ERROR);
