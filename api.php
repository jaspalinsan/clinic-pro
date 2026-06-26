<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(200);exit();}

define('DB_HOST','localhost');
define('DB_NAME','u533050603_clinicdb');
define('DB_USER','u533050603_clinicadmin');
define('DB_PASS','ClinicPro2024');

function getDB(){
  try{
    return new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
      DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  }catch(Exception $e){
    die(json_encode(['error'=>'DB failed: '.$e->getMessage()]));
  }
}
function initDB($pdo){
  $pdo->exec("CREATE TABLE IF NOT EXISTS clinic_data(
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_key VARCHAR(100) NOT NULL UNIQUE,
    data_value LONGTEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$action=$_GET['action']??'';
$pdo=getDB();
initDB($pdo);

if($action==='save'){
  $raw=file_get_contents('php://input');
  $data=json_decode($raw,true);
  if(!$data){echo json_encode(['success'=>false,'error'=>'Invalid JSON']);exit();}
  $keys=['PAT_DB','_apptData','BILLING_DB','LAB_DB','INV_DB',
         '_rxSaved','_svcList','MY_RX_TEMPLATES','CLINIC_SETTINGS'];
  $stmt=$pdo->prepare("INSERT INTO clinic_data(data_key,data_value)
    VALUES(:k,:v) ON DUPLICATE KEY UPDATE data_value=:v2,updated_at=NOW()");
  foreach($keys as $key){
    if(isset($data[$key])){
      $val=json_encode($data[$key],JSON_UNESCAPED_UNICODE);
      $stmt->execute([':k'=>$key,':v'=>$val,':v2'=>$val]);
    }
  }
  echo json_encode(['success'=>true,'saved_at'=>date('Y-m-d H:i:s')]);
  exit();
}

if($action==='save_users'){
  $raw=file_get_contents('php://input');
  $data=json_decode($raw,true);
  if(!$data||!isset($data['users'])){echo json_encode(['success'=>false]);exit();}
  $val=json_encode($data['users'],JSON_UNESCAPED_UNICODE);
  $stmt=$pdo->prepare("INSERT INTO clinic_data(data_key,data_value)
    VALUES('cp_users',:v) ON DUPLICATE KEY UPDATE data_value=:v2,updated_at=NOW()");
  $stmt->execute([':v'=>$val,':v2'=>$val]);
  echo json_encode(['success'=>true]);
  exit();
}

if($action==='load'){
  $stmt=$pdo->query("SELECT data_key,data_value FROM clinic_data");
  $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
  $result=[];
  foreach($rows as $row){
    $result[$row['data_key']]=json_decode($row['data_value'],true);
  }
  echo json_encode(['success'=>true,'data'=>$result]);
  exit();
}

if($action==='ping'){
  echo json_encode(['success'=>true,'message'=>'Clinic Pro API running',
    'database'=>DB_NAME,'time'=>date('Y-m-d H:i:s')]);
  exit();
}

echo json_encode(['error'=>'Unknown action']);
?>
