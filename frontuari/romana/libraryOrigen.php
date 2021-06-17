<?php
if(file_exists("frontuari/romana/com.php")){
  include("com.php");
}else{
  include("comOrigen.php");
}
function q($sql,$mostrarSql=false){
  if($mostrarSql) exit($sql);
    $arr=array();
   // $result = pg_query($sql) or die(false);
   $con=$GLOBALS["con"];
    if (pg_send_query($con,$sql)) {
        $res=pg_get_result($con);
        if ($res) {
          $state = pg_result_error_field($res, PGSQL_DIAG_SQLSTATE);
          if ($state==0) {
            while ($line = pg_fetch_array($res, null, PGSQL_ASSOC)) {
                $arr[]=$line;
            }
            if(count($arr)){
                return $arr;
            }else{
                return true;
            }
          }
          else {
              switch($state){
                  case 23505:
                    msj("El registro ya existe, intente de nuevo.",false);
                  break;
                  case '22P02':
                    msj("Ingrese todos los campos obligatorios.",false);
                    
                  break;
                  default:
                    msj("ERROR EN SQL - debe solventar este error y volver a iniciar el programa.",false);
              }
          }
        }  
      }
      msj("Disculpe, intente de nuevo",false);


}


function conectar_db($host,$base_dato,$usuario,$clave,$puerto){
  $segundos=10;
  while(1){
    $dbconn = pg_connect("host=".$host." dbname=$base_dato user=$usuario password=$clave port=$puerto")
    or msj('Imposible conectarse a la base de datos.');
    if($dbconn){
      return $dbconn;
    }else{    
      msj("Intentando nuevamente en ".$segundos." segundos.");
      sleep($segundos);
    }
  }
 
}


function msj($string,$bueno=true,$dev=0)
{
  global $debug;
 
  if($debug==0 && $dev==1) {
    return 0;
  }else{
    echo $string . "\n";
    flush();
    ob_flush();
  }
}

