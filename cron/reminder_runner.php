<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/includes/functions.php';

function run_reminders(): int
{
    $events=db()->query("SELECT id,title,event_date FROM events WHERE status='Open' AND deleted_at IS NULL AND event_date=DATE_ADD(CURDATE(),INTERVAL 1 DAY)")->fetchAll();
    $sent=0;
    foreach($events as $event){
        $s=db()->prepare("SELECT r.user_id FROM registrations r WHERE r.event_id=? AND r.status<>'Cancelled'");
        $s->execute([$event['id']]);
        foreach($s->fetchAll(PDO::FETCH_COLUMN) as $userId){
            db()->beginTransaction();
            try{
                db()->prepare('INSERT INTO reminder_log (event_id,user_id) VALUES (?,?)')->execute([$event['id'],$userId]);
                notify_user((int)$userId,(int)$event['id'],'reminder','Event tomorrow: '.$event['title'],'Your registered event '.$event['title'].' is happening tomorrow, '.date('M j',strtotime($event['event_date'])).'.');
                db()->commit();$sent++;
            }catch(PDOException $e){
                if(db()->inTransaction())db()->rollBack();
                if($e->getCode()!=='23000')throw $e;
            }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
        }
    }
    return $sent;
}
