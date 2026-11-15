<?php
declare(strict_types=1);
require_once __DIR__.'/reminder_runner.php';
try{$sent=run_reminders();fwrite(STDOUT,"Reminder notifications sent: {$sent}\n");}
catch(Throwable $e){fwrite(STDERR,"Reminder job failed: ".$e->getMessage()."\n");exit(1);}
