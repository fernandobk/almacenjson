<?php

define('NOMBRE_BD', 'bd.sqlite');
define('RUTA_DOC', $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'].'/doc.html');

define('ID', $_GET['id']??'');
define('LLAVE', str_replace('Bearer ', '', (apache_request_headers()['Authorization']??'')));

header('Allow: OPTIONS, HEAD, GET, POST');
header('Content-type: application/json');
header('Access-Control-Allow-Origin: '.($_SERVER['HTTP_ORIGIN']??'*'));
header('Access-Control-Allow-Methods: OPTIONS, HEAD, GET, POST');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Access-Control-Request-Method');

$_GET['id'] = trim(strtolower($_GET['id']));
if( preg_match('/[^a-z0-9]/',$_GET['id']) ){ bad_request(); }

function respuesta(int $status, string $msj = null, string $detalles = null){    
    $statusText = array();
    $statusText[200] = 'OK';
    $statusText[201] = 'Created';
    $statusText[202] = 'Accepted';
    $statusText[204] = 'No Content';
    $statusText[205] = 'Reset Content';
    $statusText[400] = 'Bad Request';
    $statusText[404] = 'Not Found';
    $statusText[405] = 'Method Not Allowed';
    $statusText[415] = 'Unsupported Media Type';
    $statusText[500] = 'Internal Server Error';
    
    if(!$detalles){
        $detalles = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? 'global';
        $detalles .= '-c' . $status;
    }
    $detalles = RUTA_DOC.'#'.$detalles;

    header('HTTP/1.1 '.$status.' '.$statusText[$status]);
    header('X-Detalles: '.$detalles);

    if( $status === 201 ){
        header('X-Llave: '.$msj);
    }else{
        if($msj){ header('X-Mensaje: '.$msj); }
    }
}

 /* ==== Controlador frontal ==== */

 switch($_SERVER['REQUEST_METHOD']){
    case 'OPTIONS':
        header('HTTP/1.0 200 OK');
        exit;
    break;
    
    case 'GET':
        if( ID ){ mostrar_hoja(); }
        else{ listar_todo(); }
    break;

    default:
        if(!ID){ respuesta(400, 'Falta ID'); }
        switch($_SERVER['REQUEST_METHOD']){
            case 'POST': modificar_hoja(); break;
            
            case 'HEAD': 
                if( LLAVE ){ borrar_hoja(); }
                else { crear_hoja(); }
            break;
            
            default: respuesta(405);
        }
}

/* ==== Funciones propias del servicio ==== */

function listar_todo(){
    $bd = new SQLite3(NOMBRE_BD);
    $result = $bd->query('SELECT * FROM carpeta');
    $items = array();
    while( $item = $result->fetchArray(SQLITE3_ASSOC)){
        unset($item['llave']);
        $item['id'] = is_numeric($item['id'])? (int)$item['id'] : $item['id'];
        $item['hoja'] = json_decode($item['hoja']);
        $items[] = $item;
    }
    exit( json_encode( $items ) );
}

function mostrar_hoja(){
    
    if(ID === 'server'){ exit(json_encode($_SERVER)); }

    $bd = new SQLite3(NOMBRE_BD);
    $sql = 'SELECT `hoja` FROM `carpeta` WHERE id = "'.ID.'"';
    $result = $bd->query($sql);
    $result = $result->fetchArray(SQLITE3_NUM);

    if( $result ){ 
        exit($result[0]); }
    else{
        respuesta(404, 'ID no encontrado.');
    }
}

function crear_hoja(){
    $llave = md5(time());
    $bd = new SQLite3(NOMBRE_BD);
    $sql = 'INSERT INTO carpeta (`id`, `llave`) VALUES ("'.ID.'", "'.$llave.'")';
    @$result = $bd->exec($sql);
    
    if( $result ){
        respuesta(201, $llave);
    }else{
        if( $bd->lastErrorCode() === 19 ){
            respuesta(400, $id, 'Este ID está ocupado.');
        }else{
            respuesta(500, $id, '('.$bd->lastErrorCode().') '.$bd->lastErrorMsg() );
        }
    }
}

function modificar_hoja(){
    $data = file_get_contents('php://input');
    if( is_null( json_decode($data) ) ){
        respuesta(415, $id, 'El cuerpo enviado no está en formato JSON válido.');
    }

    $bd = new SQLite3(NOMBRE_BD);
    $sql = "UPDATE `carpeta` SET `hoja` = '".$data."' WHERE `id` = '".ID."' AND  `llave` = '".LLAVE."';";
    @$result = $bd->exec($sql);
    
    if( $result OR $bd->changes() ){
        respuesta(202);
    }else{
        respuesta(500, '('.$bd->lastErrorCode().') '.$bd->lastErrorMsg() );
    }
}

function borrar_hoja(){
    $bd = new SQLite3(NOMBRE_BD);
    $sql = "DELETE FROM `carpeta`  WHERE `id` = '".ID."' AND `llave` = '".LLAVE."';";
    @$result = $bd->exec($sql);
    
    if( $result OR $bd->changes() ){
        respuesta(205, $id);
    }else{
        respuesta(404, $id, '('.$bd->lastErrorCode().') '.$bd->lastErrorMsg() );
    }
}