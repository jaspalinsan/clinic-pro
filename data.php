<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-cache, no-store, must-revalidate');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(200);exit();}

define('DB_HOST','localhost');
define('DB_NAME','u533050603_clinicdb');
define('DB_USER','u533050603_clinicadmin');
define('DB_PASS','ClinicPro2024');

function db(){
  static $pdo=null;
  if($pdo)return $pdo;
  $pdo=new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
    DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  return $pdo;
}

function initTables(){
  $pdo=db();
  $pdo->exec("CREATE TABLE IF NOT EXISTS patients(
    id VARCHAR(20) PRIMARY KEY,
    name VARCHAR(200),age VARCHAR(10),gender VARCHAR(5),
    mobile VARCHAR(20),city VARCHAR(100),notes TEXT,blood VARCHAR(5),
    status VARCHAR(20) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS appointments(
    id VARCHAR(50) PRIMARY KEY,
    patient_name VARCHAR(200),patient_id VARCHAR(20),
    age VARCHAR(10),gender VARCHAR(5),mobile VARCHAR(20),
    date VARCHAR(20),time VARCHAR(20),token VARCHAR(20),
    complaint TEXT,status VARCHAR(30) DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  // Lab orders with PDF file storage (LONGTEXT stores base64 up to 4GB)
  $pdo->exec("CREATE TABLE IF NOT EXISTS lab_orders(
    id VARCHAR(50) PRIMARY KEY,
    patient_name VARCHAR(200),patient_id VARCHAR(20),
    age_sex VARCHAR(20),tests TEXT,inv_id VARCHAR(50),
    rx_no VARCHAR(50),date VARCHAR(20),
    status VARCHAR(30) DEFAULT 'pending',
    submitted TINYINT DEFAULT 0,
    result_file LONGTEXT,
    result_type VARCHAR(50),
    result_name VARCHAR(200),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  // Add result columns if table exists without them
  try{$pdo->exec("ALTER TABLE lab_orders ADD COLUMN IF NOT EXISTS result_file LONGTEXT");}catch(Exception $e){}
  try{$pdo->exec("ALTER TABLE lab_orders ADD COLUMN IF NOT EXISTS result_type VARCHAR(50)");}catch(Exception $e){}
  try{$pdo->exec("ALTER TABLE lab_orders ADD COLUMN IF NOT EXISTS result_name VARCHAR(200)");}catch(Exception $e){}
  $pdo->exec("CREATE TABLE IF NOT EXISTS billing(
    id VARCHAR(50) PRIMARY KEY,
    patient_name VARCHAR(200),rx_no VARCHAR(50),
    date VARCHAR(20),description TEXT,
    amount DECIMAL(10,2),status VARCHAR(30) DEFAULT 'Unpaid',
    pay_mode VARCHAR(30),data_json LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS clinic_store(
    store_key VARCHAR(100) PRIMARY KEY,
    store_value LONGTEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$action=$_GET['action']??'';
try{
  initTables();
  $pdo=db();

  // PATIENTS
  if($action==='save_patient'){
    $d=json_decode(file_get_contents('php://input'),true);
    $st=$pdo->prepare("INSERT INTO patients(id,name,age,gender,mobile,city,notes,blood,status)
      VALUES(:id,:name,:age,:gen,:mob,:city,:notes,:blood,:status)
      ON DUPLICATE KEY UPDATE name=:n2,age=:a2,gender=:g2,mobile=:m2,city=:c2,status=:s2");
    $st->execute([':id'=>$d['id']??'',':name'=>$d['name']??'',':age'=>$d['age']??'',
      ':gen'=>$d['gender']??'',':mob'=>$d['mobile']??'',':city'=>$d['city']??'',
      ':notes'=>$d['notes']??'',':blood'=>$d['blood']??'',':status'=>$d['status']??'Active',
      ':n2'=>$d['name']??'',':a2'=>$d['age']??'',':g2'=>$d['gender']??'',
      ':m2'=>$d['mobile']??'',':c2'=>$d['city']??'',':s2'=>$d['status']??'Active']);
    echo json_encode(['ok'=>true]);exit;
  }

  // APPOINTMENTS
  if($action==='save_appointment'){
    $d=json_decode(file_get_contents('php://input'),true);
    $st=$pdo->prepare("INSERT INTO appointments(id,patient_name,patient_id,age,gender,mobile,date,time,token,complaint,status)
      VALUES(:id,:pn,:pid,:age,:gen,:mob,:date,:time,:tok,:comp,:status)
      ON DUPLICATE KEY UPDATE status=:s2,complaint=:c2,patient_name=:pn2,updated_at=NOW()");
    $st->execute([':id'=>$d['id']??'',':pn'=>$d['name']??'',':pid'=>$d['pid']??'',
      ':age'=>$d['age']??'',':gen'=>$d['gender']??'',':mob'=>$d['mob']??'',
      ':date'=>$d['date']??'',':time'=>$d['time']??'',':tok'=>$d['token']??'',
      ':comp'=>$d['complaint']??'',':status'=>$d['status']??'new',
      ':s2'=>$d['status']??'new',':c2'=>$d['complaint']??'',':pn2'=>$d['name']??'']);
    echo json_encode(['ok'=>true]);exit;
  }
  if($action==='update_appt_status'){
    $d=json_decode(file_get_contents('php://input'),true);
    $pdo->prepare("UPDATE appointments SET status=:s,updated_at=NOW() WHERE id=:id")
        ->execute([':s'=>$d['status']??'new',':id'=>$d['id']??'']);
    echo json_encode(['ok'=>true]);exit;
  }

  // LAB
  if($action==='save_lab'){
    $d=json_decode(file_get_contents('php://input'),true);
    $st=$pdo->prepare("INSERT INTO lab_orders(id,patient_name,patient_id,age_sex,tests,inv_id,rx_no,date,status,submitted)
      VALUES(:id,:pn,:pid,:as,:tests,:inv,:rx,:date,:status,:sub)
      ON DUPLICATE KEY UPDATE status=:s2,submitted=:sub2,updated_at=NOW()");
    $st->execute([':id'=>$d['id']??'',':pn'=>$d['patient']??'',':pid'=>$d['patientId']??'',
      ':as'=>$d['ageSex']??'',':tests'=>$d['tests']??'',':inv'=>$d['invId']??'',
      ':rx'=>$d['rxno']??'',':date'=>$d['date']??'',':status'=>$d['status']??'pending',
      ':sub'=>intval($d['submitted']??0),':s2'=>$d['status']??'pending',
      ':sub2'=>intval($d['submitted']??0)]);
    echo json_encode(['ok'=>true]);exit;
  }
  if($action==='update_lab_status'){
    $d=json_decode(file_get_contents('php://input'),true);
    $pdo->prepare("UPDATE lab_orders SET status=:s,submitted=:sub,updated_at=NOW() WHERE id=:id")
        ->execute([':s'=>$d['status']??'pending',':sub'=>intval($d['submitted']??0),':id'=>$d['id']??'']);
    echo json_encode(['ok'=>true]);exit;
  }
  // SAVE LAB PDF — stores base64 file in database so ALL users can see it
  if($action==='save_lab_file'){
    $d=json_decode(file_get_contents('php://input'),true);
    $st=$pdo->prepare("UPDATE lab_orders SET result_file=:rf,result_type=:rt,result_name=:rn,updated_at=NOW() WHERE id=:id");
    $st->execute([':rf'=>$d['result_file']??'',':rt'=>$d['result_type']??'',
      ':rn'=>$d['result_name']??'',':id'=>$d['id']??'']);
    echo json_encode(['ok'=>true,'rows'=>$st->rowCount()]);exit;
  }

  // BILLING
  if($action==='save_billing'){
    $d=json_decode(file_get_contents('php://input'),true);
    $st=$pdo->prepare("INSERT INTO billing(id,patient_name,rx_no,date,description,amount,status,pay_mode,data_json)
      VALUES(:id,:pn,:rx,:date,:desc,:amt,:status,:mode,:json)
      ON DUPLICATE KEY UPDATE patient_name=:pn2,description=:desc2,status=:s2,amount=:a2,pay_mode=:m2,data_json=:j2,updated_at=NOW()");
    $json=json_encode($d,JSON_UNESCAPED_UNICODE);
    $st->execute([':id'=>$d['id']??'',':pn'=>$d['patient']??'',':rx'=>$d['rxno']??'',
      ':date'=>$d['date']??'',':desc'=>$d['desc']??'',
      ':amt'=>floatval($d['amount']??0),':status'=>$d['status']??'Unpaid',
      ':mode'=>$d['paymode']??'',':json'=>$json,
      ':pn2'=>$d['patient']??'',':desc2'=>$d['desc']??'',
      ':s2'=>$d['status']??'Unpaid',':a2'=>floatval($d['amount']??0),
      ':m2'=>$d['paymode']??'',':j2'=>$json]);
    echo json_encode(['ok'=>true]);exit;
  }

  // GENERIC STORE — inventory, vendors, rx templates, settings
  if($action==='store_set'){
    $d=json_decode(file_get_contents('php://input'),true);
    $v=json_encode($d['value'],JSON_UNESCAPED_UNICODE);
    $pdo->prepare("INSERT INTO clinic_store(store_key,store_value)
      VALUES(:k,:v) ON DUPLICATE KEY UPDATE store_value=:v2,updated_at=NOW()")
      ->execute([':k'=>$d['key']??'',':v'=>$v,':v2'=>$v]);
    echo json_encode(['ok'=>true]);exit;
  }
  if($action==='store_get'){
    $key=$_GET['key']??'';
    $st=$pdo->prepare("SELECT store_value FROM clinic_store WHERE store_key=:k");
    $st->execute([':k'=>$key]);
    $row=$st->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['ok'=>true,'data'=>$row?json_decode($row['store_value'],true):null]);exit;
  }

  // GET ALL — includes PDF file data from database
  if($action==='get_all'){
    $patients=$pdo->query("SELECT * FROM patients ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    $appts=$pdo->query("SELECT * FROM appointments ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    // Include result_file so ALL users can see PDF on any computer
    $lab=$pdo->query("SELECT id,patient_name,patient_id,age_sex,tests,inv_id,rx_no,date,status,submitted,result_file,result_type,result_name FROM lab_orders ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    $bill=$pdo->query("SELECT * FROM billing ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach($bill as &$b){
      if($b['data_json']){
        $bj=json_decode($b['data_json'],true);
        $b['extra']=$bj;
        // Expose paidAmt directly for JS
        $b['paid_amt']=$bj['paidAmt']??0;
        $b['meds_json']=isset($bj['meds'])?$bj['meds']:[];
        $b['svcs_json']=isset($bj['svcs'])?$bj['svcs']:[];
      }
    }
    $inv=$pdo->query("SELECT store_value FROM clinic_store WHERE store_key='inventory'")->fetch(PDO::FETCH_ASSOC);
    $rx=$pdo->query("SELECT store_value FROM clinic_store WHERE store_key='rx_saved'")->fetch(PDO::FETCH_ASSOC);
    $vnd=$pdo->query("SELECT store_value FROM clinic_store WHERE store_key='vendors'")->fetch(PDO::FETCH_ASSOC);
    $tpl=$pdo->query("SELECT store_value FROM clinic_store WHERE store_key='rx_templates'")->fetch(PDO::FETCH_ASSOC);
    echo json_encode([
      'ok'=>true,
      'patients'=>$patients,
      'appointments'=>$appts,
      'lab'=>$lab,
      'billing'=>$bill,
      'inventory'=>$inv?json_decode($inv['store_value'],true):[],
      'rx_saved'=>$rx?json_decode($rx['store_value'],true):[],
      'vendors'=>$vnd?json_decode($vnd['store_value'],true):[],
      'rx_templates'=>$tpl?json_decode($tpl['store_value'],true):[],
      'ts'=>time()
    ]);exit;
  }

  if($action==='delete_appointment'){
    $d=json_decode(file_get_contents('php://input'),true);
    if(!isset($d['id'])){echo json_encode(['ok'=>false]);exit();}
    $st=$pdo->prepare("DELETE FROM appointments WHERE id=:id");
    $st->execute([':id'=>$d['id']]);
    echo json_encode(['ok'=>true]);exit();
  }
  if($action==='ping'){
    echo json_encode(['ok'=>true,'msg'=>'data.php running','time'=>date('Y-m-d H:i:s')]);exit;
  }
  echo json_encode(['error'=>'Unknown action: '.$action]);
}catch(Exception $e){
  echo json_encode(['error'=>$e->getMessage()]);
}
?>
