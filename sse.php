<?php
// ═══════════════════════════════════════════════════════════
// CLINIC PRO — SERVER-SENT EVENTS (sse.php)
// Pushes data updates instantly to all connected browsers
// ═══════════════════════════════════════════════════════════

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');

// DB config
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

// Get last known timestamp from client
$lastTime=$_GET['t']??0;
$lastTime=intval($lastTime);

$pdo=getDB();
if(!$pdo){
  sendEvent(['error'=>'DB connection failed']);
  exit();
}

// Keep connection alive for 30 seconds max
$maxTime=30;
$startTime=time();
$pollInterval=2; // check every 2 seconds

// Send initial heartbeat
sendEvent(['type'=>'connected','time'=>time()]);

while(true){
  // Check if client disconnected
  if(connection_aborted()){break;}

  // Check time limit
  if((time()-$startTime)>=$maxTime){
    sendEvent(['type'=>'reconnect']);
    break;
  }

  // Check for data changes since lastTime
  try{
    $stmt=$pdo->prepare(
      "SELECT data_key, data_value, UNIX_TIMESTAMP(updated_at) as ts
       FROM clinic_data
       WHERE UNIX_TIMESTAMP(updated_at) > :lt
       ORDER BY updated_at DESC"
    );
    $stmt->execute([':lt'=>$lastTime]);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);

    if(count($rows)>0){
      $result=[];
      $maxTs=$lastTime;
      foreach($rows as $row){
        $result[$row['data_key']]=json_decode($row['data_value'],true);
        if($row['ts']>$maxTs)$maxTs=$row['ts'];
      }
      sendEvent([
        'type'   =>'update',
        'data'   =>$result,
        'ts'     =>$maxTs,
        'keys'   =>array_keys($result)
      ]);
      $lastTime=$maxTs;
    } else {
      // Heartbeat every 5 seconds to keep connection alive
      if((time()-$startTime)%5===0){
        sendEvent(['type'=>'heartbeat','time'=>time()]);
      }
    }
  }catch(Exception $e){
    sendEvent(['type'=>'error','msg'=>$e->getMessage()]);
    break;
  }

  sleep($pollInterval);
}
?>
