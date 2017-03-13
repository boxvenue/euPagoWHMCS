<?php

function eupagopayshop_config() {
    $configarray = array(
        "FriendlyName" => array("Type" => "System", "Value" => "euPago - Payshop"),
        "chave_api_ps" => array("FriendlyName" => "Chave API", "Type" => "text", "Size" => "20","Chave Cedida pelo euPago",),
       
    );
    return $configarray;
}

function eupagopayshop_link($params) {


    # Gateway Specific Variables
    $gatewaychave_api = $params['chave_api_ps'];



    # Invoice Variables
    $invoiceid = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount']; # Format: ##.##
    $currency = $params['currency']; # Currency Code


    # Client Variables
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];

    # System Variables
    $companyname = $params['companyname'];
    $systemurl = $params['systemurl'];
    $currency = $params['currency'];
    
$tabela = "tbleupago_payshop";

       $fields = "referencia,invoiceid";
    $where = array("invoiceid" => $invoiceid);
    $resultado = select_query($tabela, $fields, $where);
    
    $data = mysql_fetch_array($resultado);
   
    
if($data['referencia']!="" ){
    $result->referencia =   $data['referencia']; 
    $result->valor= $amount;
}
    else
        $result = geraReferenciaPS($gatewaychave_api, $invoiceid, $amount);
    

    
    # Enter your code submit to the gateway...

    $code = '<table style="margin-top: 10px;border: 1px solid #000;" width="200px" cellspacing="0" align="center">
<tr>
	<td colspan="2" align="center">
		<img src="https://seguro.eupago.pt/repositorio/imagens/eupagopayshop.png" alt="euPago payshop" height="20" />
	</td>
</tr>


<tr>
	<td align="left" style="font-size:small;font-weight:bold;padding-left:15px;">Refer&ecirc;ncia:</td>
	<td align="left" style="font-size:small;">' . $result->referencia . ' </td>
</tr>
<tr>
	<td align="left" style="font-size:small;font-weight:bold;padding-left:15px;">Valor:</td>
	<td align="left" style="font-size:small;">' . $result->valor . ' EUR</td>
</tr>

</table>';

    # Insere os dados de payshop numa tabela para posterior valida��o
    insert_references_on_database_ps($invoiceid,'', $result->referencia, $result->valor, $invoiceid);

    return $code;
 
    
}

function insert_references_on_database_ps($invoiceid, $entidade, $referencia, $valor, $orderid) {
  $tabela = "tbleupago_payshop";
  

    check_table_exist_ps($tabela);

   $fields = "referencia";
  $where = array("invoiceid" => $invoiceid, "entidade" => $entidade, "referencia" => $referencia, "valor" => $valor, "orderid" => $orderid);

  
  $result = select_query($tabela, $fields, $where);

  $data = mysql_fetch_array($result);

    if (sizeof($data) < 2) {
       
       
            $values = array("invoiceid" => $invoiceid, "entidade" => $entidade, "referencia" => $referencia, "valor" => $valor, "orderid" => $orderid);
   
            $newid = insert_query($tabela, $values);
        
    }
 
}

function check_table_exist_ps($tabela) {
    $existeTabela = mysql_num_rows(mysql_query("SHOW TABLES LIKE '" . $tabela . "'"));

    if ($existeTabela == 0) {
        //DO SOMETHING! IT EXISTS!

        $createTable = 'CREATE TABLE IF NOT EXISTS `' . $tabela . '` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoiceid` varchar(255) NOT NULL,
  `entidade` int(5) NOT NULL,
  `referencia` varchar(14) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `orderid` int(11) NOT NULL,
  `estado` int(11) NOT NULL DEFAULT \'0\',
  `dataencomenda` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `datapago` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT=\'tabela criada pela  para gerir referencias\' AUTO_INCREMENT=1 ;';

        MYSQL_QUERY($createTable);
    }
}

//INICIO REF payshop

function geraReferenciaPS($chave_api, $nota_de_encomenda, $valor_da_encomenda) {

    $demo = explode("-", $chave_api);
    if ($demo['0'] == 'demo') {
        $url = 'http://replica.eupago.pt/replica.eupagov5.wsdl';
    }else{
        $url = 'https://seguro.eupago.pt/eupagov5.wsdl';
    }

    $client = @new SoapClient($url, array('cache_wsdl' => WSDL_CACHE_NONE)); // chamada do servi�o SOAP
    $arraydados = array("chave" => $chave_api, "valor" => $valor_da_encomenda, "id" => $nota_de_encomenda); //cada canal tem a sua chave
    $result = $client->gerarReferenciaPS($arraydados);

    // verifica erros na execu��o do servi�o e exibe o resultado
    if (is_soap_fault($result)) {
        //trigger_error("SOAP Fault: (faultcode: {$result->faultcode}, faultstring: {$result->faulstring})", E_ERROR);
    } else {
        if ($result->estado == 0) { //estados possiveis: 0 sucesso. -10 Chave invalida. -9 Valores incorretcos
            //colocar  a acao de sucesso
            return $result; // retorna 3 valores: entidade, refer�ncia e valor  
        } else {
            //acao insucesso
        }
    }
}

/* * ********* Fim Exemplo de chamada de m�todo gerarReferenciaPS ************** */




?>