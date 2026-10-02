<?php
namespace Hub\Core\Services\Conversions;
use Hub\Core\Database;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Polls accepted Data Manager requests and retries transient transport/API failures. */
class GoogleAdsPollingCron {
	private static $instance;
	public static function get_instance() { return self::$instance ?: self::$instance = new self(); }
	public function init() { /* Hook is registered in hub.php to run outside the admin. */ }
	public static function run() {
		$settings = get_option( 'hub_settings', array() ); $config = $settings['integrations']['google_ads'] ?? array();
		if ( 'active' !== ( $config['status'] ?? 'disabled' ) ) { return; }
		self::poll_processing( $config ); self::retry_transient( $config );
	}
	private static function poll_processing( array $config ) {
		$db=Database::db(); $table=Database::table('conversions_log'); $rows=$db->get_results("SELECT * FROM {$table} WHERE platform = 'Google Ads' AND status = 'processing' AND request_id <> '' ORDER BY id ASC LIMIT 50");
		$token=(new GoogleAdsOAuthService($config))->get_access_token(); if(is_wp_error($token)){return;}
		foreach($rows as $row){
			$response=wp_remote_get('https://datamanager.googleapis.com/v1/requestStatus:retrieve?requestId='.rawurlencode($row->request_id),array('headers'=>array('Authorization'=>'Bearer '.$token),'timeout'=>20));
			if(is_wp_error($response)){self::schedule_retry($row,$response->get_error_message());continue;}
			$code=(int)wp_remote_retrieve_response_code($response); $body=wp_remote_retrieve_body($response); if($code===401){GoogleAdsOAuthService::clear_cache(); self::schedule_retry($row,'OAuth expirado; nova tentativa agendada.');continue;} if($code===429||$code>=500){self::schedule_retry($row,'Falha transitoria ao consultar status (HTTP '.$code.').');continue;}
			if($code<200||$code>=300){ConversionLogger::update_log($row->id,array('status'=>'error','http_status'=>(string)$code,'api_response'=>$body,'error_message'=>'Falha nao transitoria ao consultar requestStatus.','processed_at'=>current_time('mysql')));continue;}
			$data=json_decode($body,true); $destination=$data['requestStatusPerDestination'][0]??array(); $status=$destination['requestStatus']??'REQUEST_STATUS_UNKNOWN'; $events=$destination['eventsIngestionStatus']['recordCount']??0; $errors=$destination['errorInfo']['errorCounts']??array(); $error_message=$errors ? wp_json_encode($errors) : '';
			$map=array('PROCESSING'=>'processing','SUCCESS'=>'success','PARTIAL_SUCCESS'=>'partial_success','FAILED'=>'error','REQUEST_STATUS_UNKNOWN'=>'unknown'); $new=$map[$status]??'unknown';
			ConversionLogger::update_log($row->id,array('status'=>$new,'http_status'=>(string)$code,'api_response'=>$body,'events_accepted'=>in_array($status,array('SUCCESS','PARTIAL_SUCCESS'),true)?absint($events):0,'events_rejected'=>in_array($status,array('FAILED','PARTIAL_SUCCESS'),true)?1:0,'error_message'=>$error_message,'processed_at'=>in_array($status,array('PROCESSING','REQUEST_STATUS_UNKNOWN'),true)?'':current_time('mysql')));
		}
	}
	private static function retry_transient( array $config ) {
		$db=Database::db(); $table=Database::table('conversions_log'); $now=current_time('mysql'); $rows=$db->get_results($db->prepare("SELECT * FROM {$table} WHERE platform = 'Google Ads' AND status = 'retry' AND (next_retry_at IS NULL OR next_retry_at <= %s) AND attempts < 3 ORDER BY id ASC LIMIT 20",$now)); $oauth=new GoogleAdsOAuthService($config); $token=$oauth->get_access_token(); if(is_wp_error($token)){return;}
		foreach($rows as $row){$payload=json_decode($row->request_payload,true); if(!is_array($payload)){ConversionLogger::update_log($row->id,array('status'=>'error','error_message'=>'Payload de retry invalido.','processed_at'=>$now));continue;} $response=wp_remote_post('https://datamanager.googleapis.com/v1/events:ingest',array('headers'=>array('Authorization'=>'Bearer '.$token,'Content-Type'=>'application/json'),'body'=>wp_json_encode($payload),'timeout'=>20)); if(is_wp_error($response)){self::schedule_retry($row,$response->get_error_message());continue;} $code=(int)wp_remote_retrieve_response_code($response);$body=wp_remote_retrieve_body($response);$data=json_decode($body,true);if($code>=200&&$code<300&&!empty($data['requestId'])){ConversionLogger::update_log($row->id,array('status'=>'processing','http_status'=>(string)$code,'api_response'=>$body,'request_id'=>$data['requestId'],'attempts'=>absint($row->attempts)+1,'next_retry_at'=>''));}elseif($code===429||$code>=500){self::schedule_retry($row,'Falha transitoria de reenvio (HTTP '.$code.').');}else{ConversionLogger::update_log($row->id,array('status'=>'error','http_status'=>(string)$code,'api_response'=>$body,'error_message'=>'Falha nao transitoria de reenvio.','processed_at'=>$now));}}
	}
	private static function schedule_retry($row,$message){$attempts=absint($row->attempts)+1;$delay=min(3600,300*(2**min(3,$attempts-1)));ConversionLogger::update_log($row->id,array('status'=>$attempts>=3?'error':'retry','attempts'=>$attempts,'next_retry_at'=>$attempts>=3?'':gmdate('Y-m-d H:i:s',time()+$delay),'error_message'=>$message,'processed_at'=>$attempts>=3?current_time('mysql'):''));}
}
