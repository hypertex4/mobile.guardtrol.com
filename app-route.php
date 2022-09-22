<?php
ini_set('max_execution_time', 999999);
ini_set('memory_limit','999999M');
ini_set('upload_max_filesize', '500M');
ini_set('max_input_time', '-1');
ini_set('max_execution_time', '-1');

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization,Developer_Key");

require ABSPATH.'/classes/vendor/autoload.php';

use \Firebase\JWT\JWT;
use \Mailjet\Resources;

date_default_timezone_set('Africa/Lagos'); // WAT

function app_db () {
    include_once ABSPATH.'/config/app-config.php';

    $db_conn = array(
        'host' => DB_HOST, 
        'user' => DB_USER,
        'pass' => DB_PASSWORD,
        'database' => DB_NAME, 
    );
    $db = new SimpleDBClass($db_conn);
    return $db;     
}

function getDevAccessKeyHeader(){
    $headers = null;
    if (isset($_SERVER['Developer_key'])) {
        $headers = trim($_SERVER["Developer_Key"]);
    } else if (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        if (isset($requestHeaders['Developer_Key'])) {
            $headers = trim($requestHeaders['Developer_Key']);
        }
    }
    return $headers;
}

function getDeveloperKey() {
    $headers = getDevAccessKeyHeader();
    if (!empty($headers)) {
         return $headers;
    }
    return null;
}

function clean($string) {
    $string = str_replace(' ', '_', $string);

    return preg_replace('/[^A-Za-z0-9._\-]/', '', $string);
}

function get_admin_login_permission_id(){
    $db = app_db();
    $query = $db->select("SELECT * FROM tbl_permissions WHERE perm_description = 'Admin Mobile Login' AND 
                                        perm_category='HR/ADMIN' AND perm_sub_cat='staff_detail'");
    if ($query > 0) {
        return $query[0];
    }
    return array();
}

function staff_perm_ids($staff_id){
    $db = app_db();
    $query = $db->select("SELECT * FROM tbl_company_roles_perm WHERE staff_id='$staff_id'");
    if ($query > 0) {
        return $query[0];
    }
    return array();
}

$router->map( 'GET', '/', function() {
	$ajax_url = AJAX_URL;
	include  ABSPATH.'/views/index.php';
});

$router->map( 'GET', '/v1/api/test', function() {
	$db = app_db();
	$developer_key = getDeveloperKey();
	// $q0 = $db->select("select * from t1 where email='$email' ");

	// if($q0 > 0) {
        http_response_code(200);
		echo json_encode(array('status'=>'success', 'msg' => 'Endpoint working fine => '.$developer_key));
	// } else {
	// 	echo json_encode(array('status'=>'error', 'msg' => 'no records found', 'emails'=> $q0,));
	// }
});

$router->map( 'GET', '/v1/api/get-guard/[*:action]', function($guard_id) {
    $db = app_db();
    $dev_key = getDeveloperKey();
    $dev_key_res = $db->select("select * from tbl_developers where access_code='".$dev_key."' ");
    if (!empty($dev_key) && ($dev_key_res)) {
        $q0 = $db->select("SELECT * FROM tbl_guards WHERE guard_id='$guard_id' AND guard_status='Active'");
        if($q0 > 0) {
            $q1 = $db->select("SELECT g.*,d.*,b.* FROM tbl_guard_deployments d 
                        INNER JOIN tbl_guards g ON g.guard_id=d.guard_id 
                        INNER JOIN tbl_beats b ON b.beat_id=d.beat_id 
                        WHERE d.guard_id='$guard_id'");
            if($q1 > 0) {
                $guard_arr = array();
                $guard_arr[] = array(
                    "guard_id" => $q1[0]['guard_id'],
                    "company_id" => $q1[0]['company_id'],
                    "client_id" => $q1[0]['client_id'],
                    "guard_firstname" => $q1[0]['guard_firstname'],
                    "guard_middlename" => $q1[0]['guard_middlename'],
                    "guard_lastname" => $q1[0]['guard_lastname'],
                    "guard_photo" => "https://imonitor.guardtrol.com/public/uploads/guard/".$q1[0]['guard_photo'],
                    "beat_name" => $q1[0]['beat_name'],
                    "beat_address" => $q1[0]['beat_address'],
                    "date_of_deployment" => $q1[0]['date_of_deployment']
                );
                http_response_code(200);
                echo json_encode(array("status" => 1, "data" => $guard_arr,"msg" => "record found"));
            } else {
                http_response_code(400);
                echo json_encode(array('status'=>'error', 'msg' => "Guard not yet deployed, contact admin/operations.",));
            }
        } else {
            http_response_code(400);
            echo json_encode(array('status'=>0, 'msg' => 'Account not found OR suspended, contact your supervisor',));
        }
    } else {
        http_response_code(400);
        echo json_encode(array('status' => 0, 'msg' => 'Unauthorized dev! missing/invalid developer key'));
    }
});

$router->map( 'GET', '/v1/api/get-beats', function() {
    $db = app_db();
    $dev_key = getDeveloperKey();
    $dev_key_res = $db->select("select * from tbl_developers where access_code='".$dev_key."' ");
    if (!empty($dev_key) && ($dev_key_res)) {
        $q0 = $db->Select("SELECT b.*,bl.* FROM tbl_beats b LEFT JOIN tbl_beat_loc_reg bl ON b.beat_id=bl.bl_beat_id");

        if($q0 > 0) {
            $beat_arr = array();
            foreach ($q0 as $row) {
                $q1 = $db->Select("SELECT * FROM tbl_beat_personnel_services WHERE bps_beat='".$row['beat_id']."'");
                $beat_arr[] = array(
                    "beat_id" => $row['beat_id'],
                    "company_id" => $row['company_id'],
                    "client_id" => $row['client_id'],
                    "beat_name" => $row['beat_name'],
                    "beat_address" => $row['beat_address'],
                    "beat_monthly_charges" => $row['beat_monthly_charges'],
                    "beat_status" => $row['beat_status'],
                    "beat_vat_config" => $row['beat_vat_config'],
                    "date_of_deployment" => $row['date_of_deployment'],
                    "bl_beat_id" => $row['bl_beat_id'],
                    "beat_loc_long" => $row['beat_loc_long'],
                    "beat_loc_lati" => $row['beat_loc_lati'],
                    "beat_loc_address" => $row['beat_loc_address'],
                    "personnel" => $q1
                );
            }
            http_response_code(200);
            echo json_encode(array("status" => 1, "data" => $beat_arr,"msg" => "record found"));
        } else {
            http_response_code(400);
            echo json_encode(array('status'=>0, 'msg' => 'No beat found',));
        }
    } else {
        http_response_code(400);
        echo json_encode(array('status' => 0, 'msg' => 'Unauthorized dev! missing/invalid developer key'));
    }
});

$router->map( 'GET', '/v1/api/get-beat/[*:action]', function($beat_id) {
    $db = app_db();
    $dev_key = getDeveloperKey();
    $dev_key_res = $db->select("select * from tbl_developers where access_code='".$dev_key."' ");
    if (!empty($dev_key) && ($dev_key_res)) {
        $q0 = $db->Select("SELECT b.*,bl.* FROM tbl_beats b LEFT JOIN tbl_beat_loc_reg bl ON b.beat_id=bl.bl_beat_id WHERE b.beat_id='$beat_id'");

        if($q0 > 0) {
            $beat_arr = array();
            foreach ($q0 as $row) {
                $q1 = $db->Select("SELECT * FROM tbl_beat_personnel_services WHERE bps_beat='".$row['beat_id']."'");
                $beat_arr[] = array(
                    "beat_id" => $row['beat_id'],
                    "company_id" => $row['company_id'],
                    "client_id" => $row['client_id'],
                    "beat_name" => $row['beat_name'],
                    "beat_address" => $row['beat_address'],
                    "beat_monthly_charges" => $row['beat_monthly_charges'],
                    "beat_status" => $row['beat_status'],
                    "beat_vat_config" => $row['beat_vat_config'],
                    "date_of_deployment" => $row['date_of_deployment'],
                    "bl_beat_id" => $row['bl_beat_id'],
                    "beat_loc_long" => $row['beat_loc_long'],
                    "beat_loc_lati" => $row['beat_loc_lati'],
                    "beat_loc_address" => $row['beat_loc_address'],
                    "personnel" => $q1
                );
            }
            http_response_code(200);
            echo json_encode(array("status" => 1, "data" => $beat_arr,"msg" => "record found"));
        } else {
            http_response_code(400);
            echo json_encode(array('status'=>0, 'msg' => 'No beat found',));
        }
    } else {
        http_response_code(400);
        echo json_encode(array('status' => 0, 'msg' => 'Unauthorized dev! missing/invalid developer key'));
    }
});

$router->map( 'POST', '/v1/api/get-beat-guards', function() {
//    $beats_arr = json_decode(file_get_contents("php://input"));

     $beats_arr = array("7886700","8317816","6655637","2567812");
    
    $db = app_db();
    $dev_key = getDeveloperKey();
    $dev_key_res = $db->select("select * from tbl_developers where access_code='".$dev_key."' ");
    if (!empty($dev_key) && ($dev_key_res)) {
        if(!empty($beats_arr)){
            $array = implode("','",$beats_arr);
            $q0 = $db->Select("SELECT gd.*,g.* FROM tbl_guard_deployments gd 
                                            INNER JOIN tbl_guards g ON g.guard_id = gd.guard_id 
                                            WHERE gd.beat_id IN ('".$array."')");
            if($q0 > 0) {
                $guard_arr = array();
                foreach ($q0 as $row) {
                    $r0 = $db->Select("SELECT rp.*,r.* FROM tbl_guard_routes r 
                                        INNER JOIN tbl_routes rp ON rp.route_name=r.g_route_name WHERE r.guard_id='".$row['guard_id']."'");
                    $points = ($r0 > 0) ? $r0 : array();
                    $guard_arr[] = array(
                        "guard_id" => $row['guard_id'],
                        "company_id" => $row['company_id'],
                        "client_id" => $row['client_id'],
                        "guard_firstname" => $row['guard_firstname'],
                        "guard_middlename" => $row['guard_middlename'],
                        "guard_lastname" => $row['guard_lastname'],
                        "beat_id" => $row['beat_id'],
                        "route_points" => $points,
                        "guard_photo" => "https://imonitor.guardtrol.com/public/uploads/guard/" . $row['guard_photo'],
                        "date_of_deployment" => date("d-m-Y",strtotime($row['dop']))
                    );
                }
                http_response_code(200);
                echo json_encode(array("status" => 1, "data" => $guard_arr,"msg" => "record found"));
            } else {
                http_response_code(200);
                echo json_encode(array('status'=>0, 'msg' => 'No guard found in beat',));
            }
        } else {
            http_response_code(400);
            echo json_encode(array('status' => 0, 'msg' => 'At least one beat id is required in array'));
        }
    } else {
        http_response_code(400);
        echo json_encode(array('status' => 0, 'msg' => 'Unauthorized dev! missing/invalid developer key'));
    }
});

$router->map( 'POST', '/v1/api/add-route-point', function() {
    $data = json_decode(file_get_contents("php://input"));
    $db = app_db();
    $dev_key = getDeveloperKey();
    $dev_key_res = $db->select("select * from tbl_developers where access_code='".$dev_key."' ");
    if (!empty($dev_key) && ($dev_key_res)) {
        $unique_key = preg_replace('/[^A-Za-z0-9]/', "", $data->route_name).'_'.$data->beat_id;
        $insert_route_arrays = array
        (
            'company_id' => $db->CleanDBData($data->company_id),
            'route_id' => $unique_key,
            'route_name' => $db->CleanDBData($data->route_name),
            'route_beat_id' => $db->CleanDBData($data->beat_id),
            'route_created_on' => $db->CleanDBData(date("d-m-Y H:i:s")),
        );

        $insert_points_array = array
        (
            'rp_id' => $unique_key,
            'company_id' => $db->CleanDBData($data->company_id),
            'route_name' => $db->CleanDBData($data->route_name),
            'point_code' => $db->CleanDBData($data->point_code),
            'point_long' => $db->CleanDBData($data->point_long),
            'point_lati' => $db->CleanDBData($data->point_lati),
            'rp_created_on' => $db->CleanDBData(date("d-m-Y H:i:s")),
        );

        $check_route = $db->select("select * from tbl_routes where route_name='".$data->route_name."' ");
        $q1 = $db->Insert('tbl_route_points', $insert_points_array);
        if (empty($check_route)) {
            $q0 = $db->Insert('tbl_routes', $insert_route_arrays);
        }
        if ($q1 > 0) {
        http_response_code(200);
            echo json_encode(array('status' => 1, 'msg' => 'Beat route/points created successfully.'));
        } else {
        http_response_code(400);
            echo json_encode(array('status' => 0, 'msg' => 'Unable to create route/points, possible invalid point code or try again later'));
        }
    } else {
        http_response_code(400);
        echo json_encode(array('status' => 0, 'msg' => 'Unauthorized dev! missing/invalid developer key'));
    }
});

$router->map( 'GET', '/v1/api/get-route-points/[*:action]', function($route_name) {
    $db = app_db();
    $dev_key = getDeveloperKey();
    $dev_key_res = $db->select("select * from tbl_developers where access_code='".$dev_key."' ");
    if (!empty($dev_key) && ($dev_key_res)) {
        if (!empty(trim($route_name))) {
            $q0 = $db->Select("SELECT r.*,rp.* FROM tbl_routes r inner join tbl_route_points rp on rp.route_name=r.route_name WHERE r.route_name='" . $route_name . "'");
            if ($q0 > 0) {
                http_response_code(200);
                echo json_encode(array("status" => 1, "data" => $q0, "msg" => "record found"));
            } else {
                http_response_code(400);
                echo json_encode(array('status' => 0, 'msg' => 'No route point found in beat.',));
            }
        } else {
            http_response_code(400);
            echo json_encode(array('status' => 0, 'msg' => 'Route id cannot be empty.',));
        }
    } else {
        http_response_code(400);
        echo json_encode(array('status' => 0, 'msg' => 'Unauthorized dev! missing/invalid developer key'));
    }
});

$router->map( 'POST', '/v1/api/add-beat-location', function() {
    $data = json_decode(file_get_contents("php://input"));
    $db = app_db();
    $dev_key = getDeveloperKey();
    $dev_key_res = $db->select("select * from tbl_developers where access_code='".$dev_key."' ");
    if (!empty($dev_key) && ($dev_key_res)) {
        $insert_arrays = array
        (
            'bl_beat_id' => $db->CleanDBData($data->beat_id),
            'company_id' => $db->CleanDBData($data->company_id),
            'beat_loc_long' => $db->CleanDBData($data->beat_loc_long),
            'beat_loc_lati' => $db->CleanDBData($data->beat_loc_lati),
            'beat_loc_address' => $db->CleanDBData($data->beat_loc_address),
            'blr_created_on' => $db->CleanDBData(date("d-m-Y H:i:s")),
        );

        $q0 = $db->Insert('tbl_beat_loc_reg', $insert_arrays);
        if ($q0 > 0) {
            http_response_code(200);
            echo json_encode(array('status' => 1, 'msg' => 'Beat location registered successfully.', "beat_loc_sno" => $q0));
        } else {
            http_response_code(400);
            echo json_encode(array('status' => 0, 'msg' => 'Unable to create route point, try again later'));
        }
    } else {
        http_response_code(400);
        echo json_encode(array('status' => 0, 'msg' => 'Unauthorized dev! missing/invalid developer key'));
    }
});

$router->map( 'DELETE', '/v1/api/delete-route/[*:action]', function($route_name) {
    $data = json_decode(file_get_contents("php://input"));
    $db = app_db();
    $dev_key = getDeveloperKey();
    $dev_key_res = $db->select("select * from tbl_developers where access_code='".$dev_key."' ");
    if (!empty($dev_key) && ($dev_key_res)) {
       if (!empty(trim($route_name)) && trim($route_name) !=="") {
            $array_where = array('route_name' => $db->CleanDBData(urldecode($route_name)));
            $q0 = $db->Delete('tbl_route_points', $array_where);
            $q1 = $db->Delete('tbl_routes', $array_where);


            if ($q0 > 0) {
                http_response_code(200);
                echo json_encode(array('status' => 1, 'msg' => 'Beat route deleted successfully.'));
            } else {
                http_response_code(400);
                echo json_encode(array('status' => 0, 'msg' => 'Server error, Unable to delete route'));
            }
       } else {
           http_response_code(400);
           echo json_encode(array('status' => 0, 'msg' => 'Route id is required'));
       }
    } else {
        http_response_code(400);
        echo json_encode(array('status' => 0, 'msg' => 'Unauthorized dev! missing/invalid developer key'));
    }
});

$router->map( 'DELETE', '/v1/api/delete-route-point/[*:action]', function($point_code) {
    $data = json_decode(file_get_contents("php://input"));
    $db = app_db();
    $dev_key = getDeveloperKey();
    $dev_key_res = $db->select("select * from tbl_developers where access_code='".$dev_key."' ");
    if (!empty($dev_key) && ($dev_key_res)) {
        if (!empty(trim($point_code)) && trim($point_code) !=="") {
            $rp0 = $db->Select("SELECT * FROM tbl_route_points WHERE point_code='$point_code'");
            $route_name = $rp0[0]['route_name'];

            if (!empty($rp0[0]['route_name'])) {
                $rp0 = $db->Select("SELECT COUNT(*) AS count_point FROM tbl_route_points WHERE route_name='" . urldecode($route_name) . "'");
                if ($rp0[0]['count_point'] <= 1) {
                    $array_where2 = array('route_name' => $db->CleanDBData($route_name));
                    $db->Delete('tbl_routes', $array_where2);
                }

                $array_where = array('point_code' => $db->CleanDBData($point_code));
                $q0 = $db->Delete('tbl_route_points', $array_where);

                if ($q0 > 0) {
                    http_response_code(200);
                    echo json_encode(array('status' => 1, 'msg' => 'Beat route point deleted successfully.'));
                } else {
                    http_response_code(400);
                    echo json_encode(array('status' => 0, 'msg' => 'Server error, Unable to delete route point'));
                }
            } else {
                http_response_code(400);
                echo json_encode(array('status' => 0, 'msg' => 'Route point code cannot be found'));
            }
        } else {
            http_response_code(400);
            echo json_encode(array('status' => 0, 'msg' => 'Route point code id is required'));
        }
    } else {
        http_response_code(400);
        echo json_encode(array('status' => 0, 'msg' => 'Unauthorized dev! missing/invalid developer key'));
    }
});

$router->map( 'POST', '/v1/api/send-attendance', function() {
    $data = json_decode(file_get_contents("php://input"));
    $db = app_db();
    $dev_key = getDeveloperKey();
    $dev_key_res = $db->select("select * from tbl_developers where access_code='".$dev_key."' ");
    if (!empty($dev_key) && ($dev_key_res)) {
        $insert_arrays = array
        (
            'at_id' => $db->CleanDBData(rand(10000,99999)),
            'company_id' => $db->CleanDBData($data->company_id),
            'guard_id' => $db->CleanDBData($data->guard_id),
            'a_if_present' => $db->CleanDBData($data->a_if_present),
            'clock_in_time' => $db->CleanDBData($data->clock_in_time),
            'clock_out_time' => $db->CleanDBData($data->clock_out_time),
            'created_at' => $db->CleanDBData(date("d-m-Y H:i:s"))
        );

        $q0 = $db->Insert('tbl_send_attendance', $insert_arrays);
        if ($q0 > 0) {
            http_response_code(200);
            echo json_encode(array('status' => 1, 'msg' => 'Attendance sent successfully.', "att_sno" => $q0));
        } else {
            http_response_code(400);
            echo json_encode(array('status' => 0, 'msg' => 'Unable to send attendance, try again later'));
        }
    } else {
        http_response_code(400);
        echo json_encode(array('status' => 0, 'msg' => 'Unauthorized dev! missing/invalid developer key'));
    }
});

$router->map( 'POST', '/v1/api/complete-route', function() {
    $data = json_decode(file_get_contents("php://input"));
    $db = app_db();
    $dev_key = getDeveloperKey();
    $dev_key_res = $db->select("select * from tbl_developers where access_code='".$dev_key."' ");
    if (!empty($dev_key) && ($dev_key_res)) {
        $insert_arrays = array
        (
            'cr_id' => $db->CleanDBData(rand(10000,99999)),
            'company_id' => $db->CleanDBData($data->company_id),
            'guard_id' => $db->CleanDBData($data->guard_id),
            'route_name' => $db->CleanDBData($data->route_name),
            'start_time' => $db->CleanDBData($data->start_time),
            'end_time' => $db->CleanDBData($data->end_time),
            'cp_created_on' => $db->CleanDBData(date("d-m-Y H:i:s"))
        );

        $q0 = $db->Insert('tbl_completed_route', $insert_arrays);
        if ($q0 > 0) {
            http_response_code(200);
            echo json_encode(array('status' => 1, 'msg' => 'Route completed successfully.', "c_route_sno" => $q0));
        } else {
            http_response_code(400);
            echo json_encode(array('status' => 0, 'msg' => 'Unable to complete route, try again later'));
        }
    } else {
        http_response_code(400);
        echo json_encode(array('status' => 0, 'msg' => 'Unauthorized dev! missing/invalid developer key'));
    }
});

$router->map( 'POST', '/v1/api/break-time', function() {
    $data = json_decode(file_get_contents("php://input"));
    $db = app_db();
    $dev_key = getDeveloperKey();
    $dev_key_res = $db->select("select * from tbl_developers where access_code='".$dev_key."' ");
    if (!empty($dev_key) && ($dev_key_res)) {
        $insert_arrays = array
        (
            'btm_id' => $db->CleanDBData(rand(10000,99999)),
            'company_id' => $db->CleanDBData($data->company_id),
            'guard_id' => $db->CleanDBData($data->guard_id),
            'reason' => $db->CleanDBData($data->reason),
            'break_time' => $db->CleanDBData($data->break_time),
            'break_start' => $db->CleanDBData($data->break_start),
            'break_created_on' => $db->CleanDBData(date("d-m-Y H:i:s"))
        );

        $q0 = $db->Insert('tbl_break_time', $insert_arrays);
        if ($q0 > 0) {
            http_response_code(200);
            echo json_encode(array('status' => 1, 'msg' => 'Break time details sent successfully.', "brk_time_sno" => $q0));
        } else {
            http_response_code(400);
            echo json_encode(array('status' => 0, 'msg' => 'Unable to send break time details, try again later'));
        }
    } else {
        http_response_code(400);
        echo json_encode(array('status' => 0, 'msg' => 'Unauthorized dev! missing/invalid developer key'));
    }

});

$router->map( 'POST', '/v1/api/send-beat-routing-task-old', function() {
    $data = json_decode(file_get_contents("php://input"));
    $db = app_db();
    $dev_key = getDeveloperKey();
    $dev_key_res = $db->select("select * from tbl_developers where access_code='".$dev_key."' ");
    if (!empty($dev_key) && ($dev_key_res)) {
        $insert_arrays = array
        (
            'company_id' => $db->CleanDBData($data->company_id),
            'guard_id' => $db->CleanDBData($data->guard_id),
            'route_id' => $db->CleanDBData($data->route_id),
            'route_status' => $db->CleanDBData($data->route_status),
            'start_time' => $db->CleanDBData($data->start_time),
            'end_time' => $db->CleanDBData($data->end_time),
            'cp_created_on' => $db->CleanDBData(date("Y-m-d H:i:s")),
            'at_beat_id' => $db->CleanDBData($data->beat_id),

        );

        $q0 = $db->Insert('tbl_beat_routing_task', $insert_arrays);
        if ($q0 > 0) {
            http_response_code(200);
            echo json_encode(array('status' => 1, 'msg' => 'Routing information sent.', "route_task" => $q0));
        } else {
            http_response_code(400);
            echo json_encode(array('status' => 0, 'msg' => 'Unable to save Routing information, try again later'));
        }
    } else {
        http_response_code(400);
        echo json_encode(array('status' => 0, 'msg' => 'Unauthorized dev! missing/invalid developer key'));
    }
});

$router->map( 'POST', '/v1/api/report-incident', function() {
    $data = json_decode(file_get_contents("php://input"));
    $db = app_db();
    $dev_key = getDeveloperKey();
    $dev_key_res = $db->select("select * from tbl_developers where access_code='".$dev_key."' ");
    if (!empty($dev_key) && ($dev_key_res)) {
        $insert_arrays = array
        (
            'inc_rep_id' => "RP".rand(100000,999999),
            'company_id' => $db->CleanDBData($data->company_id),
            'guard_id' => $db->CleanDBData($data->guard_id),
            'report_title' => $db->CleanDBData($data->inc_title),
            'report_beat' => $db->CleanDBData($data->beat_id),
            'report_occ_date' => $db->CleanDBData($data->inc_date),
            'report_describe' => $db->CleanDBData($data->inc_desc),
            'report_created_on' => $db->CleanDBData(date("d-m-Y H:i:s"))
        );

        $q0 = $db->Insert('tbl_incident_reports', $insert_arrays);
        if ($q0 > 0) {
            http_response_code(200);
            echo json_encode(array('status' => 1, 'msg' => 'Incident report sent successfully.', "inc_sno" => $q0));
        } else {
            http_response_code(400);
            echo json_encode(array('status' => 0, 'msg' => 'Unable to send incident report details, try again later'));
        }
    } else {
        http_response_code(400);
        echo json_encode(array('status' => 0, 'msg' => 'Unauthorized dev! missing/invalid developer key'));
    }
});

$router->map( 'GET', '/v1/api/get-all-route-points', function() {
    $db = app_db();
    $dev_key = getDeveloperKey();
    $dev_key_res = $db->select("select * from tbl_developers where access_code='".$dev_key."' ");
    if (!empty($dev_key) && ($dev_key_res)) {
        $q0 = $db->Select("SELECT r.*,rp.* FROM tbl_routes r inner join tbl_route_points rp on rp.route_name=r.route_name");

        if($q0 > 0) {
            http_response_code(200);
            echo json_encode(array("status" => 1, "data" => $q0,"msg" => "record found"));
        } else {
            http_response_code(200);
            echo json_encode(array('status'=>0, "data" => $q0, 'msg' => 'No route point found',));
        }
    } else {
        http_response_code(400);
        echo json_encode(array('status' => 0, 'msg' => 'Unauthorized dev! missing/invalid developer key'));
    }
});

$router->map( 'POST', '/v1/api/clock-in-failed-attempt', function() {
    $data = json_decode(file_get_contents("php://input"));
    $db = app_db();
    $dev_key = getDeveloperKey();
    $dev_key_res = $db->select("select * from tbl_developers where access_code='".$dev_key."' ");
    if (!empty($dev_key) && ($dev_key_res)) {
        if (!empty($data->guard_id) && !empty($data->date_time) && !empty($data->beat_id)){
            $insert_arrays = array
            (
                'company_id' => $db->CleanDBData($data->company_id),
                'guard_id' => $db->CleanDBData($data->guard_id),
                'date_time' => $db->CleanDBData($data->date_time),
                'beat_id' => $db->CleanDBData($data->beat_id)
            );
            $q0 = $db->Insert('tbl_clock_in_failed_attempt', $insert_arrays);
            if ($q0 > 0) {
                http_response_code(200);
                echo json_encode(array('status' => 1, 'msg' => 'Clock-in attempt submitted successfully.'));
            } else {
                http_response_code(400);
                echo json_encode(array('status' => 0, 'msg' => 'Unable to save details.'));
            }
        } else {
            http_response_code(400);
            echo json_encode(array('status' => 0, 'msg' => 'Fill all required field'));
        }
    } else {
        http_response_code(400);
        echo json_encode(array('status' => 0, 'msg' => 'Unauthorized dev! missing/invalid developer key'));
    }
});

$router->map( 'GET', '/v1/api/get-latest-app-version-update', function() {
    $db = app_db();
    $dev_key = getDeveloperKey();
    $dev_key_res = $db->select("select * from tbl_developers where access_code='".$dev_key."' ");
    if (!empty($dev_key) && ($dev_key_res)) {
        $q0 = $db->Select("SELECT * FROM tbl_mobile_app_updates ORDER BY mob_id DESC LIMIT 1");

        if ($q0 > 0) {
            $v_arr = array();
            foreach ($q0 as $row) {
                $v_arr = array(
                    "app_name" => $row['app_name'],
                    "apk_importance" => $row['apk_importance'],
                    "apk_version" => $row['apk_version'],
                    "apk_file" => $row['apk_file'],
                    "mob_created" => date("d-m-Y h:i:a", strtotime($row['mob_created']))
                );
            }
            http_response_code(200);
            echo json_encode(array("status" => 1,"data"=>$v_arr,"msg"=>"record found"));
        } else {
            http_response_code(400);
            echo json_encode(array('status'=>0, 'msg' => 'No app version updates found',));
        }
    } else {
        http_response_code(400);
        echo json_encode(array('status' => 0, 'msg' => 'Unauthorized dev! missing/invalid developer key'));
    }
});

$router->map( 'POST', '/v1/api/admin-login', function() {
    $data = json_decode(file_get_contents("php://input"));
    $db = app_db();
    $dev_key = getDeveloperKey();
    $dev_key_res = $db->select("select * from tbl_developers where access_code='".$dev_key."' ");
    if (!empty($dev_key) && ($dev_key_res)) {
        $ch0 = $db->select("select * from tbl_staff where staff_email='".$db->CleanDBData($data->staff_email)."'");
        $ch1 = $db->select("select * from tbl_staff where staff_acc_status='Deactivate' and staff_email='".$db->CleanDBData($data->staff_email)."'");
        if ($ch0 <= 0) {
            http_response_code(400);
            echo json_encode(array('status' => 0, 'msg' => 'Staff email not found',));
        } else {
            $staff_id = htmlspecialchars(strip_tags($ch0[0]['staff_id']));
            $permission_sno = get_admin_login_permission_id();
            $staff_perm_ids = staff_perm_ids($staff_id);

            $array = array_map('intval', explode(',',$staff_perm_ids['perm_sno']));

            if(!in_array($permission_sno['perm_sno'], $array)){
                http_response_code(200);
                echo json_encode(array("status" => 0, "message" => "Access denied as you do not have sufficient privileges"));
            } else {
                if ($ch1 > 0) {
                    http_response_code(400);
                    echo json_encode(array('status' => 0, 'msg' => 'Account is inactive, kindly activate account or contact our support'));
                } else {
                    $password_used = $ch0[0]['staff_password'];
                    if (password_verify($data->staff_password, $password_used)) {
                        $iss = 'localhost';
                        $iat = time();
                        $nbf = $iat; // issued after 1 secs of been created
                        $exp = $iat + (86400 * 7); // expired after 7days of been created
                        $aud = "admin_account"; //the type of audience e.g. admin or client

                        $secret_key = "dc698f132cd0efe8584130b0cce9fa84";
                        $payload = array(
                            "iss" => $iss, "iat" => $iat, "nbf" => $nbf, "exp" => $exp, "aud" => $aud,
                            "staff_id" => $ch0[0]['staff_id'],
                            "staff_firstname" => $ch0[0]['staff_firstname'],
                            "staff_middlename" => $ch0[0]['staff_middlename'],
                            "staff_lastname" => $ch0[0]['staff_lastname'],
                            "company_id" => $ch0[0]['company_id'],
                            "staff_email" => $ch0[0]['staff_email'],
                            "staff_role" => $ch0[0]['staff_role'],
                            "staff_photo" => "https://imonitor.guardtrol.com/public/uploads/staff/" . $ch0[0]['staff_photo'],
                            "staff_type" => $ch0[0]['staff_type']
                        );
                        $jwt = JWT::encode($payload, $secret_key, 'HS512');
                        http_response_code(200);
                        echo json_encode(array("status" => 1, "jwt" => $jwt, "message" => "Staff logged in successfully",));
                    } else {
                        http_response_code(400);
                        echo json_encode(array("status" => 0, "message" => "Incorrect password, try resetting password or contact support"));
                    }
                }
            }
        }
    } else {
        http_response_code(400);
        echo json_encode(array('status' => 0, 'msg' => 'Unauthorized dev! missing/invalid developer key'));
    }
});

$router->map( 'POST', '/v1/api/send-incomplete-beat-routing-task', function() {
    $data = json_decode(file_get_contents("php://input"));
    $db = app_db();
    $dev_key = getDeveloperKey();
    $dev_key_res = $db->select("select * from tbl_developers where access_code='".$dev_key."' ");
    if (!empty($dev_key) && ($dev_key_res)) {
        $incomplete_array = json_decode(json_encode($data, true));
        $err = 0;
        foreach ($incomplete_array as $i => $i_value){
            $insert_arrays = array
            (
                'company_id' => $db->CleanDBData($i_value->company_id),
                'guard_id' => $db->CleanDBData($i_value->guard_id),
                'route_id' => $db->CleanDBData($i_value->route_id),
                'route_status' => $db->CleanDBData($i_value->route_status),
                'start_time' => $db->CleanDBData($i_value->start_time),
                'end_time' => $db->CleanDBData($i_value->end_time),
                'cp_created_on' => $db->CleanDBData(date("Y-m-d H:i:s")),
                'beat_id' => $db->CleanDBData($i_value->beat_id),

            );
            $q0 = $db->Insert('tbl_beat_routing_task', $insert_arrays);
            if ($q0 > 0) $err = 0;
            else $err = $err + 1;
        }
        if ($err == 0) {
            http_response_code(200);
            echo json_encode(array('status' => 1, 'msg' => 'Routing information sent.'));
        } else {
            http_response_code(400);
            echo json_encode(array('status' => 0, 'msg' => 'Unable to save Routing information, try again later'));
        }
    } else {
        http_response_code(400);
        echo json_encode(array('status' => 0, 'msg' => 'Unauthorized dev! missing/invalid developer key'));
    }
});

$router->map( 'POST', '/v1/api/send-beat-routing-task', function() {
    $data = json_decode(file_get_contents("php://input"));
    $db = app_db();
    $dev_key = getDeveloperKey();
    $dev_key_res = $db->select("select * from tbl_developers where access_code='".$dev_key."' ");
    if (!empty($dev_key) && ($dev_key_res)) {
        $route_array = json_decode(json_encode($data, true));
        $err = 0;
        $count = count($route_array);

        if ($count > 0) {
            foreach ($route_array as $i => $r_val) {
                foreach ($r_val->route_id as $route_id) {
                    $insert_arr = array
                    (
                        'company_id' => $db->CleanDBData($r_val->company_id),
                        'guard_id' => $db->CleanDBData($r_val->guard_id),
                        'route_id' => $db->CleanDBData($route_id),
                        'route_status' => $db->CleanDBData($r_val->route_status),
                        'start_time' => $db->CleanDBData($r_val->start_time),
                        'end_time' => $db->CleanDBData($r_val->end_time),
                        'cp_created_on' => $db->CleanDBData(date("Y-m-d H:i:s")),
                        'beat_id' => $db->CleanDBData($r_val->beat_id)
                    );
                    $q0 = $db->Insert('tbl_beat_routing_task', $insert_arr);
                    if ($q0 > 0) $err = 0;
                    else $err = $err + 1;
                }
            }
            if ($err == 0) {
                http_response_code(200);
                echo json_encode(array('status' => 1, 'msg' => 'Routing information sent.'));
            } else {
                http_response_code(400);
                echo json_encode(array('status' => 0, 'msg' => 'Unable to save Routing information, try again later'));
            }
        } else {
            http_response_code(400);
            echo json_encode(array('status'=>0,'msg'=>'Data is not a valid array'));
        }
    } else {
        http_response_code(400);
        echo json_encode(array('status' => 0, 'msg' => 'Unauthorized dev! missing/invalid developer key'));
    }
});

?>