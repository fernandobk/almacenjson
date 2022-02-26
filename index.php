<?php
//echo '<pre>'; var_dump($_GET);
if($_GET['id'] === 'server'){ echo '<pre>'; var_dump($_SERVER); exit; }

define('NOMBRE_BD', 'bd.sqlite');
define('RUTA_DOC', $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'].'/doc.html');
header('Content-type: application/json');

$_GET['id'] = trim(strtolower($_GET['id']));
if( preg_match('/[^a-z0-9]/',$_GET['id']) ){ bad_request(); }

function respuesta(int $status, $id = null, string $msg = null, string $id_doc = null){    
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

    $obj = new stdClass();
    $obj->status = $status;
    $obj->statusText = $statusText[$status];
    $obj->id = $id;
    $obj->msg = $msg;
    
    $obj->details = RUTA_DOC.'#';
    if($id_doc){
        $obj->details .= $id_doc;
    }else{
        $fn = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? 'global';
        $obj->details .= $fn . '-c' . $status;
    }

    header('HTTP/1.1 '.$status.' '.$statusText[$status]);
    exit(
        json_encode( $obj )
    );
}

 /* ==== Controlador frontal ==== */

switch($_SERVER['REQUEST_METHOD']){
    case 'GET':
        if($_GET['id']){ mostrar_hoja( $_GET['id'] ); }
        else{ listar_todo(); }
    break;

    case 'POST':
        $id = $_GET['id']? $_GET['id'] : time();
        crear_hoja($id);
    break;

    case 'PUT':
        if( $_GET['id'] ){ modificar_hoja($_GET['id']); }
        else{ respuesta(400, null, 'Falta ID.'); }
    break;

    case 'DELETE':
        if( $_GET['id'] ){ borrar_hoja($_GET['id']); }
        else{ respuesta(400, null, 'Falta ID.'); }
    break;

    default: 
        respuesta(405);
}

/* ==== Funciones propias del servicio ==== */

function mostrar_hoja($id){
    
    $bd = new SQLite3(NOMBRE_BD);
    $sql = "SELECT * FROM carpeta WHERE id = '$id'";
    $result = $bd->query($sql);
    $result = $result->fetchArray(SQLITE3_ASSOC);

    if( $result ){ 
        exit($result['hoja']); }
    else{
        respuesta(404, $id, 'ID no encontrado.');
    }
}

function listar_todo(){
    $bd = new SQLite3(NOMBRE_BD);
    $result = $bd->query('SELECT * FROM carpeta');
    $items = array();
    while( $item = $result->fetchArray(SQLITE3_ASSOC)){
        $item['id'] = is_numeric($item['id'])? (int)$item['id'] : $item['id'];
        $item['hoja'] = json_decode($item['hoja']);
        $items[] = $item;
    }
    exit( json_encode( $items ) );
}

function crear_hoja($id){
    $data = file_get_contents('php://input');
    if( is_null( json_decode($data) ) ){
        respuesta(415, $id, 'El cuerpo enviado no está en formato JSON válido.');
    }

    $bd = new SQLite3(NOMBRE_BD);
    $sql = "INSERT INTO carpeta (id, hoja) VALUES ('$id', '$data')";
    @$result = $bd->exec($sql);
    
    if( $result ){
        respuesta(201, $id);
    }else{
        respuesta(500, $id, '('.$bd->lastErrorCode().') '.$bd->lastErrorMsg() );
    }
}

function modificar_hoja($id){
    $data = file_get_contents('php://input');
    if( is_null( json_decode($data) ) ){
        respuesta(415, $id, 'El cuerpo enviado no está en formato JSON válido.');
    }

    $bd = new SQLite3(NOMBRE_BD);
    $sql = "UPDATE carpeta SET hoja = '$data' WHERE id = '$id';";
    @$result = $bd->exec($sql);
    
    if( $result ){
        respuesta(202, $id);
    }else{
        respuesta(500, $id, '('.$bd->lastErrorCode().') '.$bd->lastErrorMsg() );
    }
}

function borrar_hoja($id){
    $bd = new SQLite3(NOMBRE_BD);
    $sql = "DELETE FROM carpeta  WHERE id = '$id';";
    @$result = $bd->exec($sql);
    
    if( $bd->changes() ){
        respuesta(205, $id);
    }else{
        respuesta(404, $id, 'No se borró ninguna hoja' );
    }
}