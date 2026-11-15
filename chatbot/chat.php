<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/includes/functions.php';require_once dirname(__DIR__).'/includes/blockchain.php';
header('Content-Type: application/json; charset=utf-8');
$user=require_role('student');if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['error'=>'POST required']);exit;}verify_csrf();
$payload=json_decode(file_get_contents('php://input'),true)??[];$message=trim((string)($payload['message']??''));if($message===''||mb_strlen($message)>1000){http_response_code(422);echo json_encode(['error'=>$message===''?'Message is required':'Message is too long.']);exit;}
$session=db()->prepare('SELECT state,context_json FROM chat_sessions WHERE user_id=?');$session->execute([$user['id']]);$saved=$session->fetch();$state=$saved['state']??'idle';$ctx=$saved?json_decode($saved['context_json'],true):[];$ctx=is_array($ctx)?$ctx:[];
$lower=mb_strtolower($message);$reply='';
$save=function(string $newState,array $context)use($user){db()->prepare('INSERT INTO chat_sessions(user_id,state,context_json) VALUES(?,?,?) ON DUPLICATE KEY UPDATE state=VALUES(state),context_json=VALUES(context_json),updated_at=NOW()')->execute([$user['id'],$newState,json_encode($context,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);};
$events=function(){return db()->query("SELECT id,title,event_date,start_time,venue,reg_deadline,max_participants,(SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id AND r.status<>'Cancelled') registered FROM events e WHERE e.status='Open' AND e.deleted_at IS NULL AND e.event_date>=CURDATE() ORDER BY e.event_date LIMIT 10")->fetchAll();};
$selectEvent=function(string $text,array $items): ?array {
 if(preg_match('/#?(\d+)/',$text,$m))foreach($items as $item)if((int)$item['id']===(int)$m[1])return $item;
 $normalized=mb_strtolower($text);$best=null;$bestScore=0;$stopWords=['and','the','for','with','event','about','where','when','what','date','venue','seats','seat','deadline','capacity','many','how','is','at'];
 foreach($items as $item){
  $title=mb_strtolower($item['title']);
  if(mb_stripos($normalized,$title)!==false||mb_stripos($title,$normalized)!==false)return $item;
  preg_match_all('/[\p{L}\p{N}]{3,}/u',$title,$titleWords);
  $score=0;foreach($titleWords[0] as $word)if(!in_array($word,$stopWords,true)&&mb_stripos($normalized,$word)!==false)$score++;
  if($score>$bestScore){$best=$item;$bestScore=$score;}
 }
 return $bestScore>0?$best:null;
};
$event_list=function()use($events){$items=$events();if(!$items)return "There aren't any open upcoming events right now."; $out="Here are upcoming events:\n";foreach($items as $e){$out.='#'.$e['id'].' '.$e['title'].' — '.date('M j',strtotime($e['event_date'])).', '.$e['venue'].' ('.max(0,(int)$e['max_participants']-(int)$e['registered']).' seats left; deadline '.date('M j',strtotime($e['reg_deadline'])).")\n";}return $out."Reply with an event number or title to register.";};
$llmEvent=function(string $text,array $items): ?int {
 if(LLM_API_KEY==='')return null;
 if(!function_exists('curl_init'))throw new RuntimeException('LLM mode requires the PHP cURL extension.');
 $choices=array_map(fn($item)=>['id'=>(int)$item['id'],'title'=>$item['title']],$items);
 $body=json_encode(['model'=>LLM_MODEL,'temperature'=>0,'max_tokens'=>80,'messages'=>[
  ['role'=>'system','content'=>'Select the single event the user means from the provided choices. Return only JSON: {"event_id": integer} or {"event_id": null}. Never invent an ID.'],
  ['role'=>'user','content'=>json_encode(['message'=>$text,'events'=>$choices],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]
 ]],JSON_THROW_ON_ERROR);
 $ch=curl_init(LLM_API_URL);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.LLM_API_KEY],CURLOPT_POSTFIELDS=>$body]);
 $response=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$failure=curl_error($ch);curl_close($ch);
 if($response===false||$status<200||$status>=300)throw new RuntimeException('Optional event-assistant service request failed'.($failure?': '.$failure:'.'));
 $decoded=json_decode($response,true);$content=$decoded['choices'][0]['message']['content']??'';
 if(!is_string($content))return null;
 $result=json_decode($content,true);$id=isset($result['event_id'])?(int)$result['event_id']:0;
 foreach($items as $item)if((int)$item['id']===$id)return $id;
 return null;
};
$prompt_next=function(string $current,array $context)use($save,&$reply){
 $prompts=['study_year'=>'What year of study are you in? (1–5)','section'=>'What is your section?','team_name'=>'What is your team name? Reply “none” if not applicable.','tshirt_size'=>'T-shirt size? Choose XS, S, M, L, XL, XXL, or none.','diet'=>'Any dietary preference? Reply “none” if not applicable.','emergency_contact'=>'Emergency contact phone? Reply “none” if not applicable.','special_requirements'=>'Any special requirements? Reply “none” if not applicable.'];
 $save($current,$context);$reply=$prompts[$current]??'';
};
if(preg_match('/^(cancel|stop|nevermind)$/i',$message)){$save('idle',[]);$reply='Registration cancelled. Ask me about events whenever you’re ready.';}
elseif(preg_match('/^(show )?(my )?registrations?$/i',$message)){$s=db()->prepare("SELECT r.id,r.status,e.title,e.event_date FROM registrations r JOIN events e ON e.id=r.event_id WHERE r.user_id=? ORDER BY r.created_at DESC LIMIT 10");$s->execute([$user['id']]);$list=$s->fetchAll();$reply=$list?"Your registrations:\n":'You do not have registrations yet.';foreach($list as $r)$reply.='#'.$r['id'].' '.$r['title'].' — '.$r['status'].' ('.date('M j',strtotime($r['event_date'])).")\n";$save('idle',[]);}
elseif(preg_match('/^(events|list events|what events|upcoming events|hello|hi|help)\b/i',$message)){$reply="Hi ".current_user()['name']."! I can help you explore events and complete registrations.\n".$event_list();$save('idle',[]);}
elseif(preg_match('/\b(when|where|venue|seats?|deadline|date|capacity|how many)\b/i',$message)){
 $items=$events();$asked=$selectEvent($message,$items);
 if(!$asked&&!empty($ctx['event_id']))foreach($items as $item)if((int)$item['id']===(int)$ctx['event_id'])$asked=$item;
 if($asked)$reply=$asked['title'].': '.date('l, M j, Y',strtotime($asked['event_date'])).' at '.$asked['venue'].', '.date('g:i A',strtotime($asked['start_time']??'09:00')).'. '.max(0,(int)$asked['max_participants']-(int)$asked['registered']).' seats left; registration deadline is '.date('M j, g:i A',strtotime($asked['reg_deadline'])).'.';
 else$reply=$event_list();
}
elseif(str_contains($lower,'change phone')){$ctx['resume_state']=$state;$save('change_phone',$ctx);$reply='What 10-digit phone number should I use for this registration?';}
elseif($state==='change_phone'){$phone=preg_replace('/\D/','',$message);if(!preg_match('/^\d{10}$/',$phone))$reply='Please enter exactly 10 digits.';else{$ctx['phone']=$phone;$resume=$ctx['resume_state']??'profile_confirm';unset($ctx['resume_state']);$save($resume,$ctx);$reply=match($resume){'profile_confirm'=>'Phone updated. Are your profile details now correct? Reply yes or no.','confirm'=>'Phone updated. Review the summary and reply confirm to register, or back to edit.','study_year'=>'Phone updated. What year of study are you in? (1–5)','section'=>'Phone updated. What is your section?','team_name'=>'Phone updated. What is your team name? Reply “none” if not applicable.','tshirt_size'=>'Phone updated. Choose a T-shirt size: XS, S, M, L, XL, XXL, or none.','diet'=>'Phone updated. Any dietary preference? Reply “none” if not applicable.','emergency_contact'=>'Phone updated. What is your emergency contact phone?','special_requirements'=>'Phone updated. Any special requirements? Reply “none” if not applicable.',default=>'Phone updated. Continue with the previous step.'};}}
elseif(preg_match('/^(go back|back)$/i',$message)){
 $order=['event','profile_confirm','name','reg_no','dob','department','phone','email','study_year','section','team_name','tshirt_size','diet','emergency_contact','special_requirements','confirm'];$index=array_search($state,$order,true);$next=$index!==false&&$index>0?$order[$index-1]:'event';$save($next,$ctx);$reply=match($next){'event'=>$event_list(),'profile_confirm'=>'Profile details: '.$ctx['name'].' · '.$ctx['reg_no'].' · '.$ctx['department'].' · '.$ctx['phone'].' · '.$ctx['email'].'. Still correct? Reply yes or no.','confirm'=>'Review your details and reply confirm to submit, or back to edit.',$next=>ucfirst(str_replace('_',' ',$next)).' is currently '.($ctx[$next]??'not set').'. Enter the updated value.'};
}
elseif($state==='idle'||$state==='event'){
 $all=$events();$selected=$selectEvent($message,$all);
 if(!$selected&&LLM_API_KEY!==''){try{$match=$llmEvent($message,$all);if($match)foreach($all as $ev)if((int)$ev['id']===$match)$selected=$ev;}catch(RuntimeException $e){$save('event',$ctx);$reply=$e->getMessage().' You can still choose by entering an event title or number.';}}
 if(!$selected&&$reply!==''){}
 elseif(!$selected){$state='event';$save('event',$ctx);$reply=$event_list();}
 else{$ctx=['event_id'=>(int)$selected['id'],'event_title'=>$selected['title']];$s=db()->prepare('SELECT name,reg_no,dob,department,phone,email FROM users WHERE id=?');$s->execute([$user['id']]);$ctx=array_merge($ctx,$s->fetch());$save('profile_confirm',$ctx);$reply='Register for '.$ctx['event_title'].'? Your profile details are: '.$ctx['name'].' · '.$ctx['reg_no'].' · '.$ctx['dob'].' · '.$ctx['department'].' · '.$ctx['phone'].' · '.$ctx['email'].'. Reply yes if correct, or no to update each profile detail.';}
}
elseif($state==='profile_confirm'){
 if(preg_match('/^(yes|y|correct|looks good)$/i',$message)){$ctx['study_year']=1;$prompt_next('study_year',$ctx);$reply='Great. '.$reply;}
 elseif(preg_match('/^(no|edit|change)$/i',$message)){$save('name',$ctx);$reply='What name should appear on this registration?';}
 else{$reply='Please reply “yes” to continue with these details, or “no” to edit them.';}
}
elseif(in_array($state,['name','reg_no','dob','department','phone','email'],true)){
 $valid=true;$value=trim($message);
 if($state==='phone'){$value=preg_replace('/\D/','',$value);$valid=(bool)preg_match('/^\d{10}$/',$value);}
 if($state==='email')$valid=(bool)filter_var($value,FILTER_VALIDATE_EMAIL);
 if($state==='dob'){$d=DateTime::createFromFormat('Y-m-d',$value);$valid=$d&&$d->format('Y-m-d')===$value&&$value<=date('Y-m-d');}
 if($state==='name'||$state==='reg_no'||$state==='department')$valid=$value!=='';
 if(!$valid)$reply=match($state){'phone'=>'Enter a valid 10-digit phone number.','email'=>'Enter a valid email address.','dob'=>'Enter your date of birth as YYYY-MM-DD.','default'=>'That value cannot be blank.'};
 else{$ctx[$state]=$value;$order=['name','reg_no','dob','department','phone','email'];$i=array_search($state,$order,true);$next=$order[$i+1]??'study_year';$save($next,$ctx);$reply=match($next){'reg_no'=>'What is your registration ID?','dob'=>'What is your date of birth? Use YYYY-MM-DD.','department'=>'What is your department?','phone'=>'What is your 10-digit phone number?','email'=>'What is your email address.',$next=>'Thanks. '.$prompt_next('study_year',$ctx)};}
}
elseif(in_array($state,['study_year','section','team_name','tshirt_size','diet','emergency_contact','special_requirements'],true)){
 $value=trim($message);$valid=true;
 if($state==='study_year')$valid=(bool)preg_match('/^[1-5]$/',$value);
 if($state==='tshirt_size'&&!in_array(strtoupper($value),['XS','S','M','L','XL','XXL','NONE','N/A'],true))$valid=false;
 if($state==='emergency_contact'&&!in_array(mb_strtolower($value),['none','n/a',''],true))$valid=(bool)preg_match('/^\+?[0-9 -]{7,20}$/',$value);
 if(!$valid)$reply=match($state){'study_year'=>'Enter a study year from 1 to 5.','tshirt_size'=>'Choose XS, S, M, L, XL, XXL, or none.','emergency_contact'=>'Enter a phone number or “none”.',default=>'Please enter a valid value.'};
 else{$ctx[$state]=in_array(mb_strtolower($value),['none','n/a'],true)?'':($state==='tshirt_size'?strtoupper($value):$value);$order=['study_year','section','team_name','tshirt_size','diet','emergency_contact','special_requirements'];$i=array_search($state,$order,true);$next=$order[$i+1]??'confirm';if($next==='confirm'){$save('confirm',$ctx);$reply="Here’s your registration summary:\nEvent: ".$ctx['event_title']."\nName: ".$ctx['name']." (".$ctx['reg_no'].")\nDepartment: ".$ctx['department']." · phone ".$ctx['phone']."\nStudy year: ".$ctx['study_year']." · section ".$ctx['section']."\nTeam: ".($ctx['team_name']?:'—')." · T-shirt: ".($ctx['tshirt_size']?:'—')."\nDiet: ".($ctx['diet']?:'—')." · emergency contact: ".($ctx['emergency_contact']?:'—')."\nSpecial requirements: ".($ctx['special_requirements']?:'—')."\nReply “confirm” to register, or “back” to make a change.";}else $prompt_next($next,$ctx);}
}
elseif($state==='confirm'){
 if(preg_match('/^(confirm|yes|submit)$/i',$message)){
  try{$ctx['consent']=1;$id=create_registration((int)$user['id'],(int)$ctx['event_id'],$ctx);$s=db()->prepare('SELECT id,block_hash FROM blockchain_ledger WHERE registration_id=? ORDER BY id DESC LIMIT 1');$s->execute([$id]);$block=$s->fetch();$reply='You’re registered! Registration ID #'.$id.'. Blockchain protection: block #'.$block['id'].' · '.substr($block['block_hash'],0,12).'…';$save('idle',[]);}
  catch(Throwable $e){$reply=$e->getMessage().' You can choose another event or start again.';$save('idle',[]);}
 }else{$reply='Reply “confirm” to submit, or “back” to edit a field.';}
}
else{$save('idle',[]);$reply='I can list upcoming events, guide you through registration, or show your registrations. Try “events”.';}
echo json_encode(['reply'=>$reply],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
