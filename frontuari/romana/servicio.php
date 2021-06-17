<?php
/*
*Comunicacion de romana a traves del puerto serial con Idempiere
*Version: 1.1-Beta
*Desarrollador: Leonardo Melendez, Frontuari C.A.
*/
if(!function_exists("readline")) {
  function readline($prompt = null){
      if($prompt){
          echo $prompt;
      }
      $fp = fopen("php://stdin","r");
      $line = rtrim(fgets($fp, 1024));
      return $line;
  }
}
encabezadoGenerico();
include("configuracion.php");
if(file_exists("frontuari/romana/library.dll")){
  include("library.dll");
}else{
  include("libraryOrigen.php");
}

$ftu_weightscale_id=null;
$f=null; //apuntador a archivos
//Validar datos en idempiere y seleccion de la romana.
seleccionar_o_ActualizarRomana($motor);
while(1){ //este ciclo se usa porque si consigue errores no tumbe el servicio sino que intene nuevamente.
  $tiempoReconexion=10; //Para intentar nuevamente si consigue algun error
  //Extraendo datos de idempiere para la configuracion del puerto serial
  $res=extraerDatosIdempierePuerto();
  //El primer y unico registro actualizable en idempiere de esta romana
  registrarPrimerPesoRomana();
//-----------------------Programacion del servicio de lectura directa a puerto y registro en idempiere---------

  switch($motor){
    case '1':   motor1(); break;
    case '3':   motor2(); break;
    case '2':   motor3(); break;
    default:    motor1();
  }
//----------------------------------------FIN------------------------------------
}




function motor1(){
  global $puertoSerial;
  global $frecuencia_lectura;
  global $corte;
  global $corteInicio;
  global $corteFin;
  global $ftu_weightscale_id;
  msj("Datos varios: ".$puertoSerial." | ".$frecuencia_lectura." | ".$corte." | ".$corteInicio." | ".$corteFin,true,true);
  msj("Motor 1 iniciado, esperando peso...");
  
  $fd = dio_open(strtolower($puertoSerial).':', O_RDONLY);
  $lineaVieja=null;
  while(1){
      $linea=dio_read($fd, $corte);
      $linea = str_replace(' ', '_', $linea);
      $linea = substr($linea,$corteInicio,$corte-($corteFin+$corteInicio));
      $linea = trim(str_replace('_', ' ', $linea));

      msj("Peso Leido: ".$linea,true,true);


      if (trim($linea)) {
     
        $peso=$linea;
        if($lineaVieja!=$peso and is_numeric($peso)){
          $lineaVieja=$peso;
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


      usleep($frecuencia_lectura);
  }

  dio_close($fd);
}
function motor2(){
  
}
function motor3(){
  
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
      if($lineaVieja!=$peso and is_numeric($peso)){
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

function cantDecimales($entrada){

  $nroDecimales=explode(".",$entrada);

  if(count($nroDecimales)>1){
  
      return strlen($nroDecimales[1]);
  
  }else{
      return 0;
  }


}

function exceMode(){
  global $puerto;
  exec("mode $puerto BAUD=9600 PARITY=n DATA=8 STOP=1 xon=off octs=off rts=on");
}

function seleccionar_o_ActualizarRomana($motor){
  global $strlength;
 while(1){
          global $ftu_weightscale_id;
          global $puerto;
          global $romana_defecto;
          if($romana_defecto!=null){
            $ftu_weightscale_id=$romana_defecto;
            break;
          }
          $sql='SELECT ftu_weightscale_id,name FROM ftu_weightscale';         
          $res=q($sql);
      
          if($res){
            msj("Elija una romana para continuar:");
            $i=0;
            $cantTotal=count($res);
            if($cantTotal>0){
              foreach($res as $obj){
                $i++;
                $name=$obj['name']." | ".$obj['ftu_weightscale_id'];
                echo "$i) $name\n";
              }
            }
            $i++;

            $romanaSeleccionada=trim(readline(""));

            
            if($res[$romanaSeleccionada-1]){
            
              $ftu_weightscale_id = $res[$romanaSeleccionada-1]['ftu_weightscale_id'];
            
              break;
            }

          }else{
            $line =readline("Registre una Bascula en idempiere y presione una tecla para continuar.");
          }

    
  }
}

function limpiarLinea($texto){
  return preg_replace('([^A-Za-z0-9 ])', ' ', $texto)."\n";
}


  function ultimaLineaOpen($archivo){
    global $f;
     $f=fopen($archivo, 'r');
  }
  function ultimaLineaClose(){
    global $f;
    fclose($f);
  }
  function ultimaLineaLeer(){
    
    global $f;
    $line = '';
    $cursor = -1;
    fseek($f, $cursor, SEEK_END);
    $char = fgetc($f);

    /**
     * Trim trailing newline chars of the file
     */
    while ($char === "\n" || $char === "\r") {
        fseek($f, $cursor--, SEEK_END);
        $char = fgetc($f);
    }

    /**
     * Read until the start of file or first newline char
     */
    while ($char !== false && $char !== "\n" && $char !== "\r") {
        /**
         * Prepend the new char
         */
        $line = $char . $line;
        fseek($f, $cursor--, SEEK_END);
        $char = fgetc($f);
    }
    return $line;
  }



  function leerLinea2(){
    fseek($file, -1, SEEK_END);
    $pos = ftell($file);
    while (fgetc($file) === "\n") {
        fseek($file, $pos--, SEEK_END);
    }
    $line = fgetc($file);
    while ((($c = fgetc($file)) !== "\n") && $pos) {
        $line = $c . $line;
        fseek($file, $pos--);
    }
    return $line;
  }

  function ultimaLinea($archivo='datoBascula.txt'){
    $line = '';

    $f = fopen($archivo, 'r');
    $cursor = -1;

    fseek($f, $cursor, SEEK_END);
    $char = fgetc($f);

    /**
     * Trim trailing newline chars of the file
     */
    while ($char === "\n" || $char === "\r") {
        fseek($f, $cursor--, SEEK_END);
        $char = fgetc($f);
    }

    /**
     * Read until the start of file or first newline char
     */
    while ($char !== false && $char !== "\n" && $char !== "\r") {
        /**
         * Prepend the new char
         */
        $line = $char . $line;
        fseek($f, $cursor--, SEEK_END);
        $char = fgetc($f);
    }

    fclose($f);

    return $line;
}

function lineaMotorArichuna(){
  exec("c:/capuertEA.exe");
  $archivo='c:/ROMANA1.TXT';
  
  $linea=file_get_contents($archivo, FILE_USE_INCLUDE_PATH);
  //dejar solo numero
  $linea= trim(preg_replace('([^0-9 ])', ' ', $linea));
  if($linea){
    return str_pad($linea, 6, " ", STR_PAD_LEFT);
  }else{
    return null;
  }
  

}
?>
