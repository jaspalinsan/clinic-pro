<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');

define('DB_HOST','localhost');
define('DB_NAME','u533050603_clinicdb');
define('DB_USER','u533050603_clinicadmin');
define('DB_PASS','ClinicPro2024');

function getDB(){
  try{
    return new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
      DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  }catch(Exception $e){return null;}
}
function sendEvent($data){
  echo "data: ".json_encode($data)."\n\n";
  if(ob_get_level()>0)ob_flush();
  flush();
}

$lastTime=intval($_GET['t']??0);
$pdo=getDB();
if(!$pdo){sendEvent(['error'=>'DB failed']);exit();}

$maxTime=30; $startTime=time(); $pollInterval=3;
sendEvent(['type'=>'connected','time'=>time()]);

while(true){
  if(connection_aborted())break;
  if((time()-$startTime)>=$maxTime){sendEvent(['type'=>'reconnect']);break;}
  try{
    $apptCount=$pdo->query("SELECT COUNT(*) FROM appointments WHERE UNIX_TIMESTAMP(updated_at)>".$lastTime)->fetchColumn();
    $patCount=$pdo->query("SELECT COUNT(*) FROM patients WHERE UNIX_TIMESTAMP(created_at)>".$lastTime)->fetchColumn();
    $billCount=$pdo->query("SELECT COUNT(*) FROM billing WHERE UNIX_TIMESTAMP(updated_at)>".$lastTime)->fetchColumn();
    if($apptCount>0||$patCount>0||$billCount>0){
      sendEvent(['type'=>'update','appts'=>intval($apptCount),'pats'=>intval($patCount),'bills'=>intval($billCount),'ts'=>time()]);
      $lastTime=time();
    } else {
      if((time()-$startTime)%10===0)sendEvent(['type'=>'heartbeat','time'=>time()]);
    }
  }catch(Exception $e){sendEvent(['type'=>'error','msg'=>$e->getMessage()]);break;}
  sleep($pollInterval);
}
?>
