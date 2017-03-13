<?php
/**
 Untouched, needs updat
 */

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

function right($value, $count) {
    return substr($value, $count * -1);
}

function left($string, $count) {
    return substr($string, 0, $count);
}

$status = 0;

$chave_api = $GATEWAY["chave_api"];
if (isset($_REQUEST['chave_api'])) {
    if ($chave_api == $_REQUEST['chave_api']) {
        $referencia = $_REQUEST['referencia'];
        $valor = $_REQUEST['valor'];
        $valor = str_replace(',', '.', $valor);
        $datahora = date("Y-m-d H:i:s");
    }
}

switch($_REQUEST['mp']){
    case 'PC:PT':
        $modulo = 'multibanco';
        break;
    case 'PS:PT':
        $modulo = 'payshop';
        break;
    case 'MW:PT':
        $modulo = 'mbway';
        break;
    case 'PF:PT':
        $modulo = 'paysafecard';
        break;
    case 'PQ:PT':
        $modulo = 'pagaqui';
        break;
    default:
        die('módulo nao encontrado');
}


    $invoiceid = "";
    $gatewaymodule = "eupago" . $modulo; # Enter your gateway module name here replacing template
    $GATEWAY = getGatewayVariables($gatewaymodule);
    if (!$GATEWAY["type"]){
        die("Module Not Activated");# Checks gateway module is active before accepting callback
    }

    $table = "tbleupago_" . $modulo;
    $fields = "orderid";
    $where = array("referencia" => str_replace(' ', '', $referencia), "valor" => $valor, "estado" => 0);
    $sort = "id";
    $sortorder = "DESC";
    $limits = "0,1";

    $result = select_query($table, $fields, $where, $sort, $sortorder, $limits);
    $data = mysql_fetch_array($result);


    if (sizeof($data) > 1) {
        $invoiceid = $data['orderid'];
        $arr = array("chave" => $chave_api, "referencia" => $referencia, "valor" => $valor, "data" => $datahora, "invoice_id" => $invoiceid);
        $transid = $_REQUEST['referencia'] . $invoiceid;
        $amount = $_REQUEST['valor'];

        $fee = "0";
        $invoiceid = checkCbInvoiceID($invoiceid, $GATEWAY["name"]); # Checks invoice ID is a valid invoice number or ends processing

        //checkCbTransID($transid); # Checks transaction number isn't already in the database and ends processing if it does
        # Successful
        addInvoicePayment($invoiceid, $transid, $amount, $fee, $gatewaymodule); # Apply Payment to Invoice: invoiceid, transactionid, amount paid, fees, modulename
        logTransaction($GATEWAY["name"], $arr, "Sucesso: pagamento realizado com sucesso"); # Save to Gateway Log: name, data array, status

        $table = "tbleupago_" . $modulo;
        $update = array("estado" => 1, "datapago" => $datahora);
        $where = array("referencia" => str_replace(' ', '', $referencia), "valor" => $valor, "estado" => 0);
        update_query($table, $update, $where);
        echo ($modulo. ' - Actualizado para pago...');
        $estado_eupago_mb = 1;
        die();
    } else {
        # Unsuccessful
        $arr = array("chave" => $chave_api, "referencia" => $referencia, "valor" => $valor, "data" => $datahora, "invoice_id" => $invoiceid);
        echo ($modulo. ' - Falhou: Ou nao existe na base de dados ou ja foi pago...');
        logTransaction($GATEWAY["name"], $arr, $modulo. " - Falhou: Ou nao existe na base de dados ou ja foi pago..."); # Save to Gateway Log: name, data array, status
}