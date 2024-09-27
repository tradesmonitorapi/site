<?php
require_once('__files/funciones.php'); // Incluir funciones de 'funciones.php'

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$monitoring_status = getMonitoringStatus();
$last_notified_trade_id = getLastNotifiedTradeId(); // Obtener el ID del último trade notificado

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['monitoring_status'])) {
        if ($_POST['monitoring_status'] === 'iniciar') {
            setMonitoringStatus('started');
            $monitoring_status = 'started';
        } elseif ($_POST['monitoring_status'] === 'detener') {
            setMonitoringStatus('stopped');
            $monitoring_status = 'stopped';
        }
    }
}

$ultimo_chequeo = date('dM24 H:i:s'); // Formatear el tiempo de chequeo

// Inicializar el estado del trade
$estado_trade = '-';
$estado_monitor = 'Estado del Monitoreo: ACTIVO'; // Por defecto

if ($monitoring_status === 'stopped') {
    $estado_monitor = 'Estado del Monitoreo: DETENIDO';
} else {
    // Verificar si hay órdenes abiertas en el símbolo XRPUSDT
    $open_trade_id = api_trades_monitor('XRPUSDT'); 
    
    if ($open_trade_id) {
        $estado_monitor = 'Estado del Monitoreo: ACTIVO';
        $estado_trade = 'ABIERTO (ID: ' . $open_trade_id .')'; // Mostrar el trade abierto
    } else {
        $estado_trade = 'No hay trades abiertos'; // Si no hay trades
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trades Monitor API</title>
    <link rel="icon" type="image/png" href="_img/icono_web.png" />
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #1F1F1F;
            color: #e0e0e0;
            text-align: center;
        }
        .container {
            margin-top: 50px;
        }
        button {
            padding: 10px 20px;
            font-size: 16px;
            margin: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-start {
            background-color: #4CAF50;
            color: white;
        }
        .btn-stop {
            background-color: #f44336;
            color: white;
        }
        p {
            color: #e0e0e0;
        }
        h1 {
            color: #ffffff;
        }
        span {
            color: #999999;
        }
        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
    <?php if ($monitoring_status === 'started') : ?>
        <meta http-equiv="refresh" content="<?php echo CHEQUEO_FRECUENCIA; ?>"> <!-- Usar la variable global -->
    <?php endif; ?>
</head>
<body>
    <div class="container">
        <h1>Trades Monitor API</h1>
        <p><?php echo $estado_monitor; ?></p> <!-- Estado general del monitoreo -->
        <p>Último chequeo: <?php echo $ultimo_chequeo; ?></p>
        <p>Estado del Trade: <?php echo $estado_trade; ?></p> <!-- Estado actualizado del trade -->

        <form method="POST">
            <button class="btn-start" type="submit" name="monitoring_status" value="iniciar" 
                <?php echo $monitoring_status === 'started' ? 'disabled' : ''; ?>>
                <?php echo $monitoring_status == 'started' ? 'Monitoreo Activo' : 'Iniciar Monitoreo'; ?>
            </button>
            <button class="btn-stop" type="submit" name="monitoring_status" value="detener" 
                <?php echo $monitoring_status === 'stopped' ? 'disabled' : ''; ?>>
                Detener Monitoreo
            </button>
        </form>

        <span style="font-size:11px;"><i>(Entorno: <?php if(isLocalhost()) echo "LOCALHOST"; else echo "SERVIDOR"; ?>)</i></span> <!-- Ubicación desde donde se está ejecutándose el script -->

    </div>
</body>
</html>
