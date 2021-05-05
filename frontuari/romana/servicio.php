<?php
/*
*Comunicacion de romana a traves del puerto serial con Idempiere
*Version: 0.1-Beta
*Desarrollador: Leonardo Melendez, Frontuari C.A.
*/
encabezadoGenerico();
$debug=0;
include("library.dll");

if(!isset($nombre)){
  $nombre=pedirNombre();
}

if(!isset($motor)){
    $motor=pedirMotor();
  }

$segundos=0; //Cada cuanto tiempo leera el puerto dejarlo en 0
$ftu_weightscale_id=null;

crear();
while(1){ //este ciclo se usa porque si consigue errores no tumbe el servicio sino que intene nuevamente.
  $tiempoReconexion=10; //Para intentar nuevamente si consigue algun error
  //Extraendo datos de idempiere para la configuracion del puerto serial
  $res=extraerDatosIdempierePuerto();
  $portName = strtoupper($res['serialport']);
  $baudRate = $res['bauds'];
  $bits     = $res['databits'];
  $stopBit  = $res['stopbits'];
  //------------------------------------------
  //----extraer campo de pruebas
  //Para modo desarrollador 1 y ver todos los procesos y errores modo produccion 0
  $isTest=$res['istest'];

  if($isTest=='Y'){
    $debug=1; 
  }else{
    $debug=0; 
  }
  //----------------------------
 
  //-----------Datos para formatear el texto
  $startcharacter =$res['startcharacter'];
  $endcharacter   =$res['endcharacter'];
  $strlength      =$res['strlength']; //NO SE USA
  $qtydecimal     =$res['qtydecimal'];
  $cutStart       =$res['cutstart'];
  $cutEnd         =$res['cutend'];
  //-----------------------------------------

  //El primer y unico registro actualizable en idempiere de esta romana

  registrarPrimerPesoRomana();
//-----------------------Programacion del servicio de lectura directa a puerto y registro en idempiere---------

  switch($motor){
    case '1':   motor1(); break;
    case '2':   motor2(); break;
    case '3':   motor3(); break;
    default:    motor1();
  }

//----------------------------------------FIN------------------------------------
}


function motor3(){
    global $portName;
    global $baudRate;
    global $bits;
    global $stopBit;
    global $strlength;
    $lineaVieja='';

    $fd = dio_open($portName, O_RDWR | O_NOCTTY | O_NONBLOCK);
    exec("mode {$portName} baud={$baudRate} data={$bits} stop={$stopBit} parity=n xon=on");

    while (1) {
        $linea = dio_read($fd, $strlength);
        if ($linea) {
            $lineaVieja=procesarLinea($linea,$lineaVieja);
        }
    }

    msj(  "Puerto cerrado" );
    dio_close($bbSerialPort);
   
}

function motor2(){
    $lineaVieja='';
    global $portName;
    global $baudRate;
    global $bits;
    global $stopBit;
    $power="powershell";
    $preparar="\$port=new-Object System.IO.Ports.SerialPort $portName,$baudRate,None,$bits,$stopBit";
    $open="\$port.open()";
    $leer="\$port.ReadLine()";
    $cerrar="\$port.Close()";
    msj(  "Conectado!");
    $descriptorspec = array(
        0 => array("pipe", "r"),  // stdin es una tubería usada por el hijo para lectura
        1 => array("pipe", "w"),  // stdout es una tubería usada por el hijo para escritura
        2 => array("file", "error-output.txt", "a") // stderr es un fichero para escritura
     );
     
     $process = proc_open($power, $descriptorspec, $pipes, null,null);
    
     if (is_resource($process)) {
         // $pipes ahora será algo como:
         // 0 => gestor de escritura conectado al stdin hijo
         // 1 => gestor de lectura conectado al stdout hijo
         // Cualquier error de salida será anexado a /tmp/error-output.txt
         fwrite($pipes[0], $preparar. PHP_EOL);
         fwrite($pipes[0], $open. PHP_EOL);
         fwrite($pipes[0], $leer. PHP_EOL);
    
         $comandoDePare="port.ReadLine()";
         while (($buffer = fgets($pipes[1], 4096)) !== false) {
             echo $buffer;
            $pos1 = stripos($buffer, $comandoDePare);
            if ($pos1 === false) {
    
            }else{
                break;
            }
        }
    
        $linea= ("\n".fgets($pipes[1],4096));
    
        $lineaVieja=procesarLinea($linea,$lineaVieja);
        while (1) {
    
            try{
                fwrite($pipes[0], $leer. PHP_EOL);
                fgets($pipes[1],4096);
                $linea= ("\n".fgets($pipes[1],4096));
                echo $linea;
                $lineaVieja=procesarLinea($linea,$lineaVieja);
            }catch(Exception $e){
              ms($e->getMessage());
              msj("Error inesperado!, Intentando nuevamente en 5 Segundos");
              sleep(5);
            }
           
        }
    
    
    
        echo "Hasta pronto!";
        fwrite($pipes[0], $cerrar. PHP_EOL);
        fclose($pipes[0]);
    
         fclose($pipes[1]);
         // Es importante que se cierren todas las tubería antes de llamar a
         // proc_close para evitar así un punto muerto
         $return_value = proc_close($process);
    
     }

}

function motor1(){
    $lineaVieja='';
    global $portName;
    $lineaVieja=procesarLinea($linea,$lineaVieja);

    exec("mode $portName BAUD=9600 PARITY=n DATA=8 STOP=1 xon=off octs=off rts=on");
    $gestor = fopen($portName, "r");
    msj(  "Conectado!");
    if ($gestor) {
        while (($linea = fgets($gestor, 4096)) !== false) {
            $lineaVieja=procesarLinea($linea,$lineaVieja);
        }
        if (!feof($gestor)) {
            echo "Error: fallo inesperado de fgets(), verifique la conexion al puerto $portName\n";
        }
        fclose($gestor);
    }
}








function procesarLinea($linea,$lineaVieja){
    $linea= filter_var($linea,FILTER_SANITIZE_STRING);
    global $cutStart;
    global $cutEnd;
    global $qtydecimal;
    global $ftu_weightscale_id;
    if (trim($linea)) {
     
      msj("\nDato recibido del Puerto: ". $linea,true,1);
     
      msj("Extrayendo peso, corte inicio: $cutStart, Corte final: $cutEnd...",true,1);
      //Cortar string
      $peso = substr($linea,$cutStart,($cutEnd*-1));
      msj("Peso para trim; ".$peso,true,1);
      //Eliminar caracteres en blanco
      $peso= trim($peso);
      msj("Peso posiblemente elegido: ".$peso,true,1);
      msj("Cpmparando peso anterior y actual if($lineaVieja!=$peso) entra.",true,1);
      if($lineaVieja!=$peso){
        msj("Data elegida: ".$peso,true,1);

        $lineaVieja=$peso;
        if($qtydecimal>0){
          $divisor=pow(10,$qtydecimal);
            msj('Calculo decimales: '.$peso.' / '.$divisor,true,1);
          $peso=$peso/$divisor;
        }
        $sql="UPDATE ftu_weight SET time_read = NOW(), value = '$peso' WHERE ftu_weightscale_id='$ftu_weightscale_id'";
        $res=q($sql);
        if($res!=false){
          msj("Peso registrado: ".$peso);
        }else{
          //para decirle que el peso no lo guardo en la db
          msj("Peso NO registrado, compruebe su conexion al servidor y reinicie la aplicacion: ".$peso);
          $lineaVieja="datoNoImportanteUsoSoloAlInicio";
        }
        
      }
    
    }
    return $lineaVieja;
}




function extraerDatosIdempierePuerto(){
  global $ftu_weightscale_id;
  $sql="SELECT serialport,bauds,databits,stopbits,startcharacter,endcharacter,strlength,qtydecimal,cutstart,cutend,w.istest FROM ftu_weightscale w 
  inner join ftu_screenconfig sc
  USING(ftu_screenconfig_id)
  INNER JOIN ftu_serialportconfig sp 
  USING(ftu_serialportconfig_id) WHERE w.ftu_weightscale_id=$ftu_weightscale_id";
  $res=q($sql)[0];
  return $res;
}


function registrarPrimerPesoRomana(){
  global $ftu_weightscale_id;
  global $nombre;
  $sql="INSERT INTO ftu_weight (ftu_weightscale_id,time_read,value,name,isactive) VALUES ('$ftu_weightscale_id',NOW(),0,'$nombre','Y')
   ON CONFLICT (ftu_weightscale_id) 
            DO 
            UPDATE SET name = EXCLUDED.name, value=EXCLUDED.value
  ";

  q($sql);
}




function encabezadoGenerico(){
  header( 'Content-type: text/plain; charset=utf-8' ); 
  error_reporting(E_ALL & ~E_NOTICE); //eliminar errores de alertas innecesarias
  error_reporting(1);
  if(!extension_loaded('dio')){msj( "Debe instalar la liberia Direct IO en PHP" );exit;}
}

//Validar datos en idempiere y seleccion de la romana.
function crear(){
 while(1){
          global $ftu_weightscale_id;
          $sql='SELECT ftu_weightscale_id,name FROM ftu_weightscale';         
          $res=q($sql);
      
          if($res){
            if(count($res)==1){
              $ftu_weightscale_id = $res[0]['ftu_weightscale_id'];
              $nombre             = $res[0]['name'];
              break;
            }
            echo "Elija una romana para continuar:\n";
            $i=0;
            foreach($res as $obj){
              $i++;
              $name=$obj['name'];
              echo "$i) $name\n";
            }
            $romana=readline("");


            if($res[$romana-1]){
            
              $ftu_weightscale_id = $res[$romana-1]['ftu_weightscale_id'];
            
              break;
            }else{
              echo "Romana no valida!\n";
            }

          }else{
            $line =readline("Registre una Bascula en idempiere y presione una tecla para continuar.");
          }

    
  }
}

//registrar nombre que identifique la pc con los registros de peso
function pedirNombre(){
  $nombre =  readline("Ingrese un nombre que identifique esta PC:");
  file_put_contents('C:\php\frontuari\romana\library.dll',"\n\$nombre='".$nombre."';\n",FILE_APPEND);
  return $nombre;
}

//Motor para procesar las lecturas del puerto
function pedirMotor(){
    echo "Motor 1 (Recomendado)\n";
    echo "Motor 2 \n";
    echo "Motor 3 \n";
    $motor =  readline("Elija un motor para leer el puerto serial:");
    file_put_contents('C:\php\frontuari\romana\library.dll',"\n\$motor='".$motor."';\n?>",FILE_APPEND);
    return $nombre;
  }
?>