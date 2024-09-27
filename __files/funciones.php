<?php

// VARIABLES GLOBALES
//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
//{
$last_notified_trade_id = null; // Variable global para almacenar el ID del último trade notificado
define('CHEQUEO_FRECUENCIA', 10); // Frecuencia de chequeo en segundos
date_default_timezone_set('America/Argentina/Buenos_Aires'); 
$registrar_cron_log = false;
//}
//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// Fin de: VARIABLES GLOBALES





// FUNCIONES Trades Monitor API
//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
//{

// Función para obtener el último trade notificado desde un archivo
function getLastNotifiedTradeId() {
    $filename = '_txt/last_notified_trade.txt';
    if (file_exists($filename)) {
        return file_get_contents($filename);
    }
    return null;
}

// Función para obtener el estado de monitoreo (iniciado o detenido)
function getMonitoringStatus() {
    $filename = '_txt/monitoring_status.txt';
    if (file_exists($filename)) {
        return trim(file_get_contents($filename));
    }
    return 'detenido';
}

// Función para establecer el estado de monitoreo (iniciado o detenido)
function setMonitoringStatus($status) {
    $filename = '_txt/monitoring_status.txt';
    file_put_contents($filename, $status);
}




function api_trades_monitor($simbolo_a_monitorear)
{
    global $last_notified_trade_id;
	global $registrar_cron_log;

    $api_key = 'oUjxVSkv9kVuC0JmXa8HMDsQ9Ace1Axw92LSbPCdz7FCtA170xy9tEmNSxyKma5L';
    $api_secret = 'a6MaCqisuwJ4s3TTHgtoN6Lio4UQY2BTVSV5lD5lERJCBUY1CymBEQZLDWPNgA0I';
    $symbol = $simbolo_a_monitorear;

    $base_url = 'https://fapi.binance.com';
    $endpoint = '/fapi/v1/openOrders';
    
    $timestamp = round(microtime(true) * 1000);
    $query_string = 'symbol=' . $symbol . '&timestamp=' . $timestamp;
    $signature = hash_hmac('sha256', $query_string, $api_secret);
    
    $url = $base_url . $endpoint . '?' . $query_string . '&signature=' . $signature;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-MBX-APIKEY: ' . $api_key));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);  
    
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        echo 'Error de cURL: ' . curl_error($ch);
        return;
    }
	
	
	// Registrar en un log cuando se ejecute un chequeo (para confirmar que el script está funcionando bien 
	//y está siendo ejecutado correctamente por el cronjob:
	//-------------------------------------------
	if($registrar_cron_log)
	{
		//De MENOS reciente a MÁS reciente
		//--------------------
//		file_put_contents('_txt/cron_log.txt', "- Chequeo ejecutado: " . date('dM24 H:i:s') . "\n", FILE_APPEND);
		//--------------------
	
		//De MÁS reciente a MENOS reciente:
		//--------------------
		$log = file_get_contents('_txt/cron_log.txt'); // Leer el log actual
		$new_log = "- Chequeo ejecutado: " . date('dM24 H:i:s') . "\n" . $log; // Añadir el nuevo registro al principio
		file_put_contents('_txt/cron_log.txt', $new_log); // Guardar el nuevo log
		//--------------------
	}
	//-------------------------------------------
	
	
	

    $open_orders = json_decode($response, true);

    // Verificar si hay órdenes abiertas
    if (json_last_error() === JSON_ERROR_NONE && !empty($open_orders) && isset($open_orders[0])) {
        $trade_id = $open_orders[0]['orderId']; // Obtener el ID de la primera orden

        if ($trade_id != "" && $trade_id != $last_notified_trade_id) {


			//Enviar e-mail con los datos de la nueva suscripción:
			//-----------------
			if(!isLocalhost())
			{
				$titulo_email = "Nuevo Trade Iniciado por ATPBot...";
				$mensaje_email = 'Se ha detectado una nueva orden abierta en el par: ' . $symbol . ' (Trade ID: '.$trade_id.')';
				$email_a = "dealgroupsrl@gmail.com";
				$email_de = "info@dealgroupsrl.com";
			
				enviar_email($email_a, 
							 $titulo_email, 
							 $mensaje_email,
							 $email_de);
			}
			//-----------------

            // Actualizar el ID del último trade notificado
            $last_notified_trade_id = $trade_id;
            file_put_contents('_txt/last_notified_trade.txt', $trade_id);

            return $trade_id; // Devolver el ID del trade
        }

        // Si es el mismo trade, no notificar, pero devolver el trade_id
        return $trade_id;
    } else {
        // Si no hay órdenes abiertas
        file_put_contents('_txt/trade_status.txt', "No hay operaciones abiertas.\n");
        return null; // No hay trades abiertos
    }
}

//}
//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// Fin de: FUNCIONES Trades Monitor API



//Funciones de: CÓDIGO ORIGINAL DE FUNCIONES.PHP DEL SISTEMA DE COACHING
//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
//{
	

//Función para enviar e-mail:
//---------------------------------------------------------------------------------------------
function enviar_email($email_a, 
					  $email_titulo, 
					  $email_mensaje,
					  $email_de, 
					  $email_cc = "", 
					  $email_bcc = "",
					  $formato = "plain")
{
	//Definir cabeceras para el mail:
	//----------------------------
	$headers = "MIME-Version: 1.0" . "\r \n";  
	$headers .= "Content-type: text/$formato; charset=iso-8859-1" . "\r \n"; 
	 
	//Cabecera "From:"...
	//--------
	if(encontrar_cadena("From:",$email_de)) //Si se ha ingresado por parámetro, agregar el campo FROM, sin modificarlo...
	{
		$headers .= $email_de . "\r \n";  
	}
	else //Si no, construir la cabecera para el campo FROM...
	{
		$headers .= "From: \"$email_de\" <$email_de>" . "\r \n";  


//		$headers .= 'From: \"'.$email_de.'\" <'.$email_de.'>" ' . "\r\n" . 
//					'X-Mailer: PHP/' . phpversion();  


	}
	//--------
	
	//Cabecera "Cc:"...
	//--------
	if($email_cc != "") //Si se ha ingresado por parámetro, agregar el campo CC...
	{
		$headers .= "Cc: $email_cc" . "\r\n";
	}
	//--------
	
	//Cabecera "Bcc:"...
	//--------
	if($email_bcc != "") //Si se ha ingresado por parámetro, agregar el campo BCC...
	{
		$headers .= "Bcc: $email_bcc" . "\r\n";
	}
	//--------

	//----------------------------
	
	
	return mail($email_a, $email_titulo, $email_mensaje, $headers); //Enviar e-mail.
}
//---------------------------------------------------------------------------------------------


//Determinar si se está en el LOCALHOST o en el SERVIDOR.
//-------------------------------------------------------------------------------
function isLocalhost()
{
global $Variables;
	
	$web_referencia = $_SERVER['HTTP_HOST'];

	if(encontrar_cadena("127.0.0.1",$web_referencia) || 
	   encontrar_cadena("localhost",$web_referencia)) return true;
	else return false;
}
//-------------------------------------------------------------------------------

//Función para encontrar un "patron" de búsqueda en una "cadena" objetivo:
//-----------------------------------------------------------------------------------------
function encontrar_cadena($patron, $cadena)
{
	return is_int(strpos($cadena,$patron));
}
//-----------------------------------------------------------------------------------------

//}
//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
//Fin Funciones de: CÓDIGO ORIGINAL DE FUNCIONES.PHP DEL SISTEMA DE COACHING



?>
