<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/includes/functions.php';require_once dirname(__DIR__).'/includes/blockchain.php';
header('Content-Type: application/json; charset=utf-8');$user=current_user();if(!$user){http_response_code(401);echo json_encode(['error'=>'Sign in required']);exit;}if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
verify_csrf();$input=json_decode(file_get_contents('php://input'),true)??[];$id=(int)($input['registration_id']??0);
if($user['role']==='student'){$s=db()->prepare('SELECT id FROM registrations WHERE id=? AND user_id=?');$s->execute([$id,$user['id']]);if(!$s->fetch()){http_response_code(404);echo json_encode(['error'=>'Registration not found']);exit;}}
try{$r=verify_registration($id);echo json_encode(['secure'=>$r['secure'],'message'=>$r['message'],'fields'=>$r['fields'],'chain_valid'=>$r['chain_valid'],'block_id'=>$r['block']['id']??null],JSON_THROW_ON_ERROR);}catch(DomainException $e){http_response_code(404);echo json_encode(['error'=>$e->getMessage()]);}
