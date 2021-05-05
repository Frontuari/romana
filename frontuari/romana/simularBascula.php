<?php
encabezadoGenerico();

$segundos=1; //Cada cuanto tiempo enviara datos
$portName = 'com2:';
$baudRate = 9600;
$bits = 8;
$spotBit = 1;

function formatoEditable(){
    $pesoAleatoreo=rand(100,900);
    //Formato archiuna
    return " 30          ".$pesoAleatoreo."00";
}

function echoFlush($string)
{
    echo $string . "\n";
    flush();
    ob_flush();
}

if(!extension_loaded('dio'))
{
    echoFlush( "Debe instalar la liberia Direct IO en PHP" );
    exit;
}

try 
{
    //the serial port resource
    $bbSerialPort;

    echoFlush(  "Conectando a puerto serial {$portName}" );

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') 
    { 
        $bbSerialPort = dio_open($portName, O_RDWR );
        //we're on windows configure com port from command line
        exec("mode {$portName} baud={$baudRate} data={$bits} stop={$spotBit} parity=n xon=on");
    } 
    else //'nix
    {
        $bbSerialPort = dio_open($portName, O_RDWR | O_NOCTTY | O_NONBLOCK );
        dio_fcntl($bbSerialPort, F_SETFL, O_SYNC);
        //we're on 'nix configure com from php direct io function
        dio_tcsetattr($bbSerialPort, array(
            'baud' => $baudRate,
            'bits' => $bits,
            'stop'  => $spotBit,
            'parity' => 0
        ));
    }

    if(!$bbSerialPort)
    {
        echoFlush( "No se puede conectar al puerto serial: {$portName} ");
        exit;
    }

    // send data


   
    while(1){
        $dataToSend = formatoEditable();
        echoFlush( "Escribiendo datos en el puerto: \"{$dataToSend}\"" );
        $bytesSent = dio_write($bbSerialPort, $dataToSend."\n");
        echoFlush( "Enviado: {$bytesSent} bytes" );
        sleep($segundos);
    }
    echoFlush(  "Puerto cerrado" );
    dio_close($bbSerialPort);

} 
catch (Exception $e) 
{
    echoFlush(  $e->getMessage() );
    exit(1);
} 



function encabezadoGenerico(){
  header( 'Content-type: text/plain; charset=utf-8' ); 
  error_reporting(E_ALL & ~E_NOTICE); //eliminar errores de alertas innecesarias
}
?>