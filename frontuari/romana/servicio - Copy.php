<?php
/*
*Comunicacion de romana a traves del puerto serial con Idempiere
*Version: 1.0-Beta
*Desarrollador: Leonardo Melendez, Frontuari C.A.
*/
encabezadoGenerico();
$debug=0;
include("library.dll");

if(!isset($nombre)){
  $nombre=pedirNombre();
}
if(!isset($libreriaPython)){
  $libreriaPython=instalarLibreriaPython();
}

if(!isset($motor)){
    $motor=pedirMotor();
}


$segundos=0; //Cada cuanto tiempo leera el puerto dejarlo en 0
$ftu_weightscale_id=null;
//valores por defectos
$puerto="COM1";
$strlength=20;
$comandoPhyton="python frontuari/romana/leer.py"; //solo para motor 4
$procesoPython=null;
//Validar datos en idempiere y seleccion de la romana.
seleccionar_o_ActualizarRomana($motor);
while(1){ //este ciclo se usa porque si consigue errores no tumbe el servicio sino que intene nuevamente.
  $tiempoReconexion=10; //Para intentar nuevamente si consigue algun error
  //Extraendo datos de idempiere para la configuracion del puerto serial
  $res=extraerDatosIdempierePuerto();
  $puerto = strtoupper($res['serialport']);
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
    case '4':   motor4(); break;
    default:    motor1();
  }
//----------------------------------------FIN------------------------------------
}


function motor4(){
  msj("Motor 4 iniciado, esperando peso...");
  global $comandoPhyton;
  global $procesoPython;
  if(!$procesoPython){
    $procesoPython = popen($comandoPhyton, 'w');
  }
  $lineaVieja='';
  while(1){
    sleep(1);
      $lineaI=ultimaLinea();
      $lineaVieja=procesarLinea($lineaI,$lineaVieja);  
      
  }
  pclose($procesoPython);
}


function motor3(){
  msj("Motor 3 iniciado, esperando peso...");
  $lineaVieja='';
  $arr=motor3_iniciar();

  $linea=motor3_leerLinea($arr);
    
  $lineaVieja=procesarLinea($linea,$lineaVieja);
  while (1) {
          $linea=motor3_leerLinea($arr);
          $lineaVieja=procesarLinea($linea,$lineaVieja);  
  }

  motor3_cerrar($arr['fd']);

}

function motor3_leerLinea($arr){
  global $strlength;
  if(!$strlength || $strlength==''){
    $strlength=20;
  }
  if ($lineaI=dio_read($arr['fd'], $strlength)) {
    if(trim($lineaI)){
        return $lineaI;
    }
  }

}
function motor3_cerrar($fd){
  dio_close($fd);
}


function motor3_iniciar(){
  global $puerto;
  if(!$puerto || $puerto==''){
    $puerto='COM1';
  }

  exceMode();
  $fd = dio_open(strtolower($puerto).':', O_RDWR);
  $arr['fd']=$fd;
  return $arr;
}



function motor2(){
  msj("Motor 2 iniciado, esperando peso...");
    $lineaVieja='';
    global $puerto;
    global $baudRate;
    global $bits;
    global $stopBit;
    $power="powershell";
    $preparar="\$port=new-Object System.IO.Ports.SerialPort $puerto,$baudRate,None,$bits,$stopBit";
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
  msj("Motor 1 iniciado, esperando peso...");
    $lineaVieja='';
    global $puerto;
    $lineaVieja=procesarLinea($linea,$lineaVieja);
    exceMode();
    $gestor = fopen($puerto, "r");
    msj(  "Conectado!");
    if ($gestor) {
        while (($linea = fgets($gestor, 4096)) !== false) {
            $lineaVieja=procesarLinea($linea,$lineaVieja);
        }
        if (!feof($gestor)) {
            msj("Error: fallo inesperado de fgets(), verifique la conexion al puerto $puerto");
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

function motor2_leerLinea($arr){
  try{
      fwrite($arr['pipe'][0], $arr['leer']. PHP_EOL);
      fgets($arr['pipe'][1],4096);
      return fgets($arr['pipe'][1],4096);
      
  }catch(Exception $e){
    ms($e->getMessage());
    msj("Error inesperado!, Intentando nuevamente en 5 Segundos");
    sleep(5);
  }
}
function motor2_cerrar($pipes,$process){
  $cerrar="\$port.Close()";
  fwrite($pipes[0], $cerrar. PHP_EOL);
  fclose($pipes[0]);
   fclose($pipes[1]);
   // Es importante que se cierren todas las tubería antes de llamar a
   // proc_close para evitar así un punto muerto
   $return_value = proc_close($process);

}

function motor2_iniciar(){
  

  exceMode();

  
  $power="powershell";
  $preparar="\$port=new-Object System.IO.Ports.SerialPort COM1,9600,None,8,1";
  $open="\$port.open()";
  $leer="\$port.ReadLine()";
 
  msj(  "Conectado!");
  $descriptorspec = array(
      0 => array("pipe", "r"),  // stdin es una tubería usada por el hijo para lectura
      1 => array("pipe", "w"),  // stdout es una tubería usada por el hijo para escritura
      2 => array("file", "error-output.txt", "a") // stderr es un fichero para escritura
   );
   
   $process = proc_open($power, $descriptorspec, $pipes, null,null);
  
   if (!is_resource($process)) {
      msj("No podemos establecer la comunicacion con el puerto");
      return;
   }
       // $pipes ahora será algo como:
       // 0 => gestor de escritura conectado al stdin hijo
       // 1 => gestor de lectura conectado al stdout hijo
       // Cualquier error de salida será anexado a /tmp/error-output.txt
       fwrite($pipes[0], $preparar. PHP_EOL);
       fwrite($pipes[0], $open. PHP_EOL);
       fwrite($pipes[0], $leer. PHP_EOL);
  
       //buscar la linea en la linea de comandos para detectar la lineas verdaderas
       $comandoDePare="port.ReadLine()";
       while (($buffer = fgets($pipes[1], 4096)) !== false) {
           echo $buffer;
          $pos1 = stripos($buffer, $comandoDePare);
          if ($pos1 === false) {
  
          }else{
              break;
          }
      }
      //necesario
      fgets($pipes[1],4096);
      $arr['pipe']=$pipes;
      $arr['leer']=$leer;
      $arr['proceso']=$process;

      return $arr;

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


function extraerCorteInicioFinal($string,$entrada){
  $cant_caracteres=strlen($string);
  $entrada= str_replace(".","",$entrada);
  $cant_caracteresEntrada=strlen($entrada);
  $posicionInicio=strpos($string,$entrada);
  $corteInicio=$posicionInicio;
  $corteFinal=$cant_caracteres-$posicionInicio-$cant_caracteresEntrada;
  
  $arr['inicio']=$corteInicio-3;
  $arr['final']=$corteFinal;

  return $arr;
}

function exceMode($puerto="COM1"){
  exec("mode $puerto BAUD=9600 PARITY=n DATA=8 STOP=1 xon=off octs=off rts=on");
}

function seleccionar_o_ActualizarRomana($motor){
  global $comandoPhyton;
  global $procesoPython;
 while(1){
          global $ftu_weightscale_id;
          global $puerto;
          $sql='SELECT ftu_weightscale_id,name FROM ftu_weightscale';         
          $res=q($sql);
      
          if($res){

            /*
            if(count($res)==1){
              $ftu_weightscale_id = $res[0]['ftu_weightscale_id'];
              $nombre             = $res[0]['name'];
              break;
            }
            */
            msj("Elija una romana para continuar o cree una nueva:");
            $i=0;
            $cantTotal=count($res);
            if($cantTotal>0){
              foreach($res as $obj){
                $i++;
                $name=$obj['name'];
                echo "$i) $name\n";
              }
            }
            $i++;
            echo "$i) Crear romana\n";
            $romanaSeleccionada=trim(readline(""));

            
            if($res[$romanaSeleccionada-1]){
            
              $ftu_weightscale_id = $res[$romanaSeleccionada-1]['ftu_weightscale_id'];
            
              break;
            }else{
              if($i==$romanaSeleccionada){
              
                $ad_client_id   =solicitarAd_client();
                $ad_org_id      =solicitarAd_org();
                $nuevoNombreRomana=trim(readline("Ingrese un nombre que identifique su romana :"));
                $ftu_serialportconfig_id=registrarSerialPort($nuevoNombreRomana,$ad_client_id,$ad_org_id);
                
          
                  msj(  "Conectado al puerto $puerto");
                  $cantLinea=0;
                  msj("\nPese uno o varios productos en la romana para detectar 4 lineas de pesos y conseguir el patron de lectura adecuado: ");
                  msj("Por favor si no visualiza ninguna linea cierre este instalador , desinstale y vuelva a ejecutarlo seleccionando otro motor");
                  msj("Esperando peso para motor $motor ...\n");
                  $linea=array();
                  $peso=array();
                  exceMode();
                  
                  switch($motor){
                    case '1':
                          $gestor = fopen($puerto, "r");
                          while (($lineaI = fgets($gestor, 4096)) !== false) {
                            $cantLinea++;
                            echo limpiarLinea($lineaI);
                            $linea[]=$lineaI;
                            if($cantLinea==4) break;

                            }
                          fclose($gestor);
                    break;

                    case '2':
                          $arr=motor2_iniciar();
                          while (1) {
                              $cantLinea++;
                              $lineaI= motor2_leerLinea($arr);
                              echo limpiarLinea($lineaI);
                              $linea[]=$lineaI;
                              if($cantLinea==4) break;
                          }
                          motor2_cerrar($arr['pipe'],$arr['proceso']);
                    break;

                    case '3':
                      $arr=motor3_iniciar();
                      $cantLinea=0;
                      while(1){
                          if ($lineaI=dio_read($arr['fd'], 20)) {
                            if(trim($lineaI)){
                                echo limpiarLinea($lineaI);
                                $cantLinea++;
                                $linea[]=$lineaI;
                                if($cantLinea==4) break;  

                            }

                        }
                      }
                  
                      motor3_cerrar($arr['fd']);
                    break;

                    case '4':
                      $procesoPython = popen($comandoPhyton, 'w');
                      while(1){
                        sleep(2);
                          $lineaI=ultimaLinea();
                          if(trim($lineaI)){
                            echo limpiarLinea($lineaI);
                            $cantLinea++;
                            $linea[]=$lineaI;
                            if($cantLinea==4) break;  

                          }
                          
                      }
                     // pclose($gestor);

                    break;
                  }

                   
                    msj("Nota: El separador decimal no es obligatorio.\nEscriba solo caracteres numericos y use el punto como separador decimal.");
                    msj("Escriba el peso en Kg. visualizado en la linea 1 (ejemplo 10.5) :");

                    $peso[]=trim(readline(""));
                    msj("Escriba el peso en Kg. visualizado en la linea 2:");
                    $peso[]=trim(readline(""));
                    msj("Escriba el peso en Kg. visualizado en la linea 3:");
                    $peso[]=trim(readline(""));
                    msj("Escriba el peso en Kg. visualizado en la linea 4:");
                    $peso[]=trim(readline(""));
                    
                    $divi=0;
                    $sumaDecimal=0;
                    $sumaCorteInicio=0;
                    $sumaCorteFinal=0;
                    $longitudLinea="";
                    foreach($linea as $cod=>$string){
                      $entrada=$peso[$cod];
                      $longitudLinea=strlen($string);
                      if(trim($entrada)>0){
                        $divi++;
                        $arr=extraerCorteInicioFinal($string,$entrada);
                        $sumaDecimal+=cantDecimales($entrada);
                        $sumaCorteInicio+=$arr['inicio'];
                        $sumaCorteFinal+=$arr['final'];
                      }
                     

                    }

                    $decimalDefinitivo    =round($sumaDecimal/$divi);
                    $corteInicioDefinitivo=round($sumaCorteInicio/$divi);
                    $corteFinalDefinitivo =round($sumaCorteFinal/$divi);

                    $ftu_screenconfig_id    =registrarScreenConfig($decimalDefinitivo, $corteInicioDefinitivo,$corteFinalDefinitivo,$ad_client_id,$ad_org_id,$nuevoNombreRomana,$longitudLinea);

                    $ftu_weightscale_id     =registrarRomana($ad_client_id,$ad_org_id,$ftu_screenconfig_id,$ftu_serialportconfig_id,$nuevoNombreRomana);


              }else{
                echo "Romana no valida!\n";
              }
              
            }

          }else{
            $line =readline("Registre una Bascula en idempiere y presione una tecla para continuar.");
          }

    
  }
}

function limpiarLinea($texto){
  return preg_replace('([^A-Za-z0-9 ])', ' ', $texto)."\n";
}

function registrarRomana($ad_client_id,$ad_org_id,$ftu_screenconfig_id,$ftu_serialportconfig_id,$name){
  $ftu_weightscale_id=rand(1100000,9999999);
  
  $createdby  =1000000;
  $updatedby  =1000000;

  $sql="INSERT INTO ftu_weightscale (ftu_weightscale_id,ftu_screenconfig_id,ftu_serialportconfig_id,ad_org_id,ad_client_id,createdby,name,updatedby,isactive,c_uom_id,istest) VALUES ($ftu_weightscale_id,$ftu_screenconfig_id,$ftu_serialportconfig_id,$ad_org_id,$ad_client_id,'$createdby','$name','$updatedby','Y','50001','Y')";
  msj("Registrando SQL, Se muestra el sql para analisis en caso de algun error.");
  msj($sql);
  $res=q($sql);
  return $ftu_weightscale_id;
}
function registrarScreenConfig($decimalDefinitivo, $corteInicioDefinitivo,$corteFinalDefinitivo,$ad_client_id,$ad_org_id,$name,$longitudLinea){

  $createdby  =1000000;
  $updatedby  =1000000;

  $ftu_screenconfig_id=rand(1001000,9999999);
  $sql="INSERT INTO ftu_screenconfig (ftu_screenconfig_id,ad_org_id,ad_client_id,createdby,name,updatedby,qtydecimal,cutstart,cutend,istest,strlength,startcharacter,endcharacter) VALUES ($ftu_screenconfig_id,$ad_org_id,$ad_client_id,'$createdby','$name','$updatedby','$decimalDefinitivo','$corteInicioDefinitivo','$corteFinalDefinitivo','Y','$longitudLinea','00','00')";
  msj("Registrando SQL, Se muestra el sql para analisis en caso de algun error.");
  msj($sql);
  q($sql);
  return $ftu_screenconfig_id;
}
function solicitarAd_client(){
  $sql="SELECT ad_client_id, name FROM ad_client";
  msj("Seleccione un cliente:");
 
  $res=q($sql);
  $i=0;
  $cantTotal=count($res);
    foreach($res as $obj){
      $i++;
      $name=$obj['name'];
      echo "$i) $name\n";
    }
    $clienteSeleccionado=trim(readline(""));

    return $res[$clienteSeleccionado-1]['ad_client_id'];
 
}

function solicitarAd_org(){
  $sql="SELECT ad_org_id, name FROM ad_org";
  msj("Seleccione una organizacion:");
 
  $res=q($sql);
  $i=0;
  $cantTotal=count($res);
    foreach($res as $obj){
      $i++;
      $name=$obj['name'];
      echo "$i) $name\n";
    }
    $orgSeleccionado=trim(readline(""));

    return $res[$orgSeleccionado-1]['ad_org_id'];
}

function registrarSerialPort($nombre,$ad_client_id,$ad_org_id){
  $ftu_serialportconfig_id=rand(1000000,9999999);
  $bauds      =9600;
  $databits   =8;
  $flowcontrol="H";
  $name       =$nombre;
  $parity     ="n";
  $serialport="COM1";
  $stopbits   =1;
  $createdby  =1000000;
  $updatedby  =1000000;
  $sql="INSERT INTO ftu_serialportconfig (ftu_serialportconfig_id,ad_org_id,ad_client_id,bauds,createdby,databits,flowcontrol,name,parity,serialport,stopbits,updatedby) VALUES ($ftu_serialportconfig_id,$ad_org_id,$ad_client_id,'$bauds','$createdby','$databits','$flowcontrol','$name','$parity','$serialport','$stopbits','$updatedby')";
  msj("Registrando SQL");
  msj($sql);
  q($sql);
  return $ftu_serialportconfig_id;
}
//registrar nombre que identifique la pc con los registros de peso
function pedirNombre(){
  $nombre =  readline("Ingrese un nombre que identifique esta PC:");
  file_put_contents('C:\php\frontuari\romana\library.dll',"\n\$nombre='".$nombre."';\n",FILE_APPEND);
  return $nombre;
}
function instalarLibreriaPython(){
  
  exec('pip install C:\php\frontuari\romana\pyserial-3.5-py2.py3-none-any.whl');
  file_put_contents('C:\php\frontuari\romana\library.dll',"\n\$libreriaPython=true;\n",FILE_APPEND);
  return true;
}
//Motor para procesar las lecturas del puerto
function pedirMotor(){
    echo "1) Motor 1 (Recomendado)\n";
    echo "2) Motor 2 \n";
    echo "3) Motor 3 \n";
    echo "4) Motor 4 (Requiere Python + pip install pyserial) \n";
    $motor =  readline("Elija un motor para leer el puerto serial:");
    file_put_contents('C:\php\frontuari\romana\library.dll',"\n\$motor='".$motor."';\n?>",FILE_APPEND);
    return $motor;
  }

  function ultimaLinea(){
    $line = '';

    $f = fopen('datoBascula.txt', 'r');
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
?>