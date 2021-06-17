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
  echo "Ahora encontraremos el peso real, abre el archivo c:/php/frontuari/romana/configuracion.php y en tiempo real comienza a cambiar el valor CORTE-INICIO hasta eliminar los primeros caracteres no deseados, resultando algo parecido a esto (como puedes observar se elimino el nro. 30):\n8000 00\n8000 00\n8000 00\n4000 00\n5000 00\nPara finalizar ajuste el valor CORTE-FINAL hasta quedar resultante el peso correcto ejemplo (como puedes observar se elimino el 00 00 al final.): \n80\n80\n80\n40\n50\n\nAL TERMINAR CIERRE ESTA CONSOLA, Presione una tecla para iniciar la configuracion.";
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
        $linea = substr($linea,$corteInicio,$corte-($corteFin+$corteInicio));
        $linea = str_replace('_', ' ', $linea);
        echo "Corte inicial y final (debe quedar el Peso real): ".trim($linea)."\n";
   
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