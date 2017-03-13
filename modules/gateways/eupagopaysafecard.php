<?php

function eupagopaysafecard_config() {
    $configarray = array(
        "FriendlyName" => array("Type" => "System", "Value" => "euPago - Paysafecard"),
        "chave_api_pf" => array("FriendlyName" => "Chave API", "Type" => "text", "Size" => "20","Chave Cedida pelo euPago",),
       
    );
    return $configarray;
}

function eupagopaysafecard_link($params) {

    if (isset($_GET['status_eupago'])){
        if ($_GET['status_eupago'] == "ok"){
            $code = "A encomenda foi concluída com sucesso";
        } else if ($_GET['status_eupago'] == "impossivel"){
            $code = "Não foi possível efetuar a encomenda,";
        }
    } else {
        # Gateway Specific Variables
        $gatewaychave_api = $params['chave_api_pf'];

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

        $tabela = "tbleupago_paysafecard";

        $fields = "referencia,invoiceid,url";
        $where = array("invoiceid" => $invoiceid);
        $resultado = select_query($tabela, $fields, $where);
        $data = mysql_fetch_array($resultado);

        if ($data['referencia'] != "" && $data['url'] != "") {
            $result->referencia = $data['referencia'];
            $result->valor = $amount;
            $result->url = $data['url'];
        } else {
            $result = geraReferenciaPF($gatewaychave_api, $params, $amount);
            $result->valor = $amount;
        }

        # Enter your code submit to the gateway...

        $code = '<table style="margin-top: 10px;border: 1px solid #000;" width="200px" cellspacing="0" align="center">
            <tr>
                <td colspan="2" align="center">
                    <img src="https://seguro.eupago.pt/repositorio/imagens/eupagopaysafecard.png" alt="euPago paysafecard" height="20" />
                </td>
            </tr>
            <tr>
                <td style="font-size:small"><a href=" ' . $result->url . ' "> <button name="pagar" value="Paysafecard"> Pagar por Paysafecard</button></a></td>
            </tr>
           
            
            </table>';

        # Insere os dados de paysafecard numa tabela para posterior valida��o
        insert_references_on_database_pf($invoiceid, $result->referencia, $result->valor, $result->url, $invoiceid);
    }


    return $code;
    
}

function insert_references_on_database_pf($invoiceid, $referencia, $valor,$url, $orderid) {
  $tabela = "tbleupago_paysafecard";


    check_table_exist_pf($tabela);


   $fields = "referencia,url";
  $where = array("invoiceid" => $invoiceid, "referencia" => $referencia, "valor" => $valor, "url"=>$url, "orderid" => $orderid);

  $result = select_query($tabela, $fields, $where);

  $data = mysql_fetch_array($result);


    if (sizeof($data) < 2) {

       
            $values = array("invoiceid" => $invoiceid, "referencia" => $referencia, "valor" => $valor, "url"=>$url, "orderid" => $orderid);

            $newid = insert_query($tabela, $values);
    }
 
}

function check_table_exist_pf($tabela) {
    $existeTabela = mysql_num_rows(mysql_query("SHOW TABLES LIKE '" . $tabela . "'"));

    if ($existeTabela == 0) {
        //DO SOMETHING! IT EXISTS!

        $createTable = 'CREATE TABLE IF NOT EXISTS `' . $tabela . '` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoiceid` varchar(255) NOT NULL,
  `referencia` varchar(14) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `url` varchar(200) NOT NULL,
  `orderid` int(11) NOT NULL,
  `estado` int(11) NOT NULL DEFAULT \'0\',
  `dataencomenda` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `datapago` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT=\'tabela criada pela  para gerir referencias\' AUTO_INCREMENT=1 ;';

        MYSQL_QUERY($createTable);
    }
}

//INICIO REF paysafecard

function geraReferenciaPF($chave_api, $params, $valor_da_encomenda)
{

    $demo = explode("-", $chave_api);
    if ($demo['0'] == 'demo') {
        $url = 'http://replica.eupago.pt/replica.eupagov5.wsdl';
    }else{
        $url = 'https://seguro.eupago.pt/eupagov5.wsdl';
    }

    $client = @new SoapClient($url, array('cache_wsdl' => WSDL_CACHE_NONE)); // chamada do servi�o SOAP

    $arraydados = array("chave" => $chave_api, "valor" => $valor_da_encomenda, "id" => $params['invoiceid'], "url_retorno" => $params['systemurl'] . "/viewinvoice.php?id=" .$params['invoiceid'] , "nome" => $params['clientdetails']['firstname'].$params['clientdetails']['lastname'], "email" => $params['clientdetails']['email'],"lang" => 'pt'); //cada canal tem a sua chave

    $result = $client->pedidoPF($arraydados);


    // verifica erros na execu��o do servi�o e exibe o resultado
    if (is_soap_fault($result)) {
        //trigger_error("SOAP Fault: (faultcode: {$result->faultcode}, faultstring: {$result->faulstring})", E_ERROR);
    } else {
        if ($result->estado == 0) { //estados possiveis: 0 sucesso. -10 Chave invalida. -9 Valores incorretcos
            //colocar  a acao de sucess

            return $result; // retorna 3 valores: entidade, refer�ncia e valor  
        } else {
            //acao insucesso
        }
    }
}

/* * ********* Fim Exemplo de chamada de m�todo gerarReferenciaPF ************** */




?>