<?php
$puerto="COM1";
if(!function_exists("readline")) {
    function readline($prompt = null){
        if($prompt){
            echo $prompt;
        }
        $fp = fopen("php://stdin","r");
        $line = rtrim(fgets($fp, 10240));
        return $line;
    }
  }
  echo "Bienvenido, te ayudare a encontrar el patron de configuracion para lectura de la bascula, abre el archivo c:/php/frontuari/romana/configuracion.php y en tiempo real comienza a cambiar el valor llamado CORTE hasta obtener lineas estables \nEjemplo de lineas con posiciones estables:\n_30____8000_00\n_30____8000_00\n_30____8000_00\n_30____4000_00\n_30____5000_00\n\nAL TERMINAR CIERRE ESTA CONSOLA. Presione una tecla para iniciar la configuracion.";
  readline("");

  exceMode();
  motor1();
  function motor1(){
    global $puerto;
    
    msj("Motor 1 iniciado, esperando peso...");

    $fd = dio_open(strtolower($puerto).':', O_RDONLY);
    while(1){
        require("configuracion.php");
        $linea=dio_read($fd, $corte);
        $linea = str_replace(' ', '_', $linea);
        
        
        
        echo "CORTE (las posiciones deben quedar estaticas): ".$linea."\n";

        sleep(1);
    }

    dio_close($fd);
  }


  function exceMode(){
        global $puerto;
        exec("mode $puerto BAUD=9600 PARITY=n DATA=8 STOP=1 xon=off octs=off rts=on");
  }

  function msj($string,$bueno=true,$dev=0){
    echo $string . "\n";
    flush();
}