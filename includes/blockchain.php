<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

function append_block(array $registration): int
{
    $pdo = db();
    $snapshot = registration_snapshot($registration);
    $dataHash = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    $last = $pdo->query('SELECT block_hash FROM blockchain_ledger ORDER BY id DESC LIMIT 1 FOR UPDATE')->fetchColumn();
    $previous = $last ?: str_repeat('0',64);
    $created = date('Y-m-d H:i:s');
    $placeholder = hash('sha256', random_bytes(32));
    $reserve = $pdo->prepare('INSERT INTO blockchain_ledger (registration_id,data_hash,previous_hash,block_hash,nonce,data_snapshot,created_at) VALUES (?,?,?,?,0,?,?)');
    $reserve->execute([$registration['id'],$dataHash,$previous,$placeholder,json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),$created]);
    $blockId = (int)$pdo->lastInsertId();
    $nonce = 0;
    do {
        $hash = hash('sha256', (string)$blockId . $dataHash . $previous . $created . $nonce);
        $nonce++;
    } while (substr($hash, 0, 2) !== '00');
    $nonce--;
    $pdo->prepare('UPDATE blockchain_ledger SET block_hash=?,nonce=? WHERE id=?')->execute([$hash,$nonce,$blockId]);
    return $blockId;
}
function verify_registration(int $registrationId, bool $log = true): array
{
    $pdo = db();
    $s = $pdo->prepare('SELECT * FROM registrations WHERE id=?'); $s->execute([$registrationId]); $registration = $s->fetch();
    if (!$registration) throw new DomainException('Registration not found.');
    $s = $pdo->prepare('SELECT * FROM blockchain_ledger WHERE registration_id=? ORDER BY id DESC LIMIT 1'); $s->execute([$registrationId]); $block = $s->fetch();
    if (!$block) return ['secure'=>false,'message'=>'No ledger block exists for this registration.','registration'=>$registration,'block'=>null,'expected'=>null,'actual'=>null,'fields'=>[],'chain_valid'=>false];
    $actual = hash('sha256', json_encode(registration_snapshot($registration), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    $fields = [];
    $snapshot = json_decode((string)$block['data_snapshot'], true) ?: [];
    foreach (array_unique(array_merge(array_keys($snapshot), array_keys(registration_snapshot($registration)))) as $key) {
        $current = registration_snapshot($registration)[$key] ?? null;
        if (($snapshot[$key] ?? null) != $current) $fields[] = $key;
    }
    $previous = $pdo->prepare('SELECT block_hash FROM blockchain_ledger WHERE id < ? ORDER BY id DESC LIMIT 1');
    $previous->execute([$block['id']]); $priorHash = $previous->fetchColumn();
    $linkValid = $priorHash === false || hash_equals((string)$priorHash, (string)$block['previous_hash']);
    $blockActual = hash('sha256', (string)$block['id'] . (string)$block['data_hash'] . (string)$block['previous_hash'] . (string)$block['created_at'] . (string)$block['nonce']);
    $secure = hash_equals((string)$block['data_hash'], $actual) && hash_equals((string)$block['block_hash'], $blockActual) && $linkValid;
    if ($log) {
        $logStmt = $pdo->prepare('INSERT INTO integrity_logs (registration_id,block_id,expected_hash,actual_hash,result) VALUES (?,?,?,?,?)');
        $logStmt->execute([$registrationId,$block['id'],$block['data_hash'],$actual,$secure?'Secure':'Tampered']);
    }
    return ['secure'=>$secure,'message'=>$secure?'Authentic':'Registration data or its ledger chain does not match.','registration'=>$registration,'block'=>$block,'expected'=>$block['data_hash'],'actual'=>$actual,'fields'=>$fields,'chain_valid'=>$linkValid];
}
function verify_chain(bool $log = false): array
{
    $pdo = db();
    $blocks = $pdo->query('SELECT id,previous_hash,block_hash,registration_id,data_hash,created_at,nonce FROM blockchain_ledger ORDER BY id')->fetchAll();
    $previous = str_repeat('0',64); $breaks = [];
    foreach ($blocks as $block) {
        $linkValid = hash_equals($previous, (string)$block['previous_hash']);
        $expected = hash('sha256', (string)$block['id'] . $block['data_hash'] . $block['previous_hash'] . $block['created_at'] . $block['nonce']);
        $hashValid = hash_equals($expected, $block['block_hash']);
        if (!$linkValid || !$hashValid) $breaks[] = (int)$block['id'];
        if ($log) {
            $expectedValue = $hashValid ? $previous : $expected;
            $actualValue = $hashValid ? $block['previous_hash'] : $block['block_hash'];
            $pdo->prepare('INSERT INTO integrity_logs (registration_id,block_id,expected_hash,actual_hash,result) VALUES (?,?,?,?,?)')
                ->execute([$block['registration_id'],$block['id'],$expectedValue,$actualValue,($linkValid && $hashValid)?'Secure':'Tampered']);
        }
        $previous = (string)$block['block_hash'];
    }
    return ['healthy'=>count($breaks)===0,'breaks'=>array_values(array_unique($breaks)),'count'=>count($blocks)];
}
